<?php

use App\Support\Stati;

/** @var array<int,array<string,mixed>> $piani */
/** @var array<string,mixed>|null $cliente */

$urlNuovo = url('/piani/nuovo') . ($cliente !== null ? '?cliente_id=' . (int) $cliente['id'] : '');
?>
<div class="intestazione-pagina">
  <div>
    <h1><?= $cliente !== null ? 'Piani di ' . e($cliente['nome']) : 'Piani' ?></h1>
    <?php if ($cliente !== null): ?>
      <p class="sfumato"><a href="<?= e(url('/clienti/' . $cliente['id'] . '/modifica')) ?>">Scheda cliente</a> · <a href="<?= e(url('/piani')) ?>">Tutti i piani</a></p>
    <?php endif; ?>
  </div>
  <a class="btn btn-primario" style="margin-top:0" href="<?= e($urlNuovo) ?>">Nuovo piano</a>
</div>

<div class="scheda" style="padding:0;overflow:hidden">
<?php if ($piani === []): ?>
  <p class="vuoto">Nessun piano. Comincia da <a href="<?= e($urlNuovo) ?>">Nuovo piano</a>.</p>
<?php else: ?>
  <table class="tabella">
    <thead>
      <tr>
        <th>Piano</th>
        <?php if ($cliente === null): ?><th style="width:180px">Cliente</th><?php endif; ?>
        <th style="width:190px">Periodo</th>
        <th style="width:150px">Stato</th>
        <th style="width:170px">Approvazione</th>
        <th style="width:200px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($piani as $piano): ?>
        <?php
        $totali    = (int) $piano['post_totali'];
        $approvati = (int) $piano['post_approvati'];
        $quota     = $totali > 0 ? (int) round($approvati / $totali * 100) : 0;
        ?>
        <tr>
          <td data-etichetta="Piano">
            <a href="<?= e(url('/piani/' . $piano['id'])) ?>"><strong><?= e($piano['titolo']) ?></strong></a>
            <?php if ((int) $piano['post_da_rivedere'] > 0): ?>
              <br><span class="stato-pill da_rivedere"><?= (int) $piano['post_da_rivedere'] ?> da rivedere</span>
            <?php endif; ?>
          </td>
          <?php if ($cliente === null): ?>
            <td class="piccolo" data-etichetta="Cliente"><a href="<?= e(url('/clienti/' . $piano['cliente_id'] . '/piani')) ?>"><?= e($piano['cliente_nome']) ?></a></td>
          <?php endif; ?>
          <td class="piccolo sfumato" data-etichetta="Periodo"><?= e(periodo_it($piano['data_inizio'], $piano['data_fine'])) ?></td>
          <td data-etichetta="Stato"><span class="stato-pill <?= e($piano['stato']) ?>"><?= e(Stati::etichettaPiano((string) $piano['stato'])) ?></span></td>
          <td data-etichetta="Approvazione">
            <div class="avanzamento" title="<?= $approvati ?> di <?= $totali ?> approvati">
              <div class="avanzamento-barra" style="width:<?= $quota ?>%"></div>
            </div>
            <span class="piccolo sfumato"><?= $approvati ?>/<?= $totali ?> post</span>
          </td>
          <td class="azioni">
            <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $piano['id'])) ?>">Apri</a>
            <form method="post" action="<?= e(url('/piani/' . $piano['id'] . '/duplica')) ?>" style="display:inline"
                  data-conferma="Duplicare «<?= e($piano['titolo']) ?>» spostando tutto di 4 settimane?">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-piccolo" title="Copia il piano 4 settimane più avanti">Duplica</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
