<?php
/** @var array<int,string> $sospese */
/** @var array<string,string> $errori */
/** @var array<string,mixed> $vecchi */
?>
<h1 class="titolo-riquadro">Piano<span class="punto">.</span></h1>
<p class="sfumato">Primo avvio: creiamo le tabelle e il tuo account.</p>

<?php if (isset($errori['migrazioni'])): ?>
  <p class="avviso errore fisso"><?= e($errori['migrazioni']) ?></p>
<?php endif; ?>

<?php if ($sospese === []): ?>
  <p class="avviso attenzione fisso">
    Nessuna migrazione da applicare: le tabelle ci sono già, oppure il database
    non è raggiungibile. Se è il secondo caso, controlla i valori <code>DB_*</code>
    nel file <code>.env</code>.
  </p>
<?php else: ?>
  <p class="sfumato piccolo">
    Da applicare: <?= count($sospese) === 1 ? '1 migrazione' : count($sospese) . ' migrazioni' ?>.
  </p>
<?php endif; ?>

<form method="post" action="<?= e(url('/installazione')) ?>" class="modulo">
  <?= csrf_field() ?>

  <label for="token">Codice di installazione</label>
  <input type="text" id="token" name="token" required autocomplete="off" autofocus>
  <p class="aiuto">È il valore di <code>SETUP_TOKEN</code> nel file <code>.env</code>.</p>
  <?php if (isset($errori['token'])): ?><p class="errore-campo"><?= e($errori['token']) ?></p><?php endif; ?>

  <label for="nome">Il tuo nome</label>
  <input type="text" id="nome" name="nome" maxlength="80" required
         value="<?= e($vecchi['nome'] ?? '') ?>">
  <?php if (isset($errori['nome'])): ?><p class="errore-campo"><?= e($errori['nome']) ?></p><?php endif; ?>

  <label for="email">Email</label>
  <input type="email" id="email" name="email" maxlength="190" required autocomplete="username"
         value="<?= e($vecchi['email'] ?? '') ?>">
  <?php if (isset($errori['email'])): ?><p class="errore-campo"><?= e($errori['email']) ?></p><?php endif; ?>

  <label for="password">Password</label>
  <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
  <p class="aiuto">Almeno 8 caratteri. Sarai il primo amministratore.</p>
  <?php if (isset($errori['password'])): ?><p class="errore-campo"><?= e($errori['password']) ?></p><?php endif; ?>

  <button type="submit" class="btn btn-primario">Installa</button>
</form>

<p class="aiuto" style="margin-top:18px">
  Dopo l'installazione questa pagina non sarà più raggiungibile: esiste solo
  finché non c'è nessun account. Puoi togliere <code>SETUP_TOKEN</code> dal
  <code>.env</code> quando hai finito.
</p>
