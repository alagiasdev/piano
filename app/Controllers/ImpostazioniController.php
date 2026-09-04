<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Impostazione;
use App\Models\Utente;

final class ImpostazioniController extends Controller
{
    /** Chiavi gestite da questa schermata. */
    private const CHIAVI = ['nome_studio', 'firma', 'nota_standard'];

    public function index(): void
    {
        $this->richiediLogin();

        $errori = Session::get('errori', []);
        Session::forget('errori');

        $this->vista('impostazioni/index', [
            'titolo'  => 'Impostazioni',
            'valori'  => Impostazione::tutte(),
            'utente'  => Auth::utente(),
            'errori'  => is_array($errori) ? $errori : [],
        ]);
    }

    public function salva(): void
    {
        // Nome studio, firma e nota standard valgono per tutti i clienti
        $this->richiediAmministratore();
        $this->verificaCsrf();

        foreach (self::CHIAVI as $chiave) {
            Impostazione::set($chiave, trim((string) $this->request->input($chiave, '')));
        }

        Session::flash('Impostazioni salvate.');
        Response::redirect('/impostazioni');
    }

    public function cambiaPassword(): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $utente  = Auth::utente();
        $attuale = (string) $this->request->input('password_attuale', '');
        $nuova   = (string) $this->request->input('password_nuova', '');
        $conferma = (string) $this->request->input('password_conferma', '');

        $errori = [];

        if (!password_verify($attuale, (string) $utente['password_hash'])) {
            $errori['password_attuale'] = 'La password attuale non è corretta.';
        }
        if (mb_strlen($nuova) < 8) {
            $errori['password_nuova'] = 'La nuova password deve avere almeno 8 caratteri.';
        }
        if ($nuova !== $conferma) {
            $errori['password_conferma'] = 'Le due password non coincidono.';
        }

        if ($errori !== []) {
            Session::set('errori', $errori);
            Response::redirect('/impostazioni');
        }

        Utente::aggiornaPassword((int) $utente['id'], $nuova);

        // Nuovo id di sessione: se qualcuno aveva la sessione vecchia, la perde.
        Session::regenerate();
        Session::flash('Password aggiornata.');

        Response::redirect('/impostazioni');
    }
}
