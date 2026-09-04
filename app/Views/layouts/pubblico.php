<?php

use App\Core\Csrf;
use App\Support\Canali;

/**
 * Layout della pagina che vede il cliente: un foglio bianco, niente
 * navigazione, stessa impronta del PDF. Gli stili di stampa vivono in
 * app.css, cosi "Stampa" da qui produce lo stesso documento dell'export.
 */

/** @var string $contenuto */
$titolo = $titolo ?? 'Piano editoriale';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titolo) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<style>:root{<?= Canali::variabiliCss() ?>}</style>
</head>
<body class="pubblica">

<?= $contenuto ?>

<script src="<?= e(asset('assets/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/pubblico.js')) ?>" defer></script>
</body>
</html>
