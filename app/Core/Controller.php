<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Cliente;
use App\Models\Media;
use App\Models\Piano;
use App\Models\Post;
use App\Support\Ambito;

/**
 * Base dei controller: tiene la richiesta e i due gesti che si ripetono
 * ovunque, cioe "serve il login" e "verifica il token CSRF".
 */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** Da chiamare all'inizio di ogni azione dell'area admin. */
    protected function richiediLogin(): void
    {
        if (Auth::autenticato()) {
            return;
        }

        if ($this->request->wantsJson()) {
            Response::jsonError('Sessione scaduta, ricarica la pagina.', 401);
        }

        // Memorizza dove voleva andare, per tornarci dopo il login.
        if ($this->request->method === 'GET') {
            Session::set('dopo_login', $this->request->path);
        }
        Response::redirect('/login');
    }

    /**
     * Per le azioni riservate agli amministratori: gestione utenti,
     * impostazioni dello studio ed eliminazioni definitive.
     */
    protected function richiediAmministratore(): void
    {
        $this->richiediLogin();

        if (Auth::amministratore()) {
            return;
        }

        if ($this->request->wantsJson()) {
            Response::jsonError('Serve un account amministratore.', 403);
        }

        http_response_code(403);
        View::rendiErrore(
            403,
            'Non hai i permessi',
            'Questa operazione è riservata agli amministratori. Chiedi a chi gestisce il gestionale.'
        );
        exit;
    }

    protected function verificaCsrf(): void
    {
        Csrf::verifica($this->request);
    }

    /** @param array<string,mixed> $dati */
    protected function vista(string $vista, array $dati = [], string $layout = 'layouts/admin'): void
    {
        View::rendi($vista, $dati, $layout);
    }

    /* ------------------------------------------------- accesso ai clienti -- */

    /*
     * Un collaboratore vede solo i clienti che gli sono stati assegnati.
     *
     * Questi quattro metodi CARICANO e AUTORIZZANO insieme, di proposito:
     * un controllo separato da aggiungere in ventisei posti e' un controllo
     * che prima o poi si dimentica in uno. Se invece l'unico modo di avere
     * in mano un piano e' `$this->piano($id)`, il permesso non si puo'
     * saltare senza saltare anche il caricamento — e si vede subito.
     *
     * Regola per chi tocchera' questo codice: nei controller dell'area
     * riservata non si chiamano piu' Cliente::trova, Piano::trova,
     * Post::conPiano e Media::trova. Fanno eccezione le pagine pubbliche
     * col token, dove non c'e' nessun utente da autorizzare.
     */

    protected function vietatoCliente(): never
    {
        $messaggio = 'Questo cliente non è fra quelli che ti sono stati assegnati. '
            . 'Se ti serve, chiedi a chi gestisce il gestionale.';

        if ($this->request->wantsJson()) {
            Response::jsonError($messaggio, 403);
        }

        http_response_code(403);
        View::rendiErrore(403, 'Non hai i permessi', $messaggio);
        exit;
    }

    /** @return array<string,mixed> */
    protected function cliente(int $id): array
    {
        $cliente = Cliente::trova($id) ?? $this->nonTrovato('Cliente non trovato.');

        if (!Ambito::permette((int) $cliente['id'])) {
            $this->vietatoCliente();
        }

        return $cliente;
    }

    /** @return array<string,mixed> */
    protected function piano(int $id): array
    {
        $piano = Piano::trova($id) ?? $this->nonTrovato('Piano non trovato.');

        if (!Ambito::permette((int) $piano['cliente_id'])) {
            $this->vietatoCliente();
        }

        return $piano;
    }

    /** Il post con il suo piano: conPiano porta gia il cliente_id. @return array<string,mixed> */
    protected function post(int $id): array
    {
        $post = Post::conPiano($id) ?? $this->nonTrovato('Post non trovato.');

        if (!Ambito::permette((int) $post['cliente_id'])) {
            $this->vietatoCliente();
        }

        return $post;
    }

    /** @return array<string,mixed> */
    protected function media(int $id): array
    {
        $media = Media::conCliente($id) ?? $this->nonTrovato('Immagine non trovata.');

        if (!Ambito::permette((int) $media['cliente_id'])) {
            $this->vietatoCliente();
        }

        return $media;
    }

    protected function nonTrovato(string $messaggio = 'Risorsa non trovata'): never
    {
        if ($this->request->wantsJson()) {
            Response::jsonError($messaggio, 404);
        }

        http_response_code(404);
        View::rendiErrore(404, 'Pagina non trovata', $messaggio);
        exit;
    }
}
