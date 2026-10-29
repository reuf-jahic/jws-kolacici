<?php
/* Postavke (opcija jws_kolacici), grupe, spisak kolačića, važeća verzija i provjera saglasnosti u PHP-u */

defined( 'ABSPATH' ) || exit;

function jwsk_podrazumijevano() {
	return [
		'prikaz'          => 'auto',            // auto (Bricks popup ako je izabran, inače ugrađena) | bricks | ugradjena | vlastiti (HTML sajta sa data-jwsk-traka)
		'popup'           => 0,                 // ID Bricks popupa (šablon tipa Popup)
		'boja_izvor'      => 'sajt',            // ugrađena traka: sajt (primary + neutral/secondary iz AT, ACSS, Elementora) | rucno
		'boja'            => '#1f6feb',         // „Prihvatam sve" kad sajt nema primary ili je izabrano ručno
		'boja_2_uloga'    => 'neutral',         // „Samo neophodni": neutral | secondary boja sajta
		'boja_2'          => '#1f2933',         // „Samo neophodni" kad sajt nema tu boju ili je izabrano ručno
		'verzija'         => gmdate( 'Y-m-d' ), // ručno se mijenja kad se promijeni TEKST; alati i spisak dodaju svoj otisak sami
		'dana'            => 180,               // koliko važi izbor; zakon dozvoljava najviše 12 mjeseci
		'grupe'           => [ 'statistika' => 1, 'marketing' => 1 ],
		'google_nacin'    => 'nista',           // nista | ga4 (gtag direktno) | gtm (Tag Manager); nikad oba, inače se GA4 broji dvaput
		'ga4_id'          => '',
		'gtm_id'          => '',
		'google_napredno' => 0,                 // Consent Mode „advanced": Google se učitava prije pristanka (pingovi bez kolačića); u EU pravno sporno
		'meta_id'         => '',
		'meta_capi_token' => '',
		'meta_test_code'  => '',
		'meta_pozivni'    => '387',             // pozivni broj zemlje za telefon iz narudžbe (CAPI); 0xx postaje 387xx
		'woo'             => 1,                 // Woo „izvor narudžbe" (sbjs_*) samo uz saglasnost
		'woo_grupa'       => 'statistika',
		'gsc'             => '',                // Search Console: sadržaj meta taga google-site-verification (nije pod saglasnošću, ne pravi kolačiće)
		'kolacici'        => '',                // ručni spisak, red po kolačiću
		'stari'           => "cmplz_*\ncomplianz_*", // kolačići prethodne trake (Complianz, child tema), brišu se pri svakom učitavanju
		'tekstovi'        => [],                // zamjene gotovih tekstova, u glavnom jeziku sajta
	];
}

function jwsk_opcije() {
	static $o = null;
	if ( null === $o ) {
		$o = wp_parse_args( (array) get_option( 'jws_kolacici', [] ), jwsk_podrazumijevano() );
		$o['grupe'] = wp_parse_args( (array) $o['grupe'], [ 'statistika' => 0, 'marketing' => 0 ] );
	}
	return $o;
}

// Admin: bosanski za bs/hr/sr korisnika, engleski za ostale
function jwsk_t( $bs, $en ) {
	return preg_match( '/^(bs|hr|sr|sh|cnr)/', determine_locale() ) ? $bs : $en;
}
function jwsk_grupa_naziv( $g ) {
	$n = [ 'neophodni' => [ 'Neophodni', 'Necessary' ], 'statistika' => [ 'Statistika', 'Statistics' ], 'marketing' => [ 'Marketing', 'Marketing' ] ];
	return isset( $n[ $g ] ) ? jwsk_t( $n[ $g ][0], $n[ $g ][1] ) : $g;
}

/* Kako se traka prikazuje: bricks (Bricks popup), ugradjena (traka iz plugina, za sajtove bez Bricksa ili bez popupa)
   ili vlastiti (element sajta sa data-jwsk-traka, npr. Elementor/Gutenberg blok; plugin ga samo pokazuje i skriva) */
function jwsk_prikaz() {
	$o = jwsk_opcije();
	if ( in_array( $o['prikaz'], [ 'bricks', 'ugradjena', 'vlastiti' ], true ) ) {
		return $o['prikaz'];
	}
	return $o['popup'] && defined( 'BRICKS_VERSION' ) ? 'bricks' : 'ugradjena';
}

// Uključene grupe osim neophodnih (koje uvijek postoje)
function jwsk_grupe() {
	return array_keys( array_filter( jwsk_opcije()['grupe'] ) );
}

// Google ID prema izabranom načinu, ili '' kad Google nije uključen
function jwsk_google_id() {
	$o = jwsk_opcije();
	if ( 'ga4' === $o['google_nacin'] ) {
		return $o['ga4_id'];
	}
	return 'gtm' === $o['google_nacin'] ? $o['gtm_id'] : '';
}
function jwsk_meta_aktivna() {
	return '' !== jwsk_opcije()['meta_id'] && in_array( 'marketing', jwsk_grupe(), true );
}

