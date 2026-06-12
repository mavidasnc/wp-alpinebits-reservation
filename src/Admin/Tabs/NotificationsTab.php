<?php
/**
 * Tab "Notifiche": configurazione email di notifica per ogni invio/reinvio.
 *
 * @package Mavida\AlpineBitsReservation\Admin\Tabs
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin\Tabs;

use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe NotificationsTab.
 *
 * Gestisce la configurazione delle notifiche email:
 * indirizzo destinatario, oggetto e corpo con placeholder {field_name},
 * selezione degli eventi che attivano la notifica.
 * Ad ogni email viene allegato un JSON con tutti i dati del form CF7.
 */
class NotificationsTab {

	/**
	 * Processa il form e renderizza il tab.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( isset( $_POST['wpar_save_notifications'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->save();
		}

		$settings = Options::notifications();
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'wpar_notifications_save', 'wpar_notifications_nonce' ); ?>

			<h2><?php esc_html_e( 'Impostazioni notifiche email', 'wp-alpinebits-reservation' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configura le notifiche email inviate a ogni invio o reinvio di prenotazione. Se il campo "Indirizzo email" è vuoto le notifiche sono disabilitate.', 'wp-alpinebits-reservation' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wpar_notify_email"><?php esc_html_e( 'Indirizzo email destinatario', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<input
							type="email"
							id="wpar_notify_email"
							name="wpar_notify_email"
							class="regular-text"
							value="<?php echo esc_attr( $settings['email_to'] ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Lascia vuoto per disabilitare completamente le notifiche.', 'wp-alpinebits-reservation' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Invia notifica per', 'wp-alpinebits-reservation' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input
									type="checkbox"
									name="wpar_notify_on_success"
									value="1"
									<?php checked( ! empty( $settings['notify_on_success'] ) ); ?>
								/>
								<?php esc_html_e( 'Invio API riuscito', 'wp-alpinebits-reservation' ); ?>
							</label>
							<br />
							<label>
								<input
									type="checkbox"
									name="wpar_notify_on_error"
									value="1"
									<?php checked( ! empty( $settings['notify_on_error'] ) ); ?>
								/>
								<?php esc_html_e( 'Errore API (invio fallito)', 'wp-alpinebits-reservation' ); ?>
							</label>
							<br />
							<label>
								<input
									type="checkbox"
									name="wpar_notify_on_resend"
									value="1"
									<?php checked( ! empty( $settings['notify_on_resend'] ) ); ?>
								/>
								<?php esc_html_e( 'Reinvio riuscito dal pannello admin', 'wp-alpinebits-reservation' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpar_notify_subject"><?php esc_html_e( 'Oggetto email', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="wpar_notify_subject"
							name="wpar_notify_subject"
							class="large-text"
							value="<?php echo esc_attr( $settings['email_subject'] ); ?>"
						/>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpar_notify_body"><?php esc_html_e( 'Corpo email', 'wp-alpinebits-reservation' ); ?></label>
					</th>
					<td>
						<textarea
							id="wpar_notify_body"
							name="wpar_notify_body"
							rows="12"
							class="large-text"
						><?php echo esc_textarea( $settings['email_body'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Puoi usare i placeholder elencati qui sotto per includere valori dinamici nell\'oggetto e nel corpo.', 'wp-alpinebits-reservation' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Placeholder disponibili', 'wp-alpinebits-reservation' ); ?></h3>
			<table class="widefat striped wpar-placeholder-table">
				<thead>
					<tr>
						<th style="width:200px;"><?php esc_html_e( 'Placeholder', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Descrizione', 'wp-alpinebits-reservation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>{externalid}</code></td>
						<td><?php esc_html_e( 'ID prenotazione generato dal plugin (es. WPAR4A2B3C4D5E6F)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{status}</code></td>
						<td><?php esc_html_e( 'Esito dell\'invio: "success" o "error"', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{remote_id}</code></td>
						<td><?php esc_html_e( 'ID restituito dall\'API AlpineBits (disponibile solo in caso di successo)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{http_code}</code></td>
						<td><?php esc_html_e( 'Codice HTTP della risposta API (es. 200, 401, 500)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{api_error}</code></td>
						<td><?php esc_html_e( 'Messaggio di errore API (presente solo in caso di errore)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{date}</code></td>
						<td><?php esc_html_e( 'Data e ora di invio (formato: YYYY-MM-DD HH:MM:SS)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{all_fields}</code></td>
						<td><?php esc_html_e( 'Tutti i campi del form CF7 in formato testuale (un campo per riga)', 'wp-alpinebits-reservation' ); ?></td>
					</tr>
					<tr>
						<td><code>{nome_campo}</code></td>
						<td>
							<?php esc_html_e( 'Valore del singolo campo CF7 con quel nome.', 'wp-alpinebits-reservation' ); ?>
							<?php esc_html_e( 'Esempi: {your-name}, {your-email}, {your-message}', 'wp-alpinebits-reservation' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="description" style="margin-top:12px;">
				<?php esc_html_e( 'A ogni notifica viene allegato automaticamente un file JSON con tutti i dati inviati dal form CF7.', 'wp-alpinebits-reservation' ); ?>
			</p>

			<p class="submit">
				<button type="submit" name="wpar_save_notifications" class="button button-primary">
					<?php esc_html_e( 'Salva notifiche', 'wp-alpinebits-reservation' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Processa e salva le impostazioni di notifica.
	 *
	 * @return void
	 */
	private function save(): void {
		if (
			! isset( $_POST['wpar_notifications_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpar_notifications_nonce'] ) ), 'wpar_notifications_save' )
		) {
			add_settings_error( 'wpar', 'nonce', __( 'Nonce non valido.', 'wp-alpinebits-reservation' ), 'error' );
			settings_errors( 'wpar' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		Options::save_notifications(
			[
				'email_to'          => isset( $_POST['wpar_notify_email'] ) ? wp_unslash( $_POST['wpar_notify_email'] ) : '',
				'email_subject'     => isset( $_POST['wpar_notify_subject'] ) ? wp_unslash( $_POST['wpar_notify_subject'] ) : '',
				'email_body'        => isset( $_POST['wpar_notify_body'] ) ? wp_unslash( $_POST['wpar_notify_body'] ) : '',
				'notify_on_success' => ! empty( $_POST['wpar_notify_on_success'] ) ? '1' : '',
				'notify_on_error'   => ! empty( $_POST['wpar_notify_on_error'] ) ? '1' : '',
				'notify_on_resend'  => ! empty( $_POST['wpar_notify_on_resend'] ) ? '1' : '',
			]
		);

		add_settings_error( 'wpar', 'saved', __( 'Impostazioni notifiche salvate.', 'wp-alpinebits-reservation' ), 'success' );
		settings_errors( 'wpar' );
	}
}
