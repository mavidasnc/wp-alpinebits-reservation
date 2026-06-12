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
		// Migrazione DB incrementale: eseguita solo se la versione è cambiata.
		if ( get_option( Activator::DB_VERSION_OPTION ) !== Activator::DB_VERSION ) {
			Activator::maybe_upgrade();
		}

		// Carica le traduzioni del plugin.
		load_plugin_textdomain(
			'wp-alpinebits-reservation',
			false,
			dirname( \WPAR_PLUGIN_BASENAME ) . '/languages'
		);

		// Inizializza il pannello di amministrazione.
		if ( is_admin() ) {
			( new AdminMenu() )->register();
			$this->register_action_links();
		}

		// Registra il listener per le submission CF7.
		( new SubmissionListener() )->register();

		// Inizializza l'updater GitHub.
		( new GitHubUpdater() )->register();
	}

	/**
	 * Aggiunge i link "Impostazioni" e "GitHub" nella riga del plugin nella lista plugin WP.
	 *
	 * @return void
	 */
	private function register_action_links(): void {
		add_filter(
			'plugin_action_links_' . \WPAR_PLUGIN_BASENAME,
			static function ( array $links ): array {
				$custom = [
					'<a href="' . esc_url( admin_url( 'admin.php?page=wp-alpinebits-reservation' ) ) . '">'
						. esc_html__( 'Impostazioni', 'wp-alpinebits-reservation' )
						. '</a>',
					'<a href="https://github.com/' . GitHubUpdater::GITHUB_OWNER . '/' . GitHubUpdater::GITHUB_REPO . '" target="_blank" rel="noopener noreferrer">'
						. esc_html__( 'GitHub', 'wp-alpinebits-reservation' )
						. '</a>',
				];
				return array_merge( $custom, $links );
			}
		);
	}
}
