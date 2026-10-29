<?php
/* Brisanje plugina briše postavke i zakazano čišćenje. Tabela jws_saglasnosti OSTAJE namjerno: to je dokaz pristanaka
   koji se čuva 3 godine. Ko je sigurno ne treba, briše je ručno: DROP TABLE {prefix}jws_saglasnosti */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jws_kolacici' );
delete_option( 'jws_kolacici_db' );
wp_clear_scheduled_hook( 'jwsk_ciscenje' );
