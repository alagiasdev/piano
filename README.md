# Piano — mini gestionale per piani editoriali

Applicazione web per gestire i piani editoriali social dei clienti e farli
approvare tramite un link pubblico, senza login per il cliente.

PHP senza framework, MVC scritto a mano, MySQL/MariaDB via PDO, un solo file
CSS e JS vanilla. Nessun build step, nessuna dipendenza obbligatoria.

## Requisiti

- PHP 8.2 o superiore (testato su 8.2 in locale, previsto 8.4 in produzione)
- Estensioni: `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL 5.7+ oppure MariaDB 10.3+
- Apache con `mod_rewrite`
- Composer **facoltativo**: senza `vendor/` l'app usa un autoloader PSR-4 interno

## Installazione in locale (XAMPP)

```bash
cp .env.example .env        # poi compila i dati del database

mysql -u root -e "CREATE DATABASE piano CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php database/migrate.php
php database/crea-admin.php tua@email.it "unapasswordlunga" "Nome Cognome"
php database/seed.php       # facoltativo: cliente e piano di esempio
```

L'app risponde su `http://localhost/crm_social1.0/public/`.
Su Windows gli eseguibili di XAMPP sono `C:\xampp\php\php.exe` e
`C:\xampp\mysql\bin\mysql.exe`.

## Installazione su cPanel

1. **Sottodominio**: crealo puntando il *document root* alla cartella `public/`
   del progetto. È l'unico passaggio davvero importante: tutto il resto del
   codice deve restare fuori dalla radice web.
2. **Database**: crea database e utente da *MySQL Databases*, assegna tutti i
   privilegi.
3. **File**: carica il progetto, poi copia `.env.example` in `.env` e compila
   `DB_*` e `APP_URL` (con `https://`, senza slash finale). `APP_URL` serve a
   costruire i link pubblici da mandare ai clienti: se è sbagliato, i link
   sono sbagliati.
4. **Migrazioni**: da *Terminal*, `php database/migrate.php`. Se il terminale
   non è disponibile, importa a mano da phpMyAdmin i file di
   `database/migrations/` in ordine numerico.
5. **Utente**: `php database/crea-admin.php ...` da Terminal, oppure inserisci
   a mano una riga in `utenti` con un hash generato da
   `php -r 'echo password_hash("...", PASSWORD_DEFAULT);'`.
6. **Permessi**: `public/uploads/` deve essere scrivibile dal processo PHP.
7. **Composer** (facoltativo): `composer install --no-dev` abilita l'autoloader
   ottimizzato e, se lo aggiungi, `dompdf` per l'export PDF lato server.
8. **Verifica**: `php database/verifica.php`. Se il Terminal non c'è, entra
   come amministratore e apri **Impostazioni → Verifica installazione**.

### Senza Terminal

Su parecchi piani cPanel non c'è né SSH né Terminal, quindi i passi 4 e 5
non si possono eseguire. In quel caso metti nel `.env` una riga
`SETUP_TOKEN` con una stringa lunga a caso e apri **`/installazione`**:
una pagina che applica le migrazioni e crea il primo amministratore dal
browser.

È chiusa da due lati insieme: esiste solo finché non c'è nessun account
(dopo risponde 404 per sempre) e chiede il valore di `SETUP_TOKEN`, che
conosce solo chi ha accesso ai file del server. Il secondo controllo copre
la finestra fra il primo deploy e la creazione dell'account, che
altrimenti sarebbe aperta a chiunque conoscesse l'indirizzo. A
installazione fatta puoi togliere la riga dal `.env`.

## Verifica dell'installazione

```bash
php database/verifica.php     # esce con codice 1 se trova un errore
```

Stessi controlli da browser su `/verifica`, riservati agli amministratori:
serve sugli hosting dove il Terminal non è disponibile.