/* Važeća verzija = ručna verzija teksta + otisak grupa, alata i spiska kolačića. Promjena bilo čega od toga poništava stare
   izbore pa svi biraju ponovo (zakon: novi pristanak kad se promijeni svrha ili spisak). Izmjena samo teksta svrhe ne mijenja
   otisak; tada ručno promijeniti verziju. */
function jwsk_verzija() {
	static $v = null;
	if ( null === $v ) {
		$o   = jwsk_opcije();
		$sta = [ jwsk_grupe(), $o['google_nacin'], jwsk_google_id(), $o['google_napredno'], $o['meta_id'], $o['woo'], $o['woo_grupa'] ];
		foreach ( jwsk_spisak() as $k ) {
			$sta[] = [ $k['naziv'], $k['grupa'], $k['pruzalac'] ];
		}
		$v = $o['verzija'] . '-' . substr( md5( wp_json_encode( $sta ) ), 0, 6 );
	}
	return $v;
}

/* Izbor: kolačić jws_kolacici = sve | nuzni | stat | mark, kolačić jws_kolacici_v = važeća verzija.
   Vraća null kad nema važećeg izbora (nikad birao ili se verzija promijenila), inače niz prihvaćenih (i uključenih) grupa. */
function jwsk_izbor() {
	$v = isset( $_COOKIE[ JWSK_KOLACIC_V ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ JWSK_KOLACIC_V ] ) ) : '';
	$i = isset( $_COOKIE[ JWSK_KOLACIC ] ) ? sanitize_key( wp_unslash( $_COOKIE[ JWSK_KOLACIC ] ) ) : '';
	if ( $v !== jwsk_verzija() || ! in_array( $i, [ 'sve', 'nuzni', 'stat', 'mark' ], true ) ) {
		return null;
	}
	$g = [ 'sve' => [ 'statistika', 'marketing' ], 'stat' => [ 'statistika' ], 'mark' => [ 'marketing' ], 'nuzni' => [] ];
	return array_values( array_intersect( $g[ $i ], jwsk_grupe() ) );
}

/**
 * Da li je posjetilac dozvolio grupu. Za kod sajta: jws_kolacici_dozvoljeno( 'marketing' ).
 * Pažnja: stranica iz keša ne prolazi kroz PHP, pa skripte u pregledniku vežite na JS (jwsKolacici.kad), ne na ovo.
 */
function jws_kolacici_dozvoljeno( $grupa ) {
	if ( 'neophodni' === $grupa ) {
		return true;
	}
	$izbor = jwsk_izbor();
	return is_array( $izbor ) && in_array( $grupa, $izbor, true );
}

/* Ručni red: naziv | grupa | pružalac | trajanje | svrha   (u glavnom jeziku sajta; prevod kroz WPML/Polylang, vidi tekstovi.php) */
function jwsk_spisak_rucni() {
	$lista = [];
	foreach ( preg_split( '/\r\n|\r|\n/', (string) jwsk_opcije()['kolacici'] ) as $red ) {
		$d = array_map( 'trim', explode( '|', $red ) );
		if ( count( $d ) < 4 || '' === $d[0] ) {
			continue;
		}
		$d = array_pad( $d, 5, '' );
		$lista[ $d[0] ] = [ 'naziv' => $d[0], 'grupa' => sanitize_key( $d[1] ), 'pruzalac' => $d[2], 'trajanje' => $d[3], 'svrha' => $d[4] ];
	}
	return $lista;
}

/* Spisak kolačića za prikaz: ručni redovi + automatski redovi za uključene alate. Kod automatskih su trajanje i svrha
   nizovi [bs, en, sl], kod ručnih tekst za prevod. */
