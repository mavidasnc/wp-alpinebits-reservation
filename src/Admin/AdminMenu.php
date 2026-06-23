<?php
/**
 * Registra il menu principale e la pagina a tab nel pannello WordPress.
 *
 * @package Mavida\AlpineBitsReservation\Admin
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin;

use Mavida\AlpineBitsReservation\Admin\Tabs\ConnectionTab;
use Mavida\AlpineBitsReservation\Admin\Tabs\FormsTab;
use Mavida\AlpineBitsReservation\Admin\Tabs\LogTab;
use Mavida\AlpineBitsReservation\Admin\Tabs\MappingTab;
use Mavida\AlpineBitsReservation\Admin\Tabs\NotificationsTab;
use Mavida\AlpineBitsReservation\Api\Client;
use Mavida\AlpineBitsReservation\Reservations\Sender;
use Mavida\AlpineBitsReservation\Updater\VersionChecker;

/**
 * Classe AdminMenu.
 *
 * Registra la voce di menu e gestisce il routing tra i tab.
 * Gestisce anche le action AJAX per test connessione, reload campi, reinvio e check versione.
 */
class AdminMenu {

	/**
	 * Slug della pagina di amministrazione.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'wp-alpinebits-reservation';

	/**
	 * Tab disponibili nell'ordine di visualizzazione.
	 *
	 * @var array<string, string>
	 */
	private array $tabs = [];

	/**
	 * Costruttore vuoto: le label dei tab sono inizializzate in modo lazy
	 * tramite get_tabs() per evitare chiamate a __() prima dell'hook `init` (WP 6.7+).
	 */
	public function __construct() {}