Controlla versione PHP ed estensioni, `.env` e `APP_URL`, fuso orario,
connessione al database e charset, migrazioni in sospeso, presenza di almeno
un amministratore attivo, scrivibilità di `public/uploads/` e della cartella
delle sessioni, spazio su disco.

Due controlli valgono da soli il resto:

- **Rewrite e raggiungibilità** — il server chiede a se stesso `APP_URL/login`.
  Un 404 significa che `mod_rewrite` è spento o che il `.htaccess` in `public/`
  non viene letto (serve `AllowOverride All`).
- **File riservati** — prova a scaricare `.env` e `app/bootstrap.php` dal web.
  Se ci riesce, il *document root* punta alla cartella del progetto invece che
  a `public/`, e le credenziali del database sono pubbliche. È l'errore di
  installazione più grave e il più facile da non accorgersene.

Poiché esce con codice 1 in caso di errore, si può incatenare a un comando di
deploy: `php database/migrate.php && php database/verifica.php`.

## Struttura

```
public/          document root: index.php, .htaccess, assets/, uploads/
app/
  Core/          Router, Request, Response, View, Db, Session, Csrf, Auth,
                 Config, Controller
  Controllers/   Auth, Dashboard, Clienti, Piani, Post (JSON), Pubblico,
                 Export, Media, Utenti, Impostazioni, Verifica,
                 Installazione
  Models/        Utente, Cliente, Piano, Post, Media, Impostazione
  Views/         layout admin / pubblico / stampa, una cartella per sezione
  Support/       Canali, Stati, Fasi, Elenchi, Periodo, Upload, Ics,
                 DatiPiano, Diagnostica, Migratore, helpers
database/
  migrations/    file SQL numerati
  migrate.php    applica le migrazioni non ancora eseguite
  crea-admin.php crea o aggiorna un utente
  seed.php       cliente di esempio con un piano di 4 settimane e 12 post
  verifica.php   controlli di installazione (stessi di /verifica)
```

## Endpoint

### Area admin (login richiesto)

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | `/login` · POST `/login` · POST `/logout` | Accesso |
| GET | `/` | Dashboard: piani del periodo, prossimi 7 giorni, novità dai clienti |
| GET | `/clienti` | Elenco clienti |
| GET | `/clienti/nuovo` · POST `/clienti` | Nuovo cliente |
| GET | `/clienti/{id}/modifica` · POST `/clienti/{id}` | Modifica cliente |
| POST | `/clienti/{id}/elimina` | Elimina cliente (con i suoi piani) |
| GET | `/clienti/{id}/piani` | Piani di un cliente |
| GET | `/piani` | Tutti i piani |
| GET | `/piani/nuovo` · POST `/piani` | Nuovo piano (mese intero o date libere) |
| GET | `/piani/{id}` | **Editor del piano** — la schermata principale |
| GET | `/piani/{id}/calendario` | Vista calendario mensile, sola lettura |
| GET | `/piani/{id}/esecutivi` | **Schermata degli esecutivi** — copy finale e immagini |
| POST | `/piani/{id}` | Titolo, periodo, nota per il cliente |
| POST | `/piani/{id}/stato` | Cambio stato (passando a «inviato» genera il token) |
| POST | `/piani/{id}/fase` | Passa fra concept ed esecutivi |
| POST | `/piani/{id}/token` | Rigenera il link pubblico |
| POST | `/piani/{id}/duplica` | Duplica su un nuovo periodo |
| POST | `/piani/{id}/elimina` | Elimina il piano |
| GET | `/piani/{id}/anteprima` | Anteprima di quello che vede il cliente |
| GET | `/piani/{id}/pdf` | Export PDF (o pagina print-friendly) |
| GET | `/piani/{id}/ics` | Export ICS per Google Calendar |
| GET | `/impostazioni` | Impostazioni e cambio password |
| POST | `/impostazioni` | Nome studio, firma, nota standard *(amministratori)* |
| POST | `/impostazioni/password` | Cambio della propria password |
| GET | `/utenti` · POST `/utenti` | Collaboratori: elenco e nuovo *(amministratori)* |
| GET | `/utenti/{id}` · POST `/utenti/{id}` | Modifica di un collaboratore *(amministratori)* |
| POST | `/utenti/{id}/elimina` | Elimina un account *(amministratori)* |
| GET | `/verifica` | Verifica dell'installazione *(amministratori)* |

