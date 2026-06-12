<?php
/**
 * Updater automatico tramite release GitHub (repo pubblico).
 *
 * @package Mavida\AlpineBitsReservation\Updater
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Updater;

use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe GitHubUpdater.
 *
 * Integra yahnis-elsts/plugin-update-checker per il controllo e l'installazione
 * degli aggiornamenti a partire dai tag/release pubblicati sul repository GitHub pubblico.
 *
 * Il package è richiesto via Composer; se la classe non è disponibile, il metodo
 * register() torna senza fare nulla.
 */
class GitHubUpdater {

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

		$owner = Options::github_owner();
		$repo  = Options::github_repo();

		if ( '' === $owner || '' === $repo ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			"https://github.com/{$owner}/{$repo}/",
			WPAR_PLUGIN_FILE,
			'wp-alpinebits-reservation'
		);

		// Usa le release GitHub (tag con asset ZIP) come sorgente degli aggiornamenti.
		$update_checker->getVcsApi()->enableReleaseAssets();
	}
}
