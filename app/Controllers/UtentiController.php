<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cliente;
use App\Models\Utente;
use App\Support\Ambito;

/**
 * Gestione dei collaboratori. Tutto riservato agli amministratori.
 *
 * Una sola regola difende l'accesso, e vale anche se si prova a forzarla
 * dall'esterno: sul proprio account non ci si puo togliere i permessi, ne
 * disattivare, ne eliminare.
 *
 * Basta quella a garantire che resti sempre un amministratore attivo: per
 * modificare qualcun altro bisogna essere amministratori attivi, quindi
 * togliendo i permessi a un collega ne resta comunque uno, se stessi.
 * Se il database venisse manomesso a mano, si recupera con
 * "php database/crea-admin.php", che crea o ripromuove un amministratore.
 */
final class UtentiController extends Controller
{
    public function index(): void
    {
        $this->richiediAmministratore();

        $this->vista('utenti/index', [
            'titolo'  => 'Collaboratori',
            'utenti'  => Utente::elenco(),
            'ioSono'  => (int) Auth::utente()['id'],
            'errori'  => $this->errori(),
            'vecchi'  => $this->vecchiValori(),
        ]);
    }

    public function crea(): void
    {
        $this->richiediAmministratore();
        $this->verificaCsrf();

        $dati = [
            'nome'           => trim((string) $this->request->input('nome', '')),
            'email'          => trim((string) $this->request->input('email', '')),
            'password'       => (string) $this->request->input('password', ''),
            'amministratore' => $this->request->input('amministratore') === '1',
            'attivo'         => 1,
        ];

        $errori = $this->valida($dati, null, true);
        if ($errori !== []) {
            $this->tornaIndietro($errori, $dati);
        }

        $idNuovo = Utente::crea($dati);

        /* Un collaboratore nuovo non ha ancora nessun cliente, quindi entra
           e non vede niente. Meglio dirlo qui e portarcelo direttamente,
           che lasciarlo scoprire a lui con una dashboard vuota. */
        if (!$dati['amministratore']) {
            Session::flash('Collaboratore aggiunto: comunicagli email e password. '
                . 'Ora scegli su quali clienti può lavorare: finché non ne assegni nessuno non vede niente.');

            Response::redirect('/utenti/' . $idNuovo);
        }

        Session::flash('Amministratore aggiunto: comunicagli email e password. Vede tutti i clienti.');

        Response::redirect('/utenti');
    }

    public function modifica(string $id): void
    {
        $this->richiediAmministratore();

        $utente = Utente::trova((int) $id) ?? $this->nonTrovato('Utente non trovato.');

        $this->vista('utenti/form', [
            'titolo'    => $utente['nome'],
            'utente'    => $utente,
            'ioSono'    => (int) Auth::utente()['id'],
            'errori'    => $this->errori(),
            'vecchi'    => $this->vecchiValori(),
            // Qui ci arriva solo un amministratore, quindi Cliente::elenco()
            // non e' ristretto e li mostra davvero tutti.
            'clienti'   => Cliente::elenco(),
            'assegnati' => Ambito::assegnati((int) $utente['id']),
        ]);
    }

    public function aggiorna(string $id): void
    {
        $this->richiediAmministratore();
        $this->verificaCsrf();

        $idUtente = (int) $id;
        $utente = Utente::trova($idUtente) ?? $this->nonTrovato('Utente non trovato.');
        $io = (int) Auth::utente()['id'];

        $dati = [
            'nome'           => trim((string) $this->request->input('nome', '')),
            'email'          => trim((string) $this->request->input('email', '')),
            'password'       => (string) $this->request->input('password', ''),
            'amministratore' => $this->request->input('amministratore') === '1',
            'attivo'         => $this->request->input('attivo') === '1',
        ];

        // Su se stessi non si tolgono permessi ne si chiude l'accesso:
        // sarebbe il modo piu rapido per restare fuori dal gestionale.
        if ($idUtente === $io) {
            $dati['amministratore'] = true;
            $dati['attivo'] = true;
        }

        $errori = $this->valida($dati, $idUtente, false);

        if ($errori !== []) {
            $this->tornaIndietro($errori, $dati, '/utenti/' . $idUtente);
        }

        Utente::aggiorna($idUtente, $dati);

        /* Le assegnazioni si salvano sempre, anche per un amministratore
           che tanto vede tutto: cosi il giorno che lo si riporta a
           collaboratore non si ritrova di colpo senza nessun cliente. */
        Ambito::assegna($idUtente, $this->request->inputArray('clienti'));

        Session::flash('Modifiche salvate.');

        Response::redirect('/utenti/' . $idUtente);
    }

    public function elimina(string $id): void
    {
        $this->richiediAmministratore();
        $this->verificaCsrf();

        $idUtente = (int) $id;
        Utente::trova($idUtente) ?? $this->nonTrovato('Utente non trovato.');

        if ($idUtente === (int) Auth::utente()['id']) {
            Session::flash('Non puoi eliminare il tuo stesso account.', 'errore');
            Response::redirect('/utenti/' . $idUtente);
        }

        Utente::elimina($idUtente);
        Session::flash('Account eliminato.');

        Response::redirect('/utenti');
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string,mixed> $dati
     * @return array<string,string>
     */
    private function valida(array $dati, ?int $id, bool $passwordObbligatoria): array
    {
        $errori = [];

        if ($dati['nome'] === '') {
            $errori['nome'] = 'Il nome è obbligatorio.';
        }

        if (!filter_var($dati['email'], FILTER_VALIDATE_EMAIL)) {
            $errori['email'] = 'Indirizzo email non valido.';
        } elseif (Utente::emailOccupata($dati['email'], $id)) {
            $errori['email'] = 'Questa email è già usata da un altro account.';
        }

        if ($passwordObbligatoria || $dati['password'] !== '') {
            if (mb_strlen((string) $dati['password']) < 8) {
                $errori['password'] = 'La password deve avere almeno 8 caratteri.';
            }
        }

        return $errori;
    }

    /**
     * @param array<string,string> $errori
     * @param array<string,mixed>  $dati
     */
    private function tornaIndietro(array $errori, array $dati, string $percorso = '/utenti'): never
    {
        unset($dati['password']);
        Session::set('errori', $errori);
        Session::set('vecchi_valori', $dati);

        Response::redirect($percorso);
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