### Endpoint JSON dell'editor (login + header `X-CSRF-Token`)

| Metodo | Percorso | Corpo |
|---|---|---|
| POST | `/piani/{id}/post` | `{data, canali}` — nuovo post |
| POST | `/post/{id}` | `{campo, valore}` — salvataggio inline di un campo |
| POST | `/post/{id}/duplica` | — |
| POST | `/post/{id}/elimina` | — |
| POST | `/post/{id}/sposta` | `{direzione: "su"\|"giu"}` |
| POST | `/post/{id}/media` | multipart `immagini[]` — carica una o più immagini |
| POST | `/media/{id}/elimina` | — |
| POST | `/media/{id}/sposta` | `{direzione: "su"\|"giu"}` |

Campi modificabili inline: `data`, `ora`, `canali`, `contenuto`, `formato`,
`cta`, `pilastro`, `visual_url`, `stato`, `copy_finale`, `stato_esecutivo`.
Qualunque altro nome viene rifiutato.

### Area pubblica (nessun login, autorizzata dal token)

| Metodo | Percorso | Descrizione |
|---|---|---|
| GET | `/p/{token}` | Pagina di approvazione del cliente |
| POST | `/p/{token}/post/{id}/approva` | Approva un post |
| POST | `/p/{token}/post/{id}/modifica` | `{commento}` — chiedi una modifica |
| POST | `/p/{token}/approva-tutti` | Approva tutti i post in attesa |

Il token non scade; rigenerandolo dall'admin il link precedente smette di
funzionare. Ogni azione verifica che il post appartenga al piano di quel token.

## Le due fasi di un piano

Un piano si fa approvare due volte, e sono due cose diverse.

**Concept** — il cliente approva le idee: la riga di descrizione di ogni post,
il formato, la call to action. È la fase in cui si decide *cosa* si pubblica.

**Esecutivi** — caricati il copy finale e le immagini, il cliente approva il
post **come lo vedrà pubblicato**: avatar, foto, caption con a capo, emoji e
hashtag. È la fase in cui si decide *com'è fatto*.

Si passa da una all'altra da **Impostazioni del piano → Fase**, e si può
tornare indietro senza perdere niente: le due fasi scrivono su colonne
distinte (`stato` e `stato_esecutivo`, con i rispettivi commenti e date), quindi
l'ok sul concept resta anche mentre si discute di una foto.

**La pagina che vede il cliente, in fase esecutivi**, è impaginata a colonna
singola larga 520 px: si scorre con lo stesso gesto con cui si guarderà il
contenuto una volta online, ed è il motivo per cui la foto è grande.

Un post che non ha ancora né copy né immagini **non occupa una scheda**:
finisce in una riga sottile in fondo alla settimana, marcata «in preparazione».
Prima era un riquadro vuoto alto quanto una foto, e su dodici post produceva una
pagina di 7.500 px lunga soprattutto di niente; ora ne bastano 2.300.

Quei post **non sono approvabili**: né singolarmente né con «approva i
rimanenti», che li salta. Approvare qualcosa che il cliente non ha visto non
significherebbe niente, e toglierebbe valore alla traccia scritta che è il motivo
per cui questa pagina esiste. Il rifiuto è anche lato server, non solo un
pulsante nascosto. In cima una barra dice «X di Y approvati» con l'avanzamento, e
quanti sono ancora in preparazione.

