<?php
/**
 * Client HTTP per l'API AlpineBits Gateway.
 *
 * @package Mavida\AlpineBitsReservation\Api
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Api;

use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe Client.
 *
 * Effettua le chiamate HTTP all'endpoint sendReservation tramite wp_remote_post.
 * Gestisce l'autenticazione Basic Auth, la serializzazione JSON e il parsing
 * della risposta.
 */
class Client {

	/**
	 * Path dell'endpoint sendReservation relativo alla base URL.
	 *
	 * @var string
	 */
	const ENDPOINT_SEND = '/sendReservation';

	/**
	 * Invia la prenotazione all'API AlpineBits.
	 *
	 * @param  array<string, mixed> $payload Il payload della prenotazione.
	 * @return ApiResponse                   Oggetto con esito, http_code e body.
	 */
	public function send_reservation( array $payload ): ApiResponse {
		$base_url = rtrim( Options::api_base_url(), '/' );
		$url      = $base_url . self::ENDPOINT_SEND;
		$username = Options::api_username();
		$password = Options::api_password();

		if ( '' === $username || '' === $password ) {
			return new ApiResponse(
				false,
				0,
				'',
				__( 'Credenziali API non configurate.', 'wp-alpinebits-reservation' )
			);
		}

		// Costruisce l'header Authorization: Basic base64(user:pass).
		$auth_header = 'Basic ' . base64_encode( $username . ':' . $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 30,
				'headers'     => array(
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		// Gestione errori di trasporto WordPress (DNS, timeout, SSL...).
		if ( is_wp_error( $response ) ) {
			return new ApiResponse(
				false,
				0,
				'',
				$response->get_error_message()
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );

		// Parsing del body JSON.
		$decoded = json_decode( $body, true );

		$success   = false;
		$error_msg = '';
		$remote_id = '';

		if ( is_array( $decoded ) ) {
			$success = ! empty( $decoded['success'] ) && true === $decoded['success'];
			if ( $success && isset( $decoded['data']['id'] ) ) {
				$remote_id = (string) $decoded['data']['id'];
			}
			if ( ! $success && isset( $decoded['error'] ) ) {
				$error_msg = (string) $decoded['error'];
			}
		} else {
			// Il body non è JSON valido.
			$error_msg = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Risposta non valida dal server (HTTP %d).', 'wp-alpinebits-reservation' ),
				$http_code
			);
		}

		// Per HTTP 2xx ma success=false, usa il messaggio di errore dall'API.
		if ( $http_code >= 400 && '' === $error_msg ) {
			$error_msg = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Errore HTTP %d.', 'wp-alpinebits-reservation' ),
				$http_code
			);
		}

		return new ApiResponse( $success, $http_code, $body, $error_msg, $remote_id );
	}

	/**
	 * Esegue una chiamata di test per verificare le credenziali.
	 *
	 * Invia un payload minimale intenzionalmente incompleto: ci aspettiamo
	 * un 400 (validazione API) oppure un 401 (credenziali errate).
	 * Un 401 indica credenziali errate; qualsiasi altra risposta (anche un 400)
	 * conferma che l'autenticazione ha funzionato.
	 *
	 * @return ApiResponse
	 */
	public function test_connection(): ApiResponse {
		$minimal_payload = array(
			'status' => 'request',
			'from'   => gmdate( 'Y-m-d' ),
			'until'  => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'guest'  => array( 'lastname' => 'Test' ),
			'rooms'  => array( array( 'occupants' => array( 'adults' => 1 ) ) ),
		);

		$result = $this->send_reservation( $minimal_payload );

		// 401 = credenziali errate; tutto il resto = connessione raggiunta.
		if ( 401 === $result->http_code ) {
			return new ApiResponse(
				false,
				$result->http_code,
				$result->raw_body,
				__( 'Autenticazione fallita: credenziali errate.', 'wp-alpinebits-reservation' )
			);
		}

		// Connessione raggiunta (anche se la prenotazione di test è rifiutata per validazione).
		return new ApiResponse(
			true,
			$result->http_code,
			$result->raw_body,
			''
		);
	}
}
