<?php
/* U <head>: Search Console meta tag, podaci za skriptu i Google Consent Mode v2 (default denied) prije bilo kojeg Google taga.
   U podnožju: js/jws-kolacici.js. Plus Woo izvor narudžbe pod saglasnošću i kratki kod [jws_kolacici_spisak]. */

defined( 'ABSPATH' ) || exit;

function jwsk_u_builderu() {
	return ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() )
		|| ( function_exists( 'bricks_is_builder_iframe' ) && bricks_is_builder_iframe() );
}

// Podaci za preglednik. Sve odluke o saglasnosti donosi preglednik, pa radi i sa keširanim stranicama.
function jwsk_podaci_za_js() {
	$o     = jwsk_opcije();
	$grupe = jwsk_grupe();
	// Imena kolačića po grupi: brišu se kad posjetilac povuče saglasnost (zvjezdica na kraju = prefiks)
	$brisi = [];
	foreach ( jwsk_spisak() as $k ) {
		if ( 'neophodni' !== $k['grupa'] ) {
			$brisi[ $k['grupa'] ][] = $k['naziv'];
		}
	}
	$google = null;
	if ( jwsk_google_id() ) {
		$google = [ 'nacin' => $o['google_nacin'], 'id' => jwsk_google_id(), 'napredno' => (bool) $o['google_napredno'], 'ev' => jwsk_ga4_dogadjaji() ];
		if ( in_array( 'marketing', $grupe, true ) ) {
			$brisi['marketing'][] = '_gcl_*'; // Google Ads konverzije (GTM, gtag), ako ih ima
		}
	}
	$stari = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $o['stari'] ) ) ) );
	return [
		'prikaz'  => jwsk_prikaz(),
		'popup'   => (int) $o['popup'],
		'blok'    => 'ugradjena' === jwsk_prikaz() && $o['blokiraj'] ? 1 : 0,
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'verzija' => jwsk_verzija(),
		'dana'    => (int) $o['dana'],
		'grupe'   => $grupe,
		'google'  => $google,
		'meta'    => jwsk_meta_aktivna() ? [ 'id' => $o['meta_id'], 'ev' => jwsk_meta_dogadjaji() ] : null,
		'woo'     => $o['woo'] && class_exists( 'WooCommerce' ) ? $o['woo_grupa'] : '',
		'brisi'   => $brisi,
		'stari'   => $stari,
	];
}

add_action( 'wp_head', function() {
	$gsc = jwsk_opcije()['gsc'];
	if ( '' !== $gsc ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $gsc ) . '">' . "\n";
	}
	if ( jwsk_u_builderu() ) {
		return;
	}
	?>
<script id="jws-kolacici-glava" data-no-defer="1" data-no-optimize="1" data-cfasync="false">
window.jwsKolaciciPodaci = <?php echo wp_json_encode( jwsk_podaci_za_js() ); ?>;
(function (D) {
	/* Google Consent Mode v2: sve odbijeno dok posjetilac ne izabere. Ne pravi nijedan zahtjev; Google se učitava tek u js/jws-kolacici.js */
	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function () { dataLayer.push(arguments); };
	gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });
	gtag('set', 'ads_data_redaction', true);
	function k(n) { var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : null; }
	var iz = k('<?php echo JWSK_KOLACIC; ?>');
	if (k('<?php echo JWSK_KOLACIC_V; ?>') !== D.verzija || ['sve', 'nuzni', 'stat', 'mark'].indexOf(iz) < 0) return;
	var s = function (g) { return D.grupe.indexOf(g) > -1 && (iz === 'sve' || iz === (g === 'statistika' ? 'stat' : 'mark')) ? 'granted' : 'denied'; };
	gtag('consent', 'update', { analytics_storage: s('statistika'), ad_storage: s('marketing'), ad_user_data: s('marketing'), ad_personalization: s('marketing') });
	dataLayer.push({ event: 'jws_consent_update', jws_statistika: s('statistika'), jws_marketing: s('marketing') });
})(window.jwsKolaciciPodaci);
</script>
	<?php
}, 1 );

add_action( 'wp_enqueue_scripts', function() {
	if ( jwsk_u_builderu() ) {
		return;
	}
	wp_enqueue_script( 'jws-kolacici', JWSK_URL . 'js/jws-kolacici.js', [], JWSK_VERZIJA_PLUGINA, true );
} );

/* WP Rocket: Delay JS bi zadržao skripte trake do prvog dodira (posjetilac ode a traku nije vidio), a RUCSS bi iz CSS-a
   kartice izbacio pravila za stanja kojih nema u HTML-u pri učitavanju (.is-postavke), pa se postavke ne bi otvarale */
add_filter( 'rocket_delay_js_exclusions', function( $e ) {
	return array_merge( (array) $e, [ 'jws-kolacici', 'jwsKolacici' ] );
} );
add_filter( 'rocket_rucss_inline_atts_exclusions', function( $e ) {
	return array_merge( (array) $e, [ 'jws-kolacici-traka-css' ] );
} );

