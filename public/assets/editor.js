/* Piano — editor inline del piano editoriale.
   Ogni modifica di un campo parte da qui e viene salvata via fetch, senza
   ricaricare. Le modifiche che cambiano la forma della tabella (aggiungi,
   duplica, elimina, riordina, cambio data) ricaricano la pagina: il server
   risponde con "ricarica": true e qui ci si limita a obbedire. */

(function () {
  'use strict';

  const editor = document.getElementById('editor');
  if (!editor) { return; }

  const dati = JSON.parse(document.getElementById('dati-editor').textContent);
  const CANALI = dati.canali;          // [{codice, etichetta}, ...]
  const CICLO = dati.ciclo;            // stati nell'ordine del clic ciclico
  const PIANO = editor.dataset.piano;
  const avviso = document.getElementById('salvataggio');

  // percorso() arriva da app.js e conosce la sottocartella dell'app.
  const urlPost = (id, coda) => window.percorso('post/' + id + (coda || ''));
  const urlNuovoPost = () => window.percorso('piani/' + PIANO + '/post');

  /* ------------------------------------------------------- indicatore -- */

  let timerAvviso;
  function mostra(testo, tipo) {
    if (!avviso) { return; }
    clearTimeout(timerAvviso);
    avviso.textContent = testo;
    avviso.className = 'salvataggio' + (tipo ? ' ' + tipo : '');
    avviso.hidden = false;
    if (tipo !== 'errore') {
      timerAvviso = setTimeout(function () { avviso.hidden = true; }, 1800);
    }
  }

  /* ------------------------------------------------------ salvataggio -- */

  /**
   * Salva un campo.
   *
   * In "extra" si può passare `atteso`: il valore che il campo aveva quando
   * lo si è aperto. Il server controlla che in database ci sia ancora quello
   * e, se nel frattempo ha scritto un collega, rifiuta invece di
   * sovrascriverlo in silenzio. `applica` serve a rimettere nel campo il
   * valore dell'altro se si decide di tenere il suo.
   */
  async function salva(id, campo, valore, extra) {
    extra = extra || {};

    const corpo = { campo: campo, valore: valore };
    if ('atteso' in extra) { corpo.atteso = extra.atteso; }

    try {
      const risposta = await window.api(urlPost(id), corpo);
      mostra('Salvato');
      if (extra.ricorda) { extra.ricorda(valore); }
      if (risposta.piano) { aggiornaConteggi(risposta.piano); }
      if (risposta.ricarica) { location.reload(); }
      return risposta;
    } catch (e) {
      if (e.risposta && e.risposta.conflitto) {
        mostraConflitto(id, campo, valore, e.risposta, extra);
        return null;
      }
      mostra(e.message, 'errore');
      return null;
    }
  }

  /** Il pannello del conflitto è in app.js: lo usa anche la pagina esecutivi. */
  function mostraConflitto(id, campo, mioValore, dati, extra) {
    const elemento = extra.elemento;
    const contenitore = elemento
      ? (elemento.closest('td') || elemento.closest('[data-post]'))
      : document.getElementById('post-' + id);

    if (!contenitore) { mostra(dati.errore, 'errore'); return; }

    window.mostraConflitto({
      contenitore: contenitore,
      mioValore: mioValore,
      dati: dati,
      applica: extra.applica,
      ricorda: extra.ricorda,
      avvisa: mostra,
      ancora: extra.elemento,
      riSalva: function (attesoNuovo) {
        salva(id, campo, mioValore, Object.assign({}, extra, { atteso: attesoNuovo }));
      },
    });
  }

  /** Azioni che cambiano la struttura: si ricarica per rifare i gruppi. */
  async function azione(url, corpo) {
    try {
      const risposta = await window.api(url, corpo || {});
      if (risposta.ricarica) { location.reload(); } else { mostra('Salvato'); }
    } catch (e) {
      mostra(e.message, 'errore');
    }
  }

  function aggiornaConteggi(piano) {
    const a = document.getElementById('conteggio-approvati');
    const t = document.getElementById('conteggio-totali');
    if (a) { a.textContent = piano.approvati; }
    if (t) { t.textContent = piano.totali; }
  }

  /* ------------------------------------------------- campi di testo -- */

  // Il valore all'ingresso, per salvare solo se e' cambiato davvero.
  let valoreIniziale = null;

  /*
   * Il valore che il server ci ha mandato, campo per campo, in data-server.
   *
   * Prima lo si fotografava al focusin, ma con i selettori nativi di data e
   * ora gli eventi di fuoco arrivano anche dopo che il valore e gia
   * cambiato: il valore atteso finiva per essere quello nuovo, e il
   * confronto scattava contro se stesso segnalando conflitti inesistenti.
   * data-server invece cambia solo quando il server conferma un
   * salvataggio, quindi e sempre quello vero.
   */
  const atteso = (el) => (el.dataset.server !== undefined ? el.dataset.server : undefined);
  const ricordaServer = (el) => (v) => { el.dataset.server = Array.isArray(v) ? v.join(',') : v; };

  editor.addEventListener('focusin', function (e) {
    if (e.target.isContentEditable) { valoreIniziale = e.target.textContent; }
  });

  editor.addEventListener('focusout', function (e) {
    const el = e.target;
    if (!el.isContentEditable || valoreIniziale === null) { return; }

    const valore = el.textContent.trim();
    const partenza = valoreIniziale.trim();
    if (valore === partenza) { valoreIniziale = null; return; }
    valoreIniziale = null;

    const riga = el.closest('tr[data-post]');
    if (riga) {
      salva(riga.dataset.post, el.dataset.campo, valore, {
        atteso: atteso(el),
        elemento: el,
        applica: function (v) { el.textContent = v; },
        ricorda: ricordaServer(el),
      });
    }
  });

  // Invio esce dal campo invece di andare a capo (tranne nel contenuto).
  editor.addEventListener('keydown', function (e) {
    /* Campi con elenco: la tastiera deve poterci navigare, altrimenti
       il pannello resta una cosa da mouse e chi scrive lo ignora. */
    if (e.target.isContentEditable && e.target.matches('.campo-con-elenco [data-campo]')) {
      const pop = elencoAperto(e.target);

      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!pop) { apriElenco(e.target, false); return; }
        const passo = e.key === 'ArrowDown' ? 1 : -1;
        const ora = indiceEvidenziato(pop);
        evidenzia(pop, ora === -1 ? (passo === 1 ? 0 : -1) : ora + passo);
        return;
      }

      if (e.key === 'Enter' && pop) {
        const voce = pop.querySelector('.voce-elenco.evidenziata');
        if (voce) {
          e.preventDefault();
          scegliVoce(e.target, voce.dataset.valore);
          return;
        }
      }

      /* Il primo Esc chiude solo l'elenco e lascia il testo scritto; il
         secondo annulla la modifica, come in tutti gli altri campi. */
      if (e.key === 'Escape' && pop) {
        e.preventDefault();
        chiudiPopover();
        return;
      }
    }

    if (e.key === 'Enter' && e.target.isContentEditable && !e.target.classList.contains('copy')) {
      e.preventDefault();
      e.target.blur();
    }
    if (e.key === 'Escape' && e.target.isContentEditable) {
      e.target.textContent = valoreIniziale === null ? e.target.textContent : valoreIniziale;
      valoreIniziale = null;
      e.target.blur();
    }
  });

  /* ------------------------------------------------------ data e ora -- */

  /* Giorno e ora si vedono come testo compatto ("Gio 3", "18:30"): il campo
     vero compare solo al clic. Tenere tre controlli nativi sempre aperti
     rendeva ogni riga alta il triplo del necessario. */

  /* Il campo si cerca per classe dentro la cella, non come fratello
     successivo: fra pulsante e campo può esserci altro (il badge "oggi"),
     e legarsi all'ordine del markup si è già rotto una volta. */
  const coppie = [
    { pulsante: '.valore-data', campo: '.campo-data' },
    { pulsante: '.valore-ora', campo: '.campo-ora' },
  ];

  function campoDi(pulsante) {
    const coppia = coppie.find(c => pulsante.matches(c.pulsante));
    return coppia ? pulsante.closest('td').querySelector(coppia.campo) : null;
  }

  function pulsanteDi(input) {
    const coppia = coppie.find(c => input.matches(c.campo));
    return coppia ? input.closest('td').querySelector(coppia.pulsante) : null;
  }

  function apriCampo(pulsante) {
    const input = campoDi(pulsante);
    if (!input) { return; }

    pulsante.hidden = true;
    input.hidden = false;
    input.focus();

    // Apre direttamente il calendario dove il browser lo consente
    if (typeof input.showPicker === 'function') {
      try { input.showPicker(); } catch (e) { /* richiede un gesto: pazienza */ }
    }
  }

  function chiudiCampo(input) {
    const pulsante = pulsanteDi(input);
    if (!pulsante) { return; }

    // La data ricarica la pagina, l'ora no: qui va riscritta l'etichetta
    if (input.classList.contains('campo-ora')) {
      pulsante.textContent = input.value === '' ? 'ora' : input.value;
      pulsante.classList.toggle('vuoto', input.value === '');
    }

    input.hidden = true;
    pulsante.hidden = false;
  }

  editor.addEventListener('focusout', function (e) {
    if (e.target.matches('.campo-data, .campo-ora')) {
      chiudiCampo(e.target);
    }
  });

  editor.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && e.target.matches('.campo-data, .campo-ora')) {
      e.target.blur();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { chiudiPopover(); }
  });

  editor.addEventListener('change', function (e) {
    const el = e.target;
    const riga = el.closest('tr[data-post]');
    if (!riga) { return; }

    if (el.classList.contains('campo-data') || el.classList.contains('campo-ora')) {
      salva(riga.dataset.post, el.dataset.campo, el.value, {
        atteso: atteso(el),
        elemento: el,
        applica: function (v) { el.value = v; chiudiCampo(el); },
        ricorda: ricordaServer(el),
      });
      return;
    }

    // Caselle del selettore canali
    if (el.matches('.ch-pop input')) {
      const cella = el.closest('.ch-cell');
      const scelti = Array.from(cella.querySelectorAll('.ch-pop input:checked')).map(function (i) { return i.value; });
      const prima = cella.dataset.server;

      cella.dataset.ch = scelti.join(',');
      disegnaTag(cella);
      // Le etichette cambiano l'altezza della riga: il pannello va riallineato
      posizionaPopover(cella, cella.querySelector('.ch-pop'));

      salva(riga.dataset.post, 'canali', scelti, {
        atteso: prima === undefined ? undefined : prima.split(',').filter(Boolean),
        elemento: cella,
        applica: function (v) {
          cella.dataset.ch = (v || []).join(',');
          disegnaTag(cella);
          chiudiPopover();
        },
        ricorda: ricordaServer(cella),
      });
    }
  });

  /* -------------------------------------------------- canali (popover) -- */

  function disegnaTag(cella) {
    const scelti = (cella.dataset.ch || '').split(',').filter(Boolean);
    const tags = cella.querySelector('.tags');

    tags.innerHTML = scelti.length
      ? scelti.map(function (c) {
          const canale = CANALI.find(function (x) { return x.codice === c; });
          return '<span class="tag ' + c + '">' + (canale ? canale.etichetta : c) + '</span>';
        }).join('')
      : '<span class="scegli">Scegli canale</span>';
  }

  function apriPopover(cella) {
    const scelti = (cella.dataset.ch || '').split(',').filter(Boolean);
    const pop = cella.querySelector('.ch-pop');

    pop.innerHTML = CANALI.map(function (c) {
      return '<label><input type="checkbox" value="' + c.codice + '"'
        + (scelti.indexOf(c.codice) > -1 ? ' checked' : '') + '>'
        + '<span class="tag ' + c.codice + '">' + c.etichetta + '</span></label>';
    }).join('');

    chiudiPopover(pop);
    pop.classList.add('aperto');
    posizionaPopover(cella, pop);
  }

  /* ------------------------------------------------ pannello dello stato -- */

  function disegnaStato(pulsante, codice) {
    const voce = CICLO.find(function (s) { return s.codice === codice; });
    pulsante.dataset.stato = codice;
    pulsante.textContent = voce ? voce.etichetta : codice;
    pulsante.className = 'stato-pill ' + codice + ' azione-stato';
  }

  /**
   * Quattro stati in un pannello, come per i canali.
   * Prima era un clic ciclico: per arrivare a "Pubblicato" ne servivano
   * tre, e sbagliando bisognava rifare tutto il giro.
   */
  function apriStato(pulsante, riga) {
    let pop = pulsante.nextElementSibling;
    if (!pop || !pop.classList.contains('stato-pop')) {
      pop = document.createElement('div');
      pop.className = 'stato-pop';
      pulsante.after(pop);
    }

    const attuale = pulsante.dataset.stato;
    pop.innerHTML = CICLO.map(function (s) {
      return '<button type="button" class="voce-stato' + (s.codice === attuale ? ' scelto' : '')
        + '" data-stato="' + s.codice + '">'
        + '<span class="stato-pill ' + s.codice + '">' + s.etichetta + '</span></button>';
    }).join('');

    chiudiPopover(pop);
    pop.classList.add('aperto');
    window.posizionaPannello(pulsante, pop);
  }

  /* ------------------------------------- nuovo post: in quale giorno -- */

  const GIORNI = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

  /**
   * "+ Post" chiede il giorno invece di mettere tutto sul lunedì.
   *
   * Prima ogni post nasceva sul primo giorno della settimana e la data
   * andava corretta subito dopo: due passaggi in più per ognuno dei dodici
   * post di un piano, sempre.
   */
  function apriGiorni(pulsante) {
    let pop = pulsante.nextElementSibling;
    if (!pop || !pop.classList.contains('giorni-pop')) {
      pop = document.createElement('div');
      pop.className = 'giorni-pop';
      pulsante.after(pop);
    }

    const lunedi = pulsante.dataset.data;
    const oggi = new Date().toISOString().slice(0, 10);

    pop.innerHTML = GIORNI.map(function (nome, i) {
      // Date in UTC: qui contano solo giorno e mese, non l'ora locale
      const g = new Date(lunedi + 'T00:00:00Z');
      g.setUTCDate(g.getUTCDate() + i);
      const iso = g.toISOString().slice(0, 10);

      return '<button type="button" class="voce-giorno' + (iso === oggi ? ' oggi' : '') + '"'
        + ' data-data="' + iso + '">'
        + '<b>' + nome + '</b> ' + g.getUTCDate()
        + (iso === oggi ? ' <span class="segno-oggi">oggi</span>' : '')
        + '</button>';
    }).join('');

    chiudiPopover(pop);
    pop.classList.add('aperto');
    window.posizionaPannello(pulsante, pop);
  }

  /* --------------------------------- scelta rapida di formato e CTA -- */

  /* Formato e call to action non sono un insieme chiuso come i canali:
     il vocabolario arriva dalle impostazioni, ma capita di dover
     scrivere qualcosa che in elenco non c'e'. Campo libero ed elenco
     quindi convivono — ma devono darsi ragione a vicenda, altrimenti
     l'uno dice "scrivi quello che vuoi" e l'altro "scegli fra questi".

     Percio': quello che si scrive filtra l'elenco invece di essere
     ignorato, e quando non corrisponde a niente il pannello lo dice,
     invece di restare li' a proporre undici voci che non c'entrano. */

  const senzaSegni = (s) => String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const semplice = (s) => senzaSegni(s).toLowerCase().trim();

  /** Il campo di testo del gruppo: la cella del formato contiene anche il pilastro. */
  function campoDelGruppo(nodo) {
    const gruppo = nodo.closest('.campo-con-elenco');
    return gruppo ? gruppo.querySelector('[data-campo]') : null;
  }

  function vociDelCampo(campo) {
    const bottone = campo.closest('.campo-con-elenco').querySelector('.apri-elenco');
    return bottone ? (dati[bottone.dataset.elenco] || []) : [];
  }

  function pannelloDi(campo) {
    const gruppo = campo.closest('.campo-con-elenco');
    let pop = gruppo.querySelector('.elenco-pop');
    if (!pop) {
      pop = document.createElement('div');
      pop.className = 'elenco-pop';
      gruppo.appendChild(pop);
    }
    return pop;
  }

  function elencoAperto(campo) {
    const gruppo = campo ? campo.closest('.campo-con-elenco') : null;
    return gruppo ? gruppo.querySelector('.elenco-pop.aperto') : null;
  }

  function evidenzia(pop, indice) {
    const voci = [...pop.querySelectorAll('.voce-elenco')];
    if (voci.length === 0) { return; }
    const i = ((indice % voci.length) + voci.length) % voci.length;
    voci.forEach(function (b, n) { b.classList.toggle('evidenziata', n === i); });
    voci[i].scrollIntoView({ block: 'nearest' });
  }

  function indiceEvidenziato(pop) {
    return [...pop.querySelectorAll('.voce-elenco')]
      .findIndex(function (b) { return b.classList.contains('evidenziata'); });
  }

  /**
   * Ridisegna le voci.
   *
   * `filtra` distingue i due momenti: appena si entra nel campo l'elenco
   * si vede tutto, perche' il valore gia' scritto non e' una ricerca e
   * mostrarne una voce sola impedirebbe di vedere le altre. Da quando si
   * digita, invece, l'elenco segue quello che si sta scrivendo.
   */
  function disegnaElenco(campo, pop) {
    const voci = vociDelCampo(campo);
    const scritto = campo.textContent.trim();
    const filtra = pop.dataset.filtra === '1' && scritto !== '';
    const cerca = semplice(scritto);
    const trovate = filtra ? voci.filter((v) => semplice(v).indexOf(cerca) !== -1) : voci;

    pop.textContent = '';

    if (trovate.length === 0) {
      const nota = document.createElement('p');
      nota.className = 'elenco-nota';
      const forte = document.createElement('b');
      forte.textContent = scritto;   // testo scritto da una persona: mai innerHTML
      nota.append('Non è in elenco. Resta ', forte);
      pop.appendChild(nota);
      return;
    }

    trovate.forEach(function (v) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'voce-elenco' + (v === scritto ? ' scelto' : '');
      b.dataset.valore = v;
      b.textContent = v;
      pop.appendChild(b);
    });

    /* Con l'elenco filtrato Invio prende la voce evidenziata: si scrive
       "car", si vede evidenziato "Carosello", si preme Invio. Senza
       filtro no: li' Invio deve continuare a confermare quello che si e'
       scritto, come in ogni altro campo della tabella. */
    if (filtra) { evidenzia(pop, 0); }
  }

  function apriElenco(campo, filtra) {
    if (!campo || vociDelCampo(campo).length === 0) { return null; }
    const pop = pannelloDi(campo);
    pop.dataset.filtra = filtra ? '1' : '0';
    disegnaElenco(campo, pop);
    chiudiPopover(pop);
    pop.classList.add('aperto');
    window.posizionaPannello(campo.closest('.campo-con-elenco'), pop);
    return pop;
  }

  /** Applica una voce scelta: la scrive, la salva, chiude il pannello. */
  function scegliVoce(campo, valore) {
    const riga = campo.closest('tr[data-post]');
    const prima = campo.textContent.trim();

    chiudiPopover();
    if (valore === prima || !riga) { return; }

    campo.textContent = valore;

    /* Il salvataggio lo fa questa funzione. Senza allineare anche il
       valore di partenza, uscendo poi dal campo focusout vedrebbe una
       differenza e salverebbe una seconda volta la stessa cosa. */
    valoreIniziale = valore;

    salva(riga.dataset.post, campo.dataset.campo, valore, {
      atteso: atteso(campo),
      elemento: campo,
      applica: function (v) { campo.textContent = v; },
      ricorda: ricordaServer(campo),
    });
  }

  const CAMPO_CON_ELENCO = '.campo-con-elenco [data-campo]';

  // Entrare nel campo apre l'elenco: sono la stessa cosa, non due.
  editor.addEventListener('focusin', function (e) {
    if (e.target.matches(CAMPO_CON_ELENCO)) { apriElenco(e.target, false); }
  });

  // Da qui in poi l'elenco segue quello che si scrive.
  editor.addEventListener('input', function (e) {
    if (!e.target.matches(CAMPO_CON_ELENCO)) { return; }
    const campo = e.target;
    const pop = elencoAperto(campo) || apriElenco(campo, true);
    if (!pop) { return; }
    pop.dataset.filtra = '1';
    disegnaElenco(campo, pop);
    window.posizionaPannello(campo.closest('.campo-con-elenco'), pop);
  });

  /* Il clic su una voce non deve togliere il fuoco dal campo: focusout
     salverebbe il testo digitato prima che la scelta venga applicata. */
  editor.addEventListener('mousedown', function (e) {
    if (e.target.closest('.elenco-pop .voce-elenco')) { e.preventDefault(); }
  });

  /** Il posizionamento sta in app.js: lo usano canali e stato. */
  function posizionaPopover(cella, pop) {
    window.posizionaPannello(cella.querySelector('.tags'), pop);
  }

  /** I pannelli a comparsa dell'editor, in un posto solo. */
  const PANNELLI = '.ch-pop, .stato-pop, .elenco-pop, .giorni-pop';

  function chiudiPopover(tranne) {
    document.querySelectorAll('.ch-pop.aperto, .stato-pop.aperto, .elenco-pop.aperto, .giorni-pop.aperto')
      .forEach(function (p) {
        if (p !== tranne) { p.classList.remove('aperto'); }
      });
  }

  /* Da fisso il pannello non segue lo scorrimento della pagina: meglio
     chiuderlo. Ma lo scorrimento DENTRO un pannello non e' un motivo per
     chiuderlo, e l'ascolto e' in cattura, quindi arriva qui anche quello.
     Succedeva con l'elenco dei formati: si provava a scendere fino alle
     ultime voci e il pannello spariva. */
  window.addEventListener('scroll', function (e) {
    if (e.target instanceof Element && e.target.closest(PANNELLI)) { return; }
    chiudiPopover();
  }, true);
  window.addEventListener('resize', function () { chiudiPopover(); });

  /* ------------------------------------------------------------- clic -- */

  editor.addEventListener('click', function (e) {
    const riga = e.target.closest('tr[data-post]');

    // Giorno e ora: il testo apre il campo
    const valore = e.target.closest('.valore');
    if (valore) {
      apriCampo(valore);
      return;
    }

    // Selettore canali
    const tags = e.target.closest('.tags');
    if (tags) {
      apriPopover(tags.closest('.ch-cell'));
      return;
    }
    /* L'elenco di formato/CTA non rientra qui: si apre col fuoco nel
       campo, e il clic che ce lo mette lo richiuderebbe all'istante. */
    if (!e.target.closest('.ch-pop, .stato-pop, .elenco-pop, .campo-con-elenco')) { chiudiPopover(); }

    // Scelta rapida di formato / call to action
    const apriEl = e.target.closest('.apri-elenco');
    if (apriEl) {
      // Il chevron mostra sempre l'elenco intero, anche a campo pieno:
      // serve proprio a vedere le voci diverse da quella scritta.
      const campo = campoDelGruppo(apriEl);
      if (campo) { campo.focus(); apriElenco(campo, false); }
      return;
    }

    const voceElenco = e.target.closest('.elenco-pop .voce-elenco');
    if (voceElenco) {
      scegliVoce(campoDelGruppo(voceElenco), voceElenco.dataset.valore);
      return;
    }

    // Stato: si apre il pannello con i quattro stati
    const pulsanteStato = e.target.closest('.azione-stato');
    if (pulsanteStato && riga) {
      apriStato(pulsanteStato, riga);
      return;
    }

    // Scelta di uno stato dal pannello
    const voceStato = e.target.closest('.stato-pop .voce-stato');
    if (voceStato) {
      const pannello = voceStato.closest('.stato-pop');
      const pulsante = pannello.previousElementSibling;
      const rigaStato = pannello.closest('tr[data-post]');
      const attuale = pulsante.dataset.stato;
      const scelto = voceStato.dataset.stato;

      chiudiPopover();
      if (scelto === attuale) { return; }

      disegnaStato(pulsante, scelto);
      salva(rigaStato.dataset.post, 'stato', scelto, {
        atteso: atteso(pulsante),
        elemento: pulsante,
        applica: function (codice) { disegnaStato(pulsante, codice); },
        ricorda: ricordaServer(pulsante),
      });
      return;
    }

    // Campi secondari della riga: visual e pilastro
    const extra = e.target.closest('.azione-extra');
    if (extra && riga) {
      const acceso = riga.classList.toggle('mostra-extra');
      if (acceso) {
        const primo = riga.querySelector('.visual:not(.pieno) [contenteditable]');
        if (primo) { primo.focus(); }
      }
      return;
    }

    // Nuovo post nella settimana
    const aggiungi = e.target.closest('.azione-aggiungi');
    if (aggiungi) {
      apriGiorni(aggiungi);
      return;
    }

    const voceGiorno = e.target.closest('.giorni-pop .voce-giorno');
    if (voceGiorno) {
      chiudiPopover();
      azione(urlNuovoPost(), { data: voceGiorno.dataset.data });
      return;
    }

    if (!riga) { return; }

    if (e.target.closest('.azione-duplica')) {
      azione(urlPost(riga.dataset.post, '/duplica'));
      return;
    }

    if (e.target.closest('.azione-elimina')) {
      if (window.confirm('Eliminare questo post?')) {
        azione(urlPost(riga.dataset.post, '/elimina'));
      }
      return;
    }

    const sposta = e.target.closest('.azione-sposta');
    if (sposta) {
      azione(urlPost(riga.dataset.post, '/sposta'), { direzione: sposta.dataset.direzione });
    }
  });

  // Clic fuori: si chiude qualunque pannello aperto
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.ch-cell')
      && !e.target.closest('.stato-cella')
      && !e.target.closest('.con-elenco')
      && !e.target.closest('.settimana-testa')) {
      chiudiPopover();
    }
  });

  /* ------------------------------------------------- copia negli appunti -- */

  document.addEventListener('click', async function (e) {
    const pulsante = e.target.closest('[data-copia]');
    if (!pulsante) { return; }

    const testo = pulsante.dataset.copia;
    const originale = pulsante.textContent;

    try {
      await navigator.clipboard.writeText(testo);
    } catch (err) {
      // Contesti senza clipboard API (http su rete locale): ripiego sul prompt
      window.prompt('Copia il link:', testo);
      return;
    }

    pulsante.textContent = 'Copiato';
    setTimeout(function () { pulsante.textContent = originale; }, 1500);
  });
})();
