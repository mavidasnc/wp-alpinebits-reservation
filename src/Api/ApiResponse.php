<?php
/**
 * DTO per la risposta dell'API AlpineBits.
 *
 * @package Mavida\AlpineBitsReservation\Api
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Api;

/**
 * Classe ApiResponse.
 *
 * Value object immutabile che incapsula l'esito di una chiamata API.
 */
readonly class ApiResponse {

	/**
	 * Costruttore.
	 *
	 * @param bool   $success   True se l'API ha restituito success=true.
	 * @param int    $http_code Codice HTTP della risposta (0 se errore di trasporto).
	 * @param string $raw_body  Body grezzo della risposta.
	 * @param string $error     Messaggio di errore (vuoto se success).
	 * @param string $remote_id ID restituito dall'API in caso di successo (data.id).
	 */
	public function __construct(
		public bool $success,
		public int $http_code,
		public string $raw_body,
		public string $error = '',
		public string $remote_id = '',
	) {}
}
