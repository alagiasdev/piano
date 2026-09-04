<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Applica le migrazioni non ancora eseguite.
 *
 * La logica sta qui e non nello script perche serve in due posti: da riga
 * di comando (database/migrate.php) e dalla pagina di installazione, che
 * e' l'unica via sugli hosting senza Terminal.
 */
final class Migratore
{
    public static function cartella(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** Crea il registro delle migrazioni se manca. */
    public static function preparaRegistro(): void
    {
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrazioni (
                file        VARCHAR(190) NOT NULL,
                eseguita_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (file)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * I file di migrazione ancora da applicare, in ordine.
     *
     * @return array<int,string> nomi dei file, non percorsi
     */
    public static function inSospeso(): array
    {
        // Volutamente non crea il registro: questa e una lettura, e la
        // chiama anche la pagina di installazione prima che qualcuno sia
        // autenticato. Se la tabella non c'e, non e stato applicato niente.
        try {
            $gia = Db::run('SELECT file FROM migrazioni')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $gia = [];
        }

        $tutti = array_map('basename', glob(self::cartella() . '/*.sql') ?: []);
        sort($tutti);

        return array_values(array_diff($tutti, $gia));
    }

    /**
     * Applica tutte quelle in sospeso.
     *
     * @return array<int,string> i nomi delle migrazioni applicate
     * @throws RuntimeException con il nome del file che ha fallito
     */
    public static function applica(): array
    {
        $pdo = Db::pdo();
        $fatte = [];

        // Qui sì: si sta per scrivere, e il registro serve.
        self::preparaRegistro();

        foreach (self::inSospeso() as $nome) {
            $percorso = self::cartella() . '/' . $nome;

            try {
                $pdo->beginTransaction();
                foreach (self::istruzioni((string) file_get_contents($percorso)) as $sql) {
                    $pdo->exec($sql);
                }
                // Il DDL in MySQL fa commit implicito: la transazione qui serve
                // solo a non lasciare la riga di registro se esplode prima.
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                Db::run('INSERT INTO migrazioni (file) VALUES (?)', [$nome]);
                $fatte[] = $nome;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw new RuntimeException("Errore in {$nome}: " . $e->getMessage(), 0, $e);
            }
        }

        return $fatte;
    }

    /**
     * Divide un file SQL nelle singole istruzioni.
     * Volutamente semplice: i file di migrazione sono scritti da noi, una
     * istruzione per blocco e il punto e virgola sempre a fine riga.
     *
     * @return array<int,string>
     */
    public static function istruzioni(string $sql): array
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
}
