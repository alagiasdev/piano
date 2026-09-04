<?php

declare(strict_types=1);

namespace App\Core;

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