Il lavoro sugli esecutivi si fa su una schermata sua, `/piani/{id}/esecutivi`: una
scheda per post con il concept approvato sotto gli occhi, il copy finale che si
salva da solo e le immagini (fino a 10, per i caroselli) con riordino ed
eliminazione. Il cliente, sul solito link pubblico, vede l'anteprima del post.

Il duplicato di un piano **non** porta con sé copy e immagini e riparte dal
concept: un mese nuovo vuole esecutivi nuovi.

**I video non si caricano.** Su cPanel non c'è modo di generarne l'anteprima e
pesano troppo per un hosting condiviso: per quelli si usa il campo `visual_url`
del concept con un link a Drive o YouTube. Le immagini sono JPG, PNG, WebP o
GIF fino a 5 MB, salvate in `public/uploads/post/{id}/`.

Eliminando un post, un piano o un cliente i file delle immagini vengono tolti
dal disco: le foreign key portano via le righe, non i file, quindi la pulizia è
esplicita nei modelli.

## Densità della riga nell'editor

L'editor è una tabella che si scorre tutto il giorno, quindi quanti post
stanno in una schermata conta. Misurando l'ingombro reale di ogni cella
sono venute fuori due cose che a occhio non si vedevano:

- **giorno e ora erano impilati** su due righe e tenevano alta tutta la
  tabella; ora stanno sulla stessa riga (da ~50 a 29 px di ingombro);
- **i campi secondari vuoti** (visual, pilastro) erano a opacità zero ma
  restavano nel flusso: 22 px sprecati su **ogni** riga. Ora non occupano
  spazio e si mostrano con il pulsante `⋯` della riga — non al passaggio
  del mouse, altrimenti la tabella ballerebbe mentre la si scorre.

Il risultato: pagina da 1645 a 1524 px con dodici post, e altezza minima
di riga da 64 a 43 px. Quel che resta è dettato da contenuto vero.

**`+ Post` chiede in quale giorno.** Prima ogni post nasceva sul lunedì
della settimana e la data andava corretta subito dopo: due passaggi in
più per ognuno dei dodici post di un piano, sempre.

## Scelte rapide di formato e call to action

I campi «Formato» e «Call to action» sono caselle di testo *e* elenco
insieme. Tenerli separati era contraddittorio: il campo diceva «scrivi
quello che vuoi» e l'elenco «scegli fra questi», e mentre si scriveva a
mano restava lì a proporre voci che non c'entravano niente.

Ora si danno ragione a vicenda:

- entrare nel campo apre l'elenco **intero**, con la voce già salvata
  segnata come scelta;
- da quando si digita, quello che si scrive **filtra** l'elenco, senza
  badare a maiuscole e accenti e cercando ovunque nella parola;
- se non corrisponde nessuna voce il pannello lo dice — *«Non è in
  elenco. Resta …»* — invece di restare muto o fuorviante;
- frecce ↑ ↓ per scorrere, Invio per prendere la voce evidenziata, Esc
  per chiudere l'elenco tenendo il testo scritto (un secondo Esc annulla
  la modifica, come in ogni altro campo);
- la freccetta a destra mostra sempre l'elenco intero, anche a campo
  pieno: serve proprio a vedere le voci diverse da quella scritta.

Non sono un insieme chiuso come i canali, e i campi restano a scrittura
libera: il giorno che serve «Carosello 5 slide» lo si scrive e basta.
L'elenco si personalizza da **Impostazioni → Scelte rapide dell'editor**,
una voce per riga; svuotando la casella si torna alle voci di partenza.

## Andare agli esecutivi e tornare indietro

Le due fasi si cambiano dalla tendina **Fase** in «Impostazioni del
piano», ma quella strada da sola non bastava: l'andata aveva un avviso
in chiaro sulla pagina degli esecutivi, il ritorno stava chiuso dentro
un pannello a fisarmonica. Si entrava da una porta e si usciva da una
botola.

