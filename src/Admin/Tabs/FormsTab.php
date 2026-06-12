<?php
/**
 * Tab "Moduli": selezione dei form CF7 da agganciare.
 *
 * @package Mavida\AlpineBitsReservation\Admin\Tabs
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin\Tabs;

use Mavida\AlpineBitsReservation\Cf7\FormFields;
use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe FormsTab.
 *
 * Mostra l'elenco dei form CF7 disponibili e permette di selezionare
 * quali devono essere intercettati per l'invio all'API AlpineBits.
 */
class FormsTab {

	/**
	 * Processa il form e renderizza il tab.
	 *
	 * @return void
	 */
	public function render(): void {
		// Salvataggio form.
		if ( isset( $_POST['wpar_save_forms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->save();
		}

		$all_forms   = FormFields::all_forms();
		$enabled_ids = Options::enabled_forms();

		if ( empty( $all_forms ) ) {
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( 'Nessun modulo Contact Form 7 trovato. Crea almeno un modulo CF7 prima di configurare questa sezione.', 'wp-alpinebits-reservation' );
			echo '</p></div>';
			return;
		}
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'wpar_forms_save', 'wpar_forms_nonce' ); ?>

			<h2><?php esc_html_e( 'Moduli Contact Form 7', 'wp-alpinebits-reservation' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Seleziona i moduli CF7 che devono inviare i dati all\'API AlpineBits. I dati del form verranno mappati nella sezione "Mapping".', 'wp-alpinebits-reservation' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input
								type="checkbox"
								id="wpar-select-all-forms"
								title="<?php esc_attr_e( 'Seleziona/deseleziona tutti', 'wp-alpinebits-reservation' ); ?>"
							/>
						</td>
						<th><?php esc_html_e( 'Titolo modulo', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'ID', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Stato', 'wp-alpinebits-reservation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_forms as $id => $title ) : ?>
						<?php $is_enabled = in_array( $id, $enabled_ids, true ); ?>
						<tr>
							<th class="check-column">
								<input
									type="checkbox"
									name="wpar_enabled_forms[]"
									value="<?php echo esc_attr( (string) $id ); ?>"
									<?php checked( $is_enabled ); ?>
									class="wpar-form-checkbox"
								/>
							</th>
							<td><strong><?php echo esc_html( $title ); ?></strong></td>
							<td><?php echo esc_html( (string) $id ); ?></td>
							<td>
								<?php if ( $is_enabled ) : ?>
									<span class="wpar-badge wpar-badge--active"><?php esc_html_e( 'Attivo', 'wp-alpinebits-reservation' ); ?></span>
								<?php else : ?>
									<span class="wpar-badge wpar-badge--inactive"><?php esc_html_e( 'Non attivo', 'wp-alpinebits-reservation' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" name="wpar_save_forms" class="button button-primary">
					<?php esc_html_e( 'Salva selezione', 'wp-alpinebits-reservation' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Salva l'elenco dei form abilitati.
	 *
	 * @return void
	 */
	private function save(): void {
		if (
			! isset( $_POST['wpar_forms_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpar_forms_nonce'] ) ), 'wpar_forms_save' )
		) {
			add_settings_error( 'wpar', 'nonce', __( 'Nonce non valido.', 'wp-alpinebits-reservation' ), 'error' );
			settings_errors( 'wpar' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Se nessun checkbox è spuntato, l'array non è presente nel POST.
		$raw_ids = isset( $_POST['wpar_enabled_forms'] )
			? array_map( 'intval', (array) wp_unslash( $_POST['wpar_enabled_forms'] ) )
			: array();

		Options::save_enabled_forms( $raw_ids );

		add_settings_error( 'wpar', 'saved', __( 'Selezione salvata.', 'wp-alpinebits-reservation' ), 'success' );
		settings_errors( 'wpar' );
	}
}