	/**
	 * Restituisce la mappa slug => label dei tab, inizializzandola al primo accesso.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs(): array {
		if ( empty( $this->tabs ) ) {
			$this->tabs = [
				'connection'    => __( 'Connessione', 'wp-alpinebits-reservation' ),
				'forms'         => __( 'Moduli', 'wp-alpinebits-reservation' ),
				'mapping'       => __( 'Mapping', 'wp-alpinebits-reservation' ),
				'notifications' => __( 'Notifiche', 'wp-alpinebits-reservation' ),
				'log'           => __( 'Invii', 'wp-alpinebits-reservation' ),
			];
		}
		return $this->tabs;
	}

	/**
	 * Registra gli hook WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_wpar_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_wpar_get_form_fields', [ $this, 'ajax_get_form_fields' ] );
		add_action( 'wp_ajax_wpar_resend', [ $this, 'ajax_resend' ] );
		add_action( 'wp_ajax_wpar_check_version', [ $this, 'ajax_check_version' ] );
	}

	/**
	 * Aggiunge la voce di menu nel pannello WordPress.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'AlpineBits Reservation', 'wp-alpinebits-reservation' ),
			__( 'AlpineBits', 'wp-alpinebits-reservation' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			'dashicons-calendar-alt',
			75
		);
	}

	/**
	 * Carica CSS e JS solo nella pagina del plugin.
	 *
	 * @param  string $hook_suffix Suffisso della pagina corrente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wpar-admin',
			\WPAR_PLUGIN_URL . 'assets/css/admin.css',
			[],
			\WPAR_VERSION
		);

		wp_enqueue_script(
			'wpar-admin',
			\WPAR_PLUGIN_URL . 'assets/js/admin-mapping.js',
			[ 'jquery' ],
			\WPAR_VERSION,
			true
		);

		wp_localize_script(
			'wpar-admin',
			'wparAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpar_admin_nonce' ),
				'i18n'    => [
					'testOk'           => __( 'Connessione riuscita', 'wp-alpinebits-reservation' ),
					'testFail'         => __( 'Connessione fallita', 'wp-alpinebits-reservation' ),
					'resendOk'         => __( 'Reinviato con successo', 'wp-alpinebits-reservation' ),
					'resendFail'       => __( 'Reinvio fallito', 'wp-alpinebits-reservation' ),
					'loading'          => __( 'Caricamento...', 'wp-alpinebits-reservation' ),
					'none'             => __( '— Nessuno (campo non mappato) —', 'wp-alpinebits-reservation' ),
					'const'            => __( '— Valore costante —', 'wp-alpinebits-reservation' ),
					'versionChecking'  => __( 'Controllo in corso...', 'wp-alpinebits-reservation' ),
					'versionUpToDate'  => __( 'Sei alla versione più recente.', 'wp-alpinebits-reservation' ),
					'versionAvailable' => __( 'Nuova versione disponibile:', 'wp-alpinebits-reservation' ),
					'versionUpdate'    => __( 'Aggiorna ora', 'wp-alpinebits-reservation' ),
					'versionError'     => __( 'Impossibile verificare gli aggiornamenti.', 'wp-alpinebits-reservation' ),
				],
			]
		);
	}

	/**
	 * Rendering della pagina a tab.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-alpinebits-reservation' ) );
		}

		$current_tab = isset( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_GET['tab'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'connection';

		$tabs = $this->get_tabs();

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'connection';
		}

		?>
		<div class="wrap wpar-wrap">
			<h1>
				<?php esc_html_e( 'AlpineBits Reservation', 'wp-alpinebits-reservation' ); ?>
				<span class="wpar-version">v<?php echo esc_html( \WPAR_VERSION ); ?></span>
			</h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>"
						class="nav-tab<?php echo $slug === $current_tab ? ' nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wpar-tab-content">
				<?php $this->render_tab( $current_tab ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Delega il rendering al tab specifico.
	 *
	 * @param  string $tab Slug del tab.
	 * @return void
	 */
	private function render_tab( string $tab ): void {
		match ( $tab ) {
			'connection'    => ( new ConnectionTab() )->render(),
			'forms'         => ( new FormsTab() )->render(),
			'mapping'       => ( new MappingTab() )->render(),
			'notifications' => ( new NotificationsTab() )->render(),
			'log'           => ( new LogTab() )->render(),
			default         => ( new ConnectionTab() )->render(),
		};
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: verifica la connessione all'API.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'wpar_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'wp-alpinebits-reservation' ) ] );
		}

		$result = ( new Client() )->test_connection();

		if ( $result->success ) {
			wp_send_json_success(
				[
					/* translators: %d: HTTP status code */
					'message' => sprintf( __( 'Connessione riuscita (HTTP %d).', 'wp-alpinebits-reservation' ), $result->http_code ),
				]
			);
		} else {
			wp_send_json_error( [ 'message' => $result->error ] );
		}
	}

	/**
	 * AJAX: restituisce i campi di un form CF7 per popolare le select nel tab Mapping.
	 *
	 * @return void
	 */
	public function ajax_get_form_fields(): void {
		check_ajax_referer( 'wpar_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $form_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'form_id non valido.' ] );
		}

		$fields = \Mavida\AlpineBitsReservation\Cf7\FormFields::for_form( $form_id );
		wp_send_json_success( [ 'fields' => $fields ] );
	}

	/**
	 * AJAX: reinvia una submission esistente.
	 *
	 * @return void
	 */
	public function ajax_resend(): void {
		check_ajax_referer( 'wpar_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$row_id = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $row_id <= 0 ) {
			wp_send_json_error( [ 'message' => 'ID non valido.' ] );
		}

		$success = ( new Sender() )->resend( $row_id );

		if ( $success ) {
			wp_send_json_success( [ 'message' => __( 'Reinviato con successo.', 'wp-alpinebits-reservation' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Reinvio fallito. Controllare il tab Invii per i dettagli.', 'wp-alpinebits-reservation' ) ] );
		}
	}

	/**
	 * AJAX: verifica la disponibilità di una nuova versione su GitHub.
	 *
	 * Svuota la cache locale e quella di WordPress per forzare un controllo fresco.
	 *
	 * @return void
	 */
	public function ajax_check_version(): void {
		check_ajax_referer( 'wpar_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permessi insufficienti.', 'wp-alpinebits-reservation' ) ] );
		}

		$checker = new VersionChecker();
		$checker->clear_cache();
		$result = $checker->check_latest();

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success( $result );
	}
}
