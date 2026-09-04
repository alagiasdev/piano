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

  /* --------------------------------- scelta rapida di formato e CTA -- */

  /**
   * Formato e call to action non sono un insieme chiuso come i canali:
   * il pannello serve a pescare in fretta il valore che si usa quasi
   * sempre, ma il campo resta a scrittura libera. Gli elenchi arrivano
   * dalle impostazioni, così il vocabolario è dello studio.
   */
  function apriElenco(pulsante) {
    const voci = dati[pulsante.dataset.elenco] || [];
    if (voci.length === 0) { return; }

    let pop = pulsante.nextElementSibling;
    if (!pop || !pop.classList.contains('elenco-pop')) {
      pop = document.createElement('div');
      pop.className = 'elenco-pop';
      pulsante.after(pop);
    }

    // dentro il gruppo, non nella cella: quella del formato contiene anche il pilastro
    const campo = pulsante.closest('.campo-con-elenco').querySelector('[data-campo]');
    const attuale = campo.textContent.trim();

    pop.innerHTML = voci.map(function (v) {
      const scelto = v === attuale ? ' scelto' : '';
      return '<button type="button" class="voce-elenco' + scelto + '"></button>';
    }).join('');

    // textContent per ogni voce: sono testi scritti dall'utente
    pop.querySelectorAll('.voce-elenco').forEach(function (b, i) {
      b.textContent = voci[i];
      b.dataset.valore = voci[i];
    });

    chiudiPopover(pop);
    pop.classList.add('aperto');
    window.posizionaPannello(pulsante, pop);
  }

  /** Il posizionamento sta in app.js: lo usano canali e stato. */
  function posizionaPopover(cella, pop) {
    window.posizionaPannello(cella.querySelector('.tags'), pop);
  }

  function chiudiPopover(tranne) {
    document.querySelectorAll('.ch-pop.aperto, .stato-pop.aperto, .elenco-pop.aperto')
      .forEach(function (p) {
        if (p !== tranne) { p.classList.remove('aperto'); }
      });
  }

  // Da fisso il pannello non segue lo scorrimento: meglio chiuderlo.
  window.addEventListener('scroll', function () { chiudiPopover(); }, true);
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
    if (!e.target.closest('.ch-pop') && !e.target.closest('.stato-pop')) { chiudiPopover(); }

    // Scelta rapida di formato / call to action
    const apriEl = e.target.closest('.apri-elenco');
    if (apriEl) {
      apriElenco(apriEl);
      return;
    }

    const voceElenco = e.target.closest('.elenco-pop .voce-elenco');
    if (voceElenco) {
      const gruppo = voceElenco.closest('.con-elenco').querySelector('.campo-con-elenco');
      const campo = gruppo.querySelector('[data-campo]');
      const rigaEl = voceElenco.closest('tr[data-post]');
      const prima = campo.textContent.trim();
      const scelto = voceElenco.dataset.valore;

      chiudiPopover();
      if (scelto === prima) { return; }

      campo.textContent = scelto;
      salva(rigaEl.dataset.post, campo.dataset.campo, scelto, {
        atteso: atteso(campo),
        elemento: campo,
        applica: function (v) { campo.textContent = v; },
        ricorda: ricordaServer(campo),
      });
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

    // Nuovo post nella settimana
    const aggiungi = e.target.closest('.azione-aggiungi');
    if (aggiungi) {
      azione(urlNuovoPost(), { data: aggiungi.dataset.data });
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
      && !e.target.closest('.con-elenco')) {
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
