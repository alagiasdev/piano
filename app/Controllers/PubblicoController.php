<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\View;
use App\Models\Piano;
use App\Models\Post;
use App\Support\DatiPiano;
use App\Support\Fasi;

/**
 * Area pubblica: la pagina che vede il cliente, senza login.
 * L'autorizzazione e il token nell'URL, quindi ogni azione lo ripete e
 * verifica che il post appartenga davvero a quel piano.
 */
final class PubblicoController extends Controller
{
    public function mostra(string $token): void
    {
        $piano = Piano::perToken($token);
        if ($piano === null) {
            $this->linkNonValido();
        }

        $this->rendiPiano($piano, $token, false);
    }

    /*
     * Stessa pagina vista dall'admin, con le azioni disattivate.
     *
     * Sta in questo controller ma NON e' una pagina pubblica: chiede il
     * login, quindi passa dal caricatore che autorizza, come tutte le
     * altre dell'area riservata. Le pagine col token qui sotto no: li'
     * non c'e' nessun utente da autorizzare, e non deve essercene.
     */
    public function anteprima(string $id): void
    {
        $this->richiediLogin();

        $piano = $this->piano((int) $id);

        $this->rendiPiano($piano, (string) ($piano['token_pubblico'] ?? ''), true);
    }

    public function approva(string $token, string $idPost): void
    {
        $piano = $this->pianoDelPost($token, (int) $idPost);

        $fase = Fasi::normalizza($piano['fase'] ?? 'concept');

        // Nella fase esecutivi non si approva quello che non si e visto
        if ($fase === 'esecutivi' && !Post::esecutivoPronto((int) $idPost)) {
            Response::jsonError('Questo post non è ancora pronto: lo riceverai a breve.', 422);
        }

        Post::approva((int) $idPost, $fase);
        Piano::ricalcolaStato((int) $piano['id'], $fase);

        $this->rispondiConStato($piano, (int) $idPost);
    }

    public function chiediModifica(string $token, string $idPost): void
    {
        $piano = $this->pianoDelPost($token, (int) $idPost);

        $commento = trim((string) ($this->request->json()['commento'] ?? ''));
        if ($commento === '') {
            Response::jsonError('Scrivi cosa vuoi modificare.', 422);
        }

        $fase = Fasi::normalizza($piano['fase'] ?? 'concept');

        Post::chiediModifica((int) $idPost, $commento, $fase);
        Piano::ricalcolaStato((int) $piano['id'], $fase);

        $this->rispondiConStato($piano, (int) $idPost);
    }

    public function approvaTutti(string $token): void
    {
        $piano = Piano::perToken($token);
        if ($piano === null) {
            Response::jsonError('Link non valido.', 404);
        }

        $this->verificaCsrf();

        $fase = Fasi::normalizza($piano['fase'] ?? 'concept');

        $quanti = Post::approvaInAttesa((int) $piano['id'], $fase);
        Piano::ricalcolaStato((int) $piano['id'], $fase);

        Response::json([
            'ok'       => true,
            'quanti'   => $quanti,
            'ricarica' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @param array<string,mixed> $piano */
    private function rendiPiano(array $piano, string $token, bool $anteprima): void
    {
        $this->vista(
            'pubblico/piano',
            DatiPiano::perVista($piano, $token, $anteprima),
            'layouts/pubblico'
        );
    }

    /**
     * Recupera il piano dal token e controlla che il post gli appartenga.
     * Vale come autorizzazione per le azioni del cliente.
     *
     * @return array<string,mixed>
     */
    private function pianoDelPost(string $token, int $idPost): array
    {
        $piano = Piano::perToken($token);
        if ($piano === null) {
            Response::jsonError('Link non valido.', 404);
        }

        $this->verificaCsrf();

        $post = Post::trova($idPost);
        if ($post === null || (int) $post['piano_id'] !== (int) $piano['id']) {
            Response::jsonError('Post non trovato in questo piano.', 404);
        }

        return $piano;
    }

    /** @param array<string,mixed> $piano */
    private function rispondiConStato(array $piano, int $idPost): never
    {
        $post       = Post::trova($idPost);
        $aggiornato = Piano::trova((int) $piano['id']);

        // Stato e commento della fase in corso: il cliente sta approvando
        // quella, e la pagina deve aggiornare la riga giusta.
        $colonne  = Fasi::colonne(Fasi::normalizza($piano['fase'] ?? 'concept'));
        $conteggi = Piano::conteggiFase($aggiornato ?? []);

        Response::json([
            'ok'       => true,
            'stato'    => $post[$colonne['stato']] ?? '',
            'commento' => $post[$colonne['commento']] ?? null,
            'piano'    => [
                'stato'     => $aggiornato['stato'] ?? '',
                'approvati' => $conteggi['approvati'],
                'totali'    => $conteggi['totali'],
                'inAttesa'  => $conteggi['totali'] - $conteggi['approvati'],
            ],
        ]);
    }

    private function linkNonValido(): never
    {
        http_response_code(404);

        // Se e l'admin a sbagliare link, meglio dirglielo in modo diretto.
        $dettaglio = Auth::autenticato()
            ? 'Il token non corrisponde a nessun piano: potrebbe essere stato rigenerato.'
            : 'Il link non è più valido. Chiedi che te ne venga inviato uno nuovo.';

        View::rendiErrore(404, 'Link non valido', $dettaglio);
        exit;
    }
}
