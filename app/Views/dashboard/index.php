<?php

use App\Core\Auth;
use App\Support\Canali;
use App\Support\Stati;

/** @var array<int,array{cliente:array<string,mixed>,piano:array<string,mixed>|null}> $righe */
/** @var array<int,array<string,mixed>> $prossimi */
/** @var array<int,array<string,mixed>> $novita */
/** @var string $oggi */

$utente = Auth::utente();
$fraTreGiorni = date('Y-m-d', strtotime('+3 days'));
?>
<div class="intestazione-pagina">
  <div>
    <h1>Ciao <?= e(explode(' ', (string) $utente['nome'])[0]) ?></h1>
    <p class="sfumato"><?= e(nome_giorno_it($oggi)) ?> <?= e(data_it($oggi)) ?></p>
  </div>
  <a class="btn btn-primario" style="margin-top:0" href="<?= e(url('/piani/nuovo')) ?>">Nuovo piano</a>
</div>

<div class="griglia-dashboard">

  <section class="scheda" style="padding:0;overflow:hidden">
    <h2 class="testa-scheda">Clienti e piano del periodo</h2>

    <?php if ($righe === []): ?>
      <p class="vuoto">Nessun cliente attivo. Comincia da <a href="<?= e(url('/clienti/nuovo')) ?>">Nuovo cliente</a>.</p>
    <?php else: ?>
      <table class="tabella">
        <tbody>
        <?php foreach ($righe as $riga): ?>
          <?php
          $cliente = $riga['cliente'];
          $piano   = $riga['piano'];
          ?>
          <tr>
            <td class="cella-logo" style="width:44px">
              <?php if ($cliente['logo_path'] !== null): ?>
                <img class="logo-mini" src="<?= e(url('/' . $cliente['logo_path'])) ?>" alt="">
              <?php else: ?>
                <span class="logo-mini vuoto-logo"><?= e(mb_strtoupper(mb_substr((string) $cliente['nome'], 0, 1))) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= e(url('/clienti/' . $cliente['id'] . '/piani')) ?>"><strong><?= e($cliente['nome']) ?></strong></a>
              <?php if ($piano !== null): ?>
                <div class="piccolo sfumato"><?= e($piano['titolo']) ?> · <?= e(periodo_it($piano['data_inizio'], $piano['data_fine'])) ?></div>
              <?php else: ?>
                <div class="piccolo sfumato">Nessun piano per il periodo corrente</div>
              <?php endif; ?>
            </td>
            <td style="width:190px">
              <?php if ($piano !== null): ?>
                <?php
                $totali    = (int) $piano['post_totali'];
                $approvati = (int) $piano['post_approvati'];
                $quota     = $totali > 0 ? (int) round($approvati / $totali * 100) : 0;
                ?>
                <span class="stato-pill <?= e($piano['stato']) ?>"><?= e(Stati::etichettaPiano((string) $piano['stato'])) ?></span>
                <div class="avanzamento" style="margin-top:6px" title="<?= $approvati ?> di <?= $totali ?> approvati">
                  <div class="avanzamento-barra" style="width:<?= $quota ?>%"></div>
                </div>
                <span class="piccolo sfumato"><?= $approvati ?>/<?= $totali ?> post approvati</span>
              <?php endif; ?>
            </td>
            <td class="azioni" style="width:120px">
              <?php if ($piano !== null): ?>
                <a class="btn btn-piccolo" href="<?= e(url('/piani/' . $piano['id'])) ?>">Apri</a>
              <?php else: ?>
                <a class="btn btn-piccolo" href="<?= e(url('/piani/nuovo') . '?cliente_id=' . (int) $cliente['id']) ?>">Crea piano</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="scheda" style="padding:0;overflow:hidden">
    <h2 class="testa-scheda">Novità dai clienti</h2>

    <?php if ($novita === []): ?>
      <p class="vuoto">Nessuna approvazione o richiesta di modifica, per ora.</p>
    <?php else: ?>
      <ul class="elenco-novita">
        <?php foreach ($novita as $post): ?>
          <?php $modifica = ($post['commento_cliente'] ?? '') !== ''; ?>
          <li>
            <?php /* L'etichetta segue lo stato reale del post, non la
                     presenza del commento: l'admin puo averlo cambiato dopo. */ ?>
            <span class="stato-pill <?= e($post['stato']) ?>">
              <?= e(Stati::etichettaPost((string) $post['stato'])) ?>
            </span>
            <a href="<?= e(url('/piani/' . $post['piano_id'])) ?>#post-<?= (int) $post['id'] ?>">
              <?= e($post['cliente_nome']) ?>
            </a>
            <span class="sfumato piccolo">
              · <?= e(data_it($post['data'], false)) ?>
              <?= Canali::tag($post['canali']) ?>
            </span>
            <?php if ($modifica): ?>
              <p class="piccolo commento-novita"><?= e(mb_strimwidth((string) $post['commento_cliente'], 0, 110, '…')) ?></p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="nota-scheda">
        Le azioni dei clienti si leggono qui: non vengono mandate email.
      </p>
    <?php endif; ?>
  </section>

</div>

<section class="scheda" style="padding:0;overflow:hidden;margin-top:20px">
  <h2 class="testa-scheda">Prossimi 7 giorni</h2>

  <?php if ($prossimi === []): ?>
    <p class="vuoto">Nessun post in pubblicazione nei prossimi sette giorni.</p>
  <?php else: ?>
    <table class="tabella">
      <thead>
        <tr>
          <th style="width:130px">Giorno</th>
          <th style="width:170px">Cliente</th>
          <th style="width:130px">Canali</th>
          <th>Contenuto</th>
          <th style="width:130px">Stato</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($prossimi as $post): ?>
          <?php
          // Da approvare e manca meno di 3 giorni: e il caso che scotta
          $urgente = $post['stato'] === 'da_approvare' && $post['data'] <= $fraTreGiorni;
          ?>
          <tr class="<?= $urgente ? 'urgente' : '' ?>">
            <td data-etichetta="Giorno">
              <strong><?= e(giorno_it($post['data'], false)) ?></strong>
              <?php if (($post['ora'] ?? null) !== null): ?>
                <span class="sfumato piccolo"><?= e(ora_it($post['ora'])) ?></span>
              <?php endif; ?>
              <?php if ($post['data'] === $oggi): ?><span class="piccolo etichetta-oggi">oggi</span><?php endif; ?>
            </td>
            <td class="piccolo" data-etichetta="Cliente"><?= e($post['cliente_nome']) ?></td>
            <td data-etichetta="Canali"><?= Canali::tag($post['canali']) ?></td>
            <td class="piccolo" data-etichetta="Contenuto">
              <a href="<?= e(url('/piani/' . $post['piano_id'])) ?>#post-<?= (int) $post['id'] ?>">
                <?= e(mb_strimwidth((string) $post['contenuto'], 0, 90, '…')) ?: '<span class="sfumato">(senza testo)</span>' ?>
              </a>
            </td>
            <td data-etichetta="Stato">
              <span class="stato-pill <?= e($post['stato']) ?>"><?= e(Stati::etichettaPost((string) $post['stato'])) ?></span>
              <?php if ($urgente): ?><span class="piccolo avviso-urgente">da approvare</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
