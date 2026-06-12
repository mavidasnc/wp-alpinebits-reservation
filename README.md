# WP AlpineBits Reservation

Plugin WordPress che intercetta l'invio di moduli Contact Form 7 e li invia all'endpoint `sendReservation` dell'[API AlpineBits Gateway](https://alpinebits-gateway.ando.cloud/apidocs/).

## Requisiti

- WordPress 6.5+
- PHP 8.1+
- Contact Form 7 6.x installato e attivo
- [Composer](https://getcomposer.org/) (per installare le dipendenze)

## Installazione

```bash
# Clona il repository nella cartella plugins di WordPress
cd wp-content/plugins/
git clone https://github.com/mavidasnc/wp-alpinebits-reservation.git

# Installa le dipendenze
cd wp-alpinebits-reservation
composer install --no-dev
```

Poi attiva il plugin dalla bacheca WordPress.

## Configurazione

### Override credenziali via wp-config.php (opzionale)

Per ambienti con gestione secrets centralizzata, è possibile definire le credenziali in `wp-config.php` anziché salvarle nel DB. Le costanti hanno precedenza sulle opzioni:

```php
define( 'WPAR_API_USERNAME', 'il-tuo-username' );
define( 'WPAR_API_PASSWORD', 'la-tua-password' );
define( 'WPAR_API_BASE_URL', 'https://alpinebits-gateway.ando.cloud/api/v1' );
define( 'WPAR_GITHUB_OWNER', 'mavidasnc' );
define( 'WPAR_GITHUB_REPO',  'wp-alpinebits-reservation' );
```

### Pannello di amministrazione

Il plugin aggiunge la voce **AlpineBits** nel menu laterale della bacheca WordPress, con 4 tab:

1. **Connessione** — Credenziali API (cifrate nel DB), status default, pulsante di test connessione.
2. **Moduli** — Selezione dei form CF7 da agganciare all'integrazione.
3. **Mapping** — Per ogni form abilitato: associazione tra i campi dell'API (a destra) e i campi del form CF7 (a sinistra). Ogni campo API può essere mappato a un campo del form oppure a un valore costante fisso.
4. **Invii** — Lista di tutte le submission con stato (pending/success/error), payload inviato, risposta dell'API e pulsante per reinviare in caso di errore.

## Limitazioni v0.1.0

- La GUI di mapping gestisce **una sola camera** per prenotazione (`rooms.0.*`). Il supporto multi-camera è previsto in una versione futura.
- Il campo `children` (array di età intere) si inserisce come campo CF7 testuale con valori separati da virgola (es. `5,8`).
- Un errore dell'API AlpineBits **non blocca** l'invio del form CF7 all'utente finale; la submission è tracciata nel tab Invii per il reinvio manuale.

## Sviluppo

```bash
# Installazione dipendenze dev
composer install

# Lint del codice (PHPCS + WPCS)
composer lint

# Fix automatico
composer fix
```

## Aggiornamenti

Il plugin si aggiorna automaticamente tramite le [release GitHub](https://github.com/mavidasnc/wp-alpinebits-reservation/releases). Le notifiche di aggiornamento appaiono nella normale schermata Aggiornamenti di WordPress.

## Changelog

Vedi [CHANGELOG.md](CHANGELOG.md).
