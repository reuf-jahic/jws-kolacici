<?php
/**
 * Plugin Name: JWS kolačići
 * Description: Obavijest o kolačićima: izbor u kolačiću, Google i Meta tek uz saglasnost, evidencija pristanaka. Traka je Bricks popup sajta, ugrađena traka plugina (bilo koji builder) ili vlastiti HTML. / Cookie consent for any WordPress site.
 * Version: 1.5.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Jaha Web Solutions
 * Author URI: https://jahaweb.com
 * Plugin URI: https://github.com/reuf-jahic/jws-kolacici
 * Update URI: https://github.com/reuf-jahic/jws-kolacici
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'JWSK_VERZIJA_PLUGINA', '1.5.0' );
define( 'JWSK_FAJL', __FILE__ );
define( 'JWSK_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWSK_URL', plugin_dir_url( __FILE__ ) );
define( 'JWSK_KOLACIC', 'jws_kolacici' );       // izbor: sve | nuzni | stat | mark
define( 'JWSK_KOLACIC_V', 'jws_kolacici_v' );   // važeća verzija u trenutku izbora; druga verzija = izbor ne važi
define( 'JWSK_KOLACIC_ID', 'jws_kolacici_id' ); // anonimni ID za evidenciju pristanaka

require_once JWSK_DIR . 'inc/postavke.php';
require_once JWSK_DIR . 'inc/tekstovi.php';
require_once JWSK_DIR . 'inc/evidencija.php';
require_once JWSK_DIR . 'inc/frontend.php';
require_once JWSK_DIR . 'inc/traka.php';
require_once JWSK_DIR . 'inc/meta.php';
require_once JWSK_DIR . 'inc/ga4.php';
require_once JWSK_DIR . 'inc/azuriranje.php';
if ( is_admin() ) {
	require_once JWSK_DIR . 'inc/admin.php';
}

register_activation_hook( __FILE__, function() {
	jwsk_tabela_napravi();
	if ( false === get_option( 'jws_kolacici' ) ) {
		add_option( 'jws_kolacici', jwsk_podrazumijevano() );
	}
	if ( ! wp_next_scheduled( 'jwsk_ciscenje' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'jwsk_ciscenje' );
	}
} );

register_deactivation_hook( __FILE__, function() {
	wp_clear_scheduled_hook( 'jwsk_ciscenje' );
} );
