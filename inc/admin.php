<?php
/* Postavke > Kolačići: popup, verzija teksta, grupe, Google (GA4 ili GTM), Meta Pixel + CAPI, Woo, Search Console,
   spisak kolačića, evidencija pristanaka */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function() {
	register_setting( 'jws_kolacici', 'jws_kolacici', [ 'type' => 'array', 'sanitize_callback' => 'jwsk_ocisti' ] );
} );

function jwsk_ocisti( $u ) {
	$u   = (array) $u;
	$d   = jwsk_podrazumijevano();
	$id  = function( $kljuc, $obrazac, $poruka ) use ( $u ) {
		$v = strtoupper( trim( (string) ( $u[ $kljuc ] ?? '' ) ) );
		if ( '' !== $v && ! preg_match( $obrazac, $v ) ) {
			add_settings_error( 'jws_kolacici', $kljuc, $poruka );
			return '';
		}
		return $v;
	};
	$ga4  = $id( 'ga4_id', '/^G-[A-Z0-9]{4,}$/', jwsk_t( 'GA4 ID mora izgledati kao G-XXXXXXX; nije sačuvan.', 'GA4 ID must look like G-XXXXXXX; not saved.' ) );
	$gtm  = $id( 'gtm_id', '/^GTM-[A-Z0-9]{4,}$/', jwsk_t( 'GTM ID mora izgledati kao GTM-XXXXXXX; nije sačuvan.', 'GTM ID must look like GTM-XXXXXXX; not saved.' ) );
	$meta = $id( 'meta_id', '/^\d{6,20}$/', jwsk_t( 'Meta Pixel ID su samo cifre; nije sačuvan.', 'Meta Pixel ID is digits only; not saved.' ) );
	$nacin = in_array( $u['google_nacin'] ?? '', [ 'nista', 'ga4', 'gtm' ], true ) ? $u['google_nacin'] : 'nista';
	// Prazan GSC sadržaj ili cijeli meta tag zalijepljen u polje: izvući samo content
	$gsc = trim( (string) ( $u['gsc'] ?? '' ) );
	if ( preg_match( '/content=["\']([^"\']+)["\']/', $gsc, $m ) ) {
		$gsc = $m[1];
	}
	$verzija = substr( preg_replace( '/[^A-Za-z0-9._-]/', '', (string) ( $u['verzija'] ?? '' ) ), 0, 20 );
	return [
		'prikaz'          => in_array( $u['prikaz'] ?? '', [ 'auto', 'bricks', 'ugradjena', 'vlastiti' ], true ) ? $u['prikaz'] : 'auto',
		'popup'           => absint( $u['popup'] ?? 0 ),
		'boja_izvor'      => 'rucno' === ( $u['boja_izvor'] ?? '' ) ? 'rucno' : 'sajt',
		'boja'            => sanitize_hex_color( $u['boja'] ?? '' ) ?: $d['boja'],
		'boja_2_uloga'    => 'secondary' === ( $u['boja_2_uloga'] ?? '' ) ? 'secondary' : 'neutral',
		'boja_2'          => sanitize_hex_color( $u['boja_2'] ?? '' ) ?: $d['boja_2'],
		'verzija'         => '' !== $verzija ? $verzija : $d['verzija'],
		'dana'            => min( 365, max( 1, absint( $u['dana'] ?? $d['dana'] ) ) ), // zakon: najviše 12 mjeseci
		'grupe'           => [ 'statistika' => empty( $u['grupe']['statistika'] ) ? 0 : 1, 'marketing' => empty( $u['grupe']['marketing'] ) ? 0 : 1 ],
		'google_nacin'    => $nacin,
		'ga4_id'          => $ga4,
		'gtm_id'          => $gtm,
		'google_napredno' => empty( $u['google_napredno'] ) ? 0 : 1,
		'meta_id'         => $meta,
		'meta_capi_token' => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $u['meta_capi_token'] ?? '' ) ),
		'meta_test_code'  => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $u['meta_test_code'] ?? '' ) ),
		'meta_pozivni'    => substr( preg_replace( '/\D+/', '', (string) ( $u['meta_pozivni'] ?? $d['meta_pozivni'] ) ), 0, 4 ) ?: $d['meta_pozivni'],
		'woo'             => empty( $u['woo'] ) ? 0 : 1,
		'woo_grupa'       => in_array( $u['woo_grupa'] ?? '', [ 'statistika', 'marketing' ], true ) ? $u['woo_grupa'] : 'statistika',
		'gsc'             => preg_replace( '/[^A-Za-z0-9_-]/', '', $gsc ),
		'kolacici'        => sanitize_textarea_field( (string) ( $u['kolacici'] ?? '' ) ),
		'stari'           => implode( "
", array_filter( array_map( function( $r ) {
			return preg_replace( '/[^A-Za-z0-9_*.-]/', '', $r );
		}, preg_split( '/

|
|
/', (string) ( $u['stari'] ?? '' ) ) ) ) ),
		'tekstovi'        => array_filter( array_map( 'sanitize_text_field', array_intersect_key( (array) ( $u['tekstovi'] ?? [] ), jwsk_tekstovi_za_postavke() ) ) ),
	];
}

