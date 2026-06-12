<?php
/**
 * Listener per l'hook di invio di Contact Form 7.
 *
 * @package Mavida\AlpineBitsReservation\Cf7
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Cf7;

use Mavida\AlpineBitsReservation\Reservations\Sender;
use Mavida\AlpineBitsReservation\Settings\Options;

/**
 * Classe SubmissionListener.
 *
 * Si aggancia all'hook `wpcf7_before_send_mail` e, se il form
 * è tra quelli abilitati, avvia il processo di invio all'API AlpineBits.
 *
 * L'errore API NON blocca l'invio CF7 (non impostiamo $abort = true).
 * La submission viene tracciata nel DB per il reinvio manuale.
 */
class SubmissionListener {

	/**
	 * Registra l'hook WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		// Priority 20: dopo i controlli interni di CF7, prima dell'invio email.
		add_action(
			'wpcf7_before_send_mail',
			array( $this, 'handle_submission' ),
			20,
			3
		);
	}

	/**
	 * Gestisce la submission di un form CF7.
	 *
	 * @param  \WPCF7_ContactForm $contact_form L'oggetto form CF7.
	 * @param  bool               &$abort       Passato per riferimento; se true CF7 annulla l'invio.
	 * @param  \WPCF7_Submission  $submission   L'oggetto submission con i dati del form.
	 * @return void
	 */
	public function handle_submission(
		\WPCF7_ContactForm $contact_form,
		bool &$abort,
		\WPCF7_Submission $submission
	): void {
		$form_id = $contact_form->id();

		// Controlla se questo form è abilitato per l'integrazione AlpineBits.
		$enabled_forms = Options::enabled_forms();
		if ( ! in_array( $form_id, $enabled_forms, true ) ) {
			return;
		}

		// Recupera tutti i dati inviati dal form.
		$posted_data = $submission->get_posted_data();

		if ( empty( $posted_data ) ) {
			return;
		}

		// Recupera la mappa dei campi configurata per questo form.
		$mapping = Options::field_mapping( $form_id );

		if ( empty( $mapping ) ) {
			// Nessuna mappa configurata: logga e prosegui senza bloccare.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WPAR] Form %d: nessuna mappa configurata, invio API saltato.', $form_id ) );
			return;
		}

		// Avvia l'invio: non blocca CF7 in caso di errore API.
		( new Sender() )->send( $form_id, $posted_data, $mapping );

		// $abort rimane invariato (false): l'invio CF7 prosegue normalmente.
	}
}
