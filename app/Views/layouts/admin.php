<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Impostazione;
use App\Support\Canali;

/** @var string $contenuto */
/** @var array<int,array{tipo:string,testo:string}> $flash */
$titolo = $titolo ?? 'Piano';
$utente = Auth::utente();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<meta name="base-url" content="<?= e(url("/")) ?>">
<title><?= e($titolo) ?> · Piano</title>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<style>:root{<?= Canali::variabiliCss() ?>}</style>
</head>
<body>

<header class="topbar">
  <a class="marchio" href="<?= e(url('/')) ?>">Piano<span>.</span></a>

  <nav class="menu">
    <a href="<?= e(url('/')) ?>"<?= attiva_se('/') ?>>Dashboard</a>
    <a href="<?= e(url('/clienti')) ?>"<?= attiva_se('/clienti') ?>>Clienti</a>
    <a href="<?= e(url('/piani')) ?>"<?= attiva_se('/piani') ?>>Piani</a>
    <?php if (Auth::amministratore()): ?>
      <a href="<?= e(url('/utenti')) ?>"<?= attiva_se('/utenti') ?>>Collaboratori</a>
    <?php endif; ?>
    <a href="<?= e(url('/impostazioni')) ?>"<?= attiva_se('/impostazioni') ?>>Impostazioni</a>
  </nav>

  <?php if ($utente !== null): ?>
    <form class="uscita" method="post" action="<?= e(url('/logout')) ?>">
      <?= csrf_field() ?>
      <span class="chi"><?= e($utente['nome']) ?></span>
      <button type="submit" class="btn-testo">Esci</button>
    </form>
  <?php endif; ?>
</header>

<main class="contenitore<?= !empty($largo) ? ' largo' : '' ?>">
  <?php foreach ($flash ?? [] as $messaggio): ?>
    <p class="avviso <?= e($messaggio['tipo']) ?>"><?= e($messaggio['testo']) ?></p>
  <?php endforeach; ?>

  <?= $contenuto ?>
</main>

<footer class="pie">
  <?= e(Impostazione::get('nome_studio', 'Piano')) ?>
</footer>

<script src="<?= e(asset('assets/app.js')) ?>" defer></script>
</body>
</html>
