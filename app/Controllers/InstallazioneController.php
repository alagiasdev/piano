<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Db;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Utente;
use App\Support\Migratore;
use Throwable;

/**
 * Primo avvio dal browser: crea le tabelle e il primo amministratore.
 *
 * Serve sugli hosting senza Terminal, dove non si possono lanciare
 * migrate.php e crea-admin.php. Su una macchina raggiungibile da internet
 * una pagina cosi va chiusa bene, e qui lo e da due lati insieme:
 *
 * 1. funziona solo finche non esiste nessun utente. Appena il primo account
 *    e creato, la pagina risponde 404 per sempre;
 * 2. chiede il valore di SETUP_TOKEN, che sta nel .env e quindi lo conosce
 *    solo chi ha accesso ai file del server. Senza quella riga nel .env la
 *    pagina non si apre nemmeno.
 *
 * Il secondo punto copre la finestra fra il primo deploy e la creazione
 * dell'account, che altrimenti sarebbe aperta a chiunque conoscesse l'URL.
 */
final class InstallazioneController extends Controller
{
    public function mostra(): void
    {
        $this->soloSeNonInstallato();

        $this->vista('installazione/index', [
            'titolo'   => 'Installazione',
            'sospese'  => $this->migrazioniInSospeso(),
            'errori'   => $this->errori(),
            'vecchi'   => $this->vecchiValori(),
        ], 'layouts/vuoto');
    }

    public function esegui(): void
    {
        $this->soloSeNonInstallato();
        $this->verificaCsrf();

        $dati = [
            'token'    => (string) $this->request->input('token', ''),
            'nome'     => trim((string) $this->request->input('nome', '')),
            'email'    => trim((string) $this->request->input('email', '')),
            'password' => (string) $this->request->input('password', ''),
        ];

        $errori = [];

        if (!hash_equals((string) Config::get('SETUP_TOKEN', ''), $dati['token'])) {
            $errori['token'] = 'Codice non corretto: è il valore di SETUP_TOKEN nel file .env.';
        }
        if ($dati['nome'] === '') {
            $errori['nome'] = 'Il nome è obbligatorio.';
        }
        if (!filter_var($dati['email'], FILTER_VALIDATE_EMAIL)) {
            $errori['email'] = 'Indirizzo email non valido.';
        }
        if (mb_strlen($dati['password']) < 8) {
            $errori['password'] = 'La password deve avere almeno 8 caratteri.';
        }

        if ($errori !== []) {
            unset($dati['password'], $dati['token']);
            Session::set('errori', $errori);
            Session::set('vecchi_valori', $dati);
            Response::redirect('/installazione');
        }

        try {
            Migratore::applica();
        } catch (Throwable $e) {
            Session::set('errori', ['migrazioni' => $e->getMessage()]);
            Response::redirect('/installazione');
        }

        // Ricontrollo dopo le migrazioni: adesso la tabella utenti esiste,
        // e se nel frattempo qualcuno avesse creato un account si fermerebbe.
        if (Utente::conteggio() > 0) {
            $this->nonTrovato('Installazione già completata.');
        }

        Utente::crea([
            'nome'           => $dati['nome'],
            'email'          => $dati['email'],
            'password'       => $dati['password'],
            'amministratore' => true,
            'attivo'         => 1,
        ]);

        Session::flash('Installazione completata. Accedi con le credenziali che hai appena creato.');
        Response::redirect('/login');
    }

    /* ------------------------------------------------------------------ */

    /**
     * La pagina esiste solo prima del primo account, e solo se nel .env
     * c'e un SETUP_TOKEN. Negli altri casi non deve nemmeno esistere.
     */
    private function soloSeNonInstallato(): void
    {
        if ((string) Config::get('SETUP_TOKEN', '') === '') {
            $this->nonTrovato('Installazione non disponibile.');
        }

        if ($this->quantiUtenti() > 0) {
            $this->nonTrovato('Installazione già completata.');
        }
    }

    /** Zero anche quando la tabella non esiste ancora: e il caso normale qui. */
    private function quantiUtenti(): int
    {
        try {
            return Utente::conteggio();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<int,string> */
    private function migrazioniInSospeso(): array
    {
        try {
            return Migratore::inSospeso();
        } catch (Throwable) {
            // Database irraggiungibile: lo dice la pagina, non serve elencare
            return [];
        }
    }

    /** @return array<string,string> */
    private function errori(): array
    {
        $errori = Session::get('errori', []);
        Session::forget('errori');

        return is_array($errori) ? $errori : [];
    }

    /** @return array<string,mixed> */
    private function vecchiValori(): array
    {
        $vecchi = Session::get('vecchi_valori', []);
        Session::forget('vecchi_valori');

        return is_array($vecchi) ? $vecchi : [];
    }
}
