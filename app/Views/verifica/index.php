<?php
/** @var array<int,array<string,string>> $esiti */
/** @var int $errori */
/** @var int $avvisi */

$etichette = [
    'ok'     => 'ok',
    'info'   => 'nota',
    'avviso' => 'da controllare',
    'errore' => 'da risolvere',
];
?>
<div class="intestazione-pagina">
  <div>
    <h1>Verifica installazione</h1>
    <p class="sfumato">
      Da controllare prima di aprire il sottodominio, e ogni volta che qualcosa non torna.
    </p>
  </div>
  <a class="btn btn-piccolo" href="<?= e(url('/impostazioni')) ?>">← Impostazioni</a>
</div>

<?php if ($errori > 0): ?>
  <p class="avviso errore fisso">
    <?= $errori === 1 ? '1 problema da risolvere' : $errori . ' problemi da risolvere' ?><?php
    ?><?= $avvisi > 0 ? ', ' . $avvisi . ' da controllare' : '' ?>.
  </p>
<?php elseif ($avvisi > 0): ?>
  <p class="avviso attenzione fisso">
    Nessun errore, ma <?= $avvisi === 1 ? '1 cosa da controllare' : $avvisi . ' cose da controllare' ?>.
  </p>
<?php else: ?>
  <p class="avviso fisso">Tutto a posto: l'installazione è pronta.</p>
<?php endif; ?>

<div class="scheda" style="padding:0;overflow:hidden">
  <table class="tabella">
    <thead>
      <tr>
        <th style="width:130px">Esito</th>
        <th style="width:210px">Controllo</th>
        <th>Risultato</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($esiti as $esito): ?>
        <tr>
          <td data-etichetta="Esito">
            <span class="esito esito-<?= e($esito['stato']) ?>"><?= e($etichette[$esito['stato']]) ?></span>
          </td>
          <td data-etichetta="Controllo"><strong><?= e($esito['titolo']) ?></strong></td>
          <td data-etichetta="Risultato">
            <?= e($esito['dettaglio']) ?>
            <?php if ($esito['soluzione'] !== ''): ?>
              <p class="aiuto"><?= e($esito['soluzione']) ?></p>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<p class="sfumato piccolo" style="margin-top:16px">
  Le stesse verifiche da riga di comando: <code>php database/verifica.php</code>
  — esce con codice 1 se trova un errore, così si può incatenare a un comando di deploy.
</p>
