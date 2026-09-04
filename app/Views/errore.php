<?php
/** @var int $codice */
/** @var string $titolo */
/** @var string $dettaglio */
?>
<h1 class="errore-codice"><?= e($codice) ?></h1>
<p class="errore-titolo"><?= e($titolo) ?></p>
<?php if (($dettaglio ?? '') !== ''): ?>
  <p class="sfumato"><?= e($dettaglio) ?></p>
<?php endif; ?>
<p><a href="<?= e(url('/')) ?>">Torna alla dashboard</a></p>
