<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';

        session_name('piano_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Rigenerazione dell'id: dopo il login e dopo il logout. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Messaggio mostrato una volta sola alla pagina successiva. */
    public static function flash(string $messaggio, string $tipo = 'ok'): void
    {
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'testo' => $messaggio];
    }

    /** @return array<int,array{tipo:string,testo:string}> */
    public static function prendiFlash(): array
    {
        $messaggi = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messaggi;
    }
}
