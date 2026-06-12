<?php
/**
 * Tab "Mapping": GUI per associare campi API ai campi del form CF7.
 *
 * @package Mavida\AlpineBitsReservation\Admin\Tabs
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin\Tabs;

use Mavida\AlpineBitsReservation\Cf7\FormFields;
use Mavida\AlpineBitsReservation\Schema\ApiSchema;
use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe MappingTab.
 *
 * Renderizza la GUI di mapping:
 * - Selezione del form CF7 da configurare
 * - Per ogni campo API (destra): una select con i campi del form CF7 (sinistra)
 *   oppure l'opzione di inserire un valore costante fisso.
 */
class MappingTab {

	/**
	 * Processa il form e renderizza il tab.
	 *
	 * @return void
	 */
	public function render(): void {
		// Salvataggio mapping.
		if ( isset( $_POST['wpar_save_mapping'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->save();
		}

		$enabled_forms = Options::enabled_forms();

		if ( empty( $enabled_forms ) ) {
			echo '<div class="notice notice-info inline"><p>';
			echo wp_kses(
				sprintf(
					/* translators: %s: link alla tab Moduli */
					__( 'Nessun modulo abilitato. Prima di configurare il mapping, seleziona almeno un modulo nel tab <a href="%s">Moduli</a>.', 'wp-alpinebits-reservation' ),
					esc_url( admin_url( 'admin.php?page=wp-alpinebits-reservation&tab=forms' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			echo '</p></div>';
			return;
		}

		// Determina il form selezionato (dal POST o dal GET, default al primo abilitato).
		$selected_form_id = isset( $_POST['wpar_mapping_form_id'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? (int) $_POST['wpar_mapping_form_id'] // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: ( isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : $enabled_forms[0] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $selected_form_id, $enabled_forms, true ) ) {
			$selected_form_id = $enabled_forms[0];
		}

		$all_forms     = FormFields::all_forms();
		$form_fields   = FormFields::for_form( $selected_form_id );
		$current_map   = Options::field_mapping( $selected_form_id );
		$schema_groups = ApiSchema::fields_by_group();
		?>
		<form method="post" action="" id="wpar-mapping-form">
			<?php wp_nonce_field( 'wpar_mapping_save', 'wpar_mapping_nonce' ); ?>
			<input type="hidden" name="wpar_mapping_form_id" value="<?php echo esc_attr( (string) $selected_form_id ); ?>" />

			<h2><?php esc_html_e( 'Mapping campi', 'wp-alpinebits-reservation' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Per ogni campo dell\'API (a destra) seleziona il campo corrispondente del form CF7 (a sinistra) oppure scegli "Valore costante" e inserisci il valore fisso da usare.', 'wp-alpinebits-reservation' ); ?>
			</p>

			<div class="wpar-form-selector">
				<label for="wpar-form-switcher"><strong><?php esc_html_e( 'Modulo CF7 da configurare:', 'wp-alpinebits-reservation' ); ?></strong></label>
				<select id="wpar-form-switcher" data-current="<?php echo esc_attr( (string) $selected_form_id ); ?>">
					<?php foreach ( $enabled_forms as $fid ) : ?>
						<option
							value="<?php echo esc_attr( (string) $fid ); ?>"
							<?php selected( $fid, $selected_form_id ); ?>
						>
							<?php echo esc_html( $all_forms[ $fid ] ?? "Form #{$fid}" ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="wpar-spinner" style="display:none;"></span>
			</div>

			<div id="wpar-mapping-table-wrap">
				<?php $this->render_mapping_table( $schema_groups, $form_fields, $current_map ); ?>
			</div>

			<p class="submit">
				<button type="submit" name="wpar_save_mapping" class="button button-primary">
					<?php esc_html_e( 'Salva mapping', 'wp-alpinebits-reservation' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Renderizza la tabella di mapping (usata anche come risposta AJAX per il form-switcher).
	 *
	 * @param array<string, array<int, array<string, mixed>>> $schema_groups Campi API per gruppo.
	 * @param array<string, string>                           $form_fields   Campi CF7 del form.
	 * @param array<string, string>                           $current_map   Mappa attuale salvata.
	 * @return void
	 */
	public function render_mapping_table( array $schema_groups, array $form_fields, array $current_map ): void {
		foreach ( $schema_groups as $group_name => $fields ) :
			?>
			<h3 class="wpar-group-title"><?php echo esc_html( $group_name ); ?></h3>
			<table class="wp-list-table widefat fixed striped wpar-mapping-table">
				<thead>
					<tr>
						<th class="wpar-col-source"><?php esc_html_e( 'Campo CF7 (sorgente)', 'wp-alpinebits-reservation' ); ?></th>
						<th class="wpar-col-arrow"></th>
						<th class="wpar-col-target"><?php esc_html_e( 'Campo API (destinazione)', 'wp-alpinebits-reservation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<?php
						$path         = $field['path'];
						$label        = $field['label'] ?? $path;
						$required     = ! empty( $field['required'] );
						$type         = $field['type'] ?? 'string';
						$notes        = $field['notes'] ?? '';
						$enum_values  = $field['enum'] ?? array();
						$mapped_value = $current_map[ $path ] ?? '';
						$is_mapped    = '' !== $mapped_value;

						// Determina se il valore attuale è una costante.
						$is_const       = str_starts_with( $mapped_value, '__const:' );
						$const_value    = $is_const ? substr( $mapped_value, 8 ) : '';
						$selected_field = $is_const ? '__const' : $mapped_value;
						$input_name     = 'wpar_mapping[' . esc_attr( $path ) . ']';
						?>
						<tr class="wpar-mapping-row<?php echo $required ? ' wpar-required' : ''; ?><?php echo $is_mapped ? ' wpar-row-mapped' : ''; ?>" data-path="<?php echo esc_attr( $path ); ?>">
							<!-- Colonna sinistra: select campo CF7 + input costante -->
							<td class="wpar-col-source">
								<select
									name="<?php echo esc_attr( $input_name ); ?>[source]"
									class="wpar-source-select"
									data-path="<?php echo esc_attr( $path ); ?>"
								>
									<option value=""><?php esc_html_e( '— Nessuno —', 'wp-alpinebits-reservation' ); ?></option>
									<?php foreach ( $form_fields as $cf7_name => $cf7_label ) : ?>
										<option
											value="<?php echo esc_attr( $cf7_name ); ?>"
											<?php selected( $selected_field, $cf7_name ); ?>
										>
											<?php echo esc_html( $cf7_label ); ?>
										</option>
									<?php endforeach; ?>
									<option value="__const" <?php selected( $selected_field, '__const' ); ?>>
										<?php esc_html_e( '— Valore costante —', 'wp-alpinebits-reservation' ); ?>
									</option>
								</select>

								<!-- Campo testo per il valore costante (visibile solo se selezionato __const) -->
								<div class="wpar-const-wrap" style="<?php echo $is_const ? '' : 'display:none;'; ?> margin-top:6px;">
									<?php if ( ! empty( $enum_values ) ) : ?>
										<select name="<?php echo esc_attr( $input_name ); ?>[const]" class="wpar-const-input">
											<option value=""><?php esc_html_e( '— seleziona —', 'wp-alpinebits-reservation' ); ?></option>
											<?php foreach ( $enum_values as $ev ) : ?>
												<option value="<?php echo esc_attr( $ev ); ?>" <?php selected( $const_value, $ev ); ?>>
													<?php echo esc_html( $ev ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<input
											type="text"
											name="<?php echo esc_attr( $input_name ); ?>[const]"
											class="wpar-const-input regular-text"
											value="<?php echo esc_attr( $const_value ); ?>"
											placeholder="<?php esc_attr_e( 'Valore fisso...', 'wp-alpinebits-reservation' ); ?>"
										/>
									<?php endif; ?>
								</div>
							</td>

							<!-- Freccia -->
							<td class="wpar-col-arrow">&#8594;</td>

							<!-- Colonna destra: descrizione campo API -->
							<td class="wpar-col-target">
								<strong class="wpar-field-label<?php echo $required ? ' wpar-required-label' : ''; ?>">
									<?php echo esc_html( $label ); ?>
									<?php if ( $required ) : ?>
										<span class="wpar-required-star" title="<?php esc_attr_e( 'Campo obbligatorio', 'wp-alpinebits-reservation' ); ?>">*</span>
									<?php endif; ?>
									<?php if ( $is_mapped ) : ?>
										<span class="wpar-mapped-check" title="<?php esc_attr_e( 'Campo mappato', 'wp-alpinebits-reservation' ); ?>">&#10003;</span>
									<?php endif; ?>
								</strong>
								<code class="wpar-field-path"><?php echo esc_html( $path ); ?></code>
								<span class="wpar-field-type"><?php echo esc_html( $type ); ?></span>
								<?php if ( $notes ) : ?>
									<p class="description"><?php echo esc_html( $notes ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endforeach;
	}

	/**
	 * Salva la mappa dei campi per il form selezionato.
	 *
	 * @return void
	 */
	private function save(): void {
		if (
			! isset( $_POST['wpar_mapping_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpar_mapping_nonce'] ) ), 'wpar_mapping_save' )
		) {
			add_settings_error( 'wpar', 'nonce', __( 'Nonce non valido.', 'wp-alpinebits-reservation' ), 'error' );
			settings_errors( 'wpar' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form_id = isset( $_POST['wpar_mapping_form_id'] ) ? (int) $_POST['wpar_mapping_form_id'] : 0;
		if ( $form_id <= 0 ) {
			return;
		}

		$raw_mapping = isset( $_POST['wpar_mapping'] )
			? (array) wp_unslash( $_POST['wpar_mapping'] )
			: array();

		$clean_mapping = array();

		// Valida i campi dello schema per sicurezza (whitelist).
		$valid_paths = array_column( ApiSchema::fields(), 'path' );

		foreach ( $raw_mapping as $path => $value ) {
			$path = sanitize_text_field( $path );

			if ( ! in_array( $path, $valid_paths, true ) ) {
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			$source = sanitize_text_field( $value['source'] ?? '' );
			$const  = sanitize_text_field( $value['const'] ?? '' );

			if ( '__const' === $source && '' !== $const ) {
				// Valore costante.
				$clean_mapping[ $path ] = '__const:' . $const;
			} elseif ( '' !== $source && '__const' !== $source ) {
				// Campo CF7.
				$clean_mapping[ $path ] = $source;
			}
			// Se source vuoto: non inserire (campo non mappato).
		}

		Options::save_field_mapping( $form_id, $clean_mapping );

		add_settings_error( 'wpar', 'saved', __( 'Mapping salvato.', 'wp-alpinebits-reservation' ), 'success' );
		settings_errors( 'wpar' );
	}
}
