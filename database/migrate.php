<?php
/**
 * Esecutore delle migrazioni.
 *
 *   php database/migrate.php
 *
 * Applica in ordine i file .sql di database/migrations/ non ancora eseguiti
 * e ne registra il nome nella tabella "migrazioni".
 *
 * Sugli hosting senza Terminal le stesse migrazioni si applicano dalla
 * pagina /installazione (al primo avvio) o da /verifica, che segnala quelle
 * in sospeso. In alternativa si importano a mano da phpMyAdmin, in ordine.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Support\Migratore;

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando.');
}

try {
    $sospese = Migratore::inSospeso();
} catch (Throwable $e) {
    fwrite(STDERR, "Database non raggiungibile: " . $e->getMessage() . "\n");
    exit(1);
}

if ($sospese === []) {
    echo "Nessuna migrazione da applicare.\n";
    exit(0);
}

foreach ($sospese as $nome) {
    echo "→ {$nome}\n";
}

try {
    $fatte = Migratore::applica();
} catch (Throwable $e) {
    fwrite(STDERR, "\n" . $e->getMessage() . "\n");
    exit(1);
}

echo 'Fatto: ' . count($fatte) . " migrazione/i applicate.\n";
