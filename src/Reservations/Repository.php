<?php
/**
 * Accesso CRUD alla tabella custom delle reservation.
 *
 * @package Mavida\AlpineBitsReservation\Reservations
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Reservations;

/**
 * Classe Repository.
 *
 * Gestisce insert, update e lettura dalla tabella `{prefix}alpinebits_reservations`.
 * Tutte le query usano $wpdb->prepare per la sicurezza.
 */
class Repository {

	/**
	 * Costanti di stato.
	 *
	 * @var string
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR   = 'error';

	/**
	 * Restituisce il nome completo della tabella.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alpinebits_reservations';
	}

	/**
	 * Inserisce una nuova riga in stato 'pending' e restituisce l'ID inserito.
	 *
	 * @param  int                  $form_id    ID del form CF7.
	 * @param  string               $externalid ID esterno generato.
	 * @param  array<string, mixed> $payload    Payload JSON da inviare.
	 * @param  array<string, mixed> $cf7_data   Dati originali del form CF7 (per le notifiche e il reinvio).
	 * @return int                              ID della riga inserita, 0 in caso di errore.
	 */
	public function insert( int $form_id, string $externalid, array $payload, array $cf7_data = [] ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$data   = [
			'form_id'    => $form_id,
			'externalid' => $externalid,
			'payload'    => wp_json_encode( $payload ),
			'status'     => self::STATUS_PENDING,
			'http_code'  => null,
			'response'   => null,
			'remote_id'  => null,
			'attempts'   => 0,
			'created_at' => $now,
			'updated_at' => $now,
		];
		$format = [ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ];

		if ( ! empty( $cf7_data ) ) {
			$data['cf7_data'] = wp_json_encode( $cf7_data );
			$format[]         = '%s';
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			$data,
			$format
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Aggiorna lo stato di una riga dopo la risposta API.
	 *
	 * @param  int    $id        ID della riga.
	 * @param  string $status    Nuovo stato ('success' o 'error').
	 * @param  int    $http_code Codice HTTP risposta.
	 * @param  string $response  Body risposta (grezzo).
	 * @param  string $remote_id ID restituito dall'API (vuoto se errore).
	 * @return void
	 */
	public function update_status(
		int $id,
		string $status,
		int $http_code,
		string $response,
		string $remote_id = ''
	): void {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'UPDATE %i SET status = %s, http_code = %d, response = %s, remote_id = %s,
				 attempts = attempts + 1, updated_at = %s WHERE id = %d',
				self::table_name(),
				$status,
				$http_code,
				$response,
				$remote_id,
				current_time( 'mysql', true ),
				$id
			)
		);
	}

	/**
	 * Restituisce una riga per ID.
	 *
	 * @param  int $id ID della riga.
	 * @return object|null    Riga come oggetto, null se non trovata.
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table_name(),
				$id
			)
		);
	}

	/**
	 * Restituisce l'elenco delle submission con paginazione e filtri.
	 *
	 * @param  array<string, mixed> $args Argomenti: status, form_id, per_page, page, orderby, order.
	 * @return object[]
	 */
	public function get_list( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'   => '',
			'form_id'  => 0,
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'DESC',
		];
		$args     = wp_parse_args( $args, $defaults );

		// Costruzione WHERE.
		$where  = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}

		// Whitelist per ORDER BY (sicurezza: non si usa prepare per colonne).
		$allowed_order = [ 'id', 'form_id', 'status', 'created_at', 'updated_at', 'attempts' ];
		$allowed_dir   = [ 'ASC', 'DESC' ];
		$orderby       = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'id';
		$order         = in_array( strtoupper( $args['order'] ), $allowed_dir, true ) ? strtoupper( $args['order'] ) : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$where_clause = implode( ' AND ', $where );
		$table        = self::table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		if ( ! empty( $params ) ) {
			$wpar_sql = $wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
				array_merge( $params, [ $per_page, $offset ] )
			);
		} else {
			$wpar_sql = $wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE 1=1 ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		return (array) $wpdb->get_results( $wpar_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable
	}

	/**
	 * Conta le righe totali con i filtri applicati (per paginazione).
	 *
	 * @param  array<string, mixed> $args Stessi argomenti di get_list.
	 * @return int
	 */
	public function count( array $args = [] ): int {
		global $wpdb;

		$defaults = [
			'status'  => '',
			'form_id' => 0,
		];
		$args     = wp_parse_args( $args, $defaults );

		$where  = [ '1=1' ];
		$params = [];
		$table  = self::table_name();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders
		if ( ! empty( $params ) ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}",
					$params
				)
			);
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1" );
		}
		// phpcs:enable

		return (int) $count;
	}
}
