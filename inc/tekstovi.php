<?php
/* Tekstovi za posjetioce: gotovi na bosanskom, engleskom i slovenačkom. Jezik po stranici (WPML, Polylang ili locale).
   Tekst iz postavki (glavni jezik sajta) zamjenjuje gotov i prevodi se kroz WPML String Translation ili Polylang Strings.
   U Bricksu: [jws_kolacici_tekst k="uvod"] u Basic/Rich Text elementu. */

defined( 'ABSPATH' ) || exit;

// bs | en | sl prema jeziku trenutne stranice
function jwsk_jezik() {
	$kod = '';
	if ( has_filter( 'wpml_current_language' ) ) {
		$kod = (string) apply_filters( 'wpml_current_language', null );
	} elseif ( function_exists( 'pll_current_language' ) ) {
		$kod = (string) pll_current_language( 'locale' );
	}
	if ( '' === $kod || 'all' === $kod ) {
		$kod = determine_locale();
	}
	if ( preg_match( '/^sl/', $kod ) ) {
		return 'sl';
	}
	return preg_match( '/^(bs|hr|sr|sh|cnr)/', $kod ) ? 'bs' : 'en';
}

function jwsk_gotovi_tekstovi() {
	return [
		'naslov'           => [ 'Kolačići', 'Cookies', 'Piškotki' ],
		'uvod'             => [ 'Koristimo neophodne kolačiće da bi sajt radio. Uz vaš pristanak koristimo i kolačiće za statistiku i marketing. Izbor možete promijeniti bilo kada preko linka „Postavke kolačića“ u podnožju.',
			'We use necessary cookies to make the site work. With your consent we also use cookies for statistics and marketing. You can change your choice at any time via “Cookie settings” in the footer.',
			'Uporabljamo nujne piškotke, da spletno mesto deluje. Z vašim soglasjem uporabljamo tudi piškotke za statistiko in trženje. Izbiro lahko kadar koli spremenite prek povezave „Nastavitve piškotkov“ v nogi strani.' ],
		'prihvati'         => [ 'Prihvatam sve', 'Accept all', 'Sprejmi vse' ],
		'odbij'            => [ 'Samo neophodni', 'Necessary only', 'Samo nujni' ],
		'postavke'         => [ 'Postavke', 'Settings', 'Nastavitve' ],
		'sacuvaj'          => [ 'Sačuvaj izbor', 'Save choice', 'Shrani izbiro' ],
		'link'             => [ 'Postavke kolačića', 'Cookie settings', 'Nastavitve piškotkov' ],
		'grupa_neophodni'  => [ 'Neophodni', 'Necessary', 'Nujni' ],
		'grupa_statistika' => [ 'Statistika', 'Statistics', 'Statistika' ],
		'grupa_marketing'  => [ 'Marketing', 'Marketing', 'Trženje' ],
		'opis_neophodni'   => [ 'Potrebni za rad sajta i za pamćenje vašeg izbora kolačića. Ne mogu se isključiti.', 'Needed for the site to work and to remember your cookie choice. They cannot be switched off.', 'Potrebni za delovanje spletnega mesta in za shranjevanje vaše izbire piškotkov. Ni jih mogoče izklopiti.' ],
		// Varijante koje plugin bira sam prema uključenim grupama i WooCommerceu (nisu u postavkama)
		'uvod_statistika'  => [ 'Koristimo neophodne kolačiće da bi sajt radio. Uz vaš pristanak koristimo i kolačiće za statistiku posjeta. Izbor možete promijeniti bilo kada preko linka „Postavke kolačića“ u podnožju.',
			'We use necessary cookies to make the site work. With your consent we also use cookies for visit statistics. You can change your choice at any time via “Cookie settings” in the footer.',
			'Uporabljamo nujne piškotke, da spletno mesto deluje. Z vašim soglasjem uporabljamo tudi piškotke za statistiko obiskov. Izbiro lahko kadar koli spremenite prek povezave „Nastavitve piškotkov“ v nogi strani.' ],
		'uvod_marketing'   => [ 'Koristimo neophodne kolačiće da bi sajt radio. Uz vaš pristanak koristimo i kolačiće za marketing. Izbor možete promijeniti bilo kada preko linka „Postavke kolačića“ u podnožju.',
			'We use necessary cookies to make the site work. With your consent we also use cookies for marketing. You can change your choice at any time via “Cookie settings” in the footer.',
			'Uporabljamo nujne piškotke, da spletno mesto deluje. Z vašim soglasjem uporabljamo tudi piškotke za trženje. Izbiro lahko kadar koli spremenite prek povezave „Nastavitve piškotkov“ v nogi strani.' ],
		'opis_neophodni_woo' => [ 'Potrebni za rad sajta (korpa, prijava, vaš izbor kolačića). Ne mogu se isključiti.', 'Needed for the site to work (cart, login, your cookie choice). They cannot be switched off.', 'Potrebni za delovanje spletnega mesta (košarica, prijava, vaša izbira piškotkov). Ni jih mogoče izklopiti.' ],
		'opis_statistika'  => [ 'Pokazuju nam koje stranice se posjećuju, zbirno i bez vašeg imena.', 'Show us which pages are visited, in aggregate and without your name.', 'Pokažejo nam, katere strani so obiskane, zbirno in brez vašega imena.' ],
		'opis_marketing'   => [ 'Mjere uspjeh naših oglasa na Googleu, Facebooku i Instagramu.', 'Measure how our ads on Google, Facebook and Instagram perform.', 'Merijo uspešnost naših oglasov na Googlu, Facebooku in Instagramu.' ],
		'politika'         => [ 'Politika privatnosti', 'Privacy policy', 'Politika zasebnosti' ],
		'spisak'           => [ 'Kolačići u ovoj grupi', 'Cookies in this group', 'Piškotki v tej skupini' ],
		'th_kolacic'       => [ 'Kolačić', 'Cookie', 'Piškotek' ],
		'th_vrsta'         => [ 'Vrsta', 'Type', 'Vrsta' ],
		'th_pruzalac'      => [ 'Pružalac', 'Provider', 'Ponudnik' ],
		'th_svrha'         => [ 'Svrha', 'Purpose', 'Namen' ],
		'th_trajanje'      => [ 'Trajanje', 'Duration', 'Trajanje' ],
	];
}

