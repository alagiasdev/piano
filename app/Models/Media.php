<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Support\Upload;

/**
 * Le immagini dei post: piu di una per post, perche i caroselli.
 *
 * I file stanno in public/uploads/post/{id}/. Il database e il disco vanno
 * tenuti allineati a mano: le foreign key cancellano le righe, non i file.
 */
final class Media
{
    /** Oltre questo numero l'editor non accetta altre immagini. */
    public const MASSIMO_PER_POST = 10;

    /** @return array<int,array<string,mixed>> */
    public static function perPost(int $postId): array
    {
        return Db::all(
            'SELECT * FROM post_media WHERE post_id = ? ORDER BY ordine, id',
            [$postId]
        );
    }

    /**
     * Tutte le immagini dei post di un piano, raggruppate per post.
     * Una query sola invece di una per post.
     *
     * @return array<int,array<int,array<string,mixed>>>
     */
    public static function perPiano(int $pianoId): array
    {
        $righe = Db::all(
            'SELECT m.* FROM post_media m
             JOIN post p ON p.id = m.post_id
             WHERE p.piano_id = ?
             ORDER BY m.post_id, m.ordine, m.id',
            [$pianoId]
        );

        $perPost = [];
        foreach ($righe as $riga) {
            $perPost[(int) $riga['post_id']][] = $riga;
        }

        return $perPost;
    }

    public static function trova(int $id): ?array
    {
        return Db::first('SELECT * FROM post_media WHERE id = ?', [$id]);
    }

    public static function conta(int $postId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM post_media WHERE post_id = ?', [$postId]);
    }

    /** @param array{percorso:string,nome_originale:string,mime:string,byte:int} $dati */
    public static function aggiungi(int $postId, array $dati): int
    {
        $ordine = (int) Db::value(
            'SELECT COALESCE(MAX(ordine), -1) + 1 FROM post_media WHERE post_id = ?',
            [$postId]
        );

        Db::run(
            'INSERT INTO post_media (post_id, percorso, nome_originale, mime, byte, ordine)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$postId, $dati['percorso'], $dati['nome_originale'], $dati['mime'], $dati['byte'], $ordine]
        );

        return Db::lastId();
    }

    /** Elimina riga e file. */
    public static function elimina(int $id): void
    {
        $media = self::trova($id);
        if ($media === null) {
            return;
        }

        Db::run('DELETE FROM post_media WHERE id = ?', [$id]);
        Upload::eliminaMedia((string) $media['percorso']);
    }

    /**
     * Elimina tutte le immagini di un post, righe e file.
     * Da chiamare prima di cancellare il post: dopo, la cascata avrebbe
     * gia portato via le righe e i percorsi dei file sarebbero persi.
     */
    public static function eliminaPerPost(int $postId): void
    {
        Db::run('DELETE FROM post_media WHERE post_id = ?', [$postId]);
        Upload::eliminaCartellaPost($postId);
    }

    /**
     * Cancella dal disco le immagini di tutti i post di un piano.
     *
     * Da chiamare PRIMA di eliminare il piano: la foreign key porta via le
     * righe di post_media ma non i file, che resterebbero a occupare spazio
     * senza che nessuno sappia piu a cosa servivano.
     */
    public static function eliminaPerPiano(int $pianoId): void
    {
        foreach (Db::all('SELECT id FROM post WHERE piano_id = ?', [$pianoId]) as $post) {
            Upload::eliminaCartellaPost((int) $post['id']);
        }
    }

    /** Come sopra, per tutti i piani di un cliente. */
    public static function eliminaPerCliente(int $clienteId): void
    {
        $post = Db::all(
            'SELECT s.id FROM post s
             JOIN piani p ON p.id = s.piano_id
             WHERE p.cliente_id = ?',
            [$clienteId]
        );

        foreach ($post as $riga) {
            Upload::eliminaCartellaPost((int) $riga['id']);
        }
    }

    /** Sposta un'immagine di una posizione, dentro lo stesso post. */
    public static function sposta(int $id, string $direzione): bool
    {
        $media = self::trova($id);
        if ($media === null) {
            return false;
        }

        $confronto = $direzione === 'su' ? '<' : '>';
        $ordinamento = $direzione === 'su' ? 'DESC' : 'ASC';

        $vicina = Db::first(
            "SELECT id, ordine FROM post_media
             WHERE post_id = ? AND (ordine, id) {$confronto} (?, ?)
             ORDER BY ordine {$ordinamento}, id {$ordinamento}
             LIMIT 1",
            [$media['post_id'], $media['ordine'], $media['id']]
        );

        if ($vicina === null) {
            return false;
        }

        // Scambio secco dei due valori di ordine
        Db::run('UPDATE post_media SET ordine = ? WHERE id = ?', [$vicina['ordine'], $media['id']]);
        Db::run('UPDATE post_media SET ordine = ? WHERE id = ?', [$media['ordine'], $vicina['id']]);

        return true;
    }
}
