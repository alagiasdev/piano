/* Piano — schermata degli esecutivi.
   Copy finale e immagini di ogni post. Stesse convenzioni dell'editor:
   i campi si salvano da soli via fetch, le operazioni che cambiano la
   struttura (immagini aggiunte, tolte, riordinate) ricaricano la pagina
   perché il server risponde con "ricarica": true. */

(function () {
  'use strict';

  const radice = document.getElementById('esecutivi');
  if (!radice) { return; }

  const avviso = document.getElementById('salvataggio');
  const urlPost = (id, coda) => window.percorso('post/' + id + (coda || ''));
  const urlMedia = (id, coda) => window.percorso('media/' + id + (coda || ''));

  const STATI = ['da_approvare', 'approvato', 'da_rivedere', 'pubblicato'];
  const ETICHETTE = {
    da_approvare: 'Da approvare',
    approvato: 'Approvato',
    da_rivedere: 'Da rivedere',
    pubblicato: 'Pubblicato',
  };

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

  /**
   * `atteso` è il valore che il campo aveva quando si è iniziato a
   * scriverci: il server rifiuta se nel frattempo ha salvato un collega,
   * invece di sovrascriverlo senza dirlo a nessuno.
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
      return risposta;
    } catch (e) {
      if (e.risposta && e.risposta.conflitto && extra.elemento) {
        window.mostraConflitto({
          contenitore: extra.elemento.closest('[data-post]'),
          mioValore: valore,
          dati: e.risposta,
          applica: extra.applica,
          ricorda: extra.ricorda,
          avvisa: mostra,
          ancora: extra.elemento,
          riSalva: function (attesoNuovo) {
            salva(id, campo, valore, Object.assign({}, extra, { atteso: attesoNuovo }));
          },
        });
        return null;
      }
      mostra(e.message, 'errore');
      return null;
    }
  }

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

  /* --------------------------------------------------------- copy -- */

  const timer = new WeakMap();

  /* Il valore atteso arriva da data-server, aggiornato solo quando il
     server conferma: fotografarlo agli eventi di fuoco dava conflitti
     inesistenti, perche il fuoco puo arrivare a valore gia cambiato. */
  const atteso = (el) => (el.dataset.server !== undefined ? el.dataset.server : undefined);
  const ricordaServer = (el) => (v) => { el.dataset.server = v; };

  radice.addEventListener('input', function (e) {
    const campo = e.target.closest('.campo-copy');
    if (!campo) { return; }

    contaCaratteri(campo);

    // Si scrive a raffica: si salva quando ci si ferma
    clearTimeout(timer.get(campo));
    timer.set(campo, setTimeout(function () {
      salva(campo.closest('[data-post]').dataset.post, 'copy_finale', campo.value, {
        atteso: atteso(campo),
        elemento: campo,
        applica: function (v) { campo.value = v; contaCaratteri(campo); },
        ricorda: ricordaServer(campo),
      });
    }, 700));
  });

  function contaCaratteri(campo) {
    const spia = campo.closest('.esecutivo-copy').querySelector('.conta-caratteri');
    if (!spia) { return; }
    const n = campo.value.length;
    spia.textContent = n === 0 ? '' : n + (n === 1 ? ' carattere' : ' caratteri');
  }

  radice.querySelectorAll('.campo-copy').forEach(contaCaratteri);

  /* ---------------------------------------------- stato del esecutivo -- */

  function disegnaStato(pulsante, codice) {
    pulsante.dataset.stato = codice;
    pulsante.textContent = ETICHETTE[codice] || codice;
    pulsante.className = 'stato-pill ' + codice + ' azione-stato-esecutivo';
  }

  function chiudiStato(tranne) {
    document.querySelectorAll('.stato-pop.aperto').forEach(function (p) {
      if (p !== tranne) { p.classList.remove('aperto'); }
    });
  }

  /** I quattro stati in un pannello, come per i canali nell'editor. */
  function apriStato(pulsante) {
    let pop = pulsante.nextElementSibling;
    if (!pop || !pop.classList.contains('stato-pop')) {
      pop = document.createElement('div');
      pop.className = 'stato-pop';
      pulsante.after(pop);
    }

    const attuale = pulsante.dataset.stato;
    pop.innerHTML = STATI.map(function (codice) {
      return '<button type="button" class="voce-stato' + (codice === attuale ? ' scelto' : '')
        + '" data-stato="' + codice + '">'
        + '<span class="stato-pill ' + codice + '">' + ETICHETTE[codice] + '</span></button>';
    }).join('');

    chiudiStato(pop);
    pop.classList.add('aperto');
    window.posizionaPannello(pulsante, pop);
  }

  radice.addEventListener('click', function (e) {
    const pulsante = e.target.closest('.azione-stato-esecutivo');
    if (pulsante) { apriStato(pulsante); return; }

    const voce = e.target.closest('.stato-pop .voce-stato');
    if (!voce) { return; }

    const pop = voce.closest('.stato-pop');
    const bottone = pop.previousElementSibling;
    const prima = bottone.dataset.stato;
    const scelto = voce.dataset.stato;

    chiudiStato();
    if (scelto === prima) { return; }

    disegnaStato(bottone, scelto);
    salva(bottone.closest('[data-post]').dataset.post, 'stato_esecutivo', scelto, {
      atteso: atteso(bottone),
      elemento: bottone,
      applica: function (codice) { disegnaStato(bottone, codice); },
      ricorda: ricordaServer(bottone),
    });
  });

  // Clic fuori, Esc, scorrimento: il pannello si chiude
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.esecutivo-testa')) { chiudiStato(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { chiudiStato(); }
  });
  window.addEventListener('scroll', function (e) {
    // Non se si sta scorrendo dentro il pannello stesso: vedi editor.js
    if (e.target instanceof Element && e.target.closest('.stato-pop')) { return; }
    chiudiStato();
  }, true);

  /* ------------------------------------------------------- immagini -- */

  radice.addEventListener('change', async function (e) {
    const campo = e.target.closest('.campo-immagini');
    if (!campo || campo.files.length === 0) { return; }

    const idPost = campo.closest('[data-post]').dataset.post;
    const modulo = new FormData();
    for (const file of campo.files) {
      modulo.append('immagini[]', file);
    }

    mostra('Caricamento…');
    try {
      await window.apiFile(urlPost(idPost, '/media'), modulo);
      location.reload();
    } catch (err) {
      mostra(err.message, 'errore');
      campo.value = '';
    }
  });

  radice.addEventListener('click', function (e) {
    const figura = e.target.closest('[data-media]');
    if (!figura) { return; }
    const id = figura.dataset.media;

    if (e.target.closest('.azione-media-elimina')) {
      if (window.confirm('Eliminare questa immagine?')) {
        azione(urlMedia(id, '/elimina'));
      }
      return;
    }

    if (e.target.closest('.azione-media-su')) {
      azione(urlMedia(id, '/sposta'), { direzione: 'su' });
      return;
    }

    if (e.target.closest('.azione-media-giu')) {
      azione(urlMedia(id, '/sposta'), { direzione: 'giu' });
    }
  });
})();
