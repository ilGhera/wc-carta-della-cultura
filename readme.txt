=== ilGhera Carta della Cultura for WooCommerce ===
Contributors: ghera74
Tags: woocommerce, carta della cultura, payment gateway, bonus cultura, checkout
Version: 1.0.0
Stable tag: 1.0.0
Requires at least: 5.0
Tested up to: 6.9
WC tested up to: 10
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Abilita in WooCommerce il pagamento con Carta della Cultura.

== Description ==

Il plugin consente di abilitare sul proprio store il pagamento con Carta della Cultura.
In fase di checkout, il buono inserito dall'utente verrà verificato per validità, credito disponibile e pertinenza in termini di tipologia di prodotto.


= Nore importanti =
Il plugin prevede l'invio di contenuti ad un servizio esterno, in particolare i dati relativi ai prodotti acquistati dall'utente come categoria d'appartenenza e prezzo.

= Indirizzo di destinazione =
[https://ws.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher](https://ws.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher)

= Maggiori informazioni sul servizio Carta della Cultura: =
[https://www.cartadellacultura.it/cartaculturaEsercente/#/login](https://www.cartadellacultura.it/cartaculturaEsercente/#/login)

= Informativa privacy del servizio: =
[https://www.cartadellacultura.it/cartaculturaEsercente/assets/docs/Infoprivacy_CDC_Esercenti.pdf](https://www.cartadellacultura.it/cartaculturaEsercente/assets/docs/Infoprivacy_CDC_Esercenti.pdf)


= Important notes =
This plugin sends data to an external service, like the categories and the prices of the products bought by the user.

= Service endpoint: =
[https://ws.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher](https://ws.cartadellacultura.it/WSUtilizzoVoucherCDCWEB/VerificaVoucher)

= Service informations: =
[https://www.cartadellacultura.it/cartaculturaEsercente/#/login](https://www.cartadellacultura.it/cartaculturaEsercente/#/login)

= Service privacy policy: =
[https://www.cartadellacultura.it/cartaculturaEsercente/assets/docs/Infoprivacy_CDC_Esercenti.pdf](https://www.cartadellacultura.it/cartaculturaEsercente/assets/docs/Infoprivacy_CDC_Esercenti.pdf)


= Funzionalità =

* Caricamento certificato (.pem)
* Impostazione categorie prodotti WooCommerce acquistabili
* Generazione richiesta certificato (.der) (Premium)
* Generazione certificato (.pem) (Premium)


== Installation ==

= Dalla Bacheca di Wordpress =

* Vai in  Plugin > Aggiungi nuovo.
* Cerca ilGhera Carta della Cultura for WooCommerce e scaricalo.
* Attiva ilGhera Carta della Cultura for Woocommerce dalla pagina dei Plugin.
* Una volta attivato, vai in <strong>WooCommerce/ Carta della Cultura for WC</strong> e imposta le tue preferenze.

= Da WordPress.org =

* Scarica ilGhera Carta della Cultura for WooCommerce
* Carica la cartella wc-carta-della-cultura su /wp-content/plugins/ utilizzando il tuo metodo preferito (ftp, sftp, scp, ecc...)
* Attiva ilGhera Carta della Cultura for WooCommerce dalla pagina dei Plugin.
* Una volta attivato, vai in <strong>WooCommerce/ Carta della Cultura for WC</strong> e imposta le tue preferenze.


== Changelog ==

= 1.0.0 =
Data di rilascio: 28 Gennaio, 2026

    * Corretto: Allineamento recupero ISBN tra validazione carrello e elaborazione ordine
    * Corretto: Controllo ISBN ora sempre attivo per conformità CdC
    * Corretto: Email area test aggiornata (cartadellacultura@sogei.it)
    * Migliorato: Anteprima controlli ISBN personalizzati per mostrare funzionalità premium
    * Rimosso: Log di debug non necessari

= 0.9.0 =
Data di rilascio: 14 Ottobre, 2025

    * Prima release.
