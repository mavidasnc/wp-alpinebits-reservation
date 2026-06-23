<?php
/**
 * Ricostruisce il payload JSON annidato dai dati del form CF7.
 *
 * @package Mavida\AlpineBitsReservation\Mapping
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Mapping;

use Mavida\AlpineBitsReservation\Schema\ApiSchema;

/**
 * Classe FieldMapper.
 *
 * Riceve i dati grezzi del form CF7 (array piatto), la mappa di configurazione
 * e restituisce un array PHP pronto per la serializzazione JSON conforme allo
 * schema `sendReservation`.
 *
 * Formato della mappa (salvata in Options):
 *   api_field_path => cf7_field_name         (es. "guest.lastname" => "your-surname")
 *   api_field_path => "__const:valore"        (es. "status" => "__const:request")
 *   api_field_path => ""                      (campo non mappato, viene ignorato)
 */
class FieldMapper {

	/**
	 * Prefisso per i valori costanti nella mappa.
	 *
	 * @var string
	 */
	const CONST_PREFIX = '__const:';

	/**
	 * Prefisso per la raccolta di più campi CF7 in un array (es. età bambini).
	 * Formato: "__collect:eta1,eta2,eta3"
	 *
	 * @var string
	 */
	const COLLECT_PREFIX = '__collect:';

	/**
	 * Costruisce il payload per l'API a partire dai posted_data di CF7.
	 *
	 * @param  array<string, mixed>  $posted_data Dati grezzi dal form CF7.
	 * @param  array<string, string> $mapping     Mappa api_path => cf7_field|__const:valore.
	 * @return array<string, mixed>               Payload annidato pronto per JSON.
	 */
	public function build( array $posted_data, array $mapping ): array {
		$payload = array();

		foreach ( ApiSchema::fields() as $field_def ) {
			$path  = $field_def['path'];
			$type  = $field_def['type'];
			$value = $this->resolve_value( $path, $type, $posted_data, $mapping );

			// Salta i campi senza valore: null, stringa vuota o array vuoto.
			if ( null === $value || '' === $value || [] === $value ) {
				continue;
			}

			// Imposta il valore nell'array annidato seguendo il path dot-notation.
			$this->set_nested( $payload, $path, $value );
		}

		// Assicura che l'array rooms abbia indici sequenziali (non associativi).
		if ( isset( $payload['rooms'] ) && is_array( $payload['rooms'] ) ) {
			$payload['rooms'] = array_values( $payload['rooms'] );
		}

		// Propaga rateplan e total della prima camera a tutte le altre presenti nel payload.
		// I due campi sono configurati una volta sola nel gruppo "Tariffe (camere)".
		if ( isset( $payload['rooms'] ) && count( $payload['rooms'] ) > 1 ) {
			$room0 = $payload['rooms'][0] ?? array();
			for ( $i = 1, $count = count( $payload['rooms'] ); $i < $count; $i++ ) {
				foreach ( array( 'rateplan', 'total' ) as $shared_key ) {
					if ( isset( $room0[ $shared_key ] ) && ! isset( $payload['rooms'][ $i ][ $shared_key ] ) ) {
						$payload['rooms'][ $i ][ $shared_key ] = $room0[ $shared_key ];
					}
				}
			}
		}

		// Garantisce externalid: se non mappato, lo genera il Sender.
		return $payload;
	}

	/**
	 * Risolve il valore di un campo dalla mappa o dai posted_data.
	 *
	 * @param  string                $path        Path dot-notation del campo API.
	 * @param  string                $type        Tipo di cast da applicare.
	 * @param  array<string, mixed>  $posted_data Dati grezzi CF7.
	 * @param  array<string, string> $mapping    Mappa di configurazione.
	 * @return mixed                             Valore castato o null se non disponibile.
	 */
	private function resolve_value(
		string $path,
		string $type,
		array $posted_data,
		array $mapping
	): mixed {
		// Cerca la mappatura per questo path.
		$mapped = $mapping[ $path ] ?? '';

		if ( '' === $mapped ) {
			return null;
		}

		// Valore costante: __const:valore_fisso.
		if ( str_starts_with( $mapped, self::CONST_PREFIX ) ) {
			$raw = substr( $mapped, strlen( self::CONST_PREFIX ) );
			return $this->cast( $raw, $type );
		}

		// Raccolta di più campi CF7: __collect:campo1,campo2,...
		// Usato per array_int (es. età bambini da campi separati).
		if ( str_starts_with( $mapped, self::COLLECT_PREFIX ) ) {
			$field_names = array_map( 'trim', explode( ',', substr( $mapped, strlen( self::COLLECT_PREFIX ) ) ) );
			return $this->collect_values( $field_names, $posted_data );
		}

		// Valore dal campo CF7.
		$raw = $posted_data[ $mapped ] ?? null;

		if ( null === $raw ) {
			return null;
		}

		// CF7 può restituire array per checkbox/select multipli; prendi il primo.
		if ( is_array( $raw ) ) {
			$raw = $raw[0] ?? '';
		}

		return $this->cast( (string) $raw, $type );
	}

