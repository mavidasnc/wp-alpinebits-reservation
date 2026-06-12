# Changelog

Tutti i cambiamenti rilevanti a questo progetto sono documentati in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

## [Unreleased]

## [0.1.0] - 2026-06-12

### Aggiunto
- Bootstrap plugin con autoload PSR-4 e guard Composer.
- Attivazione: creazione tabella `{prefix}alpinebits_reservations` tramite `dbDelta`.
- Classe `Crypto` per cifratura/decifratura AES-256-CBC delle credenziali API.
- Classe `Options` per accesso tipizzato alle opzioni con override via costanti `wp-config.php`.
- Schema statico `ApiSchema` con tutti i campi dell'endpoint `sendReservation`.
- Classe `FieldMapper` per la ricostruzione del payload annidato con casting dei tipi.
- Integrazione CF7: hook `wpcf7_before_send_mail`, estrazione campi form con `scan_form_tags`.
- Client API `Api\Client` con autenticazione Basic Auth tramite `wp_remote_post`.
- `Reservations\Repository` per CRUD sulla tabella custom.
- `Reservations\Sender` per orchestrazione invio e reinvio.
- Pannello di amministrazione a 4 tab: Connessione, Moduli, Mapping, Invii.
- Tab Mapping con GUI (campi API a destra, select campi CF7 a sinistra + valore costante).
- Tab Invii con `WP_List_Table`, dettaglio payload/risposta e pulsante Reinvia.
- Updater automatico tramite release GitHub (yahnis-elsts/plugin-update-checker).
- PHPCS con ruleset WordPress-Extra + PHPCompatibility PHP 8.1.

[Unreleased]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/releases/tag/v0.1.0
