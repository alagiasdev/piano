<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Utente;

final class AuthController extends Controller
{
    public function mostraLogin(): void
    {
        if (Auth::autenticato()) {
            Response::redirect('/');
        }

        // Prima installazione: senza utenti il login sarebbe un vicolo cieco.
        // Alla primissima visita le tabelle non esistono ancora e la query
        // fallisce: vale come "nessun utente", non come errore da mostrare.
        try {
            $senzaUtenti = Utente::conteggio() === 0;
        } catch (\Throwable) {
            $senzaUtenti = true;
        }

        $this->vista('auth/login', [
            'titolo'      => 'Accedi',
            'email'       => (string) Session::get('login_email', ''),
            'errore'      => Session::get('login_errore'),
            'senzaUtenti' => $senzaUtenti,
        ], 'layouts/vuoto');

        Session::forget('login_errore');
        Session::forget('login_email');
    }

    public function login(): void
    {
        $this->verificaCsrf();

        $email    = (string) $this->request->input('email', '');
        $password = (string) $this->request->input('password', '');

        $errore = Auth::login($email, $password, $this->request->ip());

        if ($errore !== null) {
            Session::set('login_errore', $errore);
            Session::set('login_email', $email);
            Response::redirect('/login');
        }

        $destinazione = Session::get('dopo_login');
        Session::forget('dopo_login');

        Response::redirect(is_string($destinazione) ? $destinazione : '/');
    }

    public function logout(): void
    {
        $this->verificaCsrf();
        Auth::logout();
        Session::flash('Sei uscito dal gestionale.');

        Response::redirect('/login');
    }
}
