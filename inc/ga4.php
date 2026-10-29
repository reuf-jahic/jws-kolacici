<?php
/* Google Analytics 4: događaji prodavnice (view_item, add_to_cart, begin_checkout, purchase).
   Samo kad je WooCommerce aktivan i Google uključen. Preglednik ih šalje tek uz Statistiku:
   način GA4 → gtag('event', …), način GTM → dataLayer.push({event, ecommerce}) za GA4 tagove u GTM-u. */

defined( 'ABSPATH' ) || exit;

$GLOBALS['jwsk_ga4_dogadjaji'] = [];
function jwsk_ga4_dogadjaji() {
	return $GLOBALS['jwsk_ga4_dogadjaji'];
}

function jwsk_ga4_radi() {
	return jwsk_google_id() && class_exists( 'WooCommerce' ) && in_array( 'statistika', jwsk_grupe(), true ) && ! is_admin() && ! jwsk_u_builderu();
}

function jwsk_ga4_stavka( $product, $qty = 1 ) {
	$pid  = $product->get_parent_id() ?: $product->get_id();
	$par  = wc_get_product( $pid );
	$cats = wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'names' ] );
	$item = [ 'item_id' => (string) $pid, 'item_name' => wp_strip_all_tags( $par ? $par->get_name() : $product->get_name() ),
		'price' => (float) wc_get_price_to_display( $product ), 'quantity' => (int) $qty ];
	if ( ! is_wp_error( $cats ) && $cats ) {
		$item['item_category'] = $cats[0];
	}
	if ( $product->get_parent_id() ) {
		$item['item_variant'] = wp_strip_all_tags( wc_get_formatted_variation( $product, true, false, false ) );
	}
	return $item;
}

add_action( 'template_redirect', function() {
	if ( ! jwsk_ga4_radi() ) {
		return;
	}
	$valuta = get_woocommerce_currency();
	if ( is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$it = jwsk_ga4_stavka( $p );
			$GLOBALS['jwsk_ga4_dogadjaji'][] = [ 'view_item', [ 'currency' => $valuta, 'value' => $it['price'], 'items' => [ $it ] ] ];
		}
	} elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		$items = [];
		foreach ( WC()->cart->get_cart() as $c ) {
			$items[] = jwsk_ga4_stavka( $c['data'], $c['quantity'] );
		}
		$GLOBALS['jwsk_ga4_dogadjaji'][] = [ 'begin_checkout', [ 'currency' => $valuta, 'value' => (float) WC()->cart->get_total( 'edit' ), 'items' => $items ] ];
	} elseif ( is_wc_endpoint_url( 'order-received' ) ) {
		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		$key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		if ( $order && hash_equals( $order->get_order_key(), $key ) ) {
			$items = [];
			foreach ( $order->get_items() as $it ) {
				$p = $it->get_product();
				if ( $p ) {
					$s          = jwsk_ga4_stavka( $p, $it->get_quantity() );
					$s['price'] = (float) $order->get_item_total( $it, true );
					$items[]    = $s;
				}
			}
			$GLOBALS['jwsk_ga4_dogadjaji'][] = [ 'purchase', [ 'transaction_id' => (string) $order->get_order_number(), 'currency' => $order->get_currency(),
				'value' => (float) $order->get_total(), 'shipping' => (float) $order->get_shipping_total(), 'items' => $items ] ];
		}
	}
} );

// Dodavanje u korpu (i preko AJAX-a): preglednik čita kratki kolačić jws_ga_atc
add_action( 'woocommerce_add_to_cart', function( $key, $product_id, $qty, $variation_id ) {
	if ( ! jwsk_google_id() || ! jws_kolacici_dozvoljeno( 'statistika' ) || headers_sent() ) {
		return;
	}
	$p = wc_get_product( $variation_id ?: $product_id );
	if ( ! $p ) {
		return;
	}
	$it = jwsk_ga4_stavka( $p, (int) $qty );
	// setrawcookie + rawurlencode: preglednik dekodira tačno jednom
	setrawcookie( 'jws_ga_atc', rawurlencode( wp_json_encode( [ 'currency' => get_woocommerce_currency(), 'value' => $it['price'] * $it['quantity'], 'items' => [ $it ] ] ) ),
		[ 'expires' => time() + 120, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax' ] );
}, 10, 4 );
