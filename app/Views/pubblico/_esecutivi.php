<?php

use App\Models\Post;
use App\Support\Canali;
use App\Support\Stati;

/**
 * Fase esecutivi: il cliente vede il post come sarà pubblicato.
 *
 * Impaginazione a colonna singola, larga quanto un post vero: si scorre
 * con lo stesso gesto con cui si guarderà il contenuto una volta online.
 *
 * I post che non hanno ancora né copy né immagini non occupano una scheda:
 * finiscono in una riga sottile in fondo alla settimana. Prima erano un
 * riquadro vuoto alto quanto una foto, e su dodici post facevano una pagina
 * lunga soprattutto di niente.
 *
 * Stessi attributi della vista concept (data-post, .etichetta-stato,
 * .doc-pulsanti, .doc-commento-modulo): il JS pubblico è lo stesso e non
 * sa niente delle fasi.
 *
 * @var array<int,array<string,mixed>> $postDellaSettimana
 * @var array<string,mixed>            $piano
 * @var bool                           $anteprima
 */

$pronti = [];
$inPreparazione = [];
foreach ($postDellaSettimana as $unPost) {
    if (Post::haEsecutivo($unPost)) {
        $pronti[] = $unPost;
    } else {
        $inPreparazione[] = $unPost;
    }
}

/** L'iniziale del cliente quando non c'è il logo. */
$iniziale = mb_strtoupper(mb_substr((string) $piano['cliente_nome'], 0, 1));
?>
<div class="feed">

<?php foreach ($pronti as $post): ?>
  <?php
  $stato   = (string) $post['stato_esecutivo'];
  $agibile = in_array($stato, ['da_approvare', 'da_rivedere'], true);
  $media   = $post['media'] ?? [];
  $copy    = trim((string) ($post['copy_finale'] ?? ''));

  // Storie e reel sono verticali, il resto quadrato come nel feed
  $verticale = (bool) preg_match('/stori|reel|tiktok|short/i', (string) $post['formato']);
  ?>
  <article class="scheda-feed" data-post="<?= (int) $post['id'] ?>">

    <header class="scheda-feed-capo">
      <?php if (($piano['cliente_logo'] ?? null) !== null): ?>
        <img class="avatar" src="<?= e(url('/' . $piano['cliente_logo'])) ?>" alt="">
      <?php else: ?>
        <span class="avatar senza-logo"><?= e($iniziale) ?></span>
      <?php endif; ?>

      <strong class="chi"><?= e($piano['cliente_nome']) ?></strong>

      <span class="quando">
        <?= e(giorno_it($post['data'], false)) ?><?php
        ?><?= ($post['ora'] ?? null) !== null ? ' · ' . e(ora_it($post['ora'])) : '' ?>
        <span class="canali"><?= Canali::tag($post['canali']) ?></span>
      </span>
    </header>

    <?php if ($media !== []): ?>
      <div class="scheda-feed-media <?= $verticale ? 'verticale' : '' ?>">
        <?php foreach ($media as $immagine): ?>
          <img src="<?= e(url('/' . $immagine['percorso'])) ?>" alt="" loading="lazy">
        <?php endforeach; ?>
      </div>
      <?php if (count($media) > 1): ?>
        <p class="scheda-feed-conta"><?= count($media) ?> immagini · scorri per vederle tutte</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($copy !== ''): ?>
      <div class="scheda-feed-testo"><?= nl2br(e($copy)) ?></div>
    <?php endif; ?>

    <?php if (($post['cta'] ?? '') !== ''): ?>
      <p class="scheda-feed-cta"><?= e($post['cta']) ?></p>
    <?php endif; ?>

    <div class="scheda-feed-azioni doc-azioni">
      <span class="stato-pill <?= e($stato) ?> etichetta-stato"><?= e(Stati::etichettaPost($stato)) ?></span>

      <?php if ($stato === 'approvato' && ($post['approvato_esecutivo_il'] ?? null) !== null): ?>
        <span class="piccolo sfumato">il <?= e(data_it($post['approvato_esecutivo_il'], false)) ?></span>
      <?php endif; ?>

      <?php if ($agibile && !$anteprima): ?>
        <div class="doc-pulsanti">
          <button type="button" class="btn btn-piccolo azione-modifica">Chiedi modifica</button>
          <button type="button" class="btn btn-piccolo btn-primario azione-approva" style="margin-top:0">Approva</button>
        </div>
      <?php endif; ?>

      <?php if (($post['commento_esecutivo'] ?? '') !== ''): ?>
        <p class="doc-commento"><?= e($post['commento_esecutivo']) ?></p>
      <?php endif; ?>

      <?php if ($agibile && !$anteprima): ?>
        <div class="doc-commento-modulo" hidden>
          <textarea rows="3" placeholder="Cosa vuoi modificare?" aria-label="Richiesta di modifica"></textarea>
          <div class="doc-pulsanti">
            <button type="button" class="btn btn-piccolo btn-primario azione-invia" style="margin-top:0">Invia</button>
            <button type="button" class="btn btn-piccolo azione-annulla">Annulla</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>

<?php if ($inPreparazione !== []): ?>
  <?php
  /* "Altri" solo se sopra c'è davvero qualcosa: in una settimana senza
     nemmeno un esecutivo pronto suonerebbe come se ne avessimo mostrati. */
  $quanti = count($inPreparazione);
  $altri  = $pronti !== [];
  ?>
  <p class="preparazione-titolo">
    <?php if ($quanti === 1): ?>
      <?= $altri ? 'Un altro contenuto è ancora in preparazione:' : 'Un contenuto è ancora in preparazione:' ?>
    <?php else: ?>
      <?= $altri ? 'Altri ' . $quanti : $quanti ?> contenuti sono ancora in preparazione:
    <?php endif; ?>
  </p>

  <?php foreach ($inPreparazione as $post): ?>
    <div class="riga-preparazione">
      <span class="quando">
        <?= e(giorno_it($post['data'])) ?><?php
        ?><?= ($post['ora'] ?? null) !== null ? ' · ' . e(ora_it($post['ora'])) : '' ?>
      </span>
      <span class="canali"><?= Canali::tag($post['canali']) ?></span>
      <span class="idea"><?= e($post['contenuto']) ?></span>
      <span class="in-arrivo">in preparazione</span>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

</div>
