<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Viste PHP semplici. Il file della vista viene reso in un buffer e il
 * risultato finisce nella variabile $contenuto del layout.
 */
final class View
{
    /** @param array<string,mixed> $dati */
    public static function rendi(string $vista, array $dati = [], string $layout = 'layouts/admin'): void
    {
        echo self::cattura($vista, $dati, $layout);
    }

    /** @param array<string,mixed> $dati */
    public static function cattura(string $vista, array $dati = [], ?string $layout = 'layouts/admin'): string
    {
        $contenuto = self::render($vista, $dati);

        if ($layout === null) {
            return $contenuto;
        }

        return self::render($layout, $dati + [
            'contenuto' => $contenuto,
            'flash'     => Session::prendiFlash(),
        ]);
    }

    /** @param array<string,mixed> $dati */
    private static function render(string $vista, array $dati): string
    {
        $file = BASE_PATH . '/app/Views/' . $vista . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista non trovata: {$vista}");
        }

        extract($dati, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public static function rendiErrore(int $codice, string $titolo, string $dettaglio = ''): void
    {
        echo self::cattura('errore', compact('codice', 'titolo', 'dettaglio'), 'layouts/vuoto');
    }
}
