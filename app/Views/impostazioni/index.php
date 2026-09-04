<?php

use App\Core\Auth;
use App\Support\Elenchi;

/** @var array<string,string> $valori */
/** @var array<string,mixed> $utente */
/** @var array<string,string> $errori */

$amministratore = Auth::amministratore();
?>
<div class="intestazione-pagina">
  <h1>Impostazioni</h1>
</div>

<div class="due-colonne">

  <?php if (!$amministratore): ?>
    <div class="scheda">
      <h2>Studio</h2>
      <p class="sfumato piccolo">
        Nome dello studio, firma e nota standard per il cliente sono gestiti
        dagli amministratori: valgono per tutti i clienti.
      </p>
      <p class="sfumato piccolo"><strong><?= e($valori['nome_studio'] ?? '') ?></strong></p>
    </div>
  <?php else: ?>
  <form method="post" action="<?= e(url('/impostazioni')) ?>" class="scheda">
    <?= csrf_field() ?>
    <h2>Studio</h2>

    <div class="campo">
      <label for="nome_studio">Nome dello studio</label>
      <input type="text" id="nome_studio" name="nome_studio" maxlength="120"
             value="<?= e($valori['nome_studio'] ?? '') ?>">
      <p class="aiuto">Compare in alto a destra nella pagina del cliente e nel PDF.</p>
    </div>

    <div class="campo">
      <label for="firma">Firma in fondo al documento</label>
      <input type="text" id="firma" name="firma" maxlength="190"
             value="<?= e($valori['firma'] ?? '') ?>">
    </div>

    <div class="campo">
      <label for="nota_standard">Nota standard per il cliente</label>
      <textarea id="nota_standard" name="nota_standard" rows="6"><?= e($valori['nota_standard'] ?? '') ?></textarea>
      <p class="aiuto">
        Usata in cima alla pagina di approvazione quando il piano non ha
        una nota sua. Tipicamente tempi di consegna e regole di feedback.
      </p>
    </div>

    <hr class="separatore">

    <h2>Scelte rapide dell'editor</h2>
    <p class="sfumato piccolo">
      Le voci che compaiono cliccando la freccetta accanto a «Formato» e
      «Call to action». Una per riga. I campi restano comunque a scrittura
      libera: l'elenco serve solo a fare prima.
      Lasciando vuota una casella si torna alle voci di partenza.
    </p>

    <div class="campo">
      <label for="elenco_formati">Formati</label>
      <textarea id="elenco_formati" name="elenco_formati" rows="6"
                placeholder="<?= e(Elenchi::formatiPredefiniti()) ?>"><?= e($valori['elenco_formati'] ?? '') ?></textarea>
    </div>

    <div class="campo">
      <label for="elenco_cta">Call to action</label>
      <textarea id="elenco_cta" name="elenco_cta" rows="6"
                placeholder="<?= e(Elenchi::ctaPredefinite()) ?>"><?= e($valori['elenco_cta'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primario">Salva impostazioni</button>
  </form>
  <?php endif; ?>

  <div>
    <form method="post" action="<?= e(url('/impostazioni/password')) ?>" class="scheda">
      <?= csrf_field() ?>
      <h2>Password</h2>
      <p class="sfumato piccolo">
        Accesso come <strong><?= e($utente['email']) ?></strong>
        <?php if (($utente['ultimo_accesso'] ?? null) !== null): ?>
          · ultimo accesso <?= e(data_it($utente['ultimo_accesso'], false)) ?>
        <?php endif; ?>
      </p>

      <div class="campo">
        <label for="password_attuale">Password attuale</label>
        <input type="password" id="password_attuale" name="password_attuale" required autocomplete="current-password">
        <?php if (isset($errori['password_attuale'])): ?><p class="errore-campo"><?= e($errori['password_attuale']) ?></p><?php endif; ?>
      </div>

      <div class="campo">
        <label for="password_nuova">Nuova password</label>
        <input type="password" id="password_nuova" name="password_nuova" required minlength="8" autocomplete="new-password">
        <?php if (isset($errori['password_nuova'])): ?><p class="errore-campo"><?= e($errori['password_nuova']) ?></p><?php endif; ?>
      </div>

      <div class="campo">
        <label for="password_conferma">Ripeti la nuova password</label>
        <input type="password" id="password_conferma" name="password_conferma" required minlength="8" autocomplete="new-password">
        <?php if (isset($errori['password_conferma'])): ?><p class="errore-campo"><?= e($errori['password_conferma']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primario">Cambia password</button>
    </form>

    <div class="scheda">
      <h2>Collaboratori</h2>
      <?php if ($amministratore): ?>
        <p class="sfumato piccolo">
          Ogni persona ha il suo account: così le password non si condividono
          e si può togliere l'accesso a uno solo.
        </p>
        <a class="btn" href="<?= e(url('/utenti')) ?>">Gestisci i collaboratori</a>
      </div>

      <div class="scheda">
        <h2>Verifica installazione</h2>
        <p class="sfumato piccolo">
          Controlla versione PHP, estensioni, database, migrazioni, permessi
          sulle cartelle e che <code>.env</code> non sia raggiungibile dal web.
          Da usare prima di mettere online e ogni volta che qualcosa non torna.
        </p>
        <a class="btn" href="<?= e(url('/verifica')) ?>">Esegui la verifica</a>
      <?php else: ?>
        <p class="sfumato piccolo">
          Il tuo account è un account collaboratore: puoi gestire clienti,
          piani e post. Utenti, impostazioni dello studio ed eliminazioni sono
          riservati agli amministratori.
        </p>
      <?php endif; ?>
    </div>

    <div class="scheda">
      <h2>Notifiche</h2>
      <p class="sfumato piccolo">
        Le approvazioni e le richieste di modifica dei clienti si leggono
        nella dashboard, sotto «Novità dai clienti». Non viene inviata
        nessuna email.
      </p>
    </div>
  </div>

</div>
