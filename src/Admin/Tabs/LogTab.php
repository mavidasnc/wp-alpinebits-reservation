<?php
/**
 * Tab "Invii": lista submission con dettaglio ed azione di reinvio.
 *
 * @package Mavida\AlpineBitsReservation\Admin\Tabs
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Admin\Tabs;

use Mavida\AlpineBitsReservation\Cf7\FormFields;
use Mavida\AlpineBitsReservation\Reservations\Repository;

/**
 * Classe LogTab.
 *
 * Visualizza la tabella delle submission inviate all'API con paginazione,
 * filtri per stato, dettaglio inline di payload e risposta, e pulsante reinvio.
 */
class LogTab {

	/**
	 * Righe per pagina.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * Renderizza il tab.
	 *
	 * @return void
	 */
	public function render(): void {
		$repository = new Repository();

		// Filtri dalla querystring (nessun nonce necessario per la lettura).
		$filter_status  = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_form_id = isset( $_GET['filter_form_id'] ) ? (int) $_GET['filter_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page           = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'status'   => $filter_status,
			'form_id'  => $filter_form_id,
			'per_page' => self::PER_PAGE,
			'page'     => $page,
		);

		$rows  = $repository->get_list( $args );
		$total = $repository->count( $args );
		$pages = (int) ceil( $total / self::PER_PAGE );

		$all_forms = FormFields::all_forms();
		$base_url  = admin_url( 'admin.php?page=wp-alpinebits-reservation&tab=log' );
		?>
		<h2><?php esc_html_e( 'Invii all\'API AlpineBits', 'wp-alpinebits-reservation' ); ?></h2>

		<!-- Filtri -->
		<form method="get" action="" class="wpar-log-filters">
			<input type="hidden" name="page" value="wp-alpinebits-reservation" />
			<input type="hidden" name="tab" value="log" />

			<select name="filter_status">
				<option value=""><?php esc_html_e( 'Tutti gli stati', 'wp-alpinebits-reservation' ); ?></option>
				<?php
				$statuses = array(
					Repository::STATUS_PENDING => __( 'In attesa', 'wp-alpinebits-reservation' ),
					Repository::STATUS_SUCCESS => __( 'Successo', 'wp-alpinebits-reservation' ),
					Repository::STATUS_ERROR   => __( 'Errore', 'wp-alpinebits-reservation' ),
				);
				foreach ( $statuses as $val => $label ) :
					?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="filter_form_id">
				<option value="0"><?php esc_html_e( 'Tutti i moduli', 'wp-alpinebits-reservation' ); ?></option>
				<?php foreach ( $all_forms as $fid => $ftitle ) : ?>
					<option value="<?php echo esc_attr( (string) $fid ); ?>" <?php selected( $filter_form_id, $fid ); ?>>
						<?php echo esc_html( $ftitle ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filtra', 'wp-alpinebits-reservation' ); ?></button>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'wp-alpinebits-reservation' ); ?></a>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'Nessun invio trovato.', 'wp-alpinebits-reservation' ); ?></p>
		<?php else : ?>
			<p class="wpar-log-count">
				<?php
				printf(
					/* translators: %d: numero totale righe */
					esc_html( _n( '%d invio trovato.', '%d invii trovati.', $total, 'wp-alpinebits-reservation' ) ),
					(int) $total
				);
				?>
			</p>

			<table class="wp-list-table widefat fixed striped wpar-log-table">
				<thead>
					<tr>
						<th class="wpar-col-id">#</th>
						<th><?php esc_html_e( 'Data', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Modulo', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'External ID', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Stato', 'wp-alpinebits-reservation' ); ?></th>
						<th class="wpar-col-http"><?php esc_html_e( 'HTTP', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Remote ID', 'wp-alpinebits-reservation' ); ?></th>
						<th class="wpar-col-attempts"><?php esc_html_e( 'Tentativi', 'wp-alpinebits-reservation' ); ?></th>
						<th><?php esc_html_e( 'Azioni', 'wp-alpinebits-reservation' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$status_class = match ( $row->status ) {
							'success' => 'wpar-badge--success',
							'error'   => 'wpar-badge--error',
							default   => 'wpar-badge--pending',
						};
						$status_label = match ( $row->status ) {
							'success' => __( 'Successo', 'wp-alpinebits-reservation' ),
							'error'   => __( 'Errore', 'wp-alpinebits-reservation' ),
							default   => __( 'In attesa', 'wp-alpinebits-reservation' ),
						};
						$form_title = $all_forms[ (int) $row->form_id ] ?? "Form #{$row->form_id}";
	?>
						<tr class="wpar-log-row">
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $form_title ); ?></td>
							<td><code><?php echo esc_html( $row->externalid ); ?></code></td>
							<td>
								<span class="wpar-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td><?php echo $row->http_code ? esc_html( (string) $row->http_code ) : '—'; ?></td>
							<td><?php echo $row->remote_id ? esc_html( $row->remote_id ) : '—'; ?></td>
							<td><?php echo esc_html( (string) $row->attempts ); ?></td>
							<td>
								<!-- Pulsante reinvio -->
								<button
									type="button"
									class="button button-small wpar-resend-btn"
									data-id="<?php echo esc_attr( (string) $row->id ); ?>"
								>
									<?php esc_html_e( 'Reinvia', 'wp-alpinebits-reservation' ); ?>
								</button>
								<!-- Toggle dettaglio -->
								<button
									type="button"
									class="button button-small wpar-toggle-detail"
									data-target="wpar-detail-<?php echo esc_attr( (string) $row->id ); ?>"
									style="margin-left:4px;"
								>
									<?php esc_html_e( 'Dettaglio', 'wp-alpinebits-reservation' ); ?>
								</button>
							</td>
						</tr>
						<!-- Riga di dettaglio (nascosta di default) -->
						<tr id="wpar-detail-<?php echo esc_attr( (string) $row->id ); ?>" class="wpar-detail-row" style="display:none;">
							<td colspan="9">
								<div class="wpar-detail-wrap">
									<div class="wpar-detail-col">
										<strong><?php esc_html_e( 'Payload inviato:', 'wp-alpinebits-reservation' ); ?></strong>
										<pre class="wpar-json"><?php echo esc_html( $this->pretty_json( (string) $row->payload ) ); ?></pre>
									</div>
									<?php if ( $row->response ) : ?>
										<div class="wpar-detail-col">
											<strong><?php esc_html_e( 'Risposta API:', 'wp-alpinebits-reservation' ); ?></strong>
											<pre class="wpar-json"><?php echo esc_html( $this->pretty_json( (string) $row->response ) ); ?></pre>
										</div>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="wpar-pagination tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'    => add_query_arg( 'paged', '%#%', $base_url ),
									'format'  => '',
									'current' => $page,
									'total'   => $pages,
								)
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Formatta una stringa JSON per la visualizzazione leggibile.
	 *
	 * @param  string $json Stringa JSON grezza.
	 * @return string       JSON formattato con indentazione.
	 */
	private function pretty_json( string $json ): string {
		$decoded = json_decode( $json );
		if ( null === $decoded ) {
			return $json;
		}
		return (string) json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
