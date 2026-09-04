<?php
/**
 * Esecutore delle migrazioni.
 *
 *   php database/migrate.php
 *
 * Applica in ordine i file .sql di database/migrations/ non ancora eseguiti
 * e ne registra il nome nella tabella "migrazioni".
 *
 * Su cPanel si puo fare anche a mano, importando i file da phpMyAdmin
 * nello stesso ordine.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Db;

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando.');
}

$pdo = Db::pdo();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrazioni (
        file       VARCHAR(190) NOT NULL,
        eseguita_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (file)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$gia = Db::run('SELECT file FROM migrazioni')->fetchAll(PDO::FETCH_COLUMN);
$file = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($file);

$applicate = 0;

foreach ($file as $percorso) {
    $nome = basename($percorso);
    if (in_array($nome, $gia, true)) {
        continue;
    }

    echo "→ {$nome}\n";

    try {
        $pdo->beginTransaction();
        foreach (istruzioni((string) file_get_contents($percorso)) as $sql) {
            $pdo->exec($sql);
        }
        // Il DDL in MySQL fa commit implicito: la transazione qui serve solo
        // a non lasciare la riga di registro se qualcosa esplode prima.
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        Db::run('INSERT INTO migrazioni (file) VALUES (?)', [$nome]);
        $applicate++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "\nErrore in {$nome}:\n" . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $applicate === 0
    ? "Nessuna migrazione da applicare.\n"
    : "Fatto: {$applicate} migrazione/i applicate.\n";

/**
 * Divide un file SQL nelle singole istruzioni.
 * Volutamente semplice: i file di migrazione sono scritti da noi, una
 * istruzione per blocco e il punto e virgola sempre a fine riga.
 *
 * @return array<int,string>
 */
function istruzioni(string $sql): array
{
    $righe = [];
    foreach (explode("\n", $sql) as $riga) {
        if (!str_starts_with(ltrim($riga), '--')) {
            $righe[] = $riga;
        }
    }

    $pezzi = preg_split('/;\s*(?:\r?\n|$)/', implode("\n", $righe)) ?: [];

    return array_values(array_filter(array_map('trim', $pezzi), 'strlen'));
}