function jwsk_spisak() {
	static $lista = null;
	if ( null !== $lista ) {
		return $lista;
	}
	$o     = jwsk_opcije();
	$woo   = class_exists( 'WooCommerce' );
	$sajt  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$lista = jwsk_spisak_rucni();
	$mj    = max( 1, (int) round( (int) $o['dana'] / 30 ) );
	$mjt   = [ "$mj mj.", "$mj mo.", "$mj mes." ];
	$auto  = [
		[ JWSK_KOLACIC, 'neophodni', $sajt, $mjt, [ 'Pamti vaš izbor kolačića.', 'Remembers your cookie choice.', 'Shrani vašo izbiro piškotkov.' ] ],
		[ JWSK_KOLACIC_V, 'neophodni', $sajt, $mjt, [ 'Pamti na koju verziju obavijesti se izbor odnosi.', 'Remembers which version of the notice the choice applies to.', 'Shrani, na katero različico obvestila se izbira nanaša.' ] ],
		[ JWSK_KOLACIC_ID, 'neophodni', $sajt, $mjt, [ 'Anonimna oznaka vašeg pristanka, bez ličnih podataka.', 'Anonymous reference to your consent, no personal data.', 'Anonimna oznaka vašega soglasja, brez osebnih podatkov.' ] ],
	];
	if ( $woo ) {
		$ses    = [ 'sesija', 'session', 'seja' ];
		$auto[] = [ 'woocommerce_cart_hash', 'neophodni', $sajt, $ses, [ 'Pamti sadržaj korpe.', 'Keeps track of the cart contents.', 'Shrani vsebino košarice.' ] ];
		$auto[] = [ 'woocommerce_items_in_cart', 'neophodni', $sajt, $ses, [ 'Pamti da li je korpa prazna.', 'Remembers whether the cart is empty.', 'Shrani, ali je košarica prazna.' ] ];
		$auto[] = [ 'wp_woocommerce_session_*', 'neophodni', $sajt, [ '2 dana', '2 days', '2 dni' ], [ 'Veže korpu za vašu posjetu.', 'Links the cart to your visit.', 'Poveže košarico z vašim obiskom.' ] ];
		if ( $o['woo'] ) {
			$auto[] = [ 'sbjs_*', $o['woo_grupa'], $sajt, [ 'do 6 mj.', 'up to 6 mo.', 'do 6 mes.' ], [ 'Bilježi odakle ste došli (npr. Google, Facebook) uz narudžbu.', 'Records where you came from (e.g. Google, Facebook) with the order.', 'Zabeleži, od kod ste prišli (npr. Google, Facebook), skupaj z naročilom.' ] ];
		}
	}
	if ( jwsk_google_id() ) {
		$god    = [ '2 god.', '2 yr.', '2 leti' ];
		$auto[] = [ '_ga', 'statistika', 'Google', $god, [ 'Google Analytics: razlikuje posjetioce.', 'Google Analytics: distinguishes visitors.', 'Google Analytics: razlikuje obiskovalce.' ] ];
		$auto[] = [ '_ga_*', 'statistika', 'Google', $god, [ 'Google Analytics: pamti stanje posjete.', 'Google Analytics: keeps the session state.', 'Google Analytics: shrani stanje obiska.' ] ];
		if ( $woo ) {
			$auto[] = [ 'jws_ga_atc', 'statistika', $sajt, [ '2 min.', '2 min.', '2 min.' ], [ 'Prenosi dodavanje u korpu Google Analyticsu.', 'Passes the add-to-cart event to Google Analytics.', 'Posreduje dodajanje v košarico Google Analyticsu.' ] ];
		}
	}
	if ( '' !== $o['meta_id'] ) {
		$tri    = [ '3 mj.', '3 mo.', '3 mes.' ];
		$auto[] = [ '_fbp', 'marketing', 'Meta', $tri, [ 'Meta Pixel: mjeri oglase na Facebooku i Instagramu.', 'Meta Pixel: measures Facebook and Instagram ads.', 'Meta Pixel: meri oglase na Facebooku in Instagramu.' ] ];
		$auto[] = [ '_fbc', 'marketing', 'Meta', $tri, [ 'Meta Pixel: pamti klik na oglas.', 'Meta Pixel: remembers the ad click.', 'Meta Pixel: shrani klik na oglas.' ] ];
		if ( $woo ) {
			$auto[] = [ 'jws_meta_atc', 'marketing', $sajt, [ '2 min.', '2 min.', '2 min.' ], [ 'Prenosi dodavanje u korpu Meta Pixelu.', 'Passes the add-to-cart event to the Meta Pixel.', 'Posreduje dodajanje v košarico Meta Pixlu.' ] ];
		}
	}
	foreach ( $auto as $a ) {
		if ( ! isset( $lista[ $a[0] ] ) ) {
			$lista[ $a[0] ] = [ 'naziv' => $a[0], 'grupa' => $a[1], 'pruzalac' => $a[2], 'trajanje' => $a[3], 'svrha' => $a[4] ];
		}
	}
	// Grupa koja nije uključena ne može imati kolačiće; red bi lagao posjetiocu
	$lista = array_values( array_filter( $lista, function( $k ) {
		return 'neophodni' === $k['grupa'] || in_array( $k['grupa'], jwsk_grupe(), true );
	} ) );
	return $lista;
}

// Trajanje ili svrha reda u jeziku stranice
function jwsk_polje( $k, $polje, $jezik = null ) {
	if ( is_array( $k[ $polje ] ) ) {
		$i = array_search( $jezik ?: jwsk_jezik(), [ 'bs', 'en', 'sl' ], true );
		return $k[ $polje ][ false === $i ? 1 : $i ];
	}
	return jwsk_prevedi( 'kolacic ' . $k['naziv'] . ' ' . $polje, $k[ $polje ] );
}