add_action( 'admin_menu', function() {
	add_options_page( jwsk_t( 'Kolačići', 'Cookies' ), jwsk_t( 'Kolačići', 'Cookies' ), 'manage_options', 'jws-kolacici', 'jwsk_stranica' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( JWSK_FAJL ), function( $l ) {
	array_unshift( $l, '<a href="' . esc_url( admin_url( 'options-general.php?page=jws-kolacici' ) ) . '">' . esc_html( jwsk_t( 'Postavke', 'Settings' ) ) . '</a>' );
	return $l;
} );

function jwsk_upozorenja( $o ) {
	$u     = [];
	$grupe = jwsk_grupe();
	$prikaz = jwsk_prikaz();
	if ( 'bricks' === $prikaz && ! defined( 'BRICKS_VERSION' ) ) {
		$u[] = jwsk_t( 'Prikaz je Bricks popup, a Bricks nije aktivan: traka se ne pokazuje. Izabrati „Ugrađena traka".', 'Display is Bricks popup but Bricks is not active: the banner never shows. Choose "Built-in banner".' );
	} elseif ( 'bricks' === $prikaz && ! $o['popup'] ) {
		$u[] = jwsk_t( 'Popup nije izabran: traka se ne pokazuje, pa niko ne može dati saglasnost i alati se nikad ne pale.', 'No popup selected: the banner never shows, so nobody can consent and no tool ever loads.' );
	} elseif ( 'bricks' === $prikaz && get_post_type( $o['popup'] ) !== 'bricks_template' ) {
		$u[] = jwsk_t( 'ID popupa nije Bricks šablon.', 'The popup ID is not a Bricks template.' );
	}
	if ( 'vlastiti' === $prikaz ) {
		$u[] = jwsk_t( 'Vlastiti HTML: element sa data-jwsk-traka i atributom hidden mora postojati na svakoj stranici (npr. u podnožju), inače se traka ne pokazuje.', 'Own HTML: an element with data-jwsk-traka and the hidden attribute must exist on every page (e.g. in the footer), otherwise no banner shows.' );
	}
	if ( 'ga4' === $o['google_nacin'] && ! $o['ga4_id'] || 'gtm' === $o['google_nacin'] && ! $o['gtm_id'] ) {
		$u[] = jwsk_t( 'Google način je izabran, ali ID nije upisan.', 'A Google mode is selected but the ID is empty.' );
	}
	if ( 'ga4' === $o['google_nacin'] && ! in_array( 'statistika', $grupe, true ) ) {
		$u[] = jwsk_t( 'GA4 traži grupu Statistika, a ona je isključena: GA4 se neće učitati.', 'GA4 needs the Statistics group, which is off: GA4 will not load.' );
	}
	if ( 'gtm' === $o['google_nacin'] && ! $grupe ) {
		$u[] = jwsk_t( 'GTM traži grupu Statistika ili Marketing; obje su isključene.', 'GTM needs the Statistics or Marketing group; both are off.' );
	}
	if ( $o['meta_id'] && ! in_array( 'marketing', $grupe, true ) ) {
		$u[] = jwsk_t( 'Meta Pixel traži grupu Marketing, a ona je isključena: Pixel i CAPI ne rade.', 'Meta Pixel needs the Marketing group, which is off: Pixel and CAPI are off.' );
	}
	if ( $o['meta_capi_token'] && ! $o['meta_id'] ) {
		$u[] = jwsk_t( 'CAPI token bez Pixel ID-a ne radi ništa.', 'A CAPI token without a Pixel ID does nothing.' );
	}
	if ( $o['google_napredno'] && jwsk_google_id() ) {
		$u[] = jwsk_t( 'Consent Mode „advanced" je uključen: Google se učitava i šalje pingove bez kolačića PRIJE pristanka. U EU i BiH je to pravno sporno; za klijente iz EU isključiti.', 'Consent Mode "advanced" is on: Google loads and sends cookieless pings BEFORE consent. Legally disputed in the EU and BiH; turn off for EU clients.' );
	}
	foreach ( [ 'Complianz' => 'complianz-gdpr/complianz-gpdr.php', 'Complianz Premium' => 'complianz-gdpr-premium/complianz-gpdr-premium.php', 'CookieYes' => 'cookie-law-info/cookie-law-info.php', 'Site Kit' => 'google-site-kit/google-site-kit.php', 'PixelYourSite' => 'pixelyoursite/facebook-pixel-master.php', 'GTM4WP' => 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' ] as $naziv => $fajl ) {
		if ( is_plugin_active( $fajl ) ) {
			/* translators: plugin name */
			$u[] = sprintf( jwsk_t( '%s je aktivan: provjeriti da ne ubacuje Google/Metu ili vlastitu traku pored ovog plugina (dupli alat = praćenje bez saglasnosti).', '%s is active: make sure it does not add Google/Meta or its own banner next to this plugin (duplicate tool = tracking without consent).' ), $naziv );
		}
	}
	return $u;
}

function jwsk_stranica() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$o      = jwsk_opcije();
	$n      = 'jws_kolacici';
	$ukupno = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . jwsk_tabela() );
	$zadnji = $wpdb->get_results( 'SELECT izbor, verzija, vrijeme FROM ' . jwsk_tabela() . ' ORDER BY id DESC LIMIT 15' );
	$popupi = get_posts( [ 'post_type' => 'bricks_template', 'posts_per_page' => 50, 'meta_key' => '_bricks_template_type', 'meta_value' => 'popup', 'post_status' => 'publish' ] );
	$opis   = function( $bs, $en ) {
		echo '<p class="description">' . esc_html( jwsk_t( $bs, $en ) ) . '</p>';
	};
	?>
	<div class="wrap">
		<h1><?php echo esc_html( jwsk_t( 'Kolačići', 'Cookies' ) ); ?></h1>
		<?php settings_errors( 'jws_kolacici' ); ?>
		<?php foreach ( jwsk_upozorenja( $o ) as $u ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( $u ); ?></p></div>
		<?php endforeach; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'jws_kolacici' ); ?>
			<h2><?php echo esc_html( jwsk_t( 'Traka', 'Banner' ) ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="jwsk-prikaz"><?php echo esc_html( jwsk_t( 'Prikaz trake', 'Banner display' ) ); ?></label></th><td>
					<select id="jwsk-prikaz" name="<?php echo $n; ?>[prikaz]">
						<?php foreach ( [ 'auto' => [ 'Automatski (Bricks popup ako je izabran, inače ugrađena)', 'Automatic (Bricks popup if selected, otherwise built-in)' ], 'bricks' => [ 'Bricks popup', 'Bricks popup' ], 'ugradjena' => [ 'Ugrađena traka plugina (bilo koji builder)', 'Built-in banner (any builder)' ], 'vlastiti' => [ 'Vlastiti HTML sajta (data-jwsk-traka)', 'Own site HTML (data-jwsk-traka)' ] ] as $k => $l ) : ?>
							<option value="<?php echo $k; ?>" <?php selected( $o['prikaz'], $k ); ?>><?php echo esc_html( jwsk_t( $l[0], $l[1] ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php $opis( 'Sada: ' . jwsk_prikaz() . '. Ugrađena traka radi na Elementoru, Diviju, Gutenbergu i bez buildera; tekstovi su gotovi (bs/en/sl), boje dugmadi ispod.', 'Now: ' . jwsk_prikaz() . '. The built-in banner works with Elementor, Divi, Gutenberg and without a builder; texts are ready (bs/en/sl), button colours below.' ); ?>
				</td></tr>
				<tr><th><?php echo esc_html( jwsk_t( 'Boje dugmadi (ugrađena traka)', 'Button colours (built-in banner)' ) ); ?></th><td>
					<label><input type="radio" name="<?php echo $n; ?>[boja_izvor]" value="sajt" <?php checked( $o['boja_izvor'], 'sajt' ); ?>> <?php echo esc_html( jwsk_t( 'Boje sajta: „Prihvatam sve" = primary, „Samo neophodni" =', 'Site colours: "Accept all" = primary, "Necessary only" =' ) ); ?></label>
					<select name="<?php echo $n; ?>[boja_2_uloga]"><option value="neutral" <?php selected( $o['boja_2_uloga'], 'neutral' ); ?>>neutral</option><option value="secondary" <?php selected( $o['boja_2_uloga'], 'secondary' ); ?>>secondary</option></select><br>
					<label><input type="radio" name="<?php echo $n; ?>[boja_izvor]" value="rucno" <?php checked( $o['boja_izvor'], 'rucno' ); ?>> <?php echo esc_html( jwsk_t( 'Ručno', 'Manual' ) ); ?></label>
					&nbsp; <label><input type="color" name="<?php echo $n; ?>[boja]" value="<?php echo esc_attr( $o['boja'] ); ?>"> <?php echo esc_html( jwsk_t( 'Prihvatam sve', 'Accept all' ) ); ?></label>
					&nbsp; <label><input type="color" name="<?php echo $n; ?>[boja_2]" value="<?php echo esc_attr( $o['boja_2'] ); ?>"> <?php echo esc_html( jwsk_t( 'Samo neophodni', 'Necessary only' ) ); ?></label>
					<?php $opis( 'Boje sajta se čitaju iz Advanced Themera (--at-primary, --at-neutral, --at-secondary), ACSS-a (--primary, --neutral, --secondary) ili Elementora (globalne boje Primary, Text, Secondary). Ako sajt nema nijednu, idu ručne boje. Ako glavna dugmad na sajtu nisu u boji primary (npr. žuto dugme, a primary tamna), izaberite Ručno. Tekst na dugmetu (bijel ili taman) bira se sam po kontrastu. „Prihvatam sve" je popunjeno, „Samo neophodni" iste veličine, sa obrubom i tekstom u drugoj boji (zakon: odbijanje jednako vidljivo).', 'Site colours come from Advanced Themer (--at-primary, --at-neutral, --at-secondary), ACSS (--primary, --neutral, --secondary) or Elementor (global Primary, Text, Secondary). If the site has none, the manual colours apply. If the main buttons of the site are not in the primary colour, choose Manual. Button text (white or dark) follows contrast. "Accept all" is filled, "Necessary only" is the same size, with a border and text in the second colour (law: refusing equally visible).' ); ?>
				</td></tr>
				<tr><th><label for="jwsk-popup">Bricks popup</label></th><td>
					<select id="jwsk-popup" name="<?php echo $n; ?>[popup]">
						<option value="0">-</option>
						<?php foreach ( $popupi as $p ) : ?>
							<option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['popup'], $p->ID ); ?>><?php echo esc_html( $p->post_title . ' (#' . $p->ID . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php $opis( 'Šablon tipa Popup, uslov „Cijeli sajt", bez okidača (plugin ga otvara sam), bez pozadine koja blokira sajt.', 'Popup template, condition "Entire website", no trigger (the plugin opens it), no backdrop blocking the site.' ); ?>
				</td></tr>
				<tr><th><label for="jwsk-verzija"><?php echo esc_html( jwsk_t( 'Verzija teksta', 'Text version' ) ); ?></label></th><td>
					<input id="jwsk-verzija" name="<?php echo $n; ?>[verzija]" value="<?php echo esc_attr( $o['verzija'] ); ?>" class="regular-text">
					<?php $opis( 'Promijenite (npr. na današnji datum) kad mijenjate TEKST trake ili svrhu kolačića. Promjena alata, grupa ili spiska kolačića sama traži novi pristanak. Važeća verzija: ' . jwsk_verzija(), 'Change it (e.g. to today\'s date) when the banner TEXT or a cookie purpose changes. Changing tools, groups or the cookie list asks for new consent automatically. Current version: ' . jwsk_verzija() ); ?>
				</td></tr>
				<tr><th><label for="jwsk-dana"><?php echo esc_html( jwsk_t( 'Izbor važi (dana)', 'Choice lasts (days)' ) ); ?></label></th><td>
					<input id="jwsk-dana" type="number" min="1" max="365" name="<?php echo $n; ?>[dana]" value="<?php echo (int) $o['dana']; ?>" class="small-text">
					<?php $opis( 'Najviše 365 (12 mjeseci). Standard flote: 180.', 'At most 365 (12 months). Fleet standard: 180.' ); ?>
				</td></tr>
				<tr><th><?php echo esc_html( jwsk_t( 'Grupe', 'Groups' ) ); ?></th><td>
					<label><input type="checkbox" checked disabled> <?php echo esc_html( jwsk_grupa_naziv( 'neophodni' ) ); ?></label><br>
					<label><input type="checkbox" name="<?php echo $n; ?>[grupe][statistika]" value="1" <?php checked( $o['grupe']['statistika'] ); ?>> <?php echo esc_html( jwsk_grupa_naziv( 'statistika' ) ); ?></label><br>
					<label><input type="checkbox" name="<?php echo $n; ?>[grupe][marketing]" value="1" <?php checked( $o['grupe']['marketing'] ); ?>> <?php echo esc_html( jwsk_grupa_naziv( 'marketing' ) ); ?></label>
					<?php $opis( 'Isključena grupa se sakriva u popupu (red sa data-jwsk-grupa). Prekidači su kod posjetioca uvijek isključeni dok ih sam ne uključi.', 'A group that is off is hidden in the popup (row with data-jwsk-grupa). Switches are always off until the visitor turns them on.' ); ?>
				</td></tr>
			</table>

			<h2>Google</h2>
			<table class="form-table" role="presentation">
				<tr><th><?php echo esc_html( jwsk_t( 'Način', 'Mode' ) ); ?></th><td>
					<?php foreach ( [ 'nista' => [ 'Bez Googlea', 'No Google' ], 'ga4' => [ 'GA4 direktno (gtag)', 'GA4 direct (gtag)' ], 'gtm' => [ 'Google Tag Manager', 'Google Tag Manager' ] ] as $k => $l ) : ?>
						<label><input type="radio" name="<?php echo $n; ?>[google_nacin]" value="<?php echo $k; ?>" <?php checked( $o['google_nacin'], $k ); ?>> <?php echo esc_html( jwsk_t( $l[0], $l[1] ) ); ?></label><br>
					<?php endforeach; ?>
					<?php $opis( 'Jedno ili drugo, nikad oba: GA4 kroz GTM i GA4 direktno bi se brojali dvaput. Consent Mode v2 (default sve odbijeno) se postavlja u <head> prije svakog Google taga.', 'One or the other, never both: GA4 via GTM plus GA4 direct counts twice. Consent Mode v2 (default all denied) is set in <head> before any Google tag.' ); ?>
				</td></tr>
				<tr><th><label for="jwsk-ga4">GA4 ID</label></th><td><input id="jwsk-ga4" name="<?php echo $n; ?>[ga4_id]" value="<?php echo esc_attr( $o['ga4_id'] ); ?>" placeholder="G-XXXXXXXXXX">
					<?php $opis( 'Učitava se tek uz Statistiku.', 'Loads only with Statistics.' ); ?></td></tr>
				<tr><th><label for="jwsk-gtm">GTM ID</label></th><td><input id="jwsk-gtm" name="<?php echo $n; ?>[gtm_id]" value="<?php echo esc_attr( $o['gtm_id'] ); ?>" placeholder="GTM-XXXXXXX">
					<?php $opis( 'Učitava se tek uz Statistiku ili Marketing. Tagovi u GTM-u: okidač na događaj jws_consent_update i varijable jws_statistika / jws_marketing (granted|denied), ili ugrađene Consent postavke taga. Kolačiće tagova iz GTM-a (osim _ga, _gcl) upisati ručno u spisak.', 'Loads only with Statistics or Marketing. GTM tags: trigger on the jws_consent_update event with variables jws_statistika / jws_marketing (granted|denied), or the tag\'s built-in Consent settings. Add cookies of GTM tags (other than _ga, _gcl) to the list manually.' ); ?></td></tr>
				<tr><th><?php echo esc_html( jwsk_t( 'Consent Mode advanced', 'Consent Mode advanced' ) ); ?></th><td>
					<label><input type="checkbox" name="<?php echo $n; ?>[google_napredno]" value="1" <?php checked( $o['google_napredno'] ); ?>> <?php echo esc_html( jwsk_t( 'Učitaj Google prije pristanka (pingovi bez kolačića)', 'Load Google before consent (cookieless pings)' ) ); ?></label>
					<?php $opis( 'Isključeno = Basic mode: nula zahtjeva ka Googleu prije klika. Advanced je u EU i BiH pravno sporan (podaci o posjeti idu Googleu bez pristanka); uključiti samo uz izričitu odluku klijenta.', 'Off = Basic mode: zero requests to Google before the click. Advanced is legally disputed in the EU and BiH (visit data goes to Google without consent); enable only on the client\'s explicit decision.' ); ?>
				</td></tr>
				<tr><th><label for="jwsk-gsc">Search Console</label></th><td><input id="jwsk-gsc" name="<?php echo $n; ?>[gsc]" value="<?php echo esc_attr( $o['gsc'] ); ?>" class="regular-text" placeholder="google-site-verification">
					<?php $opis( 'Sadržaj meta taga za verifikaciju (može se zalijepiti cijeli tag). Ne pravi kolačiće, pa ide uvijek, bez saglasnosti. Prazno ako je verifikacija preko DNS-a.', 'Verification meta tag content (the whole tag can be pasted). Sets no cookies, so it is always output, without consent. Empty if verified via DNS.' ); ?></td></tr>
			</table>

			<h2>Meta</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="jwsk-meta">Pixel ID</label></th><td><input id="jwsk-meta" name="<?php echo $n; ?>[meta_id]" value="<?php echo esc_attr( $o['meta_id'] ); ?>" placeholder="123456789012345">
					<?php $opis( 'Tek uz Marketing. PageView na svakoj stranici; na Woo sajtu i ViewContent, AddToCart, InitiateCheckout, Purchase. Ako sajt ima vlastiti Pixel kod, ovdje ostaviti prazno.', 'Only with Marketing. PageView on every page; on a Woo site also ViewContent, AddToCart, InitiateCheckout, Purchase. If the site has its own Pixel code, leave empty.' ); ?></td></tr>
				<tr><th><label for="jwsk-capi">Conversions API token</label></th><td><input id="jwsk-capi" type="password" autocomplete="off" name="<?php echo $n; ?>[meta_capi_token]" value="<?php echo esc_attr( $o['meta_capi_token'] ); ?>" class="regular-text">
					<?php $opis( 'Server šalje AddToCart, InitiateCheckout i Purchase (sa heširanim podacima kupca) samo kad kolačić kaže Marketing, sa istim event_id kao Pixel.', 'The server sends AddToCart, InitiateCheckout and Purchase (with hashed customer data) only when the cookie says Marketing, with the same event_id as the Pixel.' ); ?></td></tr>
				<tr><th><label for="jwsk-test"><?php echo esc_html( jwsk_t( 'Test kod', 'Test code' ) ); ?></label></th><td><input id="jwsk-test" name="<?php echo $n; ?>[meta_test_code]" value="<?php echo esc_attr( $o['meta_test_code'] ); ?>" placeholder="TEST12345">
					<?php $opis( 'Samo dok se provjerava u Events Manageru, pa obrisati.', 'Only while testing in Events Manager, then delete.' ); ?></td></tr>
				<tr><th><label for="jwsk-pozivni"><?php echo esc_html( jwsk_t( 'Pozivni broj', 'Country calling code' ) ); ?></label></th><td><input id="jwsk-pozivni" name="<?php echo $n; ?>[meta_pozivni]" value="<?php echo esc_attr( $o['meta_pozivni'] ); ?>" class="small-text">
					<?php $opis( 'Za telefon kupca bez pozivnog broja (061... postaje 38761...). BiH 387, Slovenija 386, Hrvatska 385.', 'For customer phones without a country code (061... becomes 38761...). BiH 387, Slovenia 386, Croatia 385.' ); ?></td></tr>
			</table>

			<h2>WooCommerce</h2>
			<table class="form-table" role="presentation">
				<tr><th><?php echo esc_html( jwsk_t( 'Izvor narudžbe', 'Order attribution' ) ); ?></th><td>
					<label><input type="checkbox" name="<?php echo $n; ?>[woo]" value="1" <?php checked( $o['woo'] ); ?>> <?php echo esc_html( jwsk_t( 'sbjs_* tek uz saglasnost za grupu', 'sbjs_* only with consent for group' ) ); ?></label>
					<select name="<?php echo $n; ?>[woo_grupa]"><?php foreach ( [ 'statistika', 'marketing' ] as $g ) : ?><option value="<?php echo $g; ?>" <?php selected( $o['woo_grupa'], $g ); ?>><?php echo esc_html( jwsk_grupa_naziv( $g ) ); ?></option><?php endforeach; ?></select>
					<?php if ( ! class_exists( 'WooCommerce' ) ) { $opis( 'WooCommerce nije aktivan, postavka nema efekta.', 'WooCommerce is not active; no effect.' ); } ?>
				</td></tr>
			</table>

			<h2><?php echo esc_html( jwsk_t( 'Ostali kolačići', 'Other cookies' ) ); ?></h2>
			<textarea name="<?php echo $n; ?>[kolacici]" rows="6" class="large-text code" aria-label="<?php echo esc_attr( jwsk_t( 'Ostali kolačići', 'Other cookies' ) ); ?>"><?php echo esc_textarea( $o['kolacici'] ); ?></textarea>
			<?php $opis( 'Red po kolačiću, u glavnom jeziku sajta: naziv | grupa | pružalac | trajanje | svrha. Grupa: neophodni, statistika ili marketing. Zvjezdica na kraju naziva = prefiks (npr. _hj*). Kolačići Googlea, Mete, Wooa i plugina se dodaju sami, na bs/en/sl. Trajanje i svrha se prevode u WPML String Translation / Polylang Strings (domena jws-kolacici). Kolačići statistike i marketinga se brišu kad posjetilac povuče saglasnost.', 'One cookie per line, in the site main language: name | group | provider | duration | purpose. Group: neophodni, statistika or marketing. Trailing asterisk = prefix (e.g. _hj*). Google, Meta, Woo and plugin cookies are added automatically in bs/en/sl. Duration and purpose are translated in WPML String Translation / Polylang Strings (domain jws-kolacici). Statistics and marketing cookies are deleted when consent is withdrawn.' ); ?>

			<h2><?php echo esc_html( jwsk_t( 'Kolačići prethodne trake', 'Cookies of the previous banner' ) ); ?></h2>
			<textarea name="<?php echo $n; ?>[stari]" rows="3" class="large-text code" aria-label="<?php echo esc_attr( jwsk_t( 'Kolačići prethodne trake', 'Cookies of the previous banner' ) ); ?>"><?php echo esc_textarea( $o['stari'] ); ?></textarea>
			<?php $opis( 'Brišu se u pregledniku pri svakom učitavanju (red po imenu, zvjezdica = prefiks). Complianz: cmplz_*, complianz_*. Ranija vlastita traka u temi: njeni kolačići, npr. dv_kolacici*.', 'Deleted in the browser on every page load (one name per line, asterisk = prefix). Complianz: cmplz_*, complianz_*. An earlier custom banner in the theme: its cookies, e.g. dv_kolacici*.' ); ?>

			<h2><?php echo esc_html( jwsk_t( 'Tekstovi', 'Texts' ) ); ?></h2>
			<?php $opis( 'Gotovi tekstovi postoje na bosanskom, engleskom i slovenačkom i biraju se po jeziku stranice. U Bricks popupu: [jws_kolacici_tekst k="uvod"]. Polje popuniti samo za zamjenu, u glavnom jeziku sajta; ostali jezici se tada prevode u WPML String Translation / Polylang Strings (domena jws-kolacici).', 'Ready-made texts exist in Bosnian, English and Slovenian and follow the page language. In the Bricks popup: [jws_kolacici_tekst k="uvod"]. Fill a field only to replace a text, in the site main language; other languages are then translated in WPML String Translation / Polylang Strings (domain jws-kolacici).' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( jwsk_tekstovi_za_postavke() as $k => $g ) : ?>
				<tr><th><code><?php echo esc_html( $k ); ?></code></th><td>
					<input name="<?php echo $n; ?>[tekstovi][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $o['tekstovi'][ $k ] ?? '' ); ?>" class="large-text" placeholder="<?php echo esc_attr( $g[0] ); ?>">
					<p class="description">EN: <?php echo esc_html( $g[1] ); ?><br>SL: <?php echo esc_html( $g[2] ); ?></p>
				</td></tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php echo esc_html( jwsk_t( 'Spisak koji vide posjetioci', 'List shown to visitors' ) ); ?></h2>
		<p class="description"><code>[jws_kolacici_spisak]</code> &nbsp; <code>[jws_kolacici_spisak grupa="marketing"]</code> &nbsp; <code>[jws_kolacici_spisak lang="en"]</code></p>
		<?php echo do_shortcode( '[jws_kolacici_spisak]' ); // phpcs:ignore ?>

		<h2><?php echo esc_html( jwsk_t( 'Evidencija pristanaka', 'Consent log' ) ); ?></h2>
		<p><?php echo esc_html( sprintf( jwsk_t( 'Zapisa: %d. Bez IP adrese i imena; brišu se poslije 3 godine.', 'Records: %d. No IP address or name; deleted after 3 years.' ), $ukupno ) ); ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jwsk_csv' ), 'jwsk_csv' ) ); ?>"><?php echo esc_html( jwsk_t( 'Izvezi CSV', 'Export CSV' ) ); ?></a></p>
		<?php if ( $zadnji ) : ?>
			<table class="widefat striped" style="max-width:720px">
				<thead><tr><th><?php echo esc_html( jwsk_t( 'Izbor', 'Choice' ) ); ?></th><th><?php echo esc_html( jwsk_t( 'Verzija', 'Version' ) ); ?></th><th><?php echo esc_html( jwsk_t( 'Vrijeme (UTC)', 'Time (UTC)' ) ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $zadnji as $r ) : ?>
					<tr><td><?php echo esc_html( [ 'sve' => jwsk_t( 'sve', 'all' ), 'nuzni' => jwsk_t( 'samo neophodni', 'necessary only' ), 'stat' => jwsk_grupa_naziv( 'statistika' ), 'mark' => jwsk_grupa_naziv( 'marketing' ) ][ $r->izbor ] ?? $r->izbor ); ?></td><td><?php echo esc_html( $r->verzija ); ?></td><td><?php echo esc_html( $r->vrijeme ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p class="description" style="margin-top:32px">JWS kolačići <?php echo esc_html( JWSK_VERZIJA_PLUGINA ); ?> &middot; <a href="https://github.com/reuf-jahic/jws-kolacici" target="_blank" rel="noopener">GitHub</a> &middot; <?php echo esc_html( jwsk_t( 'napravio', 'made by' ) ); ?> <a href="https://jahaweb.com" target="_blank" rel="noopener">Jaha Web Solutions</a></p>
	</div>
	<style>.jwsk-spisak{border-collapse:collapse;max-width:960px;background:#fff}.jwsk-spisak th,.jwsk-spisak td{border:1px solid #dcdcde;padding:6px 10px;text-align:left}</style>
	<?php
}
