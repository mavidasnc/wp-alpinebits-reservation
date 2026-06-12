<?php
/**
 * Tab "Connessione": credenziali API, test connessione e verifica aggiornamenti.
 *
 * @package Mavida\AlpineBitsReservation\Admin\Tabs
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin\Tabs;

use Mavida\AlpineBitsReservation\Settings\Options;
use Mavida\AlpineBitsReservation\Updater\GitHubUpdater;

/**
 * Classe ConnectionTab.
 *
 * Gestisce la visualizzazione e il salvataggio delle credenziali API.
 * Mostra anche la sezione di verifica aggiornamenti con pulsante "Controlla" (AJAX).
 */
class ConnectionTab {

	/**
	 * Processa il form e renderizza il tab.
	 *
	 * @return void
	 */
	public function render(): void {
		// Salvataggio form.
		if ( isset( $_POST['wpar_save_connection'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->save();
		}

		$settings     = Options::get_all();
		$has_password = ! empty( $settings['api_password'] );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'wpar_connection_save', 'wpar_connection_nonce' ); ?>

			<h2><?php esc_html_e( 'Credenziali API AlpineBits', 'wp-alpinebits-reservation' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Le credenziali vengono salvate in modo cifrato nel database. In alternativa puoi definirle come costanti in wp-config.php (vedi README).', 'wp-alpinebits-reservation' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wpar_api_base_url"><?php esc_html_e( 'Base URL API', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="wpar_api_base_url"
							name="wpar_api_base_url"
							class="regular-text"
							value="<?php echo esc_attr( $settings['api_base_url'] ); ?>"
							<?php echo defined( 'WPAR_API_BASE_URL' ) ? 'disabled' : ''; ?>
						/>
						<?php if ( defined( 'WPAR_API_BASE_URL' ) ) : ?>
							<p class="description"><?php esc_html_e( 'Valore definito tramite costante WPAR_API_BASE_URL in wp-config.php.', 'wp-alpinebits-reservation' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpar_api_username"><?php esc_html_e( 'Username API', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wpar_api_username"
							name="wpar_api_username"
							class="regular-text"
							value="<?php echo esc_attr( defined( 'WPAR_API_USERNAME' ) ? \WPAR_API_USERNAME : $settings['api_username'] ); ?>"
							autocomplete="off"
							<?php echo defined( 'WPAR_API_USERNAME' ) ? 'disabled' : ''; ?>
						/>
						<?php if ( defined( 'WPAR_API_USERNAME' ) ) : ?>
							<p class="description"><?php esc_html_e( 'Valore definito tramite costante WPAR_API_USERNAME in wp-config.php.', 'wp-alpinebits-reservation' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpar_api_password"><?php esc_html_e( 'Password API', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="wpar_api_password"
							name="wpar_api_password"
							class="regular-text"
							value=""
							autocomplete="new-password"
							placeholder="<?php echo $has_password ? esc_attr__( '••••••••  (salvata — lascia vuoto per non modificarla)', 'wp-alpinebits-reservation' ) : ''; ?>"
							<?php echo defined( 'WPAR_API_PASSWORD' ) ? 'disabled' : ''; ?>
						/>
						<?php if ( defined( 'WPAR_API_PASSWORD' ) ) : ?>
							<p class="description"><?php esc_html_e( 'Valore definito tramite costante WPAR_API_PASSWORD in wp-config.php.', 'wp-alpinebits-reservation' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpar_default_status"><?php esc_html_e( 'Status di default', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<select id="wpar_default_status" name="wpar_default_status">
							<option value="request" <?php selected( $settings['default_status'], 'request' ); ?>>
								<?php esc_html_e( 'request (richiesta)', 'wp-alpinebits-reservation' ); ?>
							</option>
							<option value="reservation" <?php selected( $settings['default_status'], 'reservation' ); ?>>
								<?php esc_html_e( 'reservation (prenotazione confermata)', 'wp-alpinebits-reservation' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Usato come fallback se il campo "status" non è mappato nel form.', 'wp-alpinebits-reservation' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="wpar_save_connection" class="button button-primary">
					<?php esc_html_e( 'Salva impostazioni', 'wp-alpinebits-reservation' ); ?>
				</button>
				<button
					type="button"
					id="wpar-test-connection"
					class="button button-secondary"
					style="margin-left: 8px;"
				>
					<?php esc_html_e( 'Testa connessione', 'wp-alpinebits-reservation' ); ?>
				</button>
				<span id="wpar-test-result" style="margin-left: 12px; font-weight: 600;"></span>
			</p>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Versione plugin e aggiornamenti', 'wp-alpinebits-reservation' ); ?></h2>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: URL del repository GitHub */
					__( 'Repository: <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', 'wp-alpinebits-reservation' ),
					esc_url( 'https://github.com/' . GitHubUpdater::GITHUB_OWNER . '/' . GitHubUpdater::GITHUB_REPO ),
					esc_html( 'github.com/' . GitHubUpdater::GITHUB_OWNER . '/' . GitHubUpdater::GITHUB_REPO )
				),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			);
			?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Versione installata', 'wp-alpinebits-reservation' ); ?></th>
				<td>
					<strong>v<?php echo esc_html( \WPAR_VERSION ); ?></strong>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Aggiornamenti', 'wp-alpinebits-reservation' ); ?></th>
				<td>
					<button
						type="button"
						id="wpar-check-version"
						class="button button-secondary"
					>
						<?php esc_html_e( 'Controlla aggiornamenti', 'wp-alpinebits-reservation' ); ?>
					</button>
					<span id="wpar-version-result" style="margin-left: 10px;"></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Processa e salva i dati del form.
	 *
	 * @return void
	 */
	private function save(): void {
		if (
			! isset( $_POST['wpar_connection_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpar_connection_nonce'] ) ), 'wpar_connection_save' )
		) {
			add_settings_error( 'wpar', 'nonce', __( 'Nonce non valido.', 'wp-alpinebits-reservation' ), 'error' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		Options::save_settings(
			[
				'api_base_url'   => isset( $_POST['wpar_api_base_url'] ) ? wp_unslash( $_POST['wpar_api_base_url'] ) : '',
				'api_username'   => isset( $_POST['wpar_api_username'] ) ? wp_unslash( $_POST['wpar_api_username'] ) : '',
				'api_password'   => isset( $_POST['wpar_api_password'] ) ? wp_unslash( $_POST['wpar_api_password'] ) : '',
				'default_status' => isset( $_POST['wpar_default_status'] ) ? wp_unslash( $_POST['wpar_default_status'] ) : 'request',
			]
		);

		add_settings_error( 'wpar', 'saved', __( 'Impostazioni salvate.', 'wp-alpinebits-reservation' ), 'success' );
		settings_errors( 'wpar' );
	}
}
