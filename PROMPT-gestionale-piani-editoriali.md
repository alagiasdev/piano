# Progetto "Piano" — mini gestionale per piani editoriali

## Contesto

Sono un web developer e consulente di digital marketing. Gestisco i piani editoriali social (Instagram, Facebook, LinkedIn, TikTok, YouTube, newsletter) di diversi clienti. Oggi ogni piano è un file HTML/PDF e l'approvazione avviene via email o WhatsApp: non ho una vista d'insieme e il flusso di approvazione è manuale.

Voglio un'applicazione web semplice, ad uso mio (un solo utente amministratore), con link pubblici per far approvare i post ai clienti senza login. Deve stare su un sottodominio di un VPS cPanel (AlmaLinux, PHP 8.4, MySQL/MariaDB), senza build step obbligatori e senza dipendenze pesanti.

## Stack e vincoli tecnici

- PHP 8.4, senza framework. Struttura MVC leggera scritta a mano: front controller `public/index.php`, router minimale, controller, modelli su PDO, viste PHP con layout.
- MySQL/MariaDB via PDO con prepared statement, sempre. Migrazioni come file SQL numerati in `database/migrations/`.
- Composer solo per autoload PSR-4 (`App\`) e, se serve, per `dompdf` (export PDF). Nient'altro.
- Frontend: CSS custom in un unico file (niente Tailwind, niente Bootstrap), JS vanilla senza bundler. Font di sistema. Design pulito, bianco, un solo colore accento (#2447D6), tabelle leggibili, responsive fino a mobile.
- Configurazione in `.env` (host db, credenziali, URL base, SMTP, password admin). Includi `.env.example`.
- Sicurezza: password admin con `password_hash`, sessione con rigenerazione id, token CSRF su tutti i form POST, escaping sistematico dell'output con una funzione `e()`, token pubblici casuali da 32+ caratteri, rate limit elementare sul login.
- Timezone `Europe/Rome`, lingua dell'interfaccia italiano.
- Deve funzionare dentro cPanel: document root su `public/`, nessuna estensione PHP esotica, `.htaccess` con rewrite verso `index.php`.

## Modello dati

```sql
clienti
  id, nome, slug, logo_path NULL, contatto_nome, contatto_email,
  canali JSON (es. ["ig","fb","li"]), tono_di_voce TEXT NULL,
  note TEXT NULL, attivo TINYINT default 1, created_at, updated_at

piani
  id, cliente_id FK, titolo (es. "Ottobre 2026"), data_inizio DATE, data_fine DATE,
  stato ENUM('bozza','inviato','approvato','chiuso') default 'bozza',
  nota_cliente TEXT NULL, token_pubblico VARCHAR(64) UNIQUE,
  inviato_il DATETIME NULL, created_at, updated_at

post
  id, piano_id FK, data DATE, ora TIME NULL,
  canali JSON, contenuto TEXT, formato VARCHAR(60) NULL, cta VARCHAR(120) NULL,
  pilastro VARCHAR(60) NULL, visual_url VARCHAR(500) NULL,
  stato ENUM('da_approvare','approvato','da_rivedere','pubblicato') default 'da_approvare',
  commento_cliente TEXT NULL, commento_il DATETIME NULL,
  ordine INT default 0, created_at, updated_at

impostazioni
  chiave VARCHAR(60) PK, valore TEXT
  (nome studio, email mittente, firma, testo standard della nota per il cliente)
```

Canali ammessi: `ig` Instagram, `fb` Facebook, `li` LinkedIn, `tt` TikTok, `yt` YouTube, `nl` Newsletter. Definiscili in un'unica costante/enum con etichetta e colore, riusata ovunque.

## Funzionalità — versione 1

### Area admin (login richiesto)

1. **Dashboard** `/`
   - Elenco clienti attivi. Per ciascuno: piano del periodo corrente (se esiste) con stato, conteggio post approvati/totali come barra di avanzamento, e link rapido "apri" / "crea piano".
   - Riquadro "Prossimi 7 giorni": post di tutti i clienti in ordine di data, con canali e stato. Evidenzia i post ancora `da_approvare` a meno di 3 giorni dalla pubblicazione.

2. **Clienti** `/clienti`
   - CRUD completo. Campi come da schema. Upload logo opzionale (jpg/png/svg, max 1 MB, salvato in `public/uploads/`).

3. **Piani** `/clienti/{id}/piani`, `/piani/{id}`
   - Crea piano scegliendo periodo (preset "mese" con selettore mese/anno, oppure date libere).
   - **Duplica piano**: copia tutti i post di un piano esistente sul nuovo periodo, spostando le date in avanti dello stesso numero di giorni o mesi, e resettando lo stato a `da_approvare`. È la funzione più usata: deve essere a un clic dalla lista piani.
   - Cambia stato del piano. Passare a `inviato` genera (se manca) il `token_pubblico` e mostra il link da copiare; opzionale: invia email al contatto del cliente con il link.

4. **Editor del piano** `/piani/{id}` — schermata principale, deve essere veloce da usare
   - Tabella dei post raggruppati per settimana (lunedì–domenica), colonne: giorno e ora, canali, contenuto, formato, CTA, stato, commento cliente (se presente), azioni.
   - Modifica **inline**: cliccando una cella si edita sul posto e si salva via `fetch` su endpoint JSON senza ricaricare la pagina. Canali con selettore a spunte multiple, stato con clic ciclico, data con input date.
   - Aggiungi post (in fondo alla settimana o con data libera), duplica post, elimina post con conferma, riordino tramite pulsanti su/giù dentro lo stesso giorno.
   - Barra in alto: titolo piano, periodo, stato, conteggio approvati, pulsanti "Anteprima cliente", "Copia link pubblico", "Esporta PDF", "Esporta ICS", "Duplica".
   - Vista alternativa **calendario mensile** (griglia 7 colonne) con i post come chip colorati per canale, per vedere giorni vuoti o troppo pieni. Solo lettura, con link al post nella vista tabella.

5. **Export**
   - **PDF** del piano con lo stesso layout pulito della vista pubblica (A4, colori dei canali, niente elementi di interfaccia). Usa `dompdf` se disponibile; altrimenti una pagina HTML print-friendly con `@media print` ben curato e pulsante stampa.
   - **ICS**: un evento per post (durata 30 min, titolo `[Canale] prima riga del contenuto`, descrizione completa), da importare in Google Calendar.

6. **Impostazioni** `/impostazioni`: nome studio, email mittente, firma, testo standard della nota per il cliente, cambio password.

### Area pubblica (nessun login)

7. **Pagina di approvazione** `/p/{token}`
   - Mostra il piano in sola lettura con il logo del cliente, il periodo, la nota standard, i post per settimana.
   - Per ogni post ancora `da_approvare` o `da_rivedere`: pulsanti **Approva** e **Chiedi modifica** (quest'ultimo apre un campo commento obbligatorio). Le azioni salvano via `fetch` e aggiornano la riga senza ricaricare.
   - Pulsante "Approva tutti i post in attesa" in alto, con conferma.
   - Quando il cliente approva o commenta, invia a me un'email di notifica (una sola email raggruppata se ci sono più azioni entro 10 minuti, se è semplice da fare; altrimenti una per azione).
   - Se tutti i post risultano approvati, il piano passa automaticamente a `approvato`.
   - Il token non scade ma posso rigenerarlo dall'admin, invalidando il vecchio link.

## Struttura cartelle attesa

```
piano/
  public/            (document root)
    index.php
    .htaccess
    assets/app.css, assets/app.js
    uploads/
  app/
    Core/            Router, Request, Response, View, Db, Session, Csrf, Auth, Mailer
    Controllers/     Dashboard, Clienti, Piani, Post (JSON), Pubblico, Export, Impostazioni, Auth
    Models/          Cliente, Piano, Post, Impostazione
    Views/           layout admin, layout pubblico, una cartella per sezione
    Support/         Canali (enum), helpers (e(), formattazione date italiane, ecc.)
  database/migrations/
  config/
  .env.example
  composer.json
  README.md
```

## Come procedere

1. Prima di scrivere codice, proponi in poche righe il piano di lavoro e conferma di aver capito il modello dati. Segnala subito se vedi problemi nello schema.
2. Procedi per passi verificabili, in quest'ordine: scheletro MVC + login + migrazioni → clienti → piani ed editor inline → pagina pubblica di approvazione → export → dashboard → impostazioni. Dopo ogni passo dimmi come testarlo.
3. Scrivi un `README.md` con: requisiti, installazione su cPanel (creazione db, import migrazioni, `.env`, document root), come creare l'utente admin, e un elenco degli endpoint.
4. Aggiungi uno script `database/seed.php` che crea un cliente di esempio con un piano di 4 settimane e una dozzina di post, per provare tutto subito.
5. Codice commentato dove serve, nomi in italiano per entità e campi coerenti con lo schema, nomi in inglese per le classi core.

Non aggiungere funzionalità oltre a quelle elencate. Se una cosa può essere fatta in modo più semplice, falla in modo più semplice e dimmelo.
