<?php

use App\Models\Post;
use App\Support\Canali;
use App\Support\Stati;

/** @var array<string,mixed> $piano */
/** @var string $token */
/** @var bool $anteprima */
/** @var bool $stampa */
/** @var string $nota */
/** @var string $firma */
/** @var string $nomeStudio */
/** @var array<int,array{numero:int,inizio:DateTimeImmutable,fine:DateTimeImmutable,post:array<int,array<string,mixed>>}> $settimane */
/** @var string $fase */
/** @var array{totali:int,approvati:int,da_rivedere:int,fase:string} $conteggi */

/** I conteggi seguono la fase: nella fase esecutivi si contano quelli. */
$totali    = $conteggi['totali'];
$inAttesa  = $totali - $conteggi['approvati'];
$esecutivi = $fase === 'esecutivi';

/**
 * Quanti post non hanno ancora copy né immagini. Nella fase esecutivi non
 * sono approvabili — il cliente non li ha visti — quindi vanno tolti dal
 * conto di quello che può fare adesso, altrimenti il pulsante «approva i
 * rimanenti» prometterebbe più di quanto mantiene.
 */
$nonPronti = 0;
if ($esecutivi) {
    foreach ($settimane as $unaSettimana) {
        foreach ($unaSettimana['post'] as $unPost) {
            if (!Post::haEsecutivo($unPost)) {
                $nonPronti++;
            }
        }
    }
}

/** I canali effettivamente usati nel piano, per una legenda pertinente. */
$canaliUsati = [];
foreach ($settimane as $settimana) {
    foreach ($settimana['post'] as $post) {
        foreach ($post['canali'] as $canale) {
            $canaliUsati[$canale] = true;
        }
    }
}
?>
<?php if ($anteprima && !$stampa): ?>
  <div class="striscia-anteprima">
    Anteprima di quello che vede il cliente · i pulsanti di approvazione sono disattivati
    <?php if ($token === ''): ?>
      · nessun link pubblico ancora generato
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!$stampa): ?>
  <div class="barra-cliente" data-token="<?= e($token) ?>">
    <span class="barra-cliente-conteggio">
      <strong id="conteggio-approvati"><?= $conteggi['approvati'] ?></strong>
      di <span id="conteggio-totali"><?= $totali ?></span> approvati
    </span>

    <?php /* La barra dice a colpo d'occhio quanto manca: prima si poteva
             saperlo solo contando le schede. */ ?>
    <span class="barra-avanzamento" aria-hidden="true">
      <i id="avanzamento-riempimento"
         style="width:<?= $totali > 0 ? round($conteggi['approvati'] / $totali * 100) : 0 ?>%"></i>
    </span>

    <?php if ($nonPronti > 0): ?>
      <span class="barra-cliente-nota">
        <?= $nonPronti === 1 ? '1 ancora in preparazione' : $nonPronti . ' ancora in preparazione' ?>
      </span>
    <?php endif; ?>

    <?php if (!$anteprima && $inAttesa - $nonPronti > 0): ?>
      <button type="button" class="btn btn-primario" id="approva-tutti" style="margin-top:0">
        Approva i rimanenti
      </button>
    <?php endif; ?>
    <button type="button" class="btn" onclick="window.print()">Stampa</button>
  </div>
<?php endif; ?>