// Woo „izvor narudžbe" (kolačići sbjs_*) po defaultu prati PRIJE pristanka; ovdje radi tek uz saglasnost za izabranu grupu
add_filter( 'wc_order_attribution_allow_tracking', function( $allow ) {
	$o = jwsk_opcije();
	if ( ! $o['woo'] ) {
		return $allow;
	}
	return $allow && jws_kolacici_dozvoljeno( $o['woo_grupa'] );
} );

/* [jws_kolacici_spisak] u Bricks popupu ili na stranici Politika privatnosti. lang="bs|en|sl" forsira jezik,
   inače prema jeziku stranice. grupa="marketing" prikazuje samo jednu grupu (za spisak ispod prekidača). */
add_shortcode( 'jws_kolacici_spisak', function( $atts ) {
	$a  = shortcode_atts( [ 'lang' => '', 'grupa' => '', 'traka' => '' ], $atts );
	$j  = in_array( $a['lang'], [ 'bs', 'en', 'sl' ], true ) ? $a['lang'] : null;
	$t  = function( $k ) use ( $j ) {
		return esc_html( jwsk_tekst( $k, $j ) );
	};
	// Na stranici: blag izgled (linije između redova, zaglavlje lijevo); na telefonu svaki kolačić je blok sa oznakom ispred
	// vrijednosti (data-label), pa ništa nije šire od ekrana. U kartici (traka="1") vrijedi stil iz traka.php.
	$traka = ! empty( $a['traka'] );
	static $stil = false;
	$h = '';
	if ( ! $stil && ! $traka ) {
		$s = '.jwsk-stranica';
		$l = 'rgba(127,127,127,.25)';
		$h = '<style>.jwsk-spisak-okvir{max-width:100%;overflow-x:auto}.jwsk-spisak-okvir .jwsk-spisak td:first-child{overflow-wrap:anywhere}'
			. "$s{margin:1.2em 0}$s .jwsk-spisak{width:100%;margin:0;border-collapse:collapse;font-size:.9375em;line-height:1.5}"
			. "$s .jwsk-spisak th,$s .jwsk-spisak td{padding:.7em 1em .7em 0;border:0;border-bottom:1px solid $l;background:none;text-align:left;vertical-align:top}"
			. "$s .jwsk-spisak th{font-weight:600;white-space:nowrap;border-bottom-color:rgba(127,127,127,.55)}"
			. "$s .jwsk-spisak th:last-child,$s .jwsk-spisak td:last-child{padding-right:0}"
			. '@media (max-width:600px){' . "$s .jwsk-spisak thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}"
			. "$s .jwsk-spisak,$s .jwsk-spisak tbody,$s .jwsk-spisak tr{display:block}$s .jwsk-spisak tr{padding:.8em 0;border-bottom:1px solid $l}"
			. "$s .jwsk-spisak td{display:grid;grid-template-columns:6.5em minmax(0,1fr);gap:.8em;padding:.2em 0;border:0}"
			. "$s .jwsk-spisak td::before{content:attr(data-label);font-weight:600}}</style>";
		$stil = true;
	}
	$lab = function( $k ) use ( $t ) {
		return ' data-label="' . $t( $k ) . '"';
	};
	$h .= '<div class="jwsk-spisak-okvir' . ( $traka ? '' : ' jwsk-stranica' ) . '"><table class="jwsk-spisak"><thead><tr><th>' . $t( 'th_kolacic' ) . '</th>' . ( $a['grupa'] ? '' : '<th>' . $t( 'th_vrsta' ) . '</th>' )
		. '<th>' . $t( 'th_pruzalac' ) . '</th><th>' . $t( 'th_svrha' ) . '</th><th>' . $t( 'th_trajanje' ) . '</th></tr></thead><tbody>';
	foreach ( jwsk_spisak() as $k ) {
		if ( $a['grupa'] && $k['grupa'] !== $a['grupa'] ) {
			continue;
		}
		$h .= '<tr><td' . $lab( 'th_kolacic' ) . '>' . esc_html( $k['naziv'] ) . '</td>'
			. ( $a['grupa'] ? '' : '<td' . $lab( 'th_vrsta' ) . '>' . $t( 'grupa_' . $k['grupa'] ) . '</td>' )
			. '<td' . $lab( 'th_pruzalac' ) . '>' . esc_html( $k['pruzalac'] ) . '</td><td' . $lab( 'th_svrha' ) . '>' . esc_html( jwsk_polje( $k, 'svrha', $j ) ) . '</td><td' . $lab( 'th_trajanje' ) . '>' . esc_html( jwsk_polje( $k, 'trajanje', $j ) ) . '</td></tr>';
	}
	return $h . '</tbody></table></div>';
} );