Ora, **quando e solo quando** il piano è in fase esecutivi, la riga
sotto il titolo lo dice — «Fase esecutivi» — e accanto c'è **Torna al
concept**. In fase concept non compare niente: sarebbe rumore, perché
è lo stato normale e il cliente vede già quello che c'è in pagina.

Tornare indietro **non perde niente**: le due fasi hanno colonne
separate (`stato` / `stato_esecutivo`, `commento_cliente` /
`commento_esecutivo`, e così via — la mappa sta in `App\Support\Fasi`),
quindi stati, commenti e approvazioni del concept restano dove sono, e
copy e immagini degli esecutivi pure. Si può fare avanti e indietro
quante volte serve.

## La testata del piano

In chiaro restano le tre destinazioni che si aprono ogni giorno —
**Calendario**, **Esecutivi**, **Anteprima cliente** — e le altre
quattro stanno dietro un «…»: Copia link pubblico, PDF, ICS, Duplica.

Prima erano sette pulsanti in fila con le stesse identiche sembianze:
nessuno spiccava e sugli schermi stretti la riga andava a capo.

Il menù sta in `app.js` e non in `editor.js` perché la testata è fuori
da `#editor`, e perché un menù così può servire in qualunque pagina:
basta un `.menu-azioni` con dentro un `.apri-menu` e un `.menu-pop`.
Si chiude da solo al clic fuori, con Esc, scorrendo e ridimensionando —
come tutti gli altri pannelli. L'unica eccezione è «Copia link
pubblico», che lo lascia aperto: scrive «Copiato» su se stesso per un
secondo e mezzo, e chiudendo subito quella conferma non si vedrebbe mai.

## L'anteprima non ritaglia le immagini

Il riquadro dell'anteprima cliente imponeva una forma — quadrata, o
9/16 per storie e reel — e ci faceva entrare l'esecutivo ritagliandolo.
Quando il file non aveva già quella proporzione il cliente ne vedeva
metà: di una grafica 4:5 dichiarata «Reel» sparivano i due lati, logo
compreso. E il ritaglio non era nemmeno quello vero del social: era
una nostra invenzione.

Ora la grafica si vede **intera, sempre**: sia nell'anteprima cliente sia
nelle miniature interne. Chi approva deve vedere il file che gli abbiamo
mandato, non una sua fetta.

La cornice però continua a seguire la tipologia di post — un reel si vede
alto e stretto come un reel — e quando la grafica non la riempie restano
delle bande. Le bande *sono* il messaggio: dicono che il file non ha la
forma giusta per dove andrà. Proprio per questo devono comparire solo
quando è vero, altrimenti diventano rumore. La regola sta in
`App\Support\Formati` e non è «un formato, una proporzione»:

- **storie e reel** occupano lo schermo intero, e lì 9:16 è esatto:
  qualunque altra forma è un errore e le bande ci vogliono;
- **nel feed** non esiste una forma giusta sola. Instagram pubblica senza
  toccare niente tutto quello che sta fra 4:5 e 1.91:1, quindi dentro
  quell'intervallo la cornice segue la grafica e non c'è nessuna banda.
  Fuori — una 9:16 dichiarata «Foto singola», una panoramica troppo larga
  — la cornice è 4:5 e le bande dicono che qualcosa non torna;
- i formati che **non c'entrano con le immagini** (Diretta, Sondaggio) e
  quelli **inventati dallo studio** non impongono niente: comanda la
  grafica.

I formati sono testo libero, quindi si riconoscono per parola contenuta e
non per uguaglianza: «Reel», «reel 30s» e «Reel + storia» sono tutti
verticali.

Siccome così una grafica della forma sbagliata non salta più all'occhio,
ogni miniatura in **Esecutivi** porta la misura vera del file
(`2161×2700 · 4:5`). Nessun giudizio: il dato, accanto al formato
dichiarato, così la differenza si nota prima di mandarlo al cliente.

## Due persone sullo stesso piano

