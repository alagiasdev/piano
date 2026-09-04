<?php

use App\Support\Canali;

/** Layout senza barra di navigazione: login e pagine di errore. */

/** @var string $contenuto */
$titolo = $titolo ?? 'Piano';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="base-url" content="<?= e(url("/")) ?>">
<title><?= e($titolo) ?> · Piano</title>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<style>:root{<?= Canali::variabiliCss() ?>}</style>
</head>
<body class="centrata">

<main class="riquadro">
  <?php foreach ($flash ?? [] as $messaggio): ?>
    <p class="avviso <?= e($messaggio['tipo']) ?>"><?= e($messaggio['testo']) ?></p>
  <?php endforeach; ?>

  <?= $contenuto ?>
</main>

</body>
</html>
