<?php
/**
 * Verifica manuale della disponibilità di nuove versioni dal repo GitHub.
 *
 * @package Mavida\AlpineBitsReservation\Updater
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Updater;

/**
 * Classe VersionChecker.
 *
 * Effettua una chiamata diretta all'API GitHub Releases per confrontare
 * la versione installata con l'ultima release disponibile.
 * Il risultato viene memorizzato in una transient per 30 minuti.
 */
class VersionChecker {

	/**
	 * Chiave della transient usata per il caching del risultato.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wpar_latest_version';

	/**
	 * Durata della cache in secondi (30 minuti).
	 *
	 * @var int
	 */
	const TRANSIENT_EXPIRY = 1800;

	/**
	 * Verifica la disponibilità di una nuova versione dall'API GitHub.
	 *
	 * Se il risultato è in cache, lo restituisce direttamente.
	 *
	 * @return array<string, mixed> Array con chiavi:
	 *   - current_version (string)
	 *   - latest_version  (string)
	 *   - update_available (bool)
	 *   - update_url       (string) — URL alla pagina di aggiornamento WP standard
	 *   - error            (string) — presente solo in caso di errore
	 */
	public function check_latest(): array {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . GitHubUpdater::GITHUB_OWNER . '/' . GitHubUpdater::GITHUB_REPO . '/releases/latest';
		$response = wp_remote_get(
			$url,
			[
				'headers' => [ 'User-Agent' => 'wp-alpinebits-reservation/' . \WPAR_VERSION ],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return [ 'error' => __( 'Risposta non valida dall\'API GitHub.', 'wp-alpinebits-reservation' ) ];
		}

		$latest_version   = ltrim( (string) $body['tag_name'], 'v' );
		$update_available = version_compare( $latest_version, \WPAR_VERSION, '>' );

		$result = [
			'current_version'  => \WPAR_VERSION,
			'latest_version'   => $latest_version,
			'update_available' => $update_available,
			'update_url'       => $update_available ? $this->build_update_url() : '',
		];

		set_transient( self::TRANSIENT_KEY, $result, self::TRANSIENT_EXPIRY );
		return $result;
	}

	/**
	 * Svuota la cache per forzare il ribilanciamento al prossimo check.
	 *
	 * Elimina anche la transient globale degli aggiornamenti plugin di WP
	 * in modo che plugin-update-checker rilevi l'aggiornamento immediatamente.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Restituisce l'URL alla pagina standard di aggiornamento plugin di WordPress.
	 *
	 * @return string
	 */
	private function build_update_url(): string {
		return wp_nonce_url(
			admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( \WPAR_PLUGIN_BASENAME ) ),
			'upgrade-plugin_' . \WPAR_PLUGIN_BASENAME
		);
	}
}
