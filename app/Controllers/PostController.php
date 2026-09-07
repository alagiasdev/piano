<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Conflitto;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Piano;
use App\Models\Post;
use InvalidArgumentException;

/**
 * Endpoint JSON usati dall'editor del piano: ogni modifica inline arriva qui
 * via fetch e risponde con il valore normalizzato, senza ricaricare la pagina.
 *
 * Le modifiche strutturali (aggiunta, duplicazione, eliminazione, riordino)
 * rispondono "ricarica": e' l'unica semplificazione, e costa una richiesta in
 * piu solo nei casi in cui cambia la forma della tabella.
 */
final class PostController extends Controller
{
    public function crea(string $pianoId): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $piano = $this->piano((int) $pianoId);

        $dati = $this->request->json();
        $data = (string) ($dati['data'] ?? '');

        if (to_date($data) === null) {
            // Senza data indicata si usa l'inizio del piano
            $data = (string) $piano['data_inizio'];
        }

        $id = Post::crea((int) $pianoId, [
            'data'   => to_date($data)?->format('Y-m-d') ?? (string) $piano['data_inizio'],
            'canali' => $dati['canali'] ?? [],
        ]);

        Response::json(['ok' => true, 'id' => $id, 'ricarica' => true]);
    }

    public function aggiorna(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $post = $this->post((int) $id);

        $dati  = $this->request->json();
        $campo = (string) ($dati['campo'] ?? '');

        try {
            $valore = Post::aggiornaCampo(
                (int) $id,
                $campo,
                $dati['valore'] ?? null,
                // "atteso" arriva solo dall'editor, che sa cosa aveva sotto.
                // Assente (o null) significa "scrivi e basta".
                array_key_exists('atteso', $dati) ? $dati['atteso'] : null,
                (int) Auth::utente()['id']
            );
        } catch (Conflitto $e) {
            // 409: non e un errore di chi salva, e una collisione fra due
            // persone. Il client mostra i due valori e fa scegliere.
            Response::json([
                'ok'        => false,
                'conflitto' => true,
                'errore'    => $e->getMessage(),
                'attuale'   => $e->valoreAttuale,
                'chi'       => $e->autore(),
                'quando'    => $e->quando,
            ], 409);
        } catch (InvalidArgumentException $e) {
            Response::jsonError($e->getMessage(), 422);
        }

        // Un cambio di stato puo far scattare l'approvazione automatica
        if ($campo === 'stato') {
            Piano::ricalcolaStato((int) $post['piano_id']);
        }

        Response::json([
            'ok'       => true,
            'valore'   => $valore,
            // Spostando la data il post cambia settimana: la tabella va rifatta
            'ricarica' => $campo === 'data',
            'piano'    => $this->riepilogoPiano((int) $post['piano_id']),
        ]);
    }

    public function elimina(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $post = $this->post((int) $id);

        Post::elimina((int) $id);

        Response::json(['ok' => true, 'ricarica' => true]);
    }

    public function duplica(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $post = $this->post((int) $id);

        Post::duplica((int) $id);

        Response::json(['ok' => true, 'ricarica' => true]);
    }

    public function sposta(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $post = $this->post((int) $id);

        $direzione = ($this->request->json()['direzione'] ?? '') === 'su' ? 'su' : 'giu';
        $spostato  = Post::sposta((int) $id, $direzione);

        Response::json(['ok' => true, 'ricarica' => $spostato]);
    }

    /** Conteggi da aggiornare nella barra in alto dopo ogni modifica. */
    private function riepilogoPiano(int $pianoId): array
    {
        $piano = $this->piano($pianoId);

        return [
            'stato'      => $piano['stato'] ?? '',
            'approvati'  => (int) ($piano['post_approvati'] ?? 0),
            'totali'     => (int) ($piano['post_totali'] ?? 0),
        ];
    }
}
