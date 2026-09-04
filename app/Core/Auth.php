<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Utente;

/**
 * Autenticazione a sessione, con un rate limit elementare sul login
 * basato sull'indirizzo IP (tabella login_tentativi).
 */
final class Auth
{
    private const CHIAVE_SESSIONE = 'utente_id';

    /** Tentativi falliti consentiti prima del blocco temporaneo. */
    private const MAX_TENTATIVI = 5;

    /** Durata del blocco, in minuti. */
    private const BLOCCO_MINUTI = 15;

    /** @var array<string,mixed>|null Cache dell'utente per la richiesta corrente. */
    private static ?array $utente = null;

    public static function utente(): ?array
    {
        if (self::$utente !== null) {
            return self::$utente;
        }

        $id = Session::get(self::CHIAVE_SESSIONE);
        if (!is_int($id)) {
            return null;
        }

        $utente = Utente::trova($id);
        if ($utente === null || (int) $utente['attivo'] !== 1) {
            self::logout();

            return null;
        }

        return self::$utente = $utente;
    }

    public static function autenticato(): bool
    {
        return self::utente() !== null;
    }

    /**
     * Gli amministratori gestiscono utenti, impostazioni ed eliminazioni.
     * I collaboratori fanno tutto il resto.
     */
    public static function amministratore(): bool
    {
        return (int) (self::utente()['amministratore'] ?? 0) === 1;
    }

    /**
     * Prova il login. Restituisce null se ha funzionato, altrimenti
     * il messaggio di errore da mostrare.
     */
    public static function login(string $email, string $password, string $ip): ?string
    {
        $bloccatoFino = self::bloccatoFino($ip);
        if ($bloccatoFino !== null) {
            $minuti = max(1, (int) ceil(($bloccatoFino - time()) / 60));

            return "Troppi tentativi falliti. Riprova fra {$minuti} minuti.";
        }

        $utente = Utente::perEmail($email);

        // Verifichiamo l'hash anche quando l'utente non esiste, per non
        // rendere distinguibili i due casi dal tempo di risposta.
        $hash = $utente['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

        if (!password_verify($password, $hash) || $utente === null || (int) $utente['attivo'] !== 1) {
            self::registraFallimento($ip);

            return 'Email o password non corretti.';
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Utente::aggiornaPassword((int) $utente['id'], $password);
        }

        self::azzeraTentativi($ip);
        Session::regenerate();
        Session::set(self::CHIAVE_SESSIONE, (int) $utente['id']);
        Utente::segnaAccesso((int) $utente['id']);
        self::$utente = null;

        return null;
    }

    public static function logout(): void
    {
        self::$utente = null;
        Session::forget(self::CHIAVE_SESSIONE);
        Session::regenerate();
    }

    /** Timestamp fino a cui l'IP e bloccato, o null se puo provare. */
    private static function bloccatoFino(string $ip): ?int
    {
        $riga = Db::first(
            'SELECT tentativi, ultimo_tentativo FROM login_tentativi WHERE ip = ?',
            [$ip]
        );
        if ($riga === null || (int) $riga['tentativi'] < self::MAX_TENTATIVI) {
            return null;
        }

        $scadenza = strtotime((string) $riga['ultimo_tentativo']) + self::BLOCCO_MINUTI * 60;

        return $scadenza > time() ? $scadenza : null;
    }

    private static function registraFallimento(string $ip): void
    {
        // Se l'ultimo tentativo e piu vecchio della finestra di blocco il
        // contatore riparte da uno, cosi un errore isolato non si accumula.
        Db::run(
            'INSERT INTO login_tentativi (ip, tentativi, ultimo_tentativo)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               tentativi = IF(ultimo_tentativo < DATE_SUB(NOW(), INTERVAL ? MINUTE), 1, tentativi + 1),
               ultimo_tentativo = NOW()',
            [$ip, self::BLOCCO_MINUTI]
        );
    }

    private static function azzeraTentativi(string $ip): void
    {
        Db::run('DELETE FROM login_tentativi WHERE ip = ?', [$ip]);
    }
}
