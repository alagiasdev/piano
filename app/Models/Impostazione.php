<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * Coppie chiave/valore: nome studio, firma, nota standard per il cliente.
 * Lette una volta sola per richiesta e tenute in memoria.
 */
final class Impostazione
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $chiave, string $default = ''): string
    {
        return self::tutte()[$chiave] ?? $default;
    }

    /** @return array<string,string> */
    public static function tutte(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $valori = [];
        foreach (Db::all('SELECT chiave, valore FROM impostazioni') as $riga) {
            $valori[(string) $riga['chiave']] = (string) ($riga['valore'] ?? '');
        }

        return self::$cache = $valori;
    }

    public static function set(string $chiave, string $valore): void
    {
        Db::run(
            'INSERT INTO impostazioni (chiave, valore) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valore = VALUES(valore)',
            [$chiave, $valore]
        );
        self::$cache = null;
    }
}
