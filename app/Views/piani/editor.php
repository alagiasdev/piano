<?php

use App\Core\Auth;
use App\Support\Canali;
use App\Support\Elenchi;
use App\Support\Fasi;
use App\Support\Stati;

/** @var array<string,mixed> $piano */
/** @var array<int,array{numero:int,inizio:DateTimeImmutable,fine:DateTimeImmutable,post:array<int,array<string,mixed>>}> $settimane */
/** @var array<string,string> $errori */

$idPiano   = (int) $piano['id'];
$totali    = (int) $piano['post_totali'];
$approvati = (int) $piano['post_approvati'];
$oggi        = date('Y-m-d');
$fase        = Fasi::normalizza($piano['fase'] ?? 'concept');
$mediaTotali = (int) ($piano['media_totali'] ?? 0);
$linkPubblico = $piano['token_pubblico'] !== null ? url_assoluta('p/' . $piano['token_pubblico']) : null;
?>
<div class="barra-piano">
  <div class="barra-piano-info">
    <h1><?= e($piano['titolo']) ?></h1>
    <p class="sfumato piccolo">
      <a href="<?= e(url('/clienti/' . $piano['cliente_id'] . '/piani')) ?>"><?= e($piano['cliente_nome']) ?></a>
      · <?= e(periodo_it($piano['data_inizio'], $piano['data_fine'])) ?>
      · <span class="stato-pill <?= e($piano['stato']) ?>"><?= e(Stati::etichettaPiano((string) $piano['stato'])) ?></span>
      · <span id="conteggio-approvati"><?= $approvati ?></span>/<span id="conteggio-totali"><?= $totali ?></span> approvati
    </p>
  </div>

  <div class="barra-piano-azioni">
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano . '/calendario')) ?>">Calendario</a>
    <a class="btn btn-piccolo<?= $fase === 'esecutivi' ? ' btn-fase-attiva' : '' ?>"
       href="<?= e(url('/piani/' . $idPiano . '/esecutivi')) ?>">Esecutivi<?php
       ?><?= $mediaTotali > 0 ? ' · ' . $mediaTotali : '' ?></a>
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano . '/anteprima')) ?>" target="_blank" rel="noopener">Anteprima cliente</a>
    <?php if ($linkPubblico !== null): ?>
      <button type="button" class="btn btn-piccolo" data-copia="<?= e($linkPubblico) ?>">Copia link pubblico</button>
    <?php endif; ?>
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano . '/pdf')) ?>" target="_blank" rel="noopener">PDF</a>
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano . '/ics')) ?>">ICS</a>

    <?php /* Duplica a un clic con i valori di default; le opzioni stanno
             nel pannello delle impostazioni qui sotto. */ ?>
    <form method="post" action="<?= e(url('/piani/' . $idPiano . '/duplica')) ?>" style="display:inline"
          data-conferma="Duplicare il piano spostando tutto di 4 settimane?">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-piccolo" title="Copia il piano 4 settimane più avanti">Duplica</button>
    </form>
  </div>
</div>

<?php foreach ($errori as $messaggio): ?>
  <p class="avviso errore"><?= e($messaggio) ?></p>
<?php endforeach; ?>