L'editor salva un campo alla volta, quindi due collaboratori sullo stesso
piano si pestavano i piedi in silenzio: vinceva chi salvava per ultimo e
l'altro non lo sapeva.

Ora ogni salvataggio inline dichiara al server **il valore che credeva di
avere sotto** (`atteso` nel corpo JSON). Se in database c'è qualcos'altro, il
server risponde **409** invece di scrivere, e restituisce il valore vero
insieme al nome di chi l'ha messo — da qui la colonna `post.modificato_da`.
Il client mostra i due valori affiancati e fa scegliere: *tieni la sua*
oppure *tieni la mia*. Scegliendo la propria si risalva con il valore
dell'altro come atteso, quindi la seconda volta passa.

Il controllo è **per campo, non per post**: due persone che lavorano sullo
stesso post ma su colonne diverse non si disturbano. Il confronto normalizza
le forme in cui lo stesso dato si scrive da una parte e dall'altra — i canali
sono JSON in database e array nel client, l'ora è `18:30:00` contro `18:30`,
il vuoto è `NULL` contro stringa vuota — altrimenti darebbe falsi allarmi a
ogni salvataggio.

Chi salva senza mandare `atteso` scrive e basta: serve a non rompere gli altri
punti che usano lo stesso endpoint.

## Accessi e livelli

Ogni persona ha il suo account: le password non si condividono e si può
togliere l'accesso a una sola persona. I livelli sono due, senza sfumature.

| | Amministratore | Collaboratore |
|---|---|---|
| Clienti, piani, post, export | sì | sì |
| Eliminare clienti e piani | sì | no |
| Impostazioni dello studio | sì | no |
| Gestire i collaboratori | sì | no |
| Cambiare la propria password | sì | sì |

Le eliminazioni sono riservate perché cancellano a cascata: un cliente porta
via tutti i suoi piani e post, senza recupero. Chi non collabora più va
**disattivato**, non eliminato: l'accesso si chiude subito e i dati restano.

I permessi sono controllati sul server, non solo nascondendo i pulsanti: una
richiesta costruita a mano riceve comunque 403.

Sul proprio account non si possono togliere i permessi, né disattivarsi, né
eliminarsi. È l'unica regola che serve a garantire che resti sempre almeno un
amministratore attivo: per modificare un altro utente bisogna essere
amministratori attivi, quindi togliendo i permessi a un collega ne resta
comunque uno, se stessi. Se il database venisse manomesso a mano si recupera
con `php database/crea-admin.php`, che crea o ripromuove un amministratore.

## Sicurezza

- Password con `password_hash`, sessione con id rigenerato al login e al cambio password
- Token CSRF su tutti i POST, form e chiamate `fetch` (header `X-CSRF-Token`)
- Ogni output nelle viste passa da `e()`
- Query sempre con prepared statement; i nomi di colonna modificabili vengono
  da una whitelist, non dall'input
