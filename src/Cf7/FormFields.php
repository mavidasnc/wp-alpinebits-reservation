<?php
/**
 * Estrazione dei campi di un form CF7 per la GUI di mapping.
 *
 * @package Mavida\AlpineBitsReservation\Cf7
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Cf7;

/**
 * Classe FormFields.
 *
 * Usa le API di CF7 per ottenere l'elenco dei campi compilabili di un form,
 * da usare come opzioni nelle select della GUI di mapping.
 */
class FormFields {

	/**
	 * Restituisce i campi di un form CF7 come array associativo name => label.
	 *
	 * Usa `scan_form_tags` con filtro 'name-attr' per includere solo i campi
	 * che hanno un attributo name (esclude submit, captcha visuale, ecc.)
	 * e '!not-for-mail' per escludere quiz e contatori.
	 *
	 * @param  int $form_id ID del form CF7.
	 * @return array<string, string> Mappa name => "name (basetype)".
	 */
	public static function for_form( int $form_id ): array {
		if ( ! self::is_cf7_active() ) {
			return array();
		}

		$contact_form = \WPCF7_ContactForm::get_instance( $form_id );

		if ( ! $contact_form instanceof \WPCF7_ContactForm ) {
			return array();
		}

		// Filtra solo i tag con attributo name, escludendo campi non destinati alla mail.
		$tags = $contact_form->scan_form_tags(
			array(
				'feature' => array( 'name-attr', '!not-for-mail' ),
			)
		);

		$fields = array();
		foreach ( $tags as $tag ) {
			if ( empty( $tag->name ) ) {
				continue;
			}

			// Etichetta: "nome-campo (tipo)" — es. "your-email (email)".
			$fields[ $tag->name ] = sprintf( '%s (%s)', $tag->name, $tag->basetype );
		}

		return $fields;
	}

	/**
	 * Restituisce tutti i form CF7 come array id => title.
	 *
	 * @return array<int, string>
	 */
	public static function all_forms(): array {
		if ( ! self::is_cf7_active() ) {
			return array();
		}

		$forms  = \WPCF7_ContactForm::find();
		$result = array();

		foreach ( $forms as $form ) {
			$result[ $form->id() ] = $form->title();
		}

		return $result;
	}

	/**
	 * Verifica che CF7 sia attivo e le sue classi disponibili.
	 *
	 * @return bool
	 */
	private static function is_cf7_active(): bool {
		return class_exists( 'WPCF7_ContactForm' );
	}
}
