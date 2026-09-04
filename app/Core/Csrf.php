<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF per sessione: uno solo, valido finche dura la sessione.
 * Va messo in ogni form POST (helper csrf_field()) e in ogni fetch POST
 * (header X-CSRF-Token, letto da app.js dal meta tag del layout).
 */
final class Csrf
{
    private const CHIAVE = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::CHIAVE])) {
            $_SESSION[self::CHIAVE] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CHIAVE];
    }

    public static function valido(?string $token): bool
    {
        $atteso = $_SESSION[self::CHIAVE] ?? '';

        return $token !== null && $atteso !== '' && hash_equals($atteso, $token);
    }

    /** Blocca la richiesta se il token manca o non corrisponde. */
    public static function verifica(Request $request): void
    {
        $token = $request->input('_token')
            ?? null;
        if (!is_string($token) || $token === '') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($request->json()['_token'] ?? null);
        }

        if (self::valido(is_string($token) ? $token : null)) {
            return;
        }

        // 403 e non 419: quest'ultimo non e un codice standard e Apache
        // lo trasforma in un 500 prima di uscire.
        if ($request->wantsJson()) {
            Response::jsonError('Sessione scaduta, ricarica la pagina.', 403);
        }

        http_response_code(403);
        View::rendiErrore(403, 'Sessione scaduta', 'Ricarica la pagina e riprova.');
        exit;
    }
}
