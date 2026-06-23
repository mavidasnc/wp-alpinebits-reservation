# Changelog

Tutti i cambiamenti rilevanti a questo progetto sono documentati in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce al [Versionamento Semantico](https://semver.org/lang/it/).

## [Unreleased]

## [0.3.0] - 2026-06-23

### Aggiunto
- **Reinvio con mapping aggiornato**: il pulsante "Reinvia" nel tab Invii ricostruisce ora il payload partendo dai dati CF7 originali (colonna `cf7_data`) applicando la mappatura **correntemente salvata**, anziché riusare il payload storico. Genera sempre un nuovo `externalid`.
- **Raccolta multi-campo** (`__collect:`): nuovo tipo di sorgente nel tab Mapping per i campi `array_int`. Selezionando "Raccolta multi-campo" appare un multi-select con tutti i campi CF7; i campi selezionati vengono raccolti, i vuoti ignorati, e il risultato inviato come array di interi (es. `[8, 5]` per le età bambini). Usato per `eta1`, `eta2`, … → `rooms.0.occupants.children`.
- **Camera 2**: schema API esteso con `rooms.1.category`, `rooms.1.occupants.adults`, `rooms.1.occupants.children` (gruppo "Camera 2") per il mapping della seconda piazzola (`piazzola2`, `adulti2`, `eta1 2`, …).
- `Repository::update_payload()`: nuovo metodo per aggiornare `externalid` e `payload` nel DB al reinvio.
- `FieldMapper`: nuovo prefisso `__collect:`, metodo `collect_values()`, skip automatico di array vuoti nel payload.

### Modificato
- `ApiSchema`: gruppo "Camera" rinominato "Camera 1"; aggiornate le note per i campi `children` delle due camere.

## [0.2.4] - 2026-06-23

### Corretto
- `ApiResponse`: rimosso `readonly class` (PHP 8.2+) e sostituito con `readonly` sulle singole proprietà del costruttore (PHP 8.1 compatibile). Il plugin dichiara `Requires PHP: 8.1` e il server di produzione causava un `PHP Parse error` al caricamento.

## [0.2.3] - 2026-06-23

### Corretto
- Avviso WordPress 6.7+ "Translation loading triggered too early": `load_plugin_textdomain()` spostato sull'hook `init`; le label dei tab di `AdminMenu` ora vengono inizializzate in modo lazy (al primo accesso dopo `init`) invece che nel costruttore.

## [0.2.2] - 2026-06-23

### Aggiunto
- Tab Invii: barra di meta-info nel dettaglio inline (codice HTTP, numero di tentativi, data e ora dell'ultimo invio).
- Tab Invii: la colonna "Risposta API" è ora sempre visibile nel dettaglio, anche quando vuota (mostra `—`).

### Corretto
- `Sender`: quando la chiamata API fallisce per errore di trasporto WP (timeout, DNS, SSL), il messaggio di errore viene ora serializzato come JSON e salvato nella colonna `response`, rendendolo visibile nel log invece di risultare vuoto.

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

[Unreleased]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.4...v0.3.0
[0.2.4]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/mavidasnc/wp-alpinebits-reservation/releases/tag/v0.1.0
