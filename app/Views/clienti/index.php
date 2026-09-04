<?php

use App\Support\Canali;

/** @var array<int,array<string,mixed>> $clienti */
?>
<div class="intestazione-pagina">
  <h1>Clienti</h1>
  <a class="btn btn-primario" style="margin-top:0" href="<?= e(url('/clienti/nuovo')) ?>">Nuovo cliente</a>
</div>

<div class="scheda" style="padding:0;overflow:hidden">
<?php if ($clienti === []): ?>
  <p class="vuoto">Nessun cliente. Comincia da <a href="<?= e(url('/clienti/nuovo')) ?>">Nuovo cliente</a>.</p>
<?php else: ?>
  <table class="tabella">
    <thead>
      <tr>
        <th style="width:56px"></th>
        <th>Cliente</th>
        <th style="width:220px">Canali</th>
        <th style="width:200px">Contatto</th>
        <th style="width:80px">Piani</th>
        <th style="width:170px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clienti as $cliente): ?>
        <tr<?= (int) $cliente['attivo'] === 0 ? ' class="spento"' : '' ?>>
          <td class="cella-logo">
            <?php if ($cliente['logo_path'] !== null): ?>
              <img class="logo-mini" src="<?= e(url('/' . $cliente['logo_path'])) ?>" alt="">
            <?php else: ?>
              <span class="logo-mini vuoto-logo"><?= e(mb_strtoupper(mb_substr((string) $cliente['nome'], 0, 1))) ?></span>
            <?php endif; ?>
          </td>
          <td data-etichetta="Cliente">
            <a href="<?= e(url('/clienti/' . $cliente['id'] . '/modifica')) ?>"><strong><?= e($cliente['nome']) ?></strong></a>
            <?php if ((int) $cliente['attivo'] === 0): ?>
              <span class="stato-pill chiuso">Non attivo</span>
            <?php endif; ?>
          </td>
          <td data-etichetta="Canali"><?= Canali::tag($cliente['canali']) ?: '<span class="sfumato piccolo">—</span>' ?></td>
          <td class="piccolo sfumato" data-etichetta="Contatto">
            <?= e($cliente['contatto_nome'] ?? '') ?>
            <?php if (($cliente['contatto_email'] ?? '') !== ''): ?>
              <br><?= e($cliente['contatto_email']) ?>
            <?php endif; ?>
          </td>
          <td data-etichetta="Piani"><?= (int) $cliente['piani_totali'] ?></td>
          <td class="azioni">
            <a class="btn btn-piccolo" href="<?= e(url('/clienti/' . $cliente['id'] . '/piani')) ?>">Piani</a>
            <a class="btn btn-piccolo" href="<?= e(url('/clienti/' . $cliente['id'] . '/modifica')) ?>">Modifica</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
