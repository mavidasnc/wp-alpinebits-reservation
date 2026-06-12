<?php
/**
 * Cifratura e decifratura simmetrica per le credenziali API.
 *
 * @package Mavida\AlpineBitsReservation\Support
 */

declare( strict_types=1 );

namespace Mavida\AlpineBitsReservation\Support;

/**
 * Classe Crypto.
 *
 * Cifra e decifra stringhe usando AES-256-CBC.
 * La chiave è derivata dalle costanti WordPress AUTH_KEY e SECURE_AUTH_SALT,
 * in modo che la password sia legata all'installazione e non sia trasportabile.
 *
 * Nota: se AUTH_KEY non è definita (installazione non configurata), usa un
 * fallback che rende la cifratura debole; in quel caso il dato viene comunque
 * salvato ma viene emesso un notice in WP_DEBUG mode.
 */
class Crypto {

	/**
	 * Algoritmo di cifratura.
	 *
	 * @var string
	 */
	const CIPHER = 'AES-256-CBC';

	/**
	 * Cifra una stringa in chiaro e restituisce una stringa base64.
	 *
	 * Formato del risultato: base64( iv . ciphertext )
	 *
	 * @param  string $plaintext Testo in chiaro.
	 * @return string            Testo cifrato in base64, o stringa vuota in caso di errore.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key    = self::derive_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		// Concatena IV + ciphertext e codifica in base64 per la memorizzazione.
		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decifra una stringa cifrata con `encrypt()`.
	 *
	 * @param  string $encrypted Stringa cifrata in base64.
	 * @return string            Testo in chiaro, o stringa vuota in caso di errore.
	 */
	public static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		$key    = self::derive_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );

		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded || strlen( $decoded ) <= $iv_len ) {
			return '';
		}

		// Separa IV e ciphertext.
		$iv         = substr( $decoded, 0, $iv_len );
		$ciphertext = substr( $decoded, $iv_len );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return ( false === $plaintext ) ? '' : $plaintext;
	}

	/**
	 * Deriva la chiave di cifratura dalle costanti WordPress.
	 *
	 * @return string Chiave a 32 byte (256 bit).
	 */
	private static function derive_key(): string {
		$salt   = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpar-fallback-key';
		$pepper = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'wpar-fallback-salt';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! defined( 'AUTH_KEY' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'WP AlpineBits Reservation: AUTH_KEY non definita, la cifratura è debole.', E_USER_NOTICE );
		}

		// Hash SHA-256 per ottenere esattamente 32 byte.
		return hash( 'sha256', $salt . $pepper, true );
	}
}
