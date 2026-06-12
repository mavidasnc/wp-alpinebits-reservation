<?php
/**
 * Gestisce l'attivazione e la disattivazione del plugin.
 *
 * @package Mavida\AlpineBitsReservation
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation;

/**
 * Classe Activator.
 *
 * Crea la tabella custom tramite dbDelta e imposta le opzioni di default.
 * Il metodo `activate` viene chiamato da `register_activation_hook`.
 */
class Activator {

	/**
	 * Versione dello schema del database (incrementare ad ogni modifica strutturale).
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Nome dell'option che memorizza la versione DB installata.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'wpar_db_version';

	/**
	 * Eseguito all'attivazione del plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_table();
		self::set_default_options();

		// Salva la versione DB attuale per future migrazioni.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );

		// Svuota i rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Eseguito alla disattivazione del plugin.
	 *
	 * NON elimina dati né tabelle (riservato alla disinstallazione).
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Crea (o aggiorna) la tabella delle reservation tramite dbDelta.
	 *
	 * @return void
	 */
	private static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'alpinebits_reservations';
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta richiede formattazione SQL molto precisa (spazi, fine riga).
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			externalid VARCHAR(64) NOT NULL DEFAULT '',
			payload LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			http_code SMALLINT NULL DEFAULT NULL,
			response LONGTEXT NULL DEFAULT NULL,
			remote_id VARCHAR(64) NULL DEFAULT NULL,
			attempts SMALLINT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY externalid (externalid)
		) {$charset_collate};";

		// Richiesto da dbDelta.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Imposta le opzioni di default se non esistono già.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		// Impostazioni principali: usa add_option per non sovrascrivere valori esistenti.
		add_option(
			'wpar_settings',
			array(
				'api_base_url'   => 'https://alpinebits-gateway.ando.cloud/api/v1',
				'api_username'   => '',
				'api_password'   => '', // Salvata cifrata da Settings/Options.
				'default_status' => 'request',
				'github_owner'   => 'mavidasnc',
				'github_repo'    => 'wp-alpinebits-reservation',
			),
			'',
			false // Non autoload per performance.
		);

		// Elenco dei form CF7 abilitati (array di ID).
		add_option( 'wpar_enabled_forms', array(), '', false );

		// Mappatura campi: array indicizzato per form_id.
		add_option( 'wpar_field_mappings', array(), '', false );
	}
}
