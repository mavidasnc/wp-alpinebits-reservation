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
use Mavida\AlpineBitsReservation\Notifications\Notifier;
use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe Sender.
 *
 * Coordinazione tra FieldMapper, Repository, Api\Client e Notifier.
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
		// Salva anche i dati CF7 originali per le notifiche e il reinvio.
		$row_id = $repository->insert( $form_id, (string) $payload['externalid'], $payload, $posted_data );

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
			$this->response_body( $api_response ),
			$api_response->remote_id
		);

		// Invia notifica email (solo se configurata).
		$event = $api_response->success ? Notifier::EVENT_SEND_SUCCESS : Notifier::EVENT_SEND_ERROR;
		( new Notifier() )->send( $event, $form_id, $posted_data, $api_response, (string) $payload['externalid'] );

		return $row_id;
	}

	/**
	 * Reinvia una submission esistente ricostruendo il payload dalla mappatura corrente.
	 *
	 * Se i dati CF7 originali sono disponibili (colonna cf7_data), il payload viene
	 * ricostruito con la mappatura attualmente salvata — in modo che eventuali correzioni
	 * alla mappatura si riflettano immediatamente nel reinvio.
	 * Viene sempre generato un nuovo externalid (nessuna idempotenza con l'invio originale).
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

		$cf7_data = ! empty( $row->cf7_data )
			? ( json_decode( $row->cf7_data, true ) ?? [] )
			: [];

		if ( ! empty( $cf7_data ) ) {
			// Ricostruisce il payload con la mappatura correntemente salvata.
			$mapping = Options::field_mapping( (int) $row->form_id );
			$payload = ( new FieldMapper() )->build( $cf7_data, $mapping );
		} else {
			// Fallback: usa il payload originale (cf7_data non disponibile).
			$payload = json_decode( $row->payload, true );
			if ( ! is_array( $payload ) ) {
				return false;
			}
		}

		// Genera sempre un nuovo externalid per il reinvio.
		$new_externalid        = $this->generate_external_id();
		$payload['externalid'] = $new_externalid;

		// Aggiorna payload ed externalid nel DB prima di inviare.
		$repository->update_payload( $row_id, $new_externalid, (string) wp_json_encode( $payload ) );

		// Imposta lo stato a pending.
		$repository->update_status( $row_id, Repository::STATUS_PENDING, 0, '', '' );

		// Esegue la chiamata API con il payload ricostruito.
		$api_response = ( new Client() )->send_reservation( $payload );

		$status = $api_response->success ? Repository::STATUS_SUCCESS : Repository::STATUS_ERROR;
		$repository->update_status(
			$row_id,
			$status,
			$api_response->http_code,
			$this->response_body( $api_response ),
			$api_response->remote_id
		);

		// Invia notifica email per il reinvio riuscito.
		if ( $api_response->success ) {
			( new Notifier() )->send(
				Notifier::EVENT_RESEND_SUCCESS,
				(int) $row->form_id,
				$cf7_data,
				$api_response,
				$new_externalid
			);
		}

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

	/**
	 * Restituisce il body della risposta da salvare nel DB.
	 *
	 * Se raw_body è vuoto (es. errore di trasporto WP: timeout, DNS), serializza
	 * il messaggio di errore come JSON in modo che sia sempre visibile nel log.
	 *
	 * @param  \Mavida\AlpineBitsReservation\Api\ApiResponse $api_response Risposta API.
	 * @return string                                                        Body da salvare.
	 */
	private function response_body( \Mavida\AlpineBitsReservation\Api\ApiResponse $api_response ): string {
		if ( '' !== $api_response->raw_body ) {
			return $api_response->raw_body;
		}
		return '' !== $api_response->error
			? (string) wp_json_encode( [ 'error' => $api_response->error ] ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			: '';
	}
}
