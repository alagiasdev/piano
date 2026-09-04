<?php

use App\Core\Auth;
use App\Support\Canali;

/** @var array<string,mixed> $cliente */
/** @var array<string,string> $errori */

$nuovo  = $cliente['id'] === null;
$azione = $nuovo ? url('/clienti') : url('/clienti/' . $cliente['id']);
?>
<div class="intestazione-pagina">
  <h1><?= $nuovo ? 'Nuovo cliente' : e($cliente['nome']) ?></h1>
  <a class="btn btn-piccolo" href="<?= e(url('/clienti')) ?>">← Tutti i clienti</a>
</div>

<form method="post" action="<?= e($azione) ?>" enctype="multipart/form-data" class="due-colonne">
  <?= csrf_field() ?>

  <div class="scheda">
    <h2>Anagrafica</h2>

    <div class="campo">
      <label for="nome">Nome <span class="obbligatorio">*</span></label>
      <input type="text" id="nome" name="nome" value="<?= e($cliente['nome']) ?>" required maxlength="120" autofocus>
      <?php if (isset($errori['nome'])): ?><p class="errore-campo"><?= e($errori['nome']) ?></p><?php endif; ?>
    </div>

    <div class="campo">
      <label for="contatto_nome">Referente</label>
      <input type="text" id="contatto_nome" name="contatto_nome" value="<?= e($cliente['contatto_nome']) ?>" maxlength="120">
    </div>

    <div class="campo">
      <label for="contatto_email">Email del referente</label>
      <input type="email" id="contatto_email" name="contatto_email" value="<?= e($cliente['contatto_email']) ?>" maxlength="190">
      <?php if (isset($errori['contatto_email'])): ?><p class="errore-campo"><?= e($errori['contatto_email']) ?></p><?php endif; ?>
    </div>

    <div class="campo">
      <span class="etichetta">Canali attivi</span>
      <div class="spunte">
        <?php foreach (Canali::ELENCO as $codice => $canale): ?>
          <label class="spunta">
            <input type="checkbox" name="canali[]" value="<?= e($codice) ?>"
              <?= in_array($codice, $cliente['canali'], true) ? 'checked' : '' ?>>
            <span class="tag <?= e($codice) ?>"><?= e($canale['etichetta']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="campo">
      <label class="spunta">
        <input type="checkbox" name="attivo" value="1" <?= (int) $cliente['attivo'] === 1 ? 'checked' : '' ?>>
        <span>Cliente attivo</span>
      </label>
      <p class="aiuto">I clienti non attivi restano consultabili ma spariscono dalla dashboard.</p>
    </div>
  </div>

  <div class="scheda">
    <h2>Logo</h2>

    <?php if ($cliente['logo_path'] !== null): ?>
      <img class="logo-grande" src="<?= e(url('/' . $cliente['logo_path'])) ?>" alt="Logo di <?= e($cliente['nome']) ?>">
      <label class="spunta">
        <input type="checkbox" name="rimuovi_logo" value="1">
        <span>Rimuovi il logo attuale</span>
      </label>
    <?php endif; ?>

    <div class="campo">
      <label for="logo"><?= $cliente['logo_path'] !== null ? 'Sostituisci con' : 'Carica un logo' ?></label>
      <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.svg,image/jpeg,image/png,image/svg+xml">
      <p class="aiuto">JPG, PNG o SVG, massimo 1 MB. Compare nella pagina di approvazione del cliente.</p>
      <?php if (isset($errori['logo'])): ?><p class="errore-campo"><?= e($errori['logo']) ?></p><?php endif; ?>
    </div>

    <h2 style="margin-top:24px">Indicazioni editoriali</h2>

    <div class="campo">
      <label for="tono_di_voce">Tono di voce</label>
      <textarea id="tono_di_voce" name="tono_di_voce" rows="4"><?= e($cliente['tono_di_voce']) ?></textarea>
    </div>

    <div class="campo">
      <label for="note">Note interne</label>
      <textarea id="note" name="note" rows="4"><?= e($cliente['note']) ?></textarea>
    </div>
  </div>

  <div class="barra-azioni">
    <button type="submit" class="btn btn-primario" style="margin-top:0">
      <?= $nuovo ? 'Crea cliente' : 'Salva modifiche' ?>
    </button>
    <?php if (!$nuovo): ?>
      <a class="btn" href="<?= e(url('/clienti/' . $cliente['id'] . '/piani')) ?>">Piani del cliente</a>
    <?php endif; ?>
  </div>
</form>

<?php /* Eliminare un cliente porta via anche piani e post: solo admin */ ?>
<?php if (!$nuovo && Auth::amministratore()): ?>
  <form class="scheda pericolo" method="post"
        action="<?= e(url('/clienti/' . $cliente['id'] . '/elimina')) ?>"
        data-conferma="Eliminare <?= e($cliente['nome']) ?> con tutti i suoi piani e post? L'operazione non è annullabile.">
    <?= csrf_field() ?>
    <div>
      <h2>Elimina cliente</h2>
      <p class="sfumato piccolo">
        Vengono eliminati anche
        <?= (int) $cliente['piani_totali'] === 1 ? 'il piano collegato' : (int) $cliente['piani_totali'] . ' piani collegati' ?>
        e tutti i loro post.
      </p>
    </div>
    <button type="submit" class="btn btn-pericolo">Elimina</button>
  </form>
<?php endif; ?>
