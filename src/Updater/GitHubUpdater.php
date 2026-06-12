<?php
/**
 * Updater automatico tramite release GitHub (repo pubblico).
 *
 * @package Mavida\AlpineBitsReservation\Updater
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Updater;

/**
 * Classe GitHubUpdater.
 *
 * Integra yahnis-elsts/plugin-update-checker per il controllo e l'installazione
 * degli aggiornamenti a partire dai tag/release pubblicati sul repository GitHub pubblico.
 *
 * Owner e repo sono fissi nel codice (non configurabili dall'admin).
 * Il package è richiesto via Composer; se la classe non è disponibile, il metodo
 * register() torna senza fare nulla.
 */
class GitHubUpdater {

	/**
	 * Proprietario del repository GitHub.
	 *
	 * @var string
	 */
	const GITHUB_OWNER = 'mavidasnc';

	/**
	 * Nome del repository GitHub.
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'wp-alpinebits-reservation';

	/**
	 * Registra il controllo aggiornamenti.
	 *
	 * @return void
	 */
	public function register(): void {
		// Verifica che plugin-update-checker sia installato.
		if ( ! class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/',
			\WPAR_PLUGIN_FILE,
			'wp-alpinebits-reservation'
		);

		// Usa le release GitHub (tag con asset ZIP) come sorgente degli aggiornamenti.
		$update_checker->getVcsApi()->enableReleaseAssets();
	}
}
