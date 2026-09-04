<?php
/**
 * Dati di esempio, per provare l'applicazione subito.
 *
 *   php database/seed.php
 *
 * Crea un cliente di prova con un piano di 4 settimane e una dozzina di
 * post. Rilanciandolo, il cliente di prova viene rifatto da zero: non
 * tocca gli altri clienti.
 *
 * I contenuti vengono dal prototipo calendario-editabile.html.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Db;
use App\Models\Cliente;
use App\Models\Piano;
use App\Models\Post;
use App\Support\Periodo;

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando.');
}

const SLUG_DEMO = 'pasticceria-verdi-esempio';

/* Il piano parte dal lunedi di questa settimana: cosi copre la data di
   oggi e la dashboard mostra subito il piano del periodo e i post dei
   prossimi giorni, che e' il punto di avere dei dati di esempio. */
$inizio = Periodo::lunedi(date('Y-m-d'));
$fine   = $inizio->modify('+27 days');

$giorno = static fn (int $settimana, int $giornoSettimana): string => $inizio
    ->modify('+' . ($settimana * 7 + $giornoSettimana) . ' days')
    ->format('Y-m-d');

/** Dodici post: [settimana 0-3, giorno 0=lun .. 6=dom, ora, canali, contenuto, formato, cta, pilastro] */
$modello = [
    [0, 3, '18:30', ['ig', 'fb'], 'Apertura del mese: cosa aspettarsi, anticipazione delle novità.', 'Carosello', 'Salva il post', 'Brand'],
    [0, 4, '12:00', ['li'],       'Riflessione della titolare su un tema di settore.', 'Testo + foto', 'Commenta', 'Autorevolezza'],
    [0, 5, '10:00', ['ig'],       'Reel "3 errori da evitare quando scegli una torta per un evento".', 'Reel', 'Scrivici in DM', 'Educativo'],
    [1, 1, '18:30', ['ig', 'fb'], 'Focus servizio: torte su misura, a chi servono e come si ordinano.', 'Carosello', 'Prenota dal link in bio', 'Vendita'],
    [1, 3, '13:00', ['ig'],       'Dietro le quinte: una giornata tipo in laboratorio.', 'Reel', 'Seguici', 'Brand'],
    [1, 5, '10:00', ['ig', 'fb'], 'Recensione di una cliente in evidenza, con foto del dolce.', 'Foto singola', 'Lascia la tua recensione', 'Riprova sociale'],
    [2, 1, '18:30', ['ig', 'fb'], 'Lo sapevi che… la lievitazione naturale spiegata in modo semplice.', 'Carosello', 'Condividi', 'Educativo'],
    [2, 3, '12:00', ['li'],       'Caso studio: il buffet per un matrimonio da 120 persone.', 'PDF', 'Contattaci', 'Autorevolezza'],
    [2, 5, '10:00', ['ig'],       'Reel: prima e dopo la decorazione di una torta a piani.', 'Reel', 'Scrivici in DM', 'Brand'],
    [3, 1, '18:30', ['ig', 'fb'], 'Presentazione del team di laboratorio.', 'Foto singola', 'Presentati nei commenti', 'Brand'],
    [3, 4, '13:00', ['ig'],       'Storie: box domande con risposte in giornata.', 'Storie', 'Fai una domanda', 'Community'],
    [3, 6, '09:00', ['nl'],       'Riepilogo del mese, dolce più richiesto e offerta per gli iscritti.', 'Email', 'Scopri l\'offerta', 'Vendita'],
];

$pdo = Db::pdo();
$pdo->beginTransaction();

try {
    // Via il cliente di esempio precedente: piani e post cadono con lui
    $esistente = Db::first('SELECT id, logo_path FROM clienti WHERE slug = ?', [SLUG_DEMO]);
    if ($esistente !== null) {
        Cliente::elimina((int) $esistente['id']);
        echo "Cliente di esempio precedente rimosso.\n";
    }

    $clienteId = Cliente::crea([
        'nome'           => 'Pasticceria Verdi (esempio)',
        'slug'           => SLUG_DEMO,
        'contatto_nome'  => 'Anna Verdi',
        'contatto_email' => 'anna@esempio.it',
        'canali'         => ['ig', 'fb', 'li', 'nl'],
        'tono_di_voce'   => "Caldo e familiare, mai sopra le righe. Si dà del tu.\nSi parla di ingredienti e persone, non di \"eccellenza\".",
        'note'           => 'Cliente di esempio creato da database/seed.php. Eliminabile senza conseguenze.',
        'attivo'         => 1,
    ]);

    $pianoId = Piano::crea([
        'cliente_id'   => $clienteId,
        'titolo'       => mese_it($inizio->modify('+13 days')),
        'data_inizio'  => $inizio->format('Y-m-d'),
        'data_fine'    => $fine->format('Y-m-d'),
        'stato'        => 'bozza',
        'nota_cliente' => '',
    ]);

    foreach ($modello as [$settimana, $giornoSettimana, $ora, $canali, $contenuto, $formato, $cta, $pilastro]) {
        Post::crea($pianoId, [
            'data'      => $giorno($settimana, $giornoSettimana),
            'ora'       => $ora,
            'canali'    => $canali,
            'contenuto' => $contenuto,
            'formato'   => $formato,
            'cta'       => $cta,
            'pilastro'  => $pilastro,
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Errore nel seed: " . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "Creato il cliente «Pasticceria Verdi (esempio)» con il piano «%s» (%s → %s) e %d post.\n",
    mese_it($inizio->modify('+13 days')),
    data_it($inizio->format('Y-m-d')),
    data_it($fine->format('Y-m-d')),
    count($modello)
);
echo "Apri /piani per vederlo.\n";
