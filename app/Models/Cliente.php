<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Support\Ambito;
use App\Support\Canali;

final class Cliente
{
    /**
     * Elenco ordinato per nome. Gli inattivi in fondo, cosi restano
     * raggiungibili senza sporcare la lista di lavoro.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(bool $soloAttivi = false): array
    {
        /* Il filtro sta qui e non nel controller: un elenco di clienti
           che dimentica l'ambito mostra nomi di clienti altrui, e i punti
           da cui si chiede questo elenco sono piu' di uno. Meglio che sia
           il modello a non saperli restituire. */
        [$filtro, $parametri] = Ambito::filtroSql('c.id');

        $sql = 'SELECT c.*,
                       (SELECT COUNT(*) FROM piani p WHERE p.cliente_id = c.id) AS piani_totali
                FROM clienti c
                WHERE 1 = 1';
        if ($soloAttivi) {
            $sql .= ' AND c.attivo = 1';
        }
        $sql .= $filtro . ' ORDER BY c.attivo DESC, c.nome';

        return array_map([self::class, 'espandi'], Db::all($sql, $parametri));
    }

    public static function trova(int $id): ?array
    {
        $riga = Db::first('SELECT * FROM clienti WHERE id = ?', [$id]);

        return $riga === null ? null : self::espandi($riga);
    }

    /** @param array<string,mixed> $dati */
    public static function crea(array $dati): int
    {
        Db::run(
            'INSERT INTO clienti (nome, slug, logo_path, contatto_nome, contatto_email, canali, tono_di_voce, note, attivo)
             VALUES (:nome, :slug, :logo_path, :contatto_nome, :contatto_email, :canali, :tono_di_voce, :note, :attivo)',
            self::parametri($dati)
        );

        return Db::lastId();
    }

    /** @param array<string,mixed> $dati */
    public static function aggiorna(int $id, array $dati): void
    {
        Db::run(
            'UPDATE clienti SET
                nome = :nome, slug = :slug, logo_path = :logo_path,
                contatto_nome = :contatto_nome, contatto_email = :contatto_email,
                canali = :canali, tono_di_voce = :tono_di_voce, note = :note, attivo = :attivo
             WHERE id = :id',
            self::parametri($dati) + ['id' => $id]
        );
    }

    public static function elimina(int $id): void
    {
        // Le immagini dei post vanno tolte dal disco a mano: le foreign key
        // portano via le righe, non i file.
        Media::eliminaPerCliente($id);

        // I piani e i post spariscono per effetto delle foreign key.
        Db::run('DELETE FROM clienti WHERE id = ?', [$id]);
    }

    public static function aggiornaLogo(int $id, ?string $percorso): void
    {
        Db::run('UPDATE clienti SET logo_path = ? WHERE id = ?', [$percorso, $id]);
    }

    /**
     * Slug univoco a partire dal nome. Se esiste gia aggiunge -2, -3, ...
     * $escludiId serve in modifica, per non collidere con se stesso.
     */
    public static function slugUnico(string $nome, ?int $escludiId = null): string
    {
        $base = slugify($nome);
        $slug = $base;
        $n = 1;

        while (true) {
            $esiste = Db::value(
                'SELECT COUNT(*) FROM clienti WHERE slug = ? AND id <> ?',
                [$slug, $escludiId ?? 0]
            );
            if ((int) $esiste === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }

    /** Decodifica i canali JSON in array. */
    private static function espandi(array $riga): array
    {
        $riga['canali'] = Canali::normalizza($riga['canali'] ?? []);

        return $riga;
    }

    /**
     * @param  array<string,mixed> $dati
     * @return array<string,mixed>
     */
    private static function parametri(array $dati): array
    {
        return [
            'nome'           => $dati['nome'],
            'slug'           => $dati['slug'],
            'logo_path'      => $dati['logo_path'] ?? null,
            'contatto_nome'  => $dati['contatto_nome'] ?: null,
            'contatto_email' => $dati['contatto_email'] ?: null,
            'canali'         => json_encode(Canali::normalizza($dati['canali'] ?? []), JSON_UNESCAPED_UNICODE),
            'tono_di_voce'   => $dati['tono_di_voce'] ?: null,
            'note'           => $dati['note'] ?: null,
            'attivo'         => (int) (bool) ($dati['attivo'] ?? 1),
        ];
    }
}
