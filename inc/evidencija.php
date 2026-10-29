<?php
/* Evidencija pristanaka: tabela {prefix}jws_saglasnosti (anonimni ID, izbor, verzija teksta, vrijeme), bez IP adrese i imena.
   Zapisi stariji od 3 godine se brišu jednom dnevno. */

defined( 'ABSPATH' ) || exit;

function jwsk_tabela() {
	global $wpdb;
	return $wpdb->prefix . 'jws_saglasnosti';
}

function jwsk_tabela_napravi() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . jwsk_tabela() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		cid char(36) NOT NULL,
		izbor varchar(40) NOT NULL,
		verzija varchar(40) NOT NULL,
		vrijeme datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY cid (cid),
		KEY vrijeme (vrijeme)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'jws_kolacici_db', '2', false );
}

// Sigurnosna mreža ako je plugin ubačen bez aktivacije (npr. kopiranjem preko FTP-a)
add_action( 'init', function() {
	if ( get_option( 'jws_kolacici_db' ) !== '2' ) { // '2': verzija varchar(40) (tekst + otisak alata i spiska)
		jwsk_tabela_napravi();
	}
	if ( ! wp_next_scheduled( 'jwsk_ciscenje' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'jwsk_ciscenje' );
	}
} );

/* Upis izbora iz preglednika. Bez nonce-a namjerno: stranice su keširane pa bi nonce zastario, a upis je anoniman i
   ograničen na UUID + sve|nuzni|stat|mark. Isti ID i isti izbor u zadnjoj minuti = dupli klik, ne upisuje se ponovo. */
function jwsk_upisi() {
	$cid   = isset( $_POST['cid'] ) ? sanitize_text_field( wp_unslash( $_POST['cid'] ) ) : '';
	$izbor = isset( $_POST['izbor'] ) ? sanitize_key( wp_unslash( $_POST['izbor'] ) ) : '';
	if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $cid ) || ! in_array( $izbor, [ 'sve', 'nuzni', 'stat', 'mark' ], true ) ) {
		wp_send_json_error( null, 400 );
	}
	global $wpdb;
	$skoro = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jwsk_tabela() . ' WHERE cid = %s AND izbor = %s AND vrijeme > %s LIMIT 1', $cid, $izbor, gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
	if ( ! $skoro ) {
		$wpdb->insert( jwsk_tabela(), [ 'cid' => $cid, 'izbor' => $izbor, 'verzija' => jwsk_verzija(), 'vrijeme' => gmdate( 'Y-m-d H:i:s' ) ] );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_jws_kolacici', 'jwsk_upisi' );
add_action( 'wp_ajax_nopriv_jws_kolacici', 'jwsk_upisi' );

add_action( 'jwsk_ciscenje', function() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . jwsk_tabela() . ' WHERE vrijeme < %s', gmdate( 'Y-m-d H:i:s', time() - 3 * YEAR_IN_SECONDS ) ) );
} );

// CSV izvoz evidencije (Postavke > Kolačići > Izvezi CSV); npr. kad inspekcija ili posjetilac traži dokaz pristanka
add_action( 'admin_post_jwsk_csv', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', 403 );
	}
	check_admin_referer( 'jwsk_csv' );
	global $wpdb;
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=saglasnosti-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'id', 'cid', 'izbor', 'verzija', 'vrijeme_utc' ] );
	$od = 0;
	do { // u komadima, da velika tabela ne pojede memoriju
		$redovi = $wpdb->get_results( $wpdb->prepare( 'SELECT id, cid, izbor, verzija, vrijeme FROM ' . jwsk_tabela() . ' WHERE id > %d ORDER BY id LIMIT 5000', $od ), ARRAY_N );
		foreach ( $redovi as $r ) {
			fputcsv( $out, $r );
			$od = (int) $r[0];
		}
	} while ( count( $redovi ) === 5000 );
	fclose( $out );
	exit;
} );