- Rate limit sul login per indirizzo IP: 5 tentativi, poi 15 minuti di blocco
- Upload: tipo verificato dal contenuto (non dall'estensione), massimo 1 MB,
  SVG con script rifiutati, cartella non eseguibile (`public/uploads/.htaccess`)
- Token pubblici da 64 caratteri esadecimali generati con `random_bytes`

## Scelte prese rispetto alla specifica iniziale

- **Utenti su tabella invece che password nel `.env`.** La specifica prevedeva
  un solo admin con la password nel `.env`, ma anche il cambio password dalle
  impostazioni: un'app non dovrebbe riscrivere il proprio `.env`. La tabella
  `utenti` risolve entrambe le cose e permette un account per collaboratore,
  gestibile dalla schermata «Collaboratori». `crea-admin.php` resta per il
  primo account e per il recupero.
- **Due livelli, non un sistema di ruoli.** Con più persone che entrano, il
  rischio concreto non è il sabotaggio ma l'errore: un clic sbagliato che
  elimina un cliente con tutti i suoi piani. Un flag `amministratore` copre
  quel rischio con una colonna e quattro controlli; permessi per singola
  funzione sarebbero sproporzionati per un gruppo di poche persone.
- **Niente email.** Il flusso reale prevede una telefonata al cliente e il
  controllo delle approvazioni entrando in piattaforma. Al loro posto, la
  dashboard ha il riquadro «Novità dai clienti», che elenca approvazioni e
  richieste di modifica recenti. *Sviluppo futuro:* una email al momento di
  «Approva tutti» e una per ogni «Chiedi modifica», spedite nella stessa
  richiesta HTTP, senza code né cron.
- **`post.approvato_il` aggiunto** allo schema: senza, non si sa quando il
  cliente ha approvato. Registra solo le approvazioni del cliente, non quelle
  fatte dall'admin nell'editor, e viene azzerato se il post esce da «approvato».
- **`post.ordine` è per giorno**, non per piano: il riordino avviene fra i post
  della stessa data.
- **Duplicazione: default a settimane.** Spostare di *mesi* rompe i giorni
  della settimana su cui è costruito un piano editoriale (martedì e giovedì
  diventano venerdì e domenica), e il 31 gennaio + 1 mese non esiste. Il
  default è quindi «sposta di 4 settimane», che conserva i giorni; la modalità
  a mesi resta disponibile e riporta il giorno all'ultimo valido del mese.
  Il titolo suggerito è il mese in cui cade il centro del nuovo periodo.
- **Il piano passa ad «approvato» da solo** solo se era «inviato», e i post
  già `pubblicato` contano come approvati.
- **Riordino su/giù solo dentro lo stesso giorno**, come da specifica: fra
  giorni diversi si cambia la data. I due pulsanti compaiono quindi solo nei
  giorni che hanno più di un post, e sono disattivati agli estremi del
  gruppo: altrimenti sarebbero due comandi che nella maggior parte delle
  righe non fanno nulla. Una linea più marcata separa un giorno dal
  successivo, così si vede dov'è il gruppo su cui il riordino agisce.
- **Giorno e ora sono testo, non campi sempre aperti.** Tre controlli nativi
  impilati in una cella rendevano ogni riga alta il triplo del necessario e
  il campo data usciva tagliato. Ora si legge «Gio 3 / 18:30» e il campo
  compare al clic (Esc annulla). Le righe sono passate da ~110 px a ~69 px.
- **Mobile: sotto i 760 px ogni tabella diventa una scheda per riga.** Le
  intestazioni di colonna spariscono, quindi il nome del campo arriva
  dall'attributo `data-etichetta` sulla cella e viene stampato dal CSS: senza,
  i valori impilati sarebbero indistinguibili. Il menu di navigazione scorre
  in orizzontale su una riga sola, i campi partono da 16 px (così iOS non
  zooma al fuoco) e i comandi di riga sono bersagli da 44×40 px. Nel
  calendario la griglia diventa un'agenda dei soli giorni che hanno post.
- **I campi secondari vuoti (visual, pilastro) restano invisibili** e
  compaiono passando sulla riga: dodici segnaposto «incolla un link» erano
  solo rumore. Su schermi senza mouse sono sempre visibili.
- **Le modifiche strutturali dell'editor ricaricano la pagina** (aggiunta,
  duplicazione, eliminazione, riordino, cambio data): il server risponde
  `{"ricarica": true}`. I salvataggi dei campi non ricaricano nulla.
  Era il modo più semplice per tenere corretti i raggruppamenti per settimana.
- **PDF: pagina print-friendly come strada principale.** Con `dompdf`
  installato viene prodotto un vero PDF; senza, si apre la vista cliente con
  gli stili `@media print` e la finestra di stampa, dove «Salva come PDF» dà
  lo stesso documento. Il layout è identico nei due casi perché è la stessa
  vista.
- **Non c'è `email_mittente` nelle impostazioni**: senza invio email non
  servirebbe a niente.
