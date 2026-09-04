<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * Utenti che accedono al gestionale.
 *
 * Due livelli soli: amministratore e collaboratore. Gli amministratori
 * gestiscono utenti, impostazioni ed eliminazioni; i collaboratori fanno
 * tutto il resto. Non serve un sistema di ruoli piu articolato per un
 * gruppo di poche persone che si conoscono.
 */
final class Utente
{
    /** @return array<int,array<string,mixed>> */
    public static function elenco(): array
    {
        return Db::all(
            'SELECT id, nome, email, attivo, amministratore, ultimo_accesso, created_at
             FROM utenti
             ORDER BY attivo DESC, amministratore DESC, nome'
        );
    }

    public static function trova(int $id): ?array
    {
        return Db::first('SELECT * FROM utenti WHERE id = ?', [$id]);
    }

    public static function perEmail(string $email): ?array
    {
        return Db::first('SELECT * FROM utenti WHERE email = ?', [mb_strtolower($email)]);
    }

    /** @param array<string,mixed> $dati */
    public static function crea(array $dati): int
    {
        Db::run(
            'INSERT INTO utenti (nome, email, password_hash, attivo, amministratore)
             VALUES (?, ?, ?, ?, ?)',
            [
                $dati['nome'],
                mb_strtolower((string) $dati['email']),
                password_hash((string) $dati['password'], PASSWORD_DEFAULT),
                (int) (bool) ($dati['attivo'] ?? 1),
                (int) (bool) ($dati['amministratore'] ?? 0),
            ]
        );

        return Db::lastId();
    }

    /**
     * Aggiorna i dati di un utente. La password si cambia solo se ne arriva
     * una nuova: il campo vuoto lascia quella esistente.
     *
     * @param array<string,mixed> $dati
     */
    public static function aggiorna(int $id, array $dati): void
    {
        Db::run(
            'UPDATE utenti SET nome = ?, email = ?, attivo = ?, amministratore = ? WHERE id = ?',
            [
                $dati['nome'],
                mb_strtolower((string) $dati['email']),
                (int) (bool) ($dati['attivo'] ?? 1),
                (int) (bool) ($dati['amministratore'] ?? 0),
                $id,
            ]
        );

        if (($dati['password'] ?? '') !== '') {
            self::aggiornaPassword($id, (string) $dati['password']);
        }
    }

    public static function elimina(int $id): void
    {
        Db::run('DELETE FROM utenti WHERE id = ?', [$id]);
    }

    public static function aggiornaPassword(int $id, string $password): void
    {
        Db::run(
            'UPDATE utenti SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    public static function segnaAccesso(int $id): void
    {
        Db::run('UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?', [$id]);
    }

    public static function conteggio(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM utenti');
    }

    /** L'email e gia usata da un altro utente? */
    public static function emailOccupata(string $email, ?int $escludiId = null): bool
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM utenti WHERE email = ? AND id <> ?',
            [mb_strtolower($email), $escludiId ?? 0]
        ) > 0;
    }
}
