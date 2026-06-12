<?php
/**
 * Classe principale del plugin: bootstrap, registrazione hook, singleton.
 *
 * @package Mavida\AlpineBitsReservation
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation;

use Mavida\AlpineBitsReservation\Admin\AdminMenu;
use Mavida\AlpineBitsReservation\Cf7\SubmissionListener;
use Mavida\AlpineBitsReservation\Updater\GitHubUpdater;

/**
 * Classe Plugin.
 *
 * Singleton che funge da entry-point dopo `plugins_loaded`.
 * Registra tutti gli hook e inizializza i moduli.
 */
final class Plugin {

	/**
	 * Istanza singleton.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Costruttore privato (singleton).
	 */
	private function __construct() {}

	/**
	 * Restituisce l'istanza singleton.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Avvia il plugin: registra hook di admin e frontend.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Carica le traduzioni del plugin.
		load_plugin_textdomain(
			'wp-alpinebits-reservation',
			false,
			dirname( WPAR_PLUGIN_BASENAME ) . '/languages'
		);

		// Inizializza il pannello di amministrazione.
		if ( is_admin() ) {
			( new AdminMenu() )->register();
		}

		// Registra il listener per le submission CF7.
		( new SubmissionListener() )->register();

		// Inizializza l'updater GitHub.
		( new GitHubUpdater() )->register();
	}
}
