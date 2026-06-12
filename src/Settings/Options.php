<?php
/**
 * Accesso tipizzato alle opzioni del plugin con supporto override via costanti.
 *
 * @package Mavida\AlpineBitsReservation\Settings
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Settings;

use Mavida\AlpineBitsReservation\Support\Crypto;

/**
 * Classe Options.
 *
 * Centralizza lettura e scrittura di tutte le opzioni del plugin.
 * Le impostazioni API possono essere sovrascritte da costanti in wp-config.php:
 * - WPAR_API_USERNAME
 * - WPAR_API_PASSWORD
 * - WPAR_API_BASE_URL
 */
class Options {

	/**
	 * Chiave dell'option principale in wp_options.
	 *
	 * @var string
	 */
	const OPTION_SETTINGS = 'wpar_settings';

	/**
	 * Chiave dei form CF7 abilitati.
	 *
	 * @var string
	 */
	const OPTION_ENABLED_FORMS = 'wpar_enabled_forms';

	/**
	 * Chiave della mappa dei campi.
	 *
	 * @var string
	 */
	const OPTION_MAPPINGS = 'wpar_field_mappings';

	/**
	 * Chiave delle impostazioni di notifica email.
	 *
	 * @var string
	 */
	const OPTION_NOTIFICATIONS = 'wpar_notification_settings';

	// -------------------------------------------------------------------------
	// Lettura impostazioni API
	// -------------------------------------------------------------------------

	/**
	 * Restituisce l'array delle impostazioni raw dal DB (senza decrypt).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$defaults = [
			'api_base_url'   => 'https://alpinebits-gateway.ando.cloud/api/v1',
			'api_username'   => '',
			'api_password'   => '',
			'default_status' => 'request',
		];

		$saved = get_option( self::OPTION_SETTINGS, [] );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Restituisce la base URL dell'API.
	 *
	 * @return string
	 */
	public static function api_base_url(): string {
		if ( defined( 'WPAR_API_BASE_URL' ) ) {
			return (string) \WPAR_API_BASE_URL;
		}
		return (string) ( self::get_all()['api_base_url'] ?? '' );
	}

	/**
	 * Restituisce lo username API in chiaro.
	 *
	 * @return string
	 */
	public static function api_username(): string {
		if ( defined( 'WPAR_API_USERNAME' ) ) {
			return (string) \WPAR_API_USERNAME;
		}
		return (string) ( self::get_all()['api_username'] ?? '' );
	}

	/**
	 * Restituisce la password API in chiaro (decifrata).
	 *
	 * @return string
	 */
	public static function api_password(): string {
		if ( defined( 'WPAR_API_PASSWORD' ) ) {
			return (string) \WPAR_API_PASSWORD;
		}
		$encrypted = (string) ( self::get_all()['api_password'] ?? '' );
		return Crypto::decrypt( $encrypted );
	}

	/**
	 * Restituisce lo status default per le prenotazioni ('request' o 'reservation').
	 *
	 * @return string
	 */
	public static function default_status(): string {
		$status = (string) ( self::get_all()['default_status'] ?? 'request' );
		return in_array( $status, [ 'request', 'reservation' ], true ) ? $status : 'request';
	}

	/**
	 * Restituisce l'array degli ID dei form CF7 abilitati.
	 *
	 * @return int[]
	 */
	public static function enabled_forms(): array {
		$forms = get_option( self::OPTION_ENABLED_FORMS, [] );
		return array_map( 'intval', (array) $forms );
	}

	/**
	 * Restituisce la mappa dei campi per un form specifico.
	 *
	 * @param  int $form_id ID del form CF7.
	 * @return array<string, string> Mappa: api_field_path => cf7_field_name|'__const:valore'
	 */
	public static function field_mapping( int $form_id ): array {
		$all = get_option( self::OPTION_MAPPINGS, [] );
		return (array) ( $all[ $form_id ] ?? [] );
	}

	// -------------------------------------------------------------------------
	// Lettura impostazioni notifiche
	// -------------------------------------------------------------------------

