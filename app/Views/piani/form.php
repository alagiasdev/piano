<?php
/** @var array<int,array<string,mixed>> $clienti */
/** @var array<string,mixed> $valori */
/** @var array<string,string> $errori */

$annoCorrente = (int) date('Y');
?>
<div class="intestazione-pagina">
  <h1>Nuovo piano</h1>
  <a class="btn btn-piccolo" href="<?= e(url('/piani')) ?>">← Tutti i piani</a>
</div>

<form method="post" action="<?= e(url('/piani')) ?>">
  <?= csrf_field() ?>

  <div class="scheda" style="max-width:620px">
    <div class="campo">
      <label for="cliente_id">Cliente <span class="obbligatorio">*</span></label>
      <select id="cliente_id" name="cliente_id" required>
        <option value="">— scegli —</option>
        <?php foreach ($clienti as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $valori['cliente_id'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errori['cliente_id'])): ?><p class="errore-campo"><?= e($errori['cliente_id']) ?></p><?php endif; ?>
    </div>

    <div class="campo">
      <span class="etichetta">Periodo</span>

      <label class="spunta">
        <input type="radio" name="modo" value="mese" <?= $valori['modo'] !== 'date' ? 'checked' : '' ?> data-mostra="periodo-mese">
        <span>Un mese intero</span>
      </label>

      <div class="gruppo-periodo" id="periodo-mese">
        <select name="mese" aria-label="Mese">
          <?php foreach (MESI_IT as $numero => $nome): ?>
            <option value="<?= $numero ?>" <?= (int) $valori['mese'] === $numero ? 'selected' : '' ?>><?= e(ucfirst($nome)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="anno" aria-label="Anno">
          <?php for ($anno = $annoCorrente - 1; $anno <= $annoCorrente + 2; $anno++): ?>
            <option value="<?= $anno ?>" <?= (int) $valori['anno'] === $anno ? 'selected' : '' ?>><?= $anno ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <label class="spunta" style="margin-top:10px">
        <input type="radio" name="modo" value="date" <?= $valori['modo'] === 'date' ? 'checked' : '' ?> data-mostra="periodo-date">
        <span>Date libere</span>
      </label>

      <div class="gruppo-periodo" id="periodo-date">
        <input type="date" name="data_inizio" value="<?= e($valori['data_inizio']) ?>" aria-label="Dal">
        <span class="sfumato piccolo">→</span>
        <input type="date" name="data_fine" value="<?= e($valori['data_fine']) ?>" aria-label="Al">
      </div>

      <?php foreach (['data_inizio', 'data_fine'] as $campo): ?>
        <?php if (isset($errori[$campo])): ?><p class="errore-campo"><?= e($errori[$campo]) ?></p><?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="campo">
      <label for="titolo">Titolo</label>
      <input type="text" id="titolo" name="titolo" value="<?= e($valori['titolo']) ?>" maxlength="120"
             placeholder="Lascia vuoto per usare il nome del mese">
      <?php if (isset($errori['titolo'])): ?><p class="errore-campo"><?= e($errori['titolo']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primario">Crea piano</button>
  </div>
</form>

<script>
/* Mostra solo il gruppo di campi del periodo scelto. */
(function () {
  var radio = document.querySelectorAll('input[name="modo"]');
  function aggiorna() {
    radio.forEach(function (r) {
      var gruppo = document.getElementById(r.dataset.mostra);
      if (gruppo) { gruppo.hidden = !r.checked; }
    });
  }
  radio.forEach(function (r) { r.addEventListener('change', aggiorna); });
  aggiorna();
})();
</script>
