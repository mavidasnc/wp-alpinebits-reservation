<?php
/**
 * Invio notifiche email con allegato JSON dei dati CF7.
 *
 * @package Mavida\AlpineBitsReservation\Notifications
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Notifications;

use Mavida\AlpineBitsReservation\Api\ApiResponse;
use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe Notifier.
 *
 * Invia notifiche email in risposta agli eventi di invio/reinvio prenotazione.
 * Supporta placeholder {field_name} nel soggetto e nel corpo.
 * Allega sempre un file JSON con tutti i dati del form CF7.
 */
class Notifier {

	/**
	 * Evento: invio API riuscito.
	 *
	 * @var string
	 */
	const EVENT_SEND_SUCCESS = 'send_success';

	/**
	 * Evento: errore API.
	 *
	 * @var string
	 */
	const EVENT_SEND_ERROR = 'send_error';

	/**
	 * Evento: reinvio riuscito.
	 *
	 * @var string
	 */
	const EVENT_RESEND_SUCCESS = 'resend_success';

	/**
	 * Invia la notifica email per l'evento specificato.
	 *
	 * @param  string               $event       Tipo di evento (costanti EVENT_*).
	 * @param  int                  $form_id     ID del form CF7 (non usato direttamente ma disponibile per estensioni).
	 * @param  array<string, mixed> $cf7_data   Dati originali del form CF7.
	 * @param  ApiResponse          $api_response Risposta dell'API AlpineBits.
	 * @param  string               $externalid  ID prenotazione generato.
	 * @return void
	 */
	public function send(
		string $event,
		int $form_id,
		array $cf7_data,
		ApiResponse $api_response,
		string $externalid
	): void {
		$settings = Options::notifications();

		$email_to = sanitize_email( $settings['email_to'] ?? '' );
		if ( empty( $email_to ) ) {
			return;
		}

		// Verifica il flag di abilitazione per l'evento corrente.
		if ( self::EVENT_SEND_SUCCESS === $event && empty( $settings['notify_on_success'] ) ) {
			return;
		}
		if ( self::EVENT_SEND_ERROR === $event && empty( $settings['notify_on_error'] ) ) {
			return;
		}
		if ( self::EVENT_RESEND_SUCCESS === $event && empty( $settings['notify_on_resend'] ) ) {
			return;
		}

		$placeholders = $this->build_placeholders( $cf7_data, $api_response, $externalid );
		$subject      = $this->replace_placeholders( (string) ( $settings['email_subject'] ?? '' ), $placeholders );
		$body         = $this->replace_placeholders( (string) ( $settings['email_body'] ?? '' ), $placeholders );

		$json_file   = $this->create_json_attachment( $cf7_data, $externalid );
		$headers     = [ 'Content-Type: text/plain; charset=UTF-8' ];
		$attachments = ( '' !== $json_file ) ? [ $json_file ] : [];

		wp_mail( $email_to, $subject, $body, $headers, $attachments );

		if ( '' !== $json_file ) {
			wp_delete_file( $json_file );
		}
	}

	/**
	 * Costruisce la mappa placeholder => valore.
	 *
	 * @param  array<string, mixed> $cf7_data     Dati del form CF7.
	 * @param  ApiResponse          $api_response Risposta API.
	 * @param  string               $externalid   ID prenotazione.
	 * @return array<string, string>
	 */
	private function build_placeholders( array $cf7_data, ApiResponse $api_response, string $externalid ): array {
		$placeholders = [
			'{externalid}' => $externalid,
			'{status}'     => $api_response->success ? 'success' : 'error',
			'{remote_id}'  => $api_response->remote_id,
			'{http_code}'  => (string) $api_response->http_code,
			'{api_error}'  => $api_response->success ? '' : $api_response->error,
			'{date}'       => current_time( 'Y-m-d H:i:s' ),
		];

		// Placeholder {all_fields}: tutti i campi CF7 in formato testo leggibile.
		$lines = [];
		foreach ( $cf7_data as $name => $value ) {
			// Salta i campi interni CF7 (prefissati con _).
			if ( str_starts_with( (string) $name, '_' ) ) {
				continue;
			}
			$lines[] = $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : (string) $value );
		}
		$placeholders['{all_fields}'] = implode( "\n", $lines );

		// Placeholder {field-name} per ogni campo CF7.
		foreach ( $cf7_data as $name => $value ) {
			$placeholders[ '{' . $name . '}' ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		return $placeholders;
	}

	/**
	 * Sostituisce i placeholder nel template con i valori corrispondenti.
	 *
	 * @param  string                $template     Template da elaborare.
	 * @param  array<string, string> $placeholders Mappa placeholder => valore.
	 * @return string
	 */
	private function replace_placeholders( string $template, array $placeholders ): string {
		return strtr( $template, $placeholders );
	}

	/**
	 * Crea un file JSON temporaneo con i dati CF7 da allegare all'email.
	 *
	 * @param  array<string, mixed> $cf7_data   Dati del form CF7.
	 * @param  string               $externalid ID prenotazione (usato nel nome file).
	 * @return string                           Percorso del file temporaneo creato, '' in caso di errore.
	 */
	private function create_json_attachment( array $cf7_data, string $externalid ): string {
		$encoded = wp_json_encode( $cf7_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			return '';
		}

		$filename = sanitize_file_name( 'cf7-data-' . $externalid . '.json' );
		$tmpdir   = get_temp_dir();
		$path     = trailingslashit( $tmpdir ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $path, $encoded ) ) {
			return '';
		}

		return $path;
	}
}
