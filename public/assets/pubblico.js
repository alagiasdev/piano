/* Piano — azioni del cliente nella pagina pubblica di approvazione.
   Approva e "chiedi modifica" salvano via fetch e aggiornano la riga.
   L'autorizzazione è il token nell'URL, che viene ripetuto a ogni chiamata. */

(function () {
  'use strict';

  const barra = document.querySelector('.barra-cliente');
  if (!barra) { return; }

  const TOKEN = barra.dataset.token;
  if (!TOKEN) { return; }               // anteprima senza link generato

  const avviso = document.getElementById('salvataggio');
  const contatore = document.getElementById('conteggio-approvati');

  const urlAzione = (idPost, azione) => window.percorso('p/' + TOKEN + '/post/' + idPost + '/' + azione);

  let timerAvviso;
  function mostra(testo, tipo) {
    if (!avviso) { return; }
    clearTimeout(timerAvviso);
    avviso.textContent = testo;
    avviso.className = 'salvataggio' + (tipo ? ' ' + tipo : '');
    avviso.hidden = false;
    if (tipo !== 'errore') {
      timerAvviso = setTimeout(function () { avviso.hidden = true; }, 2200);
    }
  }

  /** Riscrive la riga dopo un'azione: stato, commento, pulsanti. */
  function aggiornaRiga(riga, risposta) {
    const etichetta = riga.querySelector('.etichetta-stato');
    const stati = {
      da_approvare: 'Da approvare',
      approvato: 'Approvato',
      da_rivedere: 'Da rivedere',
      pubblicato: 'Pubblicato',
    };

    if (etichetta) {
      etichetta.className = 'stato-pill ' + risposta.stato + ' etichetta-stato';
      etichetta.textContent = stati[risposta.stato] || risposta.stato;
    }

    // Il commento appena inviato compare sotto lo stato
    let commento = riga.querySelector('.doc-commento');
    if (risposta.commento) {
      if (!commento) {
        commento = document.createElement('p');
        commento.className = 'doc-commento';
        etichetta.insertAdjacentElement('afterend', commento);
      }
      commento.textContent = risposta.commento;
    } else if (commento) {
      commento.remove();
    }

    // Approvato: le azioni non servono più
    if (risposta.stato === 'approvato' || risposta.stato === 'pubblicato') {
      riga.querySelectorAll('.doc-pulsanti, .doc-commento-modulo').forEach(function (el) { el.remove(); });
    } else {
      const modulo = riga.querySelector('.doc-commento-modulo');
      const pulsanti = riga.querySelector('.doc-pulsanti');
      if (modulo) { modulo.hidden = true; }
      if (pulsanti) { pulsanti.hidden = false; }
    }

    if (risposta.piano) { aggiornaContatore(risposta.piano); }
  }

  /** Barra in alto: quanti approvati, quanti in tutto, barra di avanzamento. */
  function aggiornaContatore(piano) {
    if (contatore) { contatore.textContent = piano.approvati; }

    const totali = document.getElementById('conteggio-totali');
    if (totali) { totali.textContent = piano.totali; }

    const riempimento = document.getElementById('avanzamento-riempimento');
    if (riempimento) {
      const quota = piano.totali > 0 ? Math.round((piano.approvati / piano.totali) * 100) : 0;
      riempimento.style.width = quota + '%';
    }

    const tutti = document.getElementById('approva-tutti');
    if (tutti && piano.inAttesa === 0) { tutti.remove(); }
  }

  document.addEventListener('click', async function (e) {
    // [data-post] e non "tr[data-post]": nella fase esecutivi ogni post e
    // una scheda <article>, non una riga di tabella. Il resto e identico.
    const riga = e.target.closest('[data-post]');

    /* Approva tutti */
    if (e.target.id === 'approva-tutti') {
      if (!window.confirm('Approvare tutti i post ancora in attesa?')) { return; }
      e.target.disabled = true;
      try {
        const r = await window.api(window.percorso('p/' + TOKEN + '/approva-tutti'), {});
        mostra(r.quanti === 1 ? '1 post approvato' : r.quanti + ' post approvati');
        setTimeout(function () { location.reload(); }, 600);
      } catch (err) {
        e.target.disabled = false;
        mostra(err.message, 'errore');
      }
      return;
    }

    if (!riga) { return; }

    /* Approva singolo */
    if (e.target.closest('.azione-approva')) {
      try {
        const r = await window.api(urlAzione(riga.dataset.post, 'approva'), {});
        aggiornaRiga(riga, r);
        mostra('Approvato');
      } catch (err) {
        mostra(err.message, 'errore');
      }
      return;
    }

    /* Apre il campo commento */
    if (e.target.closest('.azione-modifica')) {
      riga.querySelector('.doc-pulsanti').hidden = true;
      const modulo = riga.querySelector('.doc-commento-modulo');
      modulo.hidden = false;
      modulo.querySelector('textarea').focus();
      return;
    }

    /* Annulla */
    if (e.target.closest('.azione-annulla')) {
      riga.querySelector('.doc-commento-modulo').hidden = true;
      riga.querySelector('.doc-pulsanti').hidden = false;
      return;
    }

    /* Invia la richiesta di modifica */
    if (e.target.closest('.azione-invia')) {
      const area = riga.querySelector('.doc-commento-modulo textarea');
      const commento = area.value.trim();

      if (commento === '') {
        area.focus();
        mostra('Scrivi cosa vuoi modificare.', 'errore');
        return;
      }

      try {
        const r = await window.api(urlAzione(riga.dataset.post, 'modifica'), { commento: commento });
        area.value = '';
        aggiornaRiga(riga, r);
        mostra('Richiesta inviata');
      } catch (err) {
        mostra(err.message, 'errore');
      }
    }
  });
})();
