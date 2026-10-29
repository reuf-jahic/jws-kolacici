<?php
/* Meta Pixel (preglednik, js/jws-kolacici.js) i Conversions API (server), samo uz saglasnost za marketing.
   Isti event_id ide pregledniku i serveru, Meta ih spaja u jedan.
   ViewContent ide samo iz preglednika: stranica artikla je keširana, pa server ne bi ni bio pozvan, a ID iz keša bi
   bio isti za sve posjetioce. Blagajna, Hvala i dodavanje u korpu se ne keširaju, pa tu radi i CAPI. */

defined( 'ABSPATH' ) || exit;

$GLOBALS['jwsk_meta_dogadjaji'] = [];
function jwsk_meta_dogadjaji() {
	return $GLOBALS['jwsk_meta_dogadjaji'];
}

function jwsk_meta_radi() {
	return jwsk_meta_aktivna() && ! is_admin() && ! jwsk_u_builderu();
}

function jwsk_meta_hash( $v ) {
	$v = strtolower( trim( (string) $v ) );
	return '' === $v ? null : hash( 'sha256', $v );
}
function jwsk_meta_telefon( $t ) {
	$t = preg_replace( '/\D+/', '', (string) $t );
	if ( '' === $t ) {
		return '';
	}
	if ( strpos( $t, '00' ) === 0 ) {
		return substr( $t, 2 );
	}
	if ( strpos( $t, '0' ) === 0 ) {
		return preg_replace( '/\D+/', '', jwsk_opcije()['meta_pozivni'] ) . substr( $t, 1 );
	}
	return $t;
}

// Šalje događaj na Conversions API; ne čeka odgovor, da ne usporava stranicu
function jwsk_meta_capi( $event, $eid, $custom = [], $kupac = [] ) {
	$o = jwsk_opcije();
	if ( ! jwsk_meta_aktivna() || '' === $o['meta_capi_token'] || ! jws_kolacici_dozvoljeno( 'marketing' ) ) {
		return;
	}
	$ip   = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$user = array_filter( [
		'client_ip_address' => $ip,
		'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
		'fbp'               => isset( $_COOKIE['_fbp'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) ) : null,
		'fbc'               => isset( $_COOKIE['_fbc'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) ) : null,
		'external_id'       => is_user_logged_in() ? jwsk_meta_hash( get_current_user_id() ) : null,
	] );
	foreach ( [ 'em', 'ph', 'fn', 'ln', 'ct', 'zp', 'country' ] as $k ) {
		if ( ! empty( $kupac[ $k ] ) ) {
			$user[ $k ] = jwsk_meta_hash( $kupac[ $k ] );
		}
	}
	$body = [ 'data' => wp_json_encode( [ [
		'event_name'       => $event,
		'event_time'       => time(),
		'event_id'         => $eid,
		'action_source'    => 'website',
		'event_source_url' => home_url( add_query_arg( [] ) ),
		'user_data'        => $user,
		'custom_data'      => $custom,
	] ] ) ];
	if ( '' !== $o['meta_test_code'] ) {
		$body['test_event_code'] = $o['meta_test_code'];
	}
	wp_remote_post( 'https://graph.facebook.com/v21.0/' . rawurlencode( $o['meta_id'] ) . '/events?access_token=' . rawurlencode( $o['meta_capi_token'] ), [
		'body' => $body, 'timeout' => 2, 'blocking' => false,
	] );
}

function jwsk_meta_artikal( $product, $qty = 1 ) {
	$pid    = $product->get_parent_id() ?: $product->get_id();
	$roditelj = wc_get_product( $pid );
	return [ 'content_ids' => [ (string) $pid ], 'content_type' => 'product', 'content_name' => wp_strip_all_tags( $roditelj ? $roditelj->get_name() : $product->get_name() ),
		'value' => (float) wc_get_price_to_display( $product ) * $qty, 'currency' => get_woocommerce_currency() ];
}

add_action( 'template_redirect', function() {
	if ( ! jwsk_meta_radi() || ! function_exists( 'is_product' ) ) {
		return;
	}
	if ( is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$GLOBALS['jwsk_meta_dogadjaji'][] = [ 'ViewContent', '', jwsk_meta_artikal( $p ) ];
		}
	} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		$ids = [];
		foreach ( WC()->cart->get_cart() as $it ) {
			$ids[] = (string) $it['product_id'];
		}
		$eid  = 'ic_' . wp_generate_uuid4();
		$data = [ 'content_ids' => array_values( array_unique( $ids ) ), 'content_type' => 'product', 'num_items' => WC()->cart->get_cart_contents_count(),
			'value' => (float) WC()->cart->get_total( 'edit' ), 'currency' => get_woocommerce_currency() ];
		$GLOBALS['jwsk_meta_dogadjaji'][] = [ 'InitiateCheckout', $eid, $data ];
		jwsk_meta_capi( 'InitiateCheckout', $eid, $data );
	} elseif ( is_wc_endpoint_url( 'order-received' ) ) {
		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		$key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		if ( $order && hash_equals( $order->get_order_key(), $key ) ) {
			$ids = [];
			foreach ( $order->get_items() as $it ) {
				$ids[] = (string) $it->get_product_id();
			}
			$eid  = 'order_' . $order->get_id();
			$data = [ 'content_ids' => array_values( array_unique( $ids ) ), 'content_type' => 'product', 'num_items' => $order->get_item_count(),
				'value' => (float) $order->get_total(), 'currency' => $order->get_currency(), 'order_id' => (string) $order->get_order_number() ];
			$GLOBALS['jwsk_meta_dogadjaji'][] = [ 'Purchase', $eid, $data ];
			// Kupovina ide na CAPI jednom, i kad kupac osvježi stranicu Hvala
			if ( ! $order->get_meta( '_jwsk_meta_purchase' ) && jws_kolacici_dozvoljeno( 'marketing' ) ) {
				jwsk_meta_capi( 'Purchase', $eid, $data, [
					'em' => $order->get_billing_email(), 'ph' => jwsk_meta_telefon( $order->get_billing_phone() ), 'fn' => $order->get_billing_first_name(),
					'ln' => $order->get_billing_last_name(), 'ct' => preg_replace( '/\s+/', '', strtolower( $order->get_billing_city() ) ),
					'zp' => $order->get_billing_postcode(), 'country' => strtolower( $order->get_billing_country() ?: WC()->countries->get_base_country() ),
				] );
				$order->update_meta_data( '_jwsk_meta_purchase', time() );
				$order->save();
			}
		}
	}
} );

// Dodavanje u korpu (i preko AJAX-a): server šalje odmah, a preglednik čita kratki kolačić jws_meta_atc sa istim event_id
add_action( 'woocommerce_add_to_cart', function( $key, $product_id, $qty, $variation_id ) {
	if ( ! jwsk_meta_aktivna() || ! jws_kolacici_dozvoljeno( 'marketing' ) ) {
		return;
	}
	$p = wc_get_product( $variation_id ?: $product_id );
	if ( ! $p ) {
		return;
	}
	$eid  = 'atc_' . wp_generate_uuid4();
	$data = jwsk_meta_artikal( $p, (int) $qty );
	jwsk_meta_capi( 'AddToCart', $eid, $data );
	if ( ! headers_sent() ) {
		// setrawcookie + rawurlencode: preglednik dekodira tačno jednom (setcookie bi kodirao još jednom i JSON.parse bi pao)
		setrawcookie( 'jws_meta_atc', rawurlencode( wp_json_encode( [ 'eid' => $eid, 'data' => $data ] ) ), [ 'expires' => time() + 120, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax' ] );
	}
}, 10, 4 );
