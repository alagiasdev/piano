/* Piano — JS comune. Vanilla, nessuna dipendenza.
   L'editor inline del piano usa api() e percorso() definiti qui. */

(function () {
  'use strict';

  const meta = document.querySelector('meta[name="csrf-token"]');
  const CSRF = meta ? meta.content : '';

  const metaBase = document.querySelector('meta[name="base-url"]');
  const BASE = (metaBase ? metaBase.content : '/').replace(/\/+$/, '');

  /**
   * Percorso interno all'app, con la sottocartella giusta:
   * percorso('post/12') -> '/crm_social1.0/public/post/12' in locale,
   * '/post/12' su un dominio con document root su public/.
   */
  window.percorso = function (p) {
    return BASE + '/' + String(p).replace(/^\/+/, '');
  };

  /**
   * Chiamata JSON all'applicazione. Rilancia con il messaggio del server,
   * così chi chiama deve solo mostrare l'errore.
   */
  window.api = async function (url, dati, metodo) {
    const risposta = await fetch(url, {
      method: metodo || 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': CSRF,
      },
      body: dati === undefined ? undefined : JSON.stringify(dati),
    });

    let corpo = {};
    try { corpo = await risposta.json(); } catch (e) { /* risposta non JSON */ }

    if (!risposta.ok || corpo.ok === false) {
      // L'errore porta con sé il corpo: certi casi (il conflitto fra due
      // persone) hanno bisogno dei dettagli, non solo del messaggio.
      const errore = new Error(corpo.errore || 'Errore imprevisto (' + risposta.status + ')');
      errore.risposta = corpo;
      errore.stato = risposta.status;
      throw errore;
    }
    return corpo;
  };

  /**
   * Come api(), ma per i file: niente Content-Type impostato a mano, perché
   * il browser deve poterci mettere il boundary del multipart.
   */
  window.apiFile = async function (url, formData) {
    const risposta = await fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF },
      body: formData,
    });

    let corpo = {};
    try { corpo = await risposta.json(); } catch (e) { /* risposta non JSON */ }

    if (!risposta.ok || corpo.ok === false) {
      throw new Error(corpo.errore || 'Errore imprevisto (' + risposta.status + ')');
    }
    return corpo;
  };

  /**
   * Posiziona un pannello a comparsa sotto l'elemento che lo ha aperto.
   *
   * I pannelli sono position:fixed — dentro le tabelle, che hanno
   * overflow:hidden, un absolute verrebbe ritagliato. Da fissi il
   * contenitore è la finestra, quindi le coordinate vanno calcolate qui,
   * ribaltando sopra o rientrando da destra quando lo spazio non basta.
   */
  window.posizionaPannello = function (ancoraElemento, pannello) {
    const ancora = ancoraElemento.getBoundingClientRect();
    const misura = pannello.getBoundingClientRect();
    const margine = 8;

    let sinistra = ancora.left;
    if (sinistra + misura.width > window.innerWidth - margine) {
      sinistra = Math.max(margine, window.innerWidth - misura.width - margine);
    }

    let alto = ancora.bottom + 4;
    if (alto + misura.height > window.innerHeight - margine) {
      const sopra = ancora.top - misura.height - 4;
      alto = sopra >= margine
        ? sopra
        : Math.max(margine, window.innerHeight - misura.height - margine);
    }

    pannello.style.left = Math.round(sinistra) + 'px';
    pannello.style.top = Math.round(alto) + 'px';
  };

  /**
   * Due persone hanno modificato lo stesso campo.
   *
   * Non si sceglie per loro: si mostrano tutti e due i valori e si lascia
   * decidere. È il contrario di quello che succedeva prima, cioè vinceva
   * chi salvava per ultimo e l'altro non lo sapeva.
   *
   * opzioni: { contenitore, mioValore, dati, applica, ricorda, riSalva, avvisa }
   */
  window.mostraConflitto = function (opzioni) {
    const contenitore = opzioni.contenitore;
    const dati = opzioni.dati;

    const testo = function (v) {
      return Array.isArray(v) ? v.join(', ') : String(v === null || v === undefined ? '' : v);
    };

    contenitore.querySelectorAll('.conflitto').forEach(function (n) { n.remove(); });

    const box = document.createElement('div');
    box.className = 'conflitto';
    box.innerHTML =
      '<p class="conflitto-chi"><strong></strong> ha modificato questo campo mentre lo stavi cambiando.</p>' +
      '<p class="conflitto-riga"><span>Ora c\'è:</span> <b class="conflitto-sua"></b></p>' +
      '<p class="conflitto-riga"><span>Tu avevi scritto:</span> <b class="conflitto-mia"></b></p>' +
      '<div class="conflitto-pulsanti">' +
        '<button type="button" class="btn btn-piccolo azione-tieni-sua">Tieni la sua</button>' +
        '<button type="button" class="btn btn-piccolo btn-primario azione-tieni-mia" style="margin-top:0">Tieni la mia</button>' +
      '</div>';

    // textContent e non innerHTML: sono testi scritti da persone
    box.querySelector('.conflitto-chi strong').textContent = dati.chi;
    box.querySelector('.conflitto-sua').textContent = testo(dati.attuale) || '(vuoto)';
    box.querySelector('.conflitto-mia').textContent = testo(opzioni.mioValore) || '(vuoto)';

    box.querySelector('.azione-tieni-sua').addEventListener('click', function () {
      if (opzioni.applica) { opzioni.applica(dati.attuale); }
      if (opzioni.ricorda) { opzioni.ricorda(dati.attuale); }
      box.remove();
      if (opzioni.avvisa) { opzioni.avvisa('Tenuta la versione di ' + dati.chi); }
    });

    box.querySelector('.azione-tieni-mia').addEventListener('click', function () {
      box.remove();
      // Ora il valore atteso è quello dell'altro: il salvataggio passa
      if (opzioni.riSalva) { opzioni.riSalva(dati.attuale); }
    });

    contenitore.appendChild(box);

    // Come gli altri pannelli: fluttua accanto al campo invece di stare
    // schiacciato dentro una colonna larga centoventi pixel.
    if (opzioni.ancora) {
      box.classList.add('conflitto-pannello');
      window.posizionaPannello(opzioni.ancora, box);
    }
  };

  /* ------------------------------------------ menu delle azioni secondarie -- */

  /* In testa al piano i pulsanti erano sette, tutti con le stesse
     sembianze: nessuno spiccava, e sugli schermi stretti la riga andava a
     capo. Restano in chiaro i tre che si aprono ogni giorno; gli altri
     stanno dietro un "...".

     Sta qui e non in editor.js perche' la testata e' fuori da #editor, e
     perche' un menu del genere puo' servire in qualunque pagina. */

  function chiudiMenu(tranne) {
    document.querySelectorAll('.menu-pop:not([hidden])').forEach(function (pop) {
      if (pop === tranne) { return; }
      pop.hidden = true;
      const apri = pop.parentElement.querySelector('.apri-menu');
      if (apri) { apri.setAttribute('aria-expanded', 'false'); }
    });
  }

  document.addEventListener('click', function (e) {
    const apri = e.target.closest('.apri-menu');
    if (apri) {
      const pop = apri.parentElement.querySelector('.menu-pop');
      if (!pop) { return; }

      const eraAperto = !pop.hidden;
      chiudiMenu();
      if (eraAperto) { return; }

      // Prima si mostra, poi si posiziona: da nascosto non si puo' misurare
      pop.hidden = false;
      apri.setAttribute('aria-expanded', 'true');
      window.posizionaPannello(apri, pop);
      return;
    }

    const voce = e.target.closest('.voce-menu');
    if (voce) {
      /* Il "copia link" scrive "Copiato" su se stesso per un secondo e
         mezzo: chiudere subito il menu vorrebbe dire non far vedere mai la
         conferma. Quella voce lascia il menu aperto, le altre no. */
      if (voce.dataset.copia === undefined) { chiudiMenu(); }
      return;
    }

    if (!e.target.closest('.menu-pop')) { chiudiMenu(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { chiudiMenu(); }
  });

  // Come gli altri pannelli fissi: seguono la pagina solo se si chiudono
  window.addEventListener('scroll', function (e) {
    if (e.target instanceof Element && e.target.closest('.menu-pop')) { return; }
    chiudiMenu();
  }, true);
  window.addEventListener('resize', function () { chiudiMenu(); });

  // Gli avvisi di conferma spariscono da soli; quelli di errore restano.
  document.querySelectorAll('.avviso:not(.errore):not(.attenzione):not(.fisso)').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4000);
  });

  // Conferma per le azioni distruttive: <form data-conferma="Eliminare?">
  document.addEventListener('submit', function (e) {
    const messaggio = e.target.dataset ? e.target.dataset.conferma : null;
    if (messaggio && !window.confirm(messaggio)) {
      e.preventDefault();
    }
  });
})();
