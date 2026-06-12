<?php
/**
 * Definizione statica di tutti i campi dell'endpoint sendReservation.
 *
 * @package Mavida\AlpineBitsReservation\Schema
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Schema;

/**
 * Classe ApiSchema.
 *
 * Fonte di verità unica per la struttura del payload `sendReservation`.
 * Ogni campo è definito con:
 * - path:     dot-notation verso il JSON annidato (es. "guest.lastname")
 * - type:     tipo di cast applicato (string|int|float|bool|date|array_int|enum)
 * - required: se il campo è obbligatorio nell'API
 * - enum:     valori ammessi (se type = enum)
 * - group:    raggruppamento per la GUI di mapping
 * - label:    etichetta leggibile per il pannello admin
 * - notes:    note aggiuntive mostrate nella GUI
 */
class ApiSchema {

	/**
	 * Costanti per i gruppi.
	 *
	 * @var string
	 */
	const GROUP_RESERVATION = 'Prenotazione';
	const GROUP_GUEST       = 'Ospite';
	const GROUP_ROOM        = 'Camera';
	const GROUP_SERVICES    = 'Servizi';
	const GROUP_MARKETING   = 'Marketing';

	/**
	 * Restituisce la definizione completa di tutti i campi.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(): array {
		return array(
			// --- Prenotazione ---
			array(
				'path'     => 'status',
				'type'     => 'enum',
				'required' => true,
				'enum'     => array( 'request', 'reservation' ),
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Status prenotazione', 'wp-alpinebits-reservation' ),
				'notes'    => __( '"request" per richiesta, "reservation" per prenotazione confermata.', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'from',
				'type'     => 'date',
				'required' => true,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Data check-in (YYYY-MM-DD)', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'until',
				'type'     => 'date',
				'required' => true,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Data check-out (YYYY-MM-DD)', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'externalid',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'ID esterno', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Generato automaticamente dal plugin se non mappato.', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'comment',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Commento', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'total',
				'type'     => 'float',
				'required' => false,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Totale complessivo', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'forceavailability',
				'type'     => 'bool',
				'required' => false,
				'group'    => self::GROUP_RESERVATION,
				'label'    => __( 'Forza disponibilità', 'wp-alpinebits-reservation' ),
			),
			// --- Ospite principale ---
			array(
				'path'     => 'guest.lastname',
				'type'     => 'string',
				'required' => true,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Cognome', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.firstname',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Nome', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.email',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Email', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.telephone',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Telefono', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.language',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Lingua (ISO 639)', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Es. "it", "de", "en".', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.title',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Titolo', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.gender',
				'type'     => 'enum',
				'required' => false,
				'enum'     => array( 'm', 'f' ),
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Genere (m/f)', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.birthdate',
				'type'     => 'date',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Data di nascita (YYYY-MM-DD)', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.address',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Indirizzo', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.city',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Città', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.zipcode',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: CAP', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.country',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Paese (ISO 3166)', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Es. "IT", "DE", "AT".', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'guest.newsletter',
				'type'     => 'bool',
				'required' => false,
				'group'    => self::GROUP_GUEST,
				'label'    => __( 'Ospite: Newsletter', 'wp-alpinebits-reservation' ),
			),
			// --- Camera (prima camera, v0.1.0: una sola camera supportata) ---
			array(
				'path'     => 'rooms.0.category',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_ROOM,
				'label'    => __( 'Camera: Categoria', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Obbligatorio se status = "reservation".', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'rooms.0.rateplan',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_ROOM,
				'label'    => __( 'Camera: Piano tariffario', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'rooms.0.total',
				'type'     => 'float',
				'required' => false,
				'group'    => self::GROUP_ROOM,
				'label'    => __( 'Camera: Totale camera', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'rooms.0.occupants.adults',
				'type'     => 'int',
				'required' => false,
				'group'    => self::GROUP_ROOM,
				'label'    => __( 'Camera: Adulti', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Obbligatorio se status = "reservation".', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'rooms.0.occupants.children',
				'type'     => 'array_int',
				'required' => false,
				'group'    => self::GROUP_ROOM,
				'label'    => __( 'Camera: Età bambini', 'wp-alpinebits-reservation' ),
				'notes'    => __( 'Valori separati da virgola nel campo CF7 (es. "5,8"). Ogni valore = età di un bambino.', 'wp-alpinebits-reservation' ),
			),
			// --- Marketing ---
			array(
				'path'     => 'utm_source',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_MARKETING,
				'label'    => __( 'UTM Source', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'utm_medium',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_MARKETING,
				'label'    => __( 'UTM Medium', 'wp-alpinebits-reservation' ),
			),
			array(
				'path'     => 'utm_campaign',
				'type'     => 'string',
				'required' => false,
				'group'    => self::GROUP_MARKETING,
				'label'    => __( 'UTM Campaign', 'wp-alpinebits-reservation' ),
			),
		);
	}

	/**
	 * Restituisce i campi raggruppati per il rendering della GUI.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function fields_by_group(): array {
		$grouped = array();
		foreach ( self::fields() as $field ) {
			$group               = $field['group'];
			$grouped[ $group ]   = $grouped[ $group ] ?? array();
			$grouped[ $group ][] = $field;
		}
		return $grouped;
	}

	/**
	 * Restituisce i soli campi obbligatori.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function required_fields(): array {
		return array_values(
			array_filter(
				self::fields(),
				static fn( array $f ): bool => true === ( $f['required'] ?? false )
			)
		);
	}
}
