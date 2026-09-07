<?php
/** @var array<string,mixed> $utente */
/** @var int $ioSono */
/** @var array<string,string> $errori */
/** @var array<string,mixed> $vecchi */
/** @var array<int,array<string,mixed>> $clienti */
/** @var array<int,int> $assegnati */

$sonoIo = (int) $utente['id'] === $ioSono;
$v = static fn (string $campo, mixed $default) => $vecchi[$campo] ?? $default;
?>
<div class="intestazione-pagina">
  <div>
    <h1><?= e($utente['nome']) ?></h1>
    <?php if ($sonoIo): ?>
      <p class="sfumato">Questo sei tu.</p>
    <?php endif; ?>
  </div>
  <a class="btn btn-piccolo" href="<?= e(url('/utenti')) ?>">← Tutti i collaboratori</a>
</div>

<form method="post" action="<?= e(url('/utenti/' . $utente['id'])) ?>" class="scheda" style="max-width:640px">
  <?= csrf_field() ?>

  <div class="campo">
    <label for="nome">Nome <span class="obbligatorio">*</span></label>
    <input type="text" id="nome" name="nome" maxlength="80" required
           value="<?= e($v('nome', $utente['nome'])) ?>">
    <?php if (isset($errori['nome'])): ?><p class="errore-campo"><?= e($errori['nome']) ?></p><?php endif; ?>
  </div>

  <div class="campo">
    <label for="email">Email <span class="obbligatorio">*</span></label>
    <input type="email" id="email" name="email" maxlength="190" required
           value="<?= e($v('email', $utente['email'])) ?>">
    <?php if (isset($errori['email'])): ?><p class="errore-campo"><?= e($errori['email']) ?></p><?php endif; ?>
  </div>

  <div class="campo">
    <label for="password">Nuova password</label>
    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password"
           placeholder="lascia vuoto per non cambiarla">
    <p class="aiuto">Almeno 8 caratteri. Serve per reimpostarla a chi l'ha dimenticata.</p>
    <?php if (isset($errori['password'])): ?><p class="errore-campo"><?= e($errori['password']) ?></p><?php endif; ?>
  </div>

  <hr class="separatore">

  <?php if ($sonoIo): ?>
    <p class="avviso attenzione">
      Sul tuo account non puoi togliere i permessi di amministratore né
      disattivarti: sarebbe il modo più rapido per restare fuori.
    </p>
  <?php else: ?>
    <div class="campo">
      <label class="spunta">
        <input type="checkbox" name="amministratore" value="1"
          <?= $v('amministratore', (int) $utente['amministratore'] === 1) ? 'checked' : '' ?>>
        <span>Amministratore</span>
      </label>
      <p class="aiuto">Gestisce collaboratori, impostazioni ed eliminazioni.</p>
      <?php if (isset($errori['amministratore'])): ?><p class="errore-campo"><?= e($errori['amministratore']) ?></p><?php endif; ?>
    </div>

    <div class="campo">
      <label class="spunta">
        <input type="checkbox" name="attivo" value="1"
          <?= $v('attivo', (int) $utente['attivo'] === 1) ? 'checked' : '' ?>>
        <span>Account attivo</span>
      </label>
      <p class="aiuto">
        Togliendo la spunta l'accesso è bloccato subito, ma l'account resta.
        È il modo giusto per chi non collabora più.
      </p>
    </div>
  <?php endif; ?>

  <hr class="separatore">

  <?php /* Le assegnazioni si mostrano anche agli amministratori, che tanto
           vedono tutto: così restano scritte, e il giorno che uno torna
           collaboratore non si ritrova di colpo senza nessun cliente. */ ?>
  <div class="campo">
    <label>Clienti su cui può lavorare</label>

    <?php if ((int) $utente['amministratore'] === 1): ?>
      <p class="aiuto">
        È un amministratore: vede <strong>tutti</strong> i clienti, presenti e futuri,
        qualunque cosa sia spuntata qui sotto. Le spunte tornano a contare se un
        giorno lo riporti a collaboratore.
      </p>
    <?php elseif ($clienti === []): ?>
      <p class="aiuto">Non c'è ancora nessun cliente da assegnare.</p>
    <?php else: ?>
      <p class="aiuto">
        Vede solo questi, e solo i loro piani. Senza nessuna spunta non vede niente.
      </p>
    <?php endif; ?>

    <?php if ($clienti !== []): ?>
      <div class="elenco-spunte">
        <?php foreach ($clienti as $unCliente): ?>
          <label class="spunta">
            <input type="checkbox" name="clienti[]" value="<?= (int) $unCliente['id'] ?>"
              <?= in_array((int) $unCliente['id'], $assegnati, true) ? 'checked' : '' ?>>
            <span>
              <?= e($unCliente['nome']) ?>
              <?php if ((int) $unCliente['attivo'] !== 1): ?>
                <span class="sfumato piccolo">(non attivo)</span>
              <?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-primario">Salva</button>
</form>

<?php if (!$sonoIo): ?>
  <form class="scheda pericolo" method="post" action="<?= e(url('/utenti/' . $utente['id'] . '/elimina')) ?>"
        data-conferma="Eliminare l'account di <?= e($utente['nome']) ?>?">
    <?= csrf_field() ?>
    <div>
      <h2>Elimina account</h2>
      <p class="sfumato piccolo">
        Se la persona potrebbe tornare, meglio disattivarlo: l'eliminazione
        non si annulla. I piani e i post che ha creato restano.
      </p>
    </div>
    <button type="submit" class="btn btn-pericolo">Elimina</button>
  </form>
<?php endif; ?>
