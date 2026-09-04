<?php

use App\Support\Stati;

/** @var array<string,mixed> $piano */
/** @var array<string,array<int,array<string,mixed>>> $perGiorno */
/** @var array<int,DateTimeImmutable> $mesi */

$idPiano = (int) $piano['id'];
$oggi    = date('Y-m-d');
?>
<div class="barra-piano">
  <div class="barra-piano-info">
    <h1>Calendario · <?= e($piano['titolo']) ?></h1>
    <p class="sfumato piccolo">
      <?= e($piano['cliente_nome']) ?> · <?= e(periodo_it($piano['data_inizio'], $piano['data_fine'])) ?>
      · sola lettura: clicca un post per modificarlo nella tabella
    </p>
  </div>
  <div class="barra-piano-azioni">
    <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $idPiano)) ?>">← Torna alla tabella</a>
  </div>
</div>

<?php foreach ($mesi as $mese): ?>
  <?php
  $primo       = $mese;
  $ultimo      = $mese->modify('last day of this month');
  $inizioGriglia = $primo->modify('-' . ((int) $primo->format('N') - 1) . ' days');
  $fineGriglia   = $ultimo->modify('+' . (7 - (int) $ultimo->format('N')) . ' days');
  ?>
  <section class="scheda mese">
    <h2><?= e(mese_it($primo)) ?></h2>

    <div class="griglia-mese">
      <?php foreach (GIORNI_IT_BREVI as $nomeGiorno): ?>
        <div class="griglia-testa"><?= e($nomeGiorno) ?></div>
      <?php endforeach; ?>

      <?php for ($giorno = $inizioGriglia; $giorno <= $fineGriglia; $giorno = $giorno->modify('+1 day')): ?>
        <?php
        $data       = $giorno->format('Y-m-d');
        $postDelDi  = $perGiorno[$data] ?? [];
        $fuoriMese  = $giorno->format('n') !== $primo->format('n');
        $classi     = 'griglia-giorno';
        $classi    .= $fuoriMese ? ' fuori' : '';
        $classi    .= $data === $oggi ? ' oggi' : '';
        $classi    .= count($postDelDi) >= 3 ? ' pieno' : '';
        // Su mobile la griglia diventa una lista: i giorni senza post si nascondono
        $classi    .= $postDelDi === [] ? ' senza-post' : '';
        ?>
        <div class="<?= $classi ?>">
          <div class="griglia-numero">
            <?php /* Il nome del giorno serve solo nella lista mobile, dove
                     mancano le intestazioni delle colonne. */ ?>
            <span class="griglia-nome"><?= e(GIORNI_IT_BREVI[(int) $giorno->format('N') - 1]) ?></span>
            <?= (int) $giorno->format('j') ?>
          </div>

          <?php foreach ($postDelDi as $post): ?>
            <a class="chip" href="<?= e(url('/piani/' . $idPiano)) ?>#post-<?= (int) $post['id'] ?>"
               title="<?= e(Stati::etichettaPost((string) $post['stato'])) ?> — <?= e($post['contenuto']) ?>">
              <?php foreach ($post['canali'] as $canale): ?>
                <span class="pallino <?= e($canale) ?>"></span>
              <?php endforeach; ?>
              <?php if (($post['ora'] ?? null) !== null): ?>
                <span class="chip-ora"><?= e(ora_it($post['ora'])) ?></span>
              <?php endif; ?>
              <?php /* Il taglio lo fa il CSS: su desktop con i puntini di
                       sospensione, su mobile mandando a capo. Qui si limita
                       solo la lunghezza esagerata. */ ?>
              <span class="chip-testo"><?= e(mb_strimwidth((string) $post['contenuto'], 0, 120, '…')) ?></span>
              <?php if ($post['stato'] === 'da_rivedere'): ?><span class="chip-segno">!</span><?php endif; ?>
              <?php if ($post['stato'] === 'approvato' || $post['stato'] === 'pubblicato'): ?><span class="chip-segno ok">✓</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </section>
<?php endforeach; ?>

<div class="scheda legenda">
  <?php foreach (App\Support\Canali::ELENCO as $codice => $canale): ?>
    <span class="voce-legenda"><span class="pallino <?= e($codice) ?>"></span><?= e($canale['etichetta']) ?></span>
  <?php endforeach; ?>
  <span class="voce-legenda"><span class="chip-segno ok">✓</span>approvato</span>
  <span class="voce-legenda"><span class="chip-segno">!</span>da rivedere</span>
</div>