<div class="doc">
  <header class="doc-testa">
    <div>
      <h1><?= e($piano['titolo']) ?>
        <small><?= e($piano['cliente_nome']) ?> · <?= e(periodo_it($piano['data_inizio'], $piano['data_fine'])) ?></small>
      </h1>
    </div>
    <div class="doc-marchio">
      <?php if (($piano['cliente_logo'] ?? null) !== null): ?>
        <img src="<?= e(url('/' . $piano['cliente_logo'])) ?>" alt="<?= e($piano['cliente_nome']) ?>">
      <?php endif; ?>
      <span><?= e($nomeStudio) ?></span>
    </div>
  </header>

  <?php if ($canaliUsati !== []): ?>
    <div class="doc-legenda">
      <?php foreach (Canali::ELENCO as $codice => $canale): ?>
        <?php if (isset($canaliUsati[$codice])): ?>
          <span class="tag <?= e($codice) ?>"><?= e($canale['etichetta']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($nota !== ''): ?>
    <p class="doc-nota"><?= nl2br(e($nota)) ?></p>
  <?php endif; ?>

  <?php if ($totali === 0): ?>
    <p class="vuoto">Il piano non contiene ancora nessun post.</p>
  <?php endif; ?>

  <?php foreach ($settimane as $settimana): ?>
    <?php if ($settimana['post'] === []) { continue; } ?>

    <section class="doc-settimana">
      <div class="doc-settimana-testa">
        <h2><?= $settimana['numero'] > 0 ? 'Settimana ' . $settimana['numero'] : 'Altri contenuti' ?></h2>
        <span><?= e(periodo_it($settimana['inizio'], $settimana['fine'])) ?></span>
      </div>

      <?php if ($esecutivi): ?>
        <?php
        /* Fase esecutivi: una scheda per post con l'anteprima del post vero.
           Il partial usa gli stessi attributi della tabella, cosi il JS
           dell'area pubblica funziona in tutte e due le fasi. */
        $postDellaSettimana = $settimana['post'];
        require BASE_PATH . '/app/Views/pubblico/_esecutivi.php';
        ?>
      <?php else: ?>

      <table class="doc-tabella">
        <colgroup>
          <col class="c-day"><col class="c-ch"><col><col class="c-fmt"><col class="c-cta"><col class="c-az">
        </colgroup>
        <thead>
          <tr><th>Giorno</th><th>Canali</th><th>Contenuto</th><th>Formato</th><th>Call to action</th><th>Stato</th></tr>
        </thead>
        <tbody>
        <?php foreach ($settimana['post'] as $post): ?>
          <?php $agibile = in_array($post['stato'], ['da_approvare', 'da_rivedere'], true); ?>
          <tr data-post="<?= (int) $post['id'] ?>">
            <td class="doc-giorno" data-etichetta="Giorno">
              <?= e(giorno_it($post['data'], false)) ?>
              <?php if (($post['ora'] ?? null) !== null): ?>
                <small><?= e(ora_it($post['ora'])) ?></small>
              <?php endif; ?>
            </td>

            <td data-etichetta="Canali"><?= Canali::tag($post['canali']) ?></td>

            <td data-etichetta="Contenuto">
              <?= nl2br(e($post['contenuto'])) ?>
              <?php if (($post['visual_url'] ?? '') !== '' && filter_var($post['visual_url'], FILTER_VALIDATE_URL)): ?>
                <br><a class="doc-visual" href="<?= e($post['visual_url']) ?>" target="_blank" rel="noopener noreferrer">Guarda il visual</a>
              <?php endif; ?>
            </td>

            <td class="doc-secondario" data-etichetta="Formato">
              <?= e($post['formato']) ?>
              <?php if (($post['pilastro'] ?? '') !== ''): ?>
                <small><?= e($post['pilastro']) ?></small>
              <?php endif; ?>
            </td>

            <td class="doc-secondario" data-etichetta="Call to action"><?= e($post['cta']) ?></td>

            <td class="doc-azioni" data-etichetta="Stato">
              <span class="stato-pill <?= e($post['stato']) ?> etichetta-stato"><?= e(Stati::etichettaPost((string) $post['stato'])) ?></span>

              <?php if (($post['commento_cliente'] ?? '') !== ''): ?>
                <p class="doc-commento"><?= e($post['commento_cliente']) ?></p>
              <?php endif; ?>

              <?php if ($agibile && !$anteprima): ?>
                <div class="doc-pulsanti">
                  <button type="button" class="btn btn-piccolo azione-approva">Approva</button>
                  <button type="button" class="btn btn-piccolo azione-modifica">Chiedi modifica</button>
                </div>
                <div class="doc-commento-modulo" hidden>
                  <textarea rows="3" placeholder="Cosa vuoi modificare?" aria-label="Richiesta di modifica"></textarea>
                  <div class="doc-pulsanti">
                    <button type="button" class="btn btn-piccolo btn-primario azione-invia" style="margin-top:0">Invia</button>
                    <button type="button" class="btn btn-piccolo azione-annulla">Annulla</button>
                  </div>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <footer class="doc-pie">
    <span><?= e($firma) ?></span>
    <span>Documento riservato al cliente</span>
  </footer>
</div>

<p class="salvataggio" id="salvataggio" hidden></p>
