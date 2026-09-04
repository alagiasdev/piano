<?php
/** @var array<int,array<string,mixed>> $utenti */
/** @var int $ioSono */
/** @var array<string,string> $errori */
/** @var array<string,mixed> $vecchi */
?>
<div class="intestazione-pagina">
  <div>
    <h1>Collaboratori</h1>
    <p class="sfumato">Chi può entrare nel gestionale.</p>
  </div>
</div>

<div class="scheda" style="padding:0;overflow:hidden">
  <table class="tabella">
    <thead>
      <tr>
        <th>Nome</th>
        <th style="width:230px">Email</th>
        <th style="width:150px">Livello</th>
        <th style="width:150px">Ultimo accesso</th>
        <th style="width:110px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($utenti as $utente): ?>
        <tr<?= (int) $utente['attivo'] === 0 ? ' class="spento"' : '' ?>>
          <td data-etichetta="Nome">
            <strong><?= e($utente['nome']) ?></strong>
            <?php if ((int) $utente['id'] === $ioSono): ?>
              <span class="stato-pill inviato">tu</span>
            <?php endif; ?>
            <?php if ((int) $utente['attivo'] === 0): ?>
              <span class="stato-pill chiuso">Disattivato</span>
            <?php endif; ?>
          </td>
          <td class="piccolo sfumato" data-etichetta="Email"><?= e($utente['email']) ?></td>
          <td data-etichetta="Livello">
            <?php if ((int) $utente['amministratore'] === 1): ?>
              <span class="stato-pill approvato">Amministratore</span>
            <?php else: ?>
              <span class="stato-pill bozza">Collaboratore</span>
            <?php endif; ?>
          </td>
          <td class="piccolo sfumato" data-etichetta="Ultimo accesso">
            <?= ($utente['ultimo_accesso'] ?? null) !== null ? e(data_it($utente['ultimo_accesso'], false)) : 'mai' ?>
          </td>
          <td class="azioni">
            <a class="btn btn-piccolo" href="<?= e(url('/utenti/' . $utente['id'])) ?>">Modifica</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post" action="<?= e(url('/utenti')) ?>" class="scheda" style="margin-top:20px">
  <?= csrf_field() ?>
  <h2>Nuovo collaboratore</h2>
  <p class="sfumato piccolo">
    Password e email gliele comunichi tu: il gestionale non manda email.
    Potrà cambiare la password da «Impostazioni» quando entra.
  </p>

  <div class="riga-campi">
    <div class="campo" style="flex:1 1 180px">
      <label for="nome">Nome <span class="obbligatorio">*</span></label>
      <input type="text" id="nome" name="nome" maxlength="80" required
             value="<?= e($vecchi['nome'] ?? '') ?>">
      <?php if (isset($errori['nome'])): ?><p class="errore-campo"><?= e($errori['nome']) ?></p><?php endif; ?>
    </div>

    <div class="campo" style="flex:1 1 220px">
      <label for="email">Email <span class="obbligatorio">*</span></label>
      <input type="email" id="email" name="email" maxlength="190" required autocomplete="off"
             value="<?= e($vecchi['email'] ?? '') ?>">
      <?php if (isset($errori['email'])): ?><p class="errore-campo"><?= e($errori['email']) ?></p><?php endif; ?>
    </div>

    <div class="campo" style="flex:1 1 180px">
      <label for="password">Password <span class="obbligatorio">*</span></label>
      <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
      <?php if (isset($errori['password'])): ?><p class="errore-campo"><?= e($errori['password']) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="campo">
    <label class="spunta">
      <input type="checkbox" name="amministratore" value="1"
        <?= !empty($vecchi['amministratore']) ? 'checked' : '' ?>>
      <span>Amministratore</span>
    </label>
    <p class="aiuto">
      Gli amministratori gestiscono i collaboratori, le impostazioni dello studio
      e possono eliminare clienti e piani. I collaboratori fanno tutto il resto.
    </p>
  </div>

  <button type="submit" class="btn btn-primario">Aggiungi</button>
</form>
