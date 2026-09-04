<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Media;
use App\Models\Post;
use App\Support\Upload;
use RuntimeException;

/**
 * Immagini dei post, caricate dall'editor.
 *
 * Risponde in JSON come gli altri endpoint dell'editor, ma riceve
 * multipart invece di JSON: un file non passa per il corpo JSON.
 */
final class MediaController extends Controller
{
    /** Carica una o piu immagini su un post. */
    public function carica(string $idPost): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $post = Post::trova((int) $idPost);
        if ($post === null) {
            Response::jsonError('Post non trovato.', 404);
        }

        $file = $this->fileCaricati();
        if ($file === []) {
            Response::jsonError('Nessun file ricevuto.', 422);
        }

        $gia = Media::conta((int) $idPost);
        if ($gia + count($file) > Media::MASSIMO_PER_POST) {
            Response::jsonError(
                'Massimo ' . Media::MASSIMO_PER_POST . ' immagini per post: ora ne ha ' . $gia . '.',
                422
            );
        }

        $caricate = [];
        foreach ($file as $singolo) {
            try {
                $dati = Upload::immaginePost($singolo, (int) $idPost);
            } catch (RuntimeException $e) {
                // Le immagini gia salvate restano: si segnala solo quella fallita
                Response::json([
                    'ok'       => false,
                    'errore'   => $e->getMessage(),
                    'caricate' => $caricate,
                ], 422);
            }

            $id = Media::aggiungi((int) $idPost, $dati);
            $caricate[] = ['id' => $id, 'percorso' => $dati['percorso']];
        }

        Response::json(['ok' => true, 'caricate' => $caricate, 'ricarica' => true]);
    }

    public function elimina(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        if (Media::trova((int) $id) === null) {
            Response::jsonError('Immagine non trovata.', 404);
        }

        Media::elimina((int) $id);

        Response::json(['ok' => true, 'ricarica' => true]);
    }

    public function sposta(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $direzione = ($this->request->json()['direzione'] ?? '') === 'su' ? 'su' : 'giu';
        $spostata = Media::sposta((int) $id, $direzione);

        Response::json(['ok' => true, 'spostata' => $spostata, 'ricarica' => $spostata]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * $_FILES con input multiplo arriva come array di colonne
     * (name[], tmp_name[], ...): qui torna una voce per file.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fileCaricati(): array
    {
        $voce = $_FILES['immagini'] ?? null;
        if (!is_array($voce) || !isset($voce['tmp_name'])) {
            return [];
        }

        if (!is_array($voce['tmp_name'])) {
            return [$voce];
        }

        $file = [];
        foreach (array_keys($voce['tmp_name']) as $i) {
            if ((int) $voce['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file[] = [
                'name'     => $voce['name'][$i] ?? '',
                'type'     => $voce['type'][$i] ?? '',
                'tmp_name' => $voce['tmp_name'][$i] ?? '',
                'error'    => $voce['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $voce['size'][$i] ?? 0,
            ];
        }

        return $file;
    }
}
