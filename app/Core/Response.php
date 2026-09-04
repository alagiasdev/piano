<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** Redirect interno: $path e relativo alla radice dell'app. */
    public static function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonError(string $messaggio, int $status = 400): never
    {
        self::json(['ok' => false, 'errore' => $messaggio], $status);
    }

    /** Scarica un contenuto generato al volo (ICS, HTML di export). */
    public static function download(string $contenuto, string $nomeFile, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
        header('Content-Length: ' . strlen($contenuto));
        echo $contenuto;
        exit;
    }
}