	/**
	 * Restituisce le impostazioni di notifica email con i valori di default.
	 *
	 * @return array<string, string>
	 */
	public static function notifications(): array {
		$defaults = [
			'email_to'          => '',
			'email_subject'     => 'Prenotazione [{externalid}] - {status}',
			'email_body'        => "Prenotazione ricevuta.\n\nID: {externalid}\nStato: {status}\nRemote ID: {remote_id}\nData: {date}\n\n--- DATI FORM ---\n{all_fields}\n\n--- ESITO API ---\nHTTP: {http_code}\nErrore: {api_error}",
			'notify_on_success' => '1',
			'notify_on_error'   => '1',
			'notify_on_resend'  => '1',
		];

		$saved = get_option( self::OPTION_NOTIFICATIONS, [] );
		return wp_parse_args( (array) $saved, $defaults );
	}

	// -------------------------------------------------------------------------
	// Scrittura impostazioni
	// -------------------------------------------------------------------------

	/**
	 * Salva le impostazioni principali (la password viene cifrata automaticamente).
	 *
	 * @param  array<string, mixed> $data Dati da salvare.
	 * @return bool
	 */
	public static function save_settings( array $data ): bool {
		$current = self::get_all();

		// Gestione della password: cifra solo se è stata fornita una nuova password.
		if ( ! empty( $data['api_password'] ) ) {
			$data['api_password'] = Crypto::encrypt( (string) $data['api_password'] );
		} else {
			// Mantieni la password cifrata esistente se non è stata cambiata.
			$data['api_password'] = $current['api_password'];
		}

		$to_save = [
			'api_base_url'   => sanitize_url( (string) ( $data['api_base_url'] ?? '' ) ),
			'api_username'   => sanitize_text_field( (string) ( $data['api_username'] ?? '' ) ),
			'api_password'   => (string) $data['api_password'],
			'default_status' => in_array( $data['default_status'] ?? '', [ 'request', 'reservation' ], true )
				? $data['default_status']
				: 'request',
		];

		return update_option( self::OPTION_SETTINGS, $to_save, false );
	}

	/**
	 * Salva le impostazioni di notifica email.
	 *
	 * @param  array<string, string> $data Dati da salvare.
	 * @return bool
	 */
	public static function save_notifications( array $data ): bool {
		$to_save = [
			'email_to'          => sanitize_email( (string) ( $data['email_to'] ?? '' ) ),
			'email_subject'     => sanitize_text_field( (string) ( $data['email_subject'] ?? '' ) ),
			'email_body'        => wp_kses_post( (string) ( $data['email_body'] ?? '' ) ),
			'notify_on_success' => ! empty( $data['notify_on_success'] ) ? '1' : '',
			'notify_on_error'   => ! empty( $data['notify_on_error'] ) ? '1' : '',
			'notify_on_resend'  => ! empty( $data['notify_on_resend'] ) ? '1' : '',
		];

		return update_option( self::OPTION_NOTIFICATIONS, $to_save, false );
	}

	/**
	 * Salva l'elenco dei form CF7 abilitati.
	 *
	 * @param  int[] $form_ids Array di ID form.
	 * @return bool
	 */
	public static function save_enabled_forms( array $form_ids ): bool {
		$clean = array_map( 'intval', $form_ids );
		$clean = array_filter( $clean );
		$clean = array_values( $clean );
		return update_option( self::OPTION_ENABLED_FORMS, $clean, false );
	}

	/**
	 * Salva la mappa dei campi per un form specifico.
	 *
	 * @param  int                   $form_id ID del form CF7.
	 * @param  array<string, string> $mapping Mappa api_path => cf7_field|'__const:valore'.
	 * @return bool
	 */
	public static function save_field_mapping( int $form_id, array $mapping ): bool {
		$all             = (array) get_option( self::OPTION_MAPPINGS, [] );
		$all[ $form_id ] = $mapping;
		return update_option( self::OPTION_MAPPINGS, $all, false );
	}
}
