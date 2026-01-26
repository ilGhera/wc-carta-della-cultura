# Stato Sviluppo Plugin Carta della Cultura

## Modifiche Implementate

### 1. Sistema ISBN Multiplo
- **File**: `includes/class-wccdc-soap-client.php`
- **Modifiche**:
  - Nuovo metodo `get_isbn_list_from_order()`: raccoglie tutti gli ISBN dall'ordine
  - Nuovo metodo `get_isbn_from_product()`: helper per estrazione ISBN singolo prodotto
  - `insert_isbn()` aggiornato: invia array di ISBN con importi proporzionali
- **Logica**: Suddivide l'importo Confirm proporzionalmente tra i prodotti basandosi sul loro prezzo

### 2. WSDL Aggiornato
- **File**: `includes/VerificaVoucher.wsdl`
- **Modifiche**: Aggiunta operazione `InsertISBN` con struttura `ValidazioneRequest` e `DettaglioIsbnBean`

### 3. Flusso di Validazione
- **File**: `includes/class-wccdc-gateway.php`
- **Modifiche**: In `process_code()`, dopo `confirm()` di successo, chiama `insert_isbn()`
- **Sequenza**:
  1. `check()` → verifica voucher
  2. `confirm()` → conferma pagamento (importo = min(buono, totale ordine))
  3. `insert_isbn()` → invia ISBN multipli con importi proporzionali (somma = importo Confirm)

### 4. Sistema Configurazione ISBN
- **File**: `includes/class-wccdc-admin.php`
- **Modifiche**:
  - Interfaccia admin per selezione campo ISBN (meta/attributo)
  - Scansione automatica campi contenenti "ISBN"
  - Pulsante riesamina campi
  - Gestione campo manuale

### 5. Gestione Buono Inferiore all'Ordine
- **Logica attuale**:
  - Se buono < totale ordine: converte intero buono in coupon
  - Coupon applicato all'ordine, differenza pagata con altro metodo
  - Validazione buono (`confirm()` + `insert_isbn()`) avviene solo al completamento ordine via `process_coupon()`
  - Importo Confirm = valore buono (non totale ordine)

## Riflessioni / Da Verificare

### 1. Conformità Regolamento
- **Regola**: "somma degli importi deve essere uguale all'importo inserito nella Confirm"
- **Implementazione**: ✓ Rispettata (importi proporzionali sommano a importo Confirm)

### 2. Buono Inferiore all'Ordine
- **Scenario**: Buono €8, Ordine €10
- **Attuale**: Crea coupon €8 → ordine €2 con altro pagamento → validazione buono €8 al completamento
- **Conforme**: Sì, ma il buono viene utilizzato interamente (€8) non parzialmente

### 3. Edge Cases
- **Prodotti senza ISBN**: Attualmente se nessun ISBN trovato, `insert_isbn()` non invia lista. WSDL permette `listaISBN` opzionale, ma regolamento richiede ISBN.
- **ISBN non valido**: Server SOAP valida. Plugin invia ISBN pulito (solo numeri).

### 4. Test Necessari
- Ordine con multipli prodotti (tutti con ISBN)
- Ordine con mix prodotti (alcuni senza ISBN)
- Buono superiore/inferiore/uguale all'ordine
- Modalità sandbox con buoni test

## Prossimi Passi
1. Test end-to-end con buoni validi (sandbox)
2. Verifica risposta `InsertISBN` e gestione errori
3. Eventuale ottimizzazione calcolo proporzionale (arrotondamenti)
4. Documentazione utente finale per configurazione campo ISBN

## File Modificati
- `includes/class-wccdc-soap-client.php`
- `includes/class-wccdc-gateway.php`
- `includes/class-wccdc-admin.php`
- `includes/VerificaVoucher.wsdl`

## Note Tecniche
- Tutte le chiamate SOAP includono logging dettagliato (`error_log`)
- ISBN puliti: rimossi spazi, trattini, solo numeri
- Importi arrotondati a 2 decimali