<details class="pannello" <?= $errori !== [] ? 'open' : '' ?>>
  <summary>Impostazioni del piano</summary>

  <div class="pannello-corpo">
    <form method="post" action="<?= e(url('/piani/' . $idPiano)) ?>" class="riga-campi">
      <?= csrf_field() ?>
      <div class="campo" style="flex:2 1 220px">
        <label for="titolo">Titolo</label>
        <input type="text" id="titolo" name="titolo" value="<?= e($piano['titolo']) ?>" maxlength="120" required>
      </div>
      <div class="campo">
        <label for="data_inizio">Dal</label>
        <input type="date" id="data_inizio" name="data_inizio" value="<?= e($piano['data_inizio']) ?>" required>
      </div>
      <div class="campo">
        <label for="data_fine">Al</label>
        <input type="date" id="data_fine" name="data_fine" value="<?= e($piano['data_fine']) ?>" required>
      </div>
      <div class="campo" style="flex:1 1 100%">
        <label for="nota_cliente">Nota per il cliente</label>
        <textarea id="nota_cliente" name="nota_cliente" rows="2"><?= e($piano['nota_cliente']) ?></textarea>
        <p class="aiuto">Compare in cima alla pagina di approvazione. Vuota: viene usato il testo standard delle impostazioni.</p>
      </div>
      <button type="submit" class="btn btn-primario" style="margin-top:0">Salva</button>
    </form>

    <hr class="separatore">

    <div class="riga-campi">
      <form method="post" action="<?= e(url('/piani/' . $idPiano . '/stato')) ?>" class="riga-campi" style="gap:8px">
        <?= csrf_field() ?>
        <div class="campo">
          <label for="stato">Stato del piano</label>
          <select id="stato" name="stato">
            <?php foreach (Stati::PIANO as $codice => $etichetta): ?>
              <option value="<?= e($codice) ?>" <?= $piano['stato'] === $codice ? 'selected' : '' ?>><?= e($etichetta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn" style="margin-top:22px">Cambia stato</button>
      </form>

      <?php /* La fase decide cosa vede il cliente sul link pubblico: le
               idee (concept) oppure il post finito (esecutivi). */ ?>
      <form method="post" action="<?= e(url('/piani/' . $idPiano . '/fase')) ?>" class="riga-campi" style="gap:8px">
        <?= csrf_field() ?>
        <div class="campo">
          <label for="fase">Fase</label>
          <select id="fase" name="fase">
            <?php foreach (Fasi::ELENCO as $codice => $etichetta): ?>
              <option value="<?= e($codice) ?>" <?= $fase === $codice ? 'selected' : '' ?>><?= e($etichetta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn" style="margin-top:22px">Cambia fase</button>
        <p class="aiuto" style="flex:1 1 100%">
          In fase <strong>concept</strong> il cliente approva le idee; in fase
          <strong>esecutivi</strong> approva copy e immagini del post finito.
          Gli stati delle due fasi sono separati: si può tornare indietro senza perdere niente.
        </p>
      </form>

      <?php if ($linkPubblico !== null): ?>
        <div class="campo" style="flex:2 1 320px">
          <label>Link pubblico per il cliente</label>
          <div class="riga-campi" style="gap:6px">
            <input type="text" readonly value="<?= e($linkPubblico) ?>" onclick="this.select()" style="flex:1 1 220px">
            <button type="button" class="btn" data-copia="<?= e($linkPubblico) ?>">Copia</button>
          </div>
          <p class="aiuto">Non scade. Rigenerandolo, il link già inviato smette di funzionare.</p>
        </div>
        <form method="post" action="<?= e(url('/piani/' . $idPiano . '/token')) ?>"
              data-conferma="Rigenerare il link? Quello già inviato al cliente non funzionerà più.">
          <?= csrf_field() ?>
          <button type="submit" class="btn" style="margin-top:22px">Rigenera link</button>
        </form>
      <?php else: ?>
        <p class="aiuto" style="flex:1 1 260px;margin-top:22px">
          Il link pubblico viene creato quando porti il piano a «Inviato al cliente».
        </p>
      <?php endif; ?>
    </div>

    <hr class="separatore">

    <form method="post" action="<?= e(url('/piani/' . $idPiano . '/duplica')) ?>" class="riga-campi">
      <?= csrf_field() ?>
      <div class="campo" style="flex:2 1 200px">
        <label for="titolo-copia">Duplica su un nuovo periodo</label>
        <input type="text" id="titolo-copia" name="titolo" maxlength="120" placeholder="Titolo (vuoto = nome del mese)">
      </div>
      <div class="campo">
        <label for="quantita">Sposta di</label>
        <input type="number" id="quantita" name="quantita" value="4" min="-60" max="60" step="1" style="width:80px">
      </div>
      <div class="campo">
        <label for="modo">Unità</label>
        <select id="modo" name="modo">
          <option value="settimane" selected>settimane (mantiene i giorni della settimana)</option>
          <option value="mesi">mesi (mantiene il giorno del mese)</option>
        </select>
      </div>
      <button type="submit" class="btn" style="margin-top:22px">Duplica</button>
    </form>

    <?php /* Eliminare un piano porta via tutti i suoi post: solo admin */ ?>
    <?php if (Auth::amministratore()): ?>
      <hr class="separatore">

      <form method="post" action="<?= e(url('/piani/' . $idPiano . '/elimina')) ?>"
            data-conferma="Eliminare il piano «<?= e($piano['titolo']) ?>» e i suoi <?= $totali ?> post?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-pericolo">Elimina piano</button>
      </form>
    <?php endif; ?>
  </div>
</details>

<div id="editor" data-piano="<?= $idPiano ?>">
<?php foreach ($settimane as $settimana): ?>
  <section class="settimana">
    <div class="settimana-testa">
      <h2>
        <?= $settimana['numero'] > 0 ? 'Settimana ' . $settimana['numero'] : 'Fuori periodo' ?>
      </h2>
      <span class="sfumato piccolo"><?= e(periodo_it($settimana['inizio'], $settimana['fine'])) ?></span>
      <?php if ($oggi >= $settimana['inizio']->format('Y-m-d') && $oggi <= $settimana['fine']->format('Y-m-d')): ?>
        <span class="pill-corrente">in corso</span>
      <?php endif; ?>
      <button type="button" class="btn btn-piccolo azione-aggiungi"
              data-data="<?= e($settimana['inizio']->format('Y-m-d')) ?>">+ Post</button>
    </div>

    <table class="cal">
      <colgroup>
        <col class="c-day"><col class="c-ch"><col><col class="c-fmt">
        <col class="c-cta"><col class="c-st"><col class="c-x">
      </colgroup>
      <thead>
        <tr>
          <th>Giorno</th><th>Canali</th><th>Contenuto</th><th>Formato</th>
          <th>Call to action</th><th>Stato</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if ($settimana['post'] === []): ?>
        <tr class="riga-vuota">
          <td colspan="7">
            Nessun post questa settimana.
            <button type="button" class="btn-testo azione-aggiungi"
                    data-data="<?= e($settimana['inizio']->format('Y-m-d')) ?>">Aggiungine uno</button>
          </td>
        </tr>
      <?php else: ?>
        <?php
        /* Raggruppo per data: serve a sapere quali post condividono il
           giorno, perche il riordino su/giu vale solo dentro lo stesso
           giorno. Dove il giorno ha un solo post i pulsanti non compaiono. */
        $perData = [];
        foreach ($settimana['post'] as $p) {
            $perData[(string) $p['data']][] = $p;
        }
        ?>
        <?php foreach ($perData as $postDelGiorno): ?>
          <?php $quantiQuelGiorno = count($postDelGiorno); ?>
          <?php foreach ($postDelGiorno as $indice => $post): ?>
            <?php
            $primo  = $indice === 0;
            $ultimo = $indice === $quantiQuelGiorno - 1;
            $ora    = ora_it($post['ora']);
            ?>
            <tr id="post-<?= (int) $post['id'] ?>" data-post="<?= (int) $post['id'] ?>"
                class="<?= $primo ? 'inizio-giorno' : 'stesso-giorno' ?><?= $post['data'] === $oggi ? ' oggi' : '' ?>">

              <td class="day">
                <?php /* Testo compatto; il campo vero compare al clic. */ ?>
                <button type="button" class="valore valore-data" title="Cambia la data">
                  <?= e(giorno_it($post['data'])) ?>
                </button>

                <input type="date" class="campo-data" data-campo="data"
                       data-server="<?= e($post['data']) ?>" value="<?= e($post['data']) ?>" hidden>

                <button type="button" class="valore valore-ora <?= $ora === '' ? 'vuoto' : '' ?>" title="Cambia l'ora">
                  <?= $ora === '' ? 'ora' : e($ora) ?>
                </button>
                <input type="time" class="campo-ora" data-campo="ora"
                       data-server="<?= e($ora) ?>" value="<?= e($ora) ?>" hidden>
              </td>

              <td class="ch-cell" data-etichetta="Canali" data-ch="<?= e(implode(',', $post['canali'])) ?>"
                  data-server="<?= e(implode(',', $post['canali'])) ?>">
                <span class="tags"><?= Canali::tag($post['canali']) ?: '<span class="scegli">Scegli canale</span>' ?></span>
                <div class="ch-pop"></div>
              </td>

              <td data-etichetta="Contenuto">
                <div class="copy" contenteditable data-campo="contenuto" data-server="<?= e($post['contenuto']) ?>" data-ph="Descrizione del contenuto"><?= e($post['contenuto']) ?></div>
                <?php /* «Visual» da solo non diceva niente, e per giunta si
                         confondeva con le immagini vere degli esecutivi. Qui
                         c'è solo il link al file grafico di riferimento: ora
                         lo dicono l'etichetta, il segnaposto e il titolo. */ ?>
                <div class="visual <?= ($post['visual_url'] ?? '') !== '' ? 'pieno' : '' ?>"
                     title="Link al file grafico di riferimento: Drive, Canva, Dropbox. Le immagini vere si caricano nella schermata Esecutivi.">
                  <span class="visual-etichetta">Link grafico</span>
                  <span contenteditable data-campo="visual_url" data-server="<?= e($post['visual_url']) ?>"
                        data-ph="Drive, Canva…"><?= e($post['visual_url']) ?></span>
                  <?php if (($post['visual_url'] ?? '') !== '' && filter_var($post['visual_url'], FILTER_VALIDATE_URL)): ?>
                    <a href="<?= e($post['visual_url']) ?>" target="_blank" rel="noopener noreferrer">apri</a>
                  <?php endif; ?>
                </div>
              </td>

              <?php /* La freccetta pesca da un elenco di uso frequente; il
                       campo resta comunque a scrittura libera. */ ?>
              <td class="fmt con-elenco" data-etichetta="Formato">
                <?php /* La freccetta sta attaccata al valore, come nello stato:
                         un clic apre l'elenco, il testo resta modificabile. */ ?>
                <span class="campo-con-elenco">
                  <span class="formato" contenteditable data-campo="formato" data-server="<?= e($post['formato']) ?>" data-ph="formato"><?= e($post['formato']) ?></span>
                  <button type="button" class="apri-elenco" data-elenco="formati"
                          title="Scrivi per filtrare, oppure apri l&rsquo;elenco dei formati">⌄</button>
                </span>
                <div class="pilastro <?= ($post['pilastro'] ?? '') !== '' ? 'pieno' : '' ?>"
                     contenteditable data-campo="pilastro" data-server="<?= e($post['pilastro']) ?>" data-ph="pilastro"><?= e($post['pilastro']) ?></div>
              </td>

              <td class="cta con-elenco" data-etichetta="Call to action">
                <span class="campo-con-elenco">
                  <span contenteditable data-campo="cta" data-server="<?= e($post['cta']) ?>" data-ph="CTA"><?= e($post['cta']) ?></span>
                  <button type="button" class="apri-elenco" data-elenco="cta"
                          title="Scrivi per filtrare, oppure apri l&rsquo;elenco delle call to action">⌄</button>
                </span>
              </td>

              <td class="stato-cella" data-etichetta="Stato">
                <button type="button" class="stato-pill <?= e($post['stato']) ?> azione-stato" data-stato="<?= e($post['stato']) ?>"
                        data-server="<?= e($post['stato']) ?>"
                        title="Clic per passare allo stato successivo"><?= e(Stati::etichettaPost((string) $post['stato'])) ?></button>
                <?php if (($post['commento_cliente'] ?? '') !== ''): ?>
                  <p class="commento">
                    <strong>Cliente:</strong> <?= e($post['commento_cliente']) ?>
                    <span class="sfumato">— <?= e(data_it($post['commento_il'], false)) ?></span>
                  </p>
                <?php endif; ?>
              </td>

              <td class="riga-strumenti">
                <?php if ($quantiQuelGiorno > 1): ?>
                  <?php /* Solo dove c'e' davvero qualcosa da riordinare. */ ?>
                  <button type="button" class="azione-sposta" data-direzione="su"
                          <?= $primo ? 'disabled' : '' ?> title="Sposta prima, nello stesso giorno">↑</button>
                  <button type="button" class="azione-sposta" data-direzione="giu"
                          <?= $ultimo ? 'disabled' : '' ?> title="Sposta dopo, nello stesso giorno">↓</button>
                <?php endif; ?>
                <?php /* Mostra visual e pilastro, che se vuoti non
                         occupano spazio: 22px per riga di troppo. */ ?>
                <button type="button" class="azione-extra"
                        title="Mostra visual e pilastro">⋯</button>
                <button type="button" class="azione-duplica" title="Duplica il post in questo giorno">⧉</button>
                <button type="button" class="azione-elimina" title="Elimina il post">✕</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </section>
<?php endforeach; ?>
</div>

<p class="salvataggio" id="salvataggio" hidden></p>

<script src="<?= e(asset('assets/editor.js')) ?>" defer></script>

<?php
/**
 * Canali e stati passati al JS come dati, per non duplicare in JavaScript
 * definizioni che vivono in App\Support\Canali e App\Support\Stati.
 */
$datiEditor = [
    'canali' => array_map(
        static fn (string $codice) => ['codice' => $codice, 'etichetta' => Canali::etichetta($codice)],
        Canali::codici()
    ),
    'formati' => Elenchi::formati(),
    'cta'     => Elenchi::cta(),
    'ciclo'   => array_map(
        static fn (string $stato) => ['codice' => $stato, 'etichetta' => Stati::etichettaPost($stato)],
        Stati::CICLO_POST
    ),
];
?>
<script id="dati-editor" type="application/json"><?= json_encode($datiEditor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
