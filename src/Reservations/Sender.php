<?php
/**
 * Orchestrazione invio prenotazione e reinvio da pannello admin.
 *
 * @package Mavida\AlpineBitsReservation\Reservations
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Reservations;

use Mavida\AlpineBitsReservation\Api\Client;
use Mavida\AlpineBitsReservation\Mapping\FieldMapper;

/**
 * Classe Sender.
 *
 * Coordinazione tra FieldMapper, Repository e Api\Client.
 * Usata sia dal SubmissionListener (nuovo invio) che dal pannello admin (reinvio).
 */
class Sender {

	/**
	 * Invia una nuova prenotazione a partire dai dati del form CF7.
	 *
	 * @param  int                   $form_id     ID del form CF7.
	 * @param  array<string, mixed>  $posted_data Dati grezzi dal form.
	 * @param  array<string, string> $mapping     Mappa api_path => cf7_field|__const:valore.
	 * @return int                                ID della riga creata nel DB (0 se fallito prima dell'insert).
	 */
	public function send( int $form_id, array $posted_data, array $mapping ): int {
		$mapper  = new FieldMapper();
		$payload = $mapper->build( $posted_data, $mapping );

		// Genera un externalid se non presente nel payload.
		if ( empty( $payload['externalid'] ) ) {
			$payload['externalid'] = $this->generate_external_id();
		}

		$repository = new Repository();
		$row_id     = $repository->insert( $form_id, (string) $payload['externalid'], $payload );

		if ( 0 === $row_id ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WPAR] Form %d: impossibile salvare la submission nel DB.', $form_id ) );
			return 0;
		}

		// Esegue la chiamata API.
		$api_response = ( new Client() )->send_reservation( $payload );

		// Aggiorna lo stato nel DB in base alla risposta.
		$status = $api_response->success ? Repository::STATUS_SUCCESS : Repository::STATUS_ERROR;
		$repository->update_status(
			$row_id,
			$status,
			$api_response->http_code,
			$api_response->raw_body,
			$api_response->remote_id
		);

		return $row_id;
	}

	/**
	 * Reinvia una submission esistente (identificata dall'ID riga nel DB).
	 *
	 * Riusa lo stesso externalid per garantire idempotenza lato gateway.
	 *
	 * @param  int $row_id ID della riga nel DB.
	 * @return bool        True se la chiamata API ha avuto successo.
	 */
	public function resend( int $row_id ): bool {
		$repository = new Repository();
		$row        = $repository->find( $row_id );

		if ( null === $row ) {
			return false;
		}

		// Decodifica il payload JSON salvato.
		$payload = json_decode( $row->payload, true );

		if ( ! is_array( $payload ) ) {
			return false;
		}

		// Aggiorna lo stato a pending prima del tentativo.
		$repository->update_status(
			$row_id,
			Repository::STATUS_PENDING,
			0,
			'',
			''
		);

		// Esegue la chiamata API con lo stesso payload (e stesso externalid).
		$api_response = ( new Client() )->send_reservation( $payload );

		$status = $api_response->success ? Repository::STATUS_SUCCESS : Repository::STATUS_ERROR;
		$repository->update_status(
			$row_id,
			$status,
			$api_response->http_code,
			$api_response->raw_body,
			$api_response->remote_id
		);

		return $api_response->success;
	}

	/**
	 * Genera un ID esterno univoco alfanumerico.
	 * Formato: "WPAR" + 12 caratteri alfanumerici (totale 16 char).
	 *
	 * @return string
	 */
	private function generate_external_id(): string {
		return 'WPAR' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 12 ) );
	}
}
