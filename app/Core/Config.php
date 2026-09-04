<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lettura del file .env. Parser volutamente minimale: KEY=valore,
 * righe vuote e commenti con #, valori opzionalmente fra virgolette.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            // Senza .env l'app non puo collegarsi al database: meglio dirlo subito
            // e in modo comprensibile invece di lasciare esplodere PDO piu avanti.
            self::fail(
                'File .env mancante',
                'Copia <code>.env.example</code> in <code>.env</code> e compila i dati del database.'
            );
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            // Toglie le virgolette esterne, se presenti
            if (strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::$values[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::$values[$key] ?? null;
        return $value === null || $value === '' ? $default : (int) $value;
    }

    /**
     * Errore di configurazione: pagina secca e stop. Non dipende da View
     * perche puo scattare prima che l'app sia in piedi.
     */
    public static function fail(string $titolo, string $messaggio): never
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, strip_tags($titolo . "\n" . $messaggio) . "\n");
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>' . htmlspecialchars($titolo) . '</title>'
            . '<div style="font:15px/1.6 system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:0 24px;color:#182230">'
            . '<h1 style="font-size:1.3rem;margin:0 0 8px">' . htmlspecialchars($titolo) . '</h1>'
            . '<p style="color:#4B5563;margin:0">' . $messaggio . '</p></div>';
        exit(1);
    }
}
