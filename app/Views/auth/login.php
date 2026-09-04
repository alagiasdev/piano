<?php
/** @var string $email */
/** @var string|null $errore */
/** @var bool $senzaUtenti */
?>
<h1 class="titolo-riquadro">Piano<span class="punto">.</span></h1>
<p class="sfumato">Gestionale dei piani editoriali</p>

<?php if ($senzaUtenti): ?>
  <p class="avviso attenzione">
    Non esiste ancora nessun utente. Crealo da riga di comando:<br>
    <code>php database/crea-admin.php tua@email.it "password"</code>
  </p>
<?php endif; ?>

<?php if ($errore !== null): ?>
  <p class="avviso errore"><?= e($errore) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="modulo">
  <?= csrf_field() ?>

  <label for="email">Email</label>
  <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">

  <label for="password">Password</label>
  <input type="password" id="password" name="password" required autocomplete="current-password">

  <button type="submit" class="btn btn-primario">Accedi</button>
</form>
