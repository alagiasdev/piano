<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Auth;
use App\Core\Db;

/**
 * Quali clienti puo vedere chi sta usando il gestionale.
 *
 * ATTENZIONE alla distinzione, perche' sbagliarla e' un buco e non un bug
 * qualsiasi:
 *
 *   null  =  nessun limite, li vede tutti          (amministratore)
 *   []    =  nessun cliente, non vede niente       (collaboratore senza assegnazioni)
 *
 * Sono due cose opposte e si somigliano da vicino. Per questo qui dentro
 * non si restituisce mai un array vuoto per dire "tutti", e chi chiama non
 * deve mai fare `if (!$clienti)`: si confronta sempre con `=== null`.
 *
 * Gli amministratori non stanno nella tabella utente_cliente: vedono tutto
 * per definizione. Tenerceli avrebbe voluto dire aggiornarli a ogni nuovo
 * cliente, e prima o poi dimenticarsene.
 */
final class Ambito
{
    /** @var array<int,array<int,int>> */
    private static array $cache = [];

    /**
     * Gli id dei clienti visibili all'utente corrente, o null se li vede
     * tutti. Senza sessione (pagine pubbliche col token) restituisce []:
     * il link pubblico non passa mai di qui, e se ci passasse per errore
     * deve trovare una porta chiusa, non una spalancata.
     *
     * @return array<int,int>|null
     */
    public static function clienti(): ?array
    {
        $utente = Auth::utente();

        if ($utente === null) {
            return [];
        }

        if ((int) ($utente['amministratore'] ?? 0) === 1) {
            return null;
        }

        return self::assegnati((int) $utente['id']);
    }

    /** Puo l'utente corrente lavorare su questo cliente? */
    public static function permette(int $clienteId): bool
    {
        $visibili = self::clienti();

        return $visibili === null || in_array($clienteId, $visibili, true);
    }

    /**
     * Gli id assegnati a un utente qualsiasi, per il modulo di modifica.
     * Qui [] vuol dire davvero "nessuno assegnato".
     *
     * @return array<int,int>
     */
    public static function assegnati(int $utenteId): array
    {
        if (isset(self::$cache[$utenteId])) {
            return self::$cache[$utenteId];
        }

        $righe = Db::all(
            'SELECT cliente_id FROM utente_cliente WHERE utente_id = ? ORDER BY cliente_id',
            [$utenteId]
        );

        return self::$cache[$utenteId] = array_map(static fn(array $r): int => (int) $r['cliente_id'], $righe);
    }

    /**
     * Riscrive le assegnazioni di un utente.
     *
     * @param array<int,int> $clienti
     */
    public static function assegna(int $utenteId, array $clienti): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            Db::run('DELETE FROM utente_cliente WHERE utente_id = ?', [$utenteId]);

            // array_unique perche' un modulo manomesso puo mandare doppioni,
            // e la chiave primaria composta li rifiuterebbe con un errore.
            foreach (array_unique(array_map('intval', $clienti)) as $clienteId) {
                if ($clienteId > 0) {
                    Db::run(
                        'INSERT INTO utente_cliente (utente_id, cliente_id) VALUES (?, ?)',
                        [$utenteId, $clienteId]
                    );
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        unset(self::$cache[$utenteId]);
    }

    /**
     * Pezzo di SQL per filtrare su una colonna che contiene un cliente_id,
     * con i parametri da accodare. Restituisce ['', []] quando non c'e'
     * nessun limite, cosi chi chiama puo concatenarlo senza condizioni.
     *
     * Il caso "nessun cliente assegnato" diventa `AND 1 = 0`: una query che
     * non torna niente e' la risposta giusta, e non richiede a chi chiama
     * di ricordarsi di gestirlo a parte.
     *
     * @return array{0:string,1:array<int,int>}
     */
    public static function filtroSql(string $colonna): array
    {
        $visibili = self::clienti();

        if ($visibili === null) {
            return ['', []];
        }

        if ($visibili === []) {
            return [' AND 1 = 0', []];
        }

        $segni = implode(',', array_fill(0, count($visibili), '?'));

        return [" AND {$colonna} IN ({$segni})", $visibili];
    }
}