	/**
	 * Applica il casting al tipo previsto dallo schema.
	 *
	 * @param  string $value Valore grezzo (stringa).
	 * @param  string $type  Tipo target (string|int|float|bool|date|array_int|enum).
	 * @return mixed         Valore castato, o null se la conversione fallisce per `date`.
	 */
	private function cast( string $value, string $type ): mixed {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		return match ( $type ) {
			'int'       => (int) $value,
			'float'     => (float) str_replace( ',', '.', $value ),
			'bool'      => in_array( strtolower( $value ), array( '1', 'true', 'yes', 'si', 'sì', 'on' ), true ),
			'date'      => $this->cast_date( $value ),
			'array_int' => $this->cast_array_int( $value ),
			default     => $value, // 'string', 'enum' — nessun cast aggiuntivo.
		};
	}

	/**
	 * Converte una stringa in formato data YYYY-MM-DD.
	 * Accetta YYYY-MM-DD o DD/MM/YYYY; restituisce null se il formato non è valido.
	 *
	 * @param  string $value Stringa data.
	 * @return string|null   Data YYYY-MM-DD o null.
	 */
	private function cast_date( string $value ): ?string {
		// Formato nativo dei campi [date] di CF7: già YYYY-MM-DD.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		// Formato alternativo DD/MM/YYYY (input europeo testo libero).
		if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m ) ) {
			return "{$m[3]}-{$m[2]}-{$m[1]}";
		}

		// Formato non riconosciuto: restituisce null (la submission andrà in error).
		return null;
	}

	/**
	 * Converte una stringa di valori separati da virgola in array di interi.
	 * Usato per il campo rooms.0.occupants.children (es. "5,8" => [5, 8]).
	 *
	 * @param  string $value Stringa con valori separati da virgola.
	 * @return int[]         Array di interi.
	 */
	private function cast_array_int( string $value ): array {
		$parts = explode( ',', $value );
		return array_values(
			array_filter(
				array_map(
					static fn( string $v ): int => (int) trim( $v ),
					$parts
				),
				static fn( int $v ): bool => $v >= 0
			)
		);
	}

	/**
	 * Raccoglie valori da più campi CF7 e li restituisce come array di interi.
	 *
	 * I campi vuoti o non presenti nei posted_data vengono ignorati.
	 * Usato per mappare campi separati (es. eta1, eta2, eta3) in un array di età.
	 *
	 * @param  string[]             $field_names Nomi dei campi CF7 da raccogliere.
	 * @param  array<string, mixed> $posted_data Dati grezzi CF7.
	 * @return int[]                             Array di interi (vuoto se nessun campo valorizzato).
	 */
	private function collect_values( array $field_names, array $posted_data ): array {
		$result = array();

		foreach ( $field_names as $field_name ) {
			$raw = $posted_data[ $field_name ] ?? null;

			if ( is_array( $raw ) ) {
				$raw = $raw[0] ?? '';
			}

			$raw = trim( (string) $raw );

			// Salta campi vuoti — non significa età 0.
			if ( '' === $raw ) {
				continue;
			}

			$result[] = (int) $raw;
		}

		return $result;
	}

	/**
	 * Imposta un valore in un array multidimensionale seguendo il path dot-notation.
	 * Supporta path con indici numerici per array (es. "rooms.0.category").
	 *
	 * @param  array<string, mixed> &$data  Array da modificare (per riferimento).
	 * @param  string               $path   Path dot-notation (es. "guest.lastname").
	 * @param  mixed                $value  Valore da impostare.
	 * @return void
	 */
	private function set_nested( array &$data, string $path, mixed $value ): void {
		$keys    = explode( '.', $path );
		$current = &$data;

		foreach ( $keys as $i => $key ) {
			$is_last = ( count( $keys ) - 1 === $i );

			if ( $is_last ) {
				$current[ $key ] = $value;
				break;
			}

			// Crea il nodo intermedio se non esiste.
			if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
				$current[ $key ] = array();
			}

			$current = &$current[ $key ];
		}
	}
}