// Ključevi koje admin može zamijeniti (zaglavlja tabele ostaju gotova)
function jwsk_tekstovi_za_postavke() {
	return array_filter( jwsk_gotovi_tekstovi(), function( $k ) {
		return strpos( $k, 'th_' ) !== 0 && ! in_array( $k, [ 'uvod_statistika', 'uvod_marketing', 'opis_neophodni_woo' ], true );
	}, ARRAY_FILTER_USE_KEY );
}

// Prevod niza iz postavki kroz WPML ili Polylang; bez njih vraća original
function jwsk_prevedi( $ime, $tekst ) {
	if ( has_filter( 'wpml_translate_single_string' ) ) {
		return (string) apply_filters( 'wpml_translate_single_string', $tekst, 'jws-kolacici', $ime );
	}
	if ( function_exists( 'pll__' ) ) {
		return pll__( $tekst );
	}
	return $tekst;
}

function jwsk_tekst( $kljuc, $jezik = null ) {
	$o = jwsk_opcije();
	if ( ! empty( $o['tekstovi'][ $kljuc ] ) && null === $jezik ) {
		return jwsk_prevedi( $kljuc, $o['tekstovi'][ $kljuc ] );
	}
	// Gotov tekst ne smije pominjati grupu koje na sajtu nema, ni korpu na sajtu bez prodavnice
	if ( 'uvod' === $kljuc ) {
		$gr = jwsk_grupe();
		if ( 1 === count( $gr ) ) {
			$kljuc = 'uvod_' . $gr[0];
		}
	} elseif ( 'opis_neophodni' === $kljuc && class_exists( 'WooCommerce' ) ) {
		$kljuc = 'opis_neophodni_woo';
	}
	$g = jwsk_gotovi_tekstovi();
	if ( ! isset( $g[ $kljuc ] ) ) {
		return '';
	}
	$i = array_search( $jezik ?: jwsk_jezik(), [ 'bs', 'en', 'sl' ], true );
	return $g[ $kljuc ][ false === $i ? 1 : $i ];
}

// Ručni redovi spiska kolačića i zamjenski tekstovi se registruju za prevod (WPML String Translation / Polylang Strings), samo u adminu
add_action( 'admin_init', function() {
	$o      = jwsk_opcije();
	$nizovi = [];
	foreach ( (array) $o['tekstovi'] as $k => $v ) {
		if ( '' !== $v ) {
			$nizovi[ $k ] = $v;
		}
	}
	foreach ( jwsk_spisak_rucni() as $k ) {
		$nizovi[ 'kolacic ' . $k['naziv'] . ' trajanje' ] = $k['trajanje'];
		$nizovi[ 'kolacic ' . $k['naziv'] . ' svrha' ]    = $k['svrha'];
	}
	foreach ( $nizovi as $ime => $v ) {
		if ( has_action( 'wpml_register_single_string' ) ) {
			do_action( 'wpml_register_single_string', 'jws-kolacici', $ime, $v );
		} elseif ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( $ime, $v, 'JWS kolačići', strlen( $v ) > 60 );
		}
	}
} );

add_shortcode( 'jws_kolacici_tekst', function( $atts ) {
	$a = shortcode_atts( [ 'k' => '', 'lang' => '' ], $atts );
	return esc_html( jwsk_tekst( sanitize_key( $a['k'] ), $a['lang'] ?: null ) );
} );
