# Piano — funzionalità realizzate e sviluppi da valutare

Questo file risponde a due domande che il `README.md` non copre: **cosa fa oggi
il gestionale**, visto da chi lo usa e non da chi lo scrive, e **cosa
varrebbe la pena aggiungere**, con un giudizio onesto su costo e beneficio.

Il `README.md` resta il riferimento tecnico: installazione, endpoint, decisioni
di implementazione e il perché di come sono fatte le cose.

Ultimo aggiornamento: **7 settembre 2026**, giorno in cui il prodotto è stato
dichiarato pronto per essere usato sui piani editoriali veri.

---

## 1. Cosa fa oggi

### Clienti

- Anagrafica: nome, logo, referente, canali social abitual­mente usati, tono di
  voce, note libere.
- Un cliente si disattiva invece di eliminarlo: l'eliminazione porta via a
  cascata tutti i suoi piani e post, senza recupero.
- **Prendere un cliente nuovo è cosa da amministratore.**

### Piani editoriali

- Un piano copre un periodo (un mese intero con un clic, oppure date libere) e
  contiene i post organizzati per settimana.
- **Editor a tabella con salvataggio automatico**: si scrive in una cella e si
  salva da solo, senza pulsanti. Ogni campo va per conto suo.
- Campi di un post: giorno e ora, canali, contenuto (l'idea), pilastro
  editoriale, formato, call to action, link al file grafico, stato.
- **Formato e call to action sono caselle di testo *e* elenco insieme**: si
  scrive e l'elenco filtra; se quello che si scrive non c'è, il pannello lo
  dice invece di proporre voci a caso. Gli elenchi si personalizzano dalle
  impostazioni.
- Post: si aggiungono a una settimana scegliendo il giorno, si duplicano, si
  riordinano dentro lo stesso giorno, si eliminano.
- Un piano si **duplica** su un nuovo periodo, spostando tutte le date.
- Vista **calendario** mensile in sola lettura.

### Le due fasi: concept ed esecutivi

- In fase **concept** il cliente approva le *idee*: la riga di descrizione di
  ogni post.
- In fase **esecutivi** approva il *post finito*: copy definitivo e immagini
  (fino a 10 per post), visti come appariranno pubblicati.
- Le due fasi hanno **stati, commenti e approvazioni separati**: si va avanti e
  indietro quante volte serve senza perdere niente.
- L'anteprima del post **non ritaglia mai** la grafica. La cornice segue la
  tipologia (un reel si vede alto e stretto come un reel) e, se il file non la
  riempie, restano delle bande: sono il segnale che la grafica non ha la forma
  giusta per dove andrà. Nel feed vale l'intervallo che Instagram accetta
  davvero (4:5 ↔ 1.91:1), così le bande compaiono solo quando c'è un problema
  vero.
- Ogni miniatura porta la **misura reale del file** (`2161×2700 · 4:5`),
  accanto al formato dichiarato.

### Approvazione da parte del cliente

- Il cliente riceve un **link pubblico**, senza account e senza password.
- Approva il singolo post, oppure tutti quelli rimasti in un colpo; oppure
  chiede una modifica scrivendo cosa cambiare.
- Il link si **rigenera** quando serve chiuderne uno vecchio.
- Pagina pensata per il telefono, che è dove i clienti approvano davvero.

### Collaboratori e permessi

- Due livelli: **amministratore** e **collaboratore**.
- **Ogni collaboratore vede solo i clienti che gli sono stati assegnati.** Non
  è un filtro sull'interfaccia: i piani e i post degli altri rispondono 403 e
  non compaiono nemmeno negli elenchi o nella dashboard.
- Chi non collabora più si **disattiva**, non si elimina.
- **Modifiche in contemporanea**: se due persone toccano lo stesso campo, il
  secondo non sovrascrive in silenzio — vede i due valori affiancati e sceglie.

### Export e amministrazione

- **PDF** del piano (o pagina pronta per la stampa) e **ICS** per Google
  Calendar.
- Dashboard con i piani in corso, i post dei prossimi 7 giorni e le ultime
  approvazioni o richieste di modifica dei clienti.
- **Verifica dell'installazione** in 15 controlli, da riga di comando o da
  browser, con il pulsante per applicare le migrazioni al database.

---

## 2. Cose assenti di proposito

Vale la pena scriverle, perché altrimenti fra sei mesi qualcuno (io compreso)
le riproporrà come se fossero dimenticanze.

| Cosa manca | Perché |
|---|---|
| **Notifiche email** | Il flusso vero prevede una telefonata al cliente: si sa già che risponde in poche ore, e si controlla in dashboard. Un'email in più sarebbe rumore. |
| **Login per il cliente** | Una password in più è un attrito in più su un'azione che deve costare due tocchi. Il link col token fa lo stesso lavoro. |
| **Framework, build step, dipendenze obbligatorie** | Deve girare su un cPanel qualunque e restare modificabile fra due anni senza ricostruire una toolchain. |
| **Caricamento di video** | Pesano troppo per uno spazio hosting condiviso: si mette un link a Drive o YouTube. |
| **Creare clienti da collaboratore** | Prendere un cliente è una decisione di chi gestisce lo studio, e con i clienti assegnati un collaboratore si troverebbe davanti un cliente che non può vedere. |

---

## 3. Sviluppi da valutare

In ordine di quanto **io** li consiglierei. Il criterio non è la difficoltà, è
quanto tempo fanno risparmiare rispetto a quanto costano da mantenere.

### Vale la pena, appena l'uso lo chiede

**1. Ricerca.** Oggi non esiste. Con tre clienti non serve; con quindici, e un
anno di piani alle spalle, «in che piano avevamo fatto quel post sulla
giornata mondiale?» diventa una domanda quotidiana e la risposta è aprire i
piani uno per uno. Costo basso: una casella in testa, una query, una pagina di
risultati.
*Quando: al secondo o terzo cliente con più di due piani chiusi.*

**2. Commenti interni sul post,** non visibili al cliente. Oggi l'unico posto
dove scrivere è il contenuto stesso, e quello lo legge il cliente. Serve dal
momento in cui un piano lo lavorano davvero in due.
*Costo basso. Quando: al primo piano lavorato da due persone.*

**3. «Cosa tocca a me».** La dashboard mostra i prossimi 7 giorni di tutti i
clienti visibili. Da quando i collaboratori hanno clienti assegnati, il pezzo
mancante è banale: un filtro «solo i miei» e l'ordinamento per urgenza.
*Costo molto basso, ora che l'ambito esiste già.*

**4. Storico delle modifiche di un post.** Oggi si sa solo **chi ha toccato per
ultimo** (`post.modificato_da`), che serve al pannello dei conflitti. Un
elenco «chi ha cambiato cosa e quando» diventa utile quando un cliente dice
«ma avevamo detto un'altra cosa».
*Costo medio: una tabella in più e un pannello. Da fare solo se la domanda si
presenta davvero.*

### Forse, ma non ancora

**5. Post ricorrenti.** «Ogni martedì una rubrica». Oggi si duplica a mano. La
duplicazione del piano intero copre già il caso più frequente (il mese
successivo), quindi il guadagno è minore di quanto sembri.
*Da riconsiderare se ci si accorge di duplicare a mano più volte a settimana.*

**6. Export CSV del piano.** Banale da fare. Serve solo se qualcuno chiede
davvero il piano in Excel: se non lo chiede nessuno, è una funzione in più da
mantenere per niente.

**7. Archivio dei contenuti riusabili.** Le ricorrenze annuali (giornate
mondiali, festività) tornano ogni anno. Un archivio da cui pescare avrebbe
senso, ma solo dopo aver visto **almeno un anno** di piani veri: prima non si
sa che cosa valga la pena archiviare.

### Non lo farei

**8. Pubblicazione automatica sui social.** Le API di Meta cambiano spesso,
richiedono revisione dell'app e token che scadono, e romperebbero il
gestionale a ogni cambio di regole. Per uno strumento che è al 90% interno il
rapporto fra manutenzione e beneficio è pessimo: meglio pubblicare a mano e
segnare lo stato.

**9. Metriche e risultati dei post.** È un altro prodotto, non un'aggiunta a
questo. I dati stanno già negli strumenti di Meta e LinkedIn, e replicarli qui
significherebbe tenerli aggiornati per sempre.

---

## 4. Cose che funzionano ma nessuno ha ancora messo alla prova

Non sono difetti noti: sono le parti verificate **in laboratorio e mai da
persone vere**. Se qualcosa si comporterà in modo strano al primo piano vero,
è molto probabile che sia una di queste tre.

1. **Il pannello dei conflitti** con due colleghi che scrivono davvero nello
   stesso momento sullo stesso campo. Provato simulando due sessioni, mai con
   due persone.
2. **L'approvazione del cliente dal telefono.** Il layout è stato misurato, ma
   un cliente vero che approva dal divano è un'altra cosa.
3. **Le bande dell'anteprima con venti grafiche vere.** Con una sola ho
   verificato che la regola è giusta; con venti si vedrà se l'intervallo
   4:5 ↔ 1.91:1 è tarato bene o se le bande compaiono quando non dovrebbero.

---

## 5. Come si lavora su questo progetto

Poche regole, tutte imparate sbagliando. I dettagli tecnici stanno nel
`README.md`; qui il criterio.

- **Misurare, non stimare.** Le decisioni sulla densità della tabella sono
  state prese sbagliate due volte perché fondate su una stima. Misurare anche
  il «prima», mettendo da parte le modifiche, non fidarsi del ricordo.
- **Verificare a più larghezze.** Una modifica che migliora a 1440px può
  peggiorare a 1024.
- **Mai `display:flex` su un `<td>`.** Esce dal layout di tabella e il bordo
  inferiore finisce disegnato all'altezza del contenuto: una linea grigia
  spezzata a metà riga. Usare un `div` interno.
- **`prove/permessi.php`** va rilanciata dopo ogni modifica ai controller: è
  l'unica garanzia che i clienti assegnati non abbiano una falla.
- Il deploy **non cancella**: un file tolto dal repository resta sul server.
  Vedi la nota in `.cpanel.yml`.
