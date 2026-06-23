<?php
/**
 * Plugin Name:       WP AlpineBits Reservation
 * Plugin URI:        https://github.com/mavidasnc/wp-alpinebits-reservation
 * Description:       Intercetta form Contact Form 7 e li invia all'endpoint sendReservation dell'API AlpineBits Gateway.
 * Version:           0.2.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Maurizio Mavida
 * Author URI:        https://mavida.com
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-alpinebits-reservation
 * Domain Path:       /languages
 *
 * @package Mavida\AlpineBitsReservation
 */

declare( strict_types=1 );

// Impedisce l'accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti del plugin.
define( 'WPAR_VERSION', '0.2.3' );
define( 'WPAR_PLUGIN_FILE', __FILE__ );
define( 'WPAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Carica il file di autoload generato da Composer.
$wpar_autoload = WPAR_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $wpar_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>WP AlpineBits Reservation:</strong> ';
			printf(
				/* translators: %s: comando da eseguire nella cartella del plugin */
				esc_html__(
					'Le dipendenze Composer non sono installate. Esegui %s nella cartella del plugin.',
					'wp-alpinebits-reservation'
				),
				'<code>composer install</code>'
			);
			echo '</p></div>';
		}
	);
	return;
}
require_once $wpar_autoload;

use Mavida\AlpineBitsReservation\Activator;
use Mavida\AlpineBitsReservation\Plugin;

// Hook di attivazione e disattivazione.
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

// Avvia il plugin dopo che tutti i plugin sono stati caricati.
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::get_instance()->boot();
	}
);
