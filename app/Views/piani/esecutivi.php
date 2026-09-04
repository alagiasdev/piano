<?php

use App\Support\Canali;
use App\Support\Fasi;
use App\Support\Stati;

/** @var array<string,mixed> $piano */
/** @var array<int,array{numero:int,inizio:DateTimeImmutable,fine:DateTimeImmutable,post:array<int,array<string,mixed>>}> $settimane */
/** @var array{totali:int,approvati:int,da_rivedere:int,fase:string} $conteggi */
/** @var int $massimo */

$idPiano = (int) $piano['id'];
$inFase  = Fasi::normalizza($piano['fase'] ?? 'concept') === 'esecutivi';
$oggi    = date('Y-m-d');
?>
<div class="barra-piano">
  <div class="barra-piano-info">
    <h1>Esecutivi</h1>
    <p class="sfumato piccolo barra-piano-riga">
      <a href="<?= e(url('/piani/' . $idPiano)) ?>"><?= e($piano['titolo']) ?></a>
      · <?= e($piano['cliente_nome']) ?>
      · <span id="conteggio-approvati"><?= $conteggi['approvati'] ?></span>/<span id="conteggio-totali"><?= $conteggi['totali'] ?></span> approvati
    </p>
  </div>

  <div class="barra-piano-azioni">
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano)) ?>">← Torna al piano</a>
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano . '/anteprima')) ?>"
       target="_blank" rel="noopener">Anteprima cliente</a>
  </div>
</div>

<?php if (!$inFase): ?>
  <div class="avviso attenzione fisso">
    Il piano è ancora in fase <strong>concept</strong>: il cliente vede le idee, non gli esecutivi.
    Puoi preparare copy e immagini adesso e passare agli esecutivi quando sei pronto.
    <form method="post" action="<?= e(url('/piani/' . $idPiano . '/fase')) ?>" style="display:inline;margin-left:8px">
      <?= csrf_field() ?>
      <input type="hidden" name="fase" value="esecutivi">
      <button type="submit" class="btn-testo">Passa agli esecutivi</button>
    </form>
  </div>
<?php endif; ?>

<div id="esecutivi" data-piano="<?= $idPiano ?>" data-massimo="<?= $massimo ?>">
<?php foreach ($settimane as $settimana): ?>
  <?php if ($settimana['post'] === []) { continue; } ?>

  <section class="settimana">
    <div class="settimana-testa">
      <h2><?= $settimana['numero'] > 0 ? 'Settimana ' . $settimana['numero'] : 'Fuori periodo' ?></h2>
      <span class="sfumato piccolo"><?= e(periodo_it($settimana['inizio'], $settimana['fine'])) ?></span>
    </div>

    <?php foreach ($settimana['post'] as $post): ?>
      <?php
      $idPost   = (int) $post['id'];
      $media    = $post['media'] ?? [];
      $statoCre = (string) $post['stato_esecutivo'];
      ?>
      <article class="esecutivo <?= $post['data'] === $oggi ? 'oggi' : '' ?>" id="post-<?= $idPost ?>" data-post="<?= $idPost ?>">

        <header class="esecutivo-testa">
          <div>
            <strong><?= e(giorno_it($post['data'], false)) ?></strong>
            <?php if (ora_it($post['ora']) !== ''): ?>
              <span class="sfumato"><?= e(ora_it($post['ora'])) ?></span>
            <?php endif; ?>
            <?= Canali::tag($post['canali']) ?>
          </div>

          <button type="button" class="stato-pill <?= e($statoCre) ?> azione-stato-esecutivo"
                  data-stato="<?= e($statoCre) ?>" data-server="<?= e($statoCre) ?>"
                  title="Scegli lo stato">
            <?= e(Stati::etichettaPost($statoCre)) ?>
          </button>
        </header>

        <?php /* Il concept approvato resta sotto gli occhi mentre si scrive
                 il copy: e' quello che il cliente ha gia detto di volere. */ ?>
        <p class="esecutivo-concept">
          <span class="esecutivo-etichetta">Concept</span>
          <?= e($post['contenuto']) ?: '<em class="sfumato">(nessuna descrizione)</em>' ?>
          <?php if (($post['formato'] ?? '') !== ''): ?>
            <span class="sfumato">· <?= e($post['formato']) ?></span>
          <?php endif; ?>
        </p>

        <?php if (($post['commento_esecutivo'] ?? '') !== ''): ?>
          <p class="commento">
            <strong>Il cliente ha chiesto:</strong> <?= e($post['commento_esecutivo']) ?>
            <span class="sfumato">— <?= e(data_it($post['commento_esecutivo_il'], false)) ?></span>
          </p>
        <?php endif; ?>

        <div class="esecutivo-corpo">
          <div class="esecutivo-copy">
            <label for="copy-<?= $idPost ?>">Copy finale</label>
            <textarea id="copy-<?= $idPost ?>" class="campo-copy" data-campo="copy_finale" rows="7"
                      data-server="<?= e($post['copy_finale']) ?>"
                      placeholder="Il testo esatto che verrà pubblicato: a capo, emoji e hashtag compresi."><?= e($post['copy_finale']) ?></textarea>
            <p class="aiuto">Si salva da solo. <span class="conta-caratteri"></span></p>
          </div>

          <div class="esecutivo-media">
            <span class="etichetta">Immagini <span class="sfumato">(<?= count($media) ?>/<?= $massimo ?>)</span></span>

            <div class="griglia-media">
              <?php foreach ($media as $indice => $immagine): ?>
                <?php $misura = misura_immagine((string) $immagine['percorso']); ?>
                <figure class="media" data-media="<?= (int) $immagine['id'] ?>">
                  <img src="<?= e(url('/' . $immagine['percorso'])) ?>" alt="" loading="lazy">
                  <?php if ($misura !== null): ?>
                    <span class="media-misura" title="Dimensioni del file caricato"><?= e($misura) ?></span>
                  <?php endif; ?>
                  <figcaption>
                    <button type="button" class="azione-media-su" <?= $indice === 0 ? 'disabled' : '' ?>
                            title="Sposta prima">↑</button>
                    <button type="button" class="azione-media-giu" <?= $indice === count($media) - 1 ? 'disabled' : '' ?>
                            title="Sposta dopo">↓</button>
                    <button type="button" class="azione-media-elimina" title="Elimina l'immagine">✕</button>
                  </figcaption>
                </figure>
              <?php endforeach; ?>

              <?php if (count($media) < $massimo): ?>
                <label class="media-aggiungi">
                  <input type="file" class="campo-immagini" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                  <span>+ Aggiungi</span>
                </label>
              <?php endif; ?>
            </div>

            <p class="aiuto">
              JPG, PNG, WebP o GIF, massimo 5 MB ciascuna.
              Per i video usa «Link grafico» nell'editor del piano, con un link a Drive o YouTube.
            </p>

            <?php if (($post['visual_url'] ?? '') !== ''): ?>
              <p class="aiuto">
                Link grafico indicato nel concept:
                <?php if (filter_var($post['visual_url'], FILTER_VALIDATE_URL)): ?>
                  <a href="<?= e($post['visual_url']) ?>" target="_blank" rel="noopener noreferrer">apri il link</a>
                <?php else: ?>
                  <?= e($post['visual_url']) ?>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>

<div id="salvataggio" class="salvataggio" hidden></div>

<script src="<?= e(asset('assets/esecutivi.js')) ?>" defer></script>
