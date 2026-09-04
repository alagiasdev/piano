<?php

use App\Support\Canali;

/**
 * Layout per l'export PDF: la vista del cliente senza barre, con la
 * finestra di stampa che si apre da sola. Gli stili @media print di
 * app.css fanno il resto.
 */

/** @var string $contenuto */
$titolo = $titolo ?? 'Piano editoriale';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titolo) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<style>:root{<?= Canali::variabiliCss() ?>}</style>
</head>
<body class="pubblica">

<div class="barra-stampa">
  <span>Usa «Salva come PDF» nella finestra di stampa.</span>
  <button type="button" class="btn btn-piccolo" onclick="window.print()">Stampa di nuovo</button>
  <button type="button" class="btn btn-piccolo" onclick="window.close()">Chiudi</button>
</div>

<?= $contenuto ?>

<script>
  /* La finestra di stampa si apre appena i font e le immagini sono pronti,
     altrimenti il documento verrebbe misurato prima del tempo. */
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 250);
  });
</script>
</body>
</html>
