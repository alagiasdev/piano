<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cliente;
use App\Support\Canali;
use App\Support\Upload;
use RuntimeException;

final class ClientiController extends Controller
{
    public function index(): void
    {
        $this->richiediLogin();

        $this->vista('clienti/index', [
            'titolo'  => 'Clienti',
            'clienti' => Cliente::elenco(),
        ]);
    }

    public function nuovo(): void
    {
        $this->richiediLogin();

        $this->vista('clienti/form', [
            'titolo'  => 'Nuovo cliente',
            'cliente' => $this->valoriDelModulo(null),
            'errori'  => $this->errori(),
        ]);
    }

    public function crea(): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $dati = $this->leggiModulo();
        $errori = $this->valida($dati);

        if ($errori !== []) {
            $this->tornaAlModulo($errori, $dati, '/clienti/nuovo');
        }

        $dati['slug'] = Cliente::slugUnico($dati['nome']);

        try {
            $dati['logo_path'] = Upload::logo($_FILES['logo'] ?? null, $dati['nome']);
        } catch (RuntimeException $e) {
            $this->tornaAlModulo(['logo' => $e->getMessage()], $dati, '/clienti/nuovo');
        }

        $id = Cliente::crea($dati);
        Session::flash('Cliente creato.');

        Response::redirect('/clienti/' . $id . '/modifica');
    }

    public function modifica(string $id): void
    {
        $this->richiediLogin();

        $cliente = Cliente::trova((int) $id) ?? $this->nonTrovato('Cliente non trovato.');

        $this->vista('clienti/form', [
            'titolo'  => $cliente['nome'],
            'cliente' => $this->valoriDelModulo($cliente),
            'errori'  => $this->errori(),
        ]);
    }

    public function aggiorna(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $idCliente = (int) $id;
        $cliente = Cliente::trova($idCliente) ?? $this->nonTrovato('Cliente non trovato.');

        $dati = $this->leggiModulo();
        $errori = $this->valida($dati);

        if ($errori !== []) {
            $this->tornaAlModulo($errori, $dati, '/clienti/' . $idCliente . '/modifica');
        }

        // Lo slug segue il nome, ma solo se il nome e davvero cambiato: cosi
        // un link gia condiviso non cambia per una correzione di maiuscole.
        $dati['slug'] = $dati['nome'] === $cliente['nome']
            ? $cliente['slug']
            : Cliente::slugUnico($dati['nome'], $idCliente);

        $logoAttuale = $cliente['logo_path'];
        $dati['logo_path'] = $logoAttuale;

        try {
            $nuovoLogo = Upload::logo($_FILES['logo'] ?? null, $dati['nome']);
        } catch (RuntimeException $e) {
            $this->tornaAlModulo(['logo' => $e->getMessage()], $dati, '/clienti/' . $idCliente . '/modifica');
        }

        if ($nuovoLogo !== null) {
            $dati['logo_path'] = $nuovoLogo;
            Upload::elimina($logoAttuale);
        } elseif ($this->request->input('rimuovi_logo') === '1') {
            $dati['logo_path'] = null;
            Upload::elimina($logoAttuale);
        }

        Cliente::aggiorna($idCliente, $dati);
        Session::flash('Modifiche salvate.');

        Response::redirect('/clienti/' . $idCliente . '/modifica');
    }

    public function elimina(string $id): void
    {
        // Elimina anche piani e post: riservata agli amministratori
        $this->richiediAmministratore();
        $this->verificaCsrf();

        $cliente = Cliente::trova((int) $id) ?? $this->nonTrovato('Cliente non trovato.');

        Upload::elimina($cliente['logo_path']);
        Cliente::elimina((int) $id);
        Session::flash('Cliente eliminato con tutti i suoi piani.');

        Response::redirect('/clienti');
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string,mixed> */
    private function leggiModulo(): array
    {
        return [
            'nome'           => (string) $this->request->input('nome', ''),
            'contatto_nome'  => (string) $this->request->input('contatto_nome', ''),
            'contatto_email' => (string) $this->request->input('contatto_email', ''),
            'canali'         => Canali::normalizza($this->request->inputArray('canali')),
            'tono_di_voce'   => (string) $this->request->input('tono_di_voce', ''),
            'note'           => (string) $this->request->input('note', ''),
            'attivo'         => $this->request->input('attivo') === '1' ? 1 : 0,
        ];
    }

    /**
     * @param  array<string,mixed> $dati
     * @return array<string,string>
     */
    private function valida(array $dati): array
    {
        $errori = [];

        if ($dati['nome'] === '') {
            $errori['nome'] = 'Il nome è obbligatorio.';
        } elseif (mb_strlen($dati['nome']) > 120) {
            $errori['nome'] = 'Il nome è troppo lungo (massimo 120 caratteri).';
        }

        if ($dati['contatto_email'] !== '' && !filter_var($dati['contatto_email'], FILTER_VALIDATE_EMAIL)) {
            $errori['contatto_email'] = 'Indirizzo email non valido.';
        }

        return $errori;
    }

    /**
     * Valori da mostrare nel modulo: quelli rifiutati dalla validazione se
     * ci sono, altrimenti quelli del cliente, altrimenti i valori di default.
     *
     * @param  array<string,mixed>|null $cliente
     * @return array<string,mixed>
     */
    private function valoriDelModulo(?array $cliente): array
    {
        $vuoto = [
            'id' => null, 'nome' => '', 'logo_path' => null, 'contatto_nome' => '',
            'contatto_email' => '', 'canali' => [], 'tono_di_voce' => '', 'note' => '',
            'attivo' => 1, 'piani_totali' => 0,
        ];

        $valori = array_merge($vuoto, $cliente ?? []);

        $vecchi = Session::get('vecchi_valori');
        Session::forget('vecchi_valori');
        if (is_array($vecchi)) {
            $valori = array_merge($valori, $vecchi);
        }

        return $valori;
    }

    /** @return array<string,string> */
    private function errori(): array
    {
        $errori = Session::get('errori', []);
        Session::forget('errori');

        return is_array($errori) ? $errori : [];
    }

    /**
     * @param array<string,string> $errori
     * @param array<string,mixed>  $dati
     */
    private function tornaAlModulo(array $errori, array $dati, string $percorso): never
    {
        Session::set('errori', $errori);
        Session::set('vecchi_valori', $dati);

        Response::redirect($percorso);
    }
}
