<?php
/**
 * Verifica dell'installazione.
 *
 *   php database/verifica.php
 *
 * Da eseguire sul server prima di aprire il sottodominio, e ogni volta che
 * qualcosa non torna. Esce con codice 1 se trova almeno un errore, cosi si
 * puo incatenare a un comando di deploy.
 *
 * Le stesse verifiche sono disponibili da browser su /verifica, per gli
 * hosting dove il Terminal non c'e'.
 */

declare(strict_types=1);

$radice = dirname(__DIR__);

// Il .env va controllato prima di avviare l'app: senza, l'avvio si ferma
// da solo con un messaggio che qui non aiuterebbe.
if (!is_file($radice . '/.env')) {
    fwrite(STDERR, "\n  [KO] File .env non trovato in {$radice}\n"
        . "       Copia .env.example in .env e compila i dati del database.\n\n");
    exit(1);
}

require $radice . '/app/bootstrap.php';

use App\Support\Diagnostica;

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando. Da browser: /verifica');
}

$esiti = Diagnostica::esegui();

$simboli = ['ok' => '[ok]', 'info' => '[--]', 'avviso' => '[!!]', 'errore' => '[KO]'];

echo "\n  Verifica dell'installazione\n";
echo '  ' . str_repeat('-', 60) . "\n\n";

foreach ($esiti as $esito) {
    // Non printf: %-26s conta i byte, e con le lettere accentate la colonna
    // si disallinea di un carattere per ogni accento.
    $titolo = $esito['titolo'] . str_repeat(' ', max(1, 26 - mb_strlen($esito['titolo'])));
    echo '  ' . $simboli[$esito['stato']] . ' ' . $titolo . ' ' . $esito['dettaglio'] . "\n";

    if ($esito['soluzione'] !== '') {
        // A capo ogni 66 caratteri, indentato sotto il dettaglio
        foreach (explode("\n", wordwrap($esito['soluzione'], 66, "\n", false)) as $riga) {
            echo '       ' . $riga . "\n";
        }
    }
}

$errori = Diagnostica::errori($esiti);
$avvisi = Diagnostica::avvisi($esiti);

echo "\n  " . str_repeat('-', 60) . "\n";

if ($errori > 0) {
    echo "  {$errori} " . ($errori === 1 ? 'problema da risolvere' : 'problemi da risolvere')
        . ($avvisi > 0 ? ", {$avvisi} da controllare" : '') . ".\n\n";
    exit(1);
}

if ($avvisi > 0) {
    echo "  Nessun errore, ma {$avvisi} " . ($avvisi === 1 ? 'cosa da controllare' : 'cose da controllare') . ".\n\n";
    exit(0);
}

echo "  Tutto a posto: l'installazione è pronta.\n\n";
exit(0);
