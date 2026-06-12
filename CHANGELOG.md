# Changelog

Tutti i cambiamenti rilevanti a questo progetto sono documentati in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

## [Unreleased]

## [0.2.1] - 2026-06-12

### Corretto
- Build ZIP di rilascio: usa `git archive` per garantire l'inclusione di tutti i file tracciati (nella v0.2.0 il file principale `wp-alpinebits-reservation.php` era assente dallo ZIP).
- Aggiunto `*.zip` al `.gitignore`; il file di rilascio viene ora salvato nella cartella del plugin.

## [0.2.0] - 2026-06-12

### Aggiunto
- Tab **Notifiche**: configurazione email con indirizzo destinatario, oggetto e corpo con placeholder `{field_name}`; allegato automatico JSON con tutti i dati CF7; abilitazione separata per successo, errore e reinvio.
- Classe `Notifications\Notifier` per invio email tramite `wp_mail()` con allegato JSON temporaneo.
- Classe `Updater\VersionChecker` per la verifica manuale della versione più recente via API GitHub Releases.
- Pulsante **Controlla aggiornamenti** nel tab Connessione con risposta AJAX: mostra versione disponibile e link diretto alla schermata di aggiornamento WP standard.
- **Spunta verde** (✓) affianco a ogni campo già mappato nel tab Mapping; sfondo verde tenue sulla riga.
- Link **Impostazioni** e **GitHub** nella riga del plugin nell'elenco plugin WordPress (filtro `plugin_action_links_`).
- Migrazione DB automatica v1.0 → v1.1: aggiunta colonna `cf7_data LONGTEXT NULL` per conservare i dati CF7 originali (usati nelle notifiche di reinvio).
- Metodo `Activator::maybe_upgrade()` per migrazioni incrementali eseguito a ogni avvio del plugin.

### Modificato
- `GitHubUpdater`: owner e repo hardcoded come costanti di classe (`GITHUB_OWNER`, `GITHUB_REPO`); rimossa dipendenza da `Options`.
- `ConnectionTab`: rimossa la sezione "Aggiornamenti GitHub" (owner/repo non più configurabili dall'admin); aggiunta sezione "Versione plugin e aggiornamenti".
- `Options`: rimossi i metodi `github_owner()` e `github_repo()`; aggiunti `notifications()` e `save_notifications()`; semplificati i default (rimossi `github_owner`/`github_repo`).
- `Plugin::boot()`: aggiunto controllo versione DB per le migrazioni; registrazione filtro `plugin_action_links_`.
- `Sender::send()`: i dati CF7 originali vengono ora salvati in `cf7_data` e passati a `Notifier`.
- `Sender::resend()`: chiama `Notifier` in caso di reinvio riuscito con i dati CF7 recuperati dal DB.
- `AdminMenu`: aggiunto tab Notifiche e handler AJAX `wpar_check_version`.

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

[Unreleased]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/releases/tag/v0.1.0
