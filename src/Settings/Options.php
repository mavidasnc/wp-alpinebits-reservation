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
 * - WPAR_GITHUB_OWNER
 * - WPAR_GITHUB_REPO
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

	// -------------------------------------------------------------------------
	// Lettura impostazioni
	// -------------------------------------------------------------------------

	/**
	 * Restituisce l'array delle impostazioni raw dal DB (senza decrypt).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$defaults = array(
			'api_base_url'   => 'https://alpinebits-gateway.ando.cloud/api/v1',
			'api_username'   => '',
			'api_password'   => '',
			'default_status' => 'request',
			'github_owner'   => 'mavidasnc',
			'github_repo'    => 'wp-alpinebits-reservation',
		);

		$saved = get_option( self::OPTION_SETTINGS, array() );
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
		return in_array( $status, array( 'request', 'reservation' ), true ) ? $status : 'request';
	}

	/**
	 * Restituisce il proprietario del repo GitHub.
	 *
	 * @return string
	 */
	public static function github_owner(): string {
		if ( defined( 'WPAR_GITHUB_OWNER' ) ) {
			return (string) \WPAR_GITHUB_OWNER;
		}
		return (string) ( self::get_all()['github_owner'] ?? '' );
	}

	/**
	 * Restituisce il nome del repo GitHub.
	 *
	 * @return string
	 */
	public static function github_repo(): string {
		if ( defined( 'WPAR_GITHUB_REPO' ) ) {
			return (string) \WPAR_GITHUB_REPO;
		}
		return (string) ( self::get_all()['github_repo'] ?? '' );
	}

	/**
	 * Restituisce l'array degli ID dei form CF7 abilitati.
	 *
	 * @return int[]
	 */
	public static function enabled_forms(): array {
		$forms = get_option( self::OPTION_ENABLED_FORMS, array() );
		return array_map( 'intval', (array) $forms );
	}

	/**
	 * Restituisce la mappa dei campi per un form specifico.
	 *
	 * @param  int $form_id ID del form CF7.
	 * @return array<string, string> Mappa: api_field_path => cf7_field_name|'__const:valore'
	 */
	public static function field_mapping( int $form_id ): array {
		$all = get_option( self::OPTION_MAPPINGS, array() );
		return (array) ( $all[ $form_id ] ?? array() );
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

		$to_save = array(
			'api_base_url'   => sanitize_url( (string) ( $data['api_base_url'] ?? '' ) ),
			'api_username'   => sanitize_text_field( (string) ( $data['api_username'] ?? '' ) ),
			'api_password'   => (string) $data['api_password'],
			'default_status' => in_array( $data['default_status'] ?? '', array( 'request', 'reservation' ), true )
				? $data['default_status']
				: 'request',
			'github_owner'   => sanitize_text_field( (string) ( $data['github_owner'] ?? '' ) ),
			'github_repo'    => sanitize_text_field( (string) ( $data['github_repo'] ?? '' ) ),
		);

		return update_option( self::OPTION_SETTINGS, $to_save, false );
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
		$all             = (array) get_option( self::OPTION_MAPPINGS, array() );
		$all[ $form_id ] = $mapping;
		return update_option( self::OPTION_MAPPINGS, $all, false );
	}
}
