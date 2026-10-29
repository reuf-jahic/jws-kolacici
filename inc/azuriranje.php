<?php
/* Ažuriranja sa GitHuba: plugin jednom u 12 sati pita javni repo za najnovije izdanje (release) i, ako je novije, nudi
   „Ažuriraj" u adminu kao svaki plugin sa wordpress.org-a (vidi i MainWP i svaki alat koji čita update_plugins).
   Paket je prilog izdanja jws-kolacici.zip (folder jws-kolacici/ unutra). Bez tokena: repo je javan. */

defined( 'ABSPATH' ) || exit;

define( 'JWSK_GITHUB', 'reuf-jahic/jws-kolacici' );

// Najnovije izdanje: ['verzija', 'zip', 'opis', 'datum'] ili null. Neuspjeh se pamti sat vremena, uspjeh 12 sati.
function jwsk_github_izdanje( $svjeze = false ) {
	$kljuc = 'jwsk_github_izdanje';
	$c     = get_site_transient( $kljuc );
	if ( false !== $c && ! $svjeze ) {
		return $c ?: null;
	}
	$out = [];
	$res = wp_remote_get( 'https://api.github.com/repos/' . JWSK_GITHUB . '/releases/latest', [
		'timeout' => 8,
		'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'jws-kolacici/' . JWSK_VERZIJA_PLUGINA ],
	] );
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$j   = json_decode( wp_remote_retrieve_body( $res ), true );
		$v   = ltrim( (string) ( $j['tag_name'] ?? '' ), 'vV' );
		$zip = '';
		foreach ( (array) ( $j['assets'] ?? [] ) as $a ) {
			if ( 'jws-kolacici.zip' === ( $a['name'] ?? '' ) ) {
				$zip = (string) $a['browser_download_url'];
			}
		}
		if ( preg_match( '/^\d+\.\d+(\.\d+)?$/', $v ) && 0 === strpos( $zip, 'https://github.com/' ) ) {
			$out = [ 'verzija' => $v, 'zip' => $zip, 'opis' => (string) ( $j['body'] ?? '' ), 'datum' => (string) ( $j['published_at'] ?? '' ) ];
		}
	}
	set_site_transient( $kljuc, $out, $out ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $out ?: null;
}

add_filter( 'pre_set_site_transient_update_plugins', function( $t ) {
	if ( ! is_object( $t ) || empty( $t->checked ) ) {
		return $t;
	}
	$iz = jwsk_github_izdanje();
	if ( ! $iz ) {
		return $t;
	}
	$plugin = plugin_basename( JWSK_FAJL );
	$stavka = (object) [
		'id'           => 'github.com/' . JWSK_GITHUB,
		'slug'         => 'jws-kolacici',
		'plugin'       => $plugin,
		'new_version'  => $iz['verzija'],
		'url'          => 'https://github.com/' . JWSK_GITHUB,
		'package'      => $iz['zip'],
		'requires_php' => '7.4',
		'icons'        => [],
		'banners'      => [],
	];
	if ( version_compare( $iz['verzija'], JWSK_VERZIJA_PLUGINA, '>' ) ) {
		$t->response[ $plugin ] = $stavka;
	} else {
		$t->no_update[ $plugin ] = $stavka;
	}
	return $t;
} );

// Prozor „Pogledaj detalje verzije" u listi pluginova
add_filter( 'plugins_api', function( $res, $akcija, $args ) {
	if ( 'plugin_information' !== $akcija || 'jws-kolacici' !== ( $args->slug ?? '' ) ) {
		return $res;
	}
	$iz = jwsk_github_izdanje();
	return (object) [
		'name'          => 'JWS kolačići',
		'slug'          => 'jws-kolacici',
		'version'       => $iz ? $iz['verzija'] : JWSK_VERZIJA_PLUGINA,
		'author'        => '<a href="https://jahaweb.com">Jaha Web Solutions</a>',
		'homepage'      => 'https://github.com/' . JWSK_GITHUB,
		'requires'      => '6.4',
		'requires_php'  => '7.4',
		'last_updated'  => $iz ? $iz['datum'] : '',
		'download_link' => $iz ? $iz['zip'] : '',
		'sections'      => [
			'description' => '<p>' . esc_html( jwsk_t( 'Obavijest o kolačićima: Google i Meta tek uz saglasnost, Consent Mode v2, evidencija pristanaka, bosanski, engleski i slovenački.', 'Cookie consent: Google and Meta only after consent, Consent Mode v2, consent log, Bosnian, English and Slovenian.' ) ) . '</p><p><a href="https://jahaweb.com">Jaha Web Solutions</a></p>',
			'changelog'   => $iz ? wpautop( esc_html( $iz['opis'] ) ) : '',
		],
	];
}, 10, 3 );

// Poslije ažuriranja odmah pitaj ponovo, da lista ne pokazuje staru verziju
add_action( 'upgrader_process_complete', function() {
	delete_site_transient( 'jwsk_github_izdanje' );
} );

// Linkovi u listi pluginova
add_filter( 'plugin_row_meta', function( $l, $fajl ) {
	if ( plugin_basename( JWSK_FAJL ) === $fajl ) {
		$l[] = '<a href="https://github.com/' . JWSK_GITHUB . '" target="_blank" rel="noopener">GitHub</a>';
		$l[] = '<a href="https://jahaweb.com" target="_blank" rel="noopener">' . esc_html( jwsk_t( 'Treba vam sajt ili održavanje? Jaha Web Solutions', 'Need a website or maintenance? Jaha Web Solutions' ) ) . '</a>';
	}
	return $l;
}, 10, 2 );
