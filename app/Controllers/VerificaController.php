<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Support\Diagnostica;

/**
 * Le stesse verifiche di database/verifica.php, da browser.
 * Serve sugli hosting cPanel dove il Terminal non e' disponibile.
 *
 * Riservata agli amministratori: l'esito racconta versioni, percorsi e
 * stato del database, cioe' esattamente quello che non si mostra in giro.
 */
final class VerificaController extends Controller
{
    public function index(): void
    {
        $this->richiediAmministratore();

        $esiti = Diagnostica::esegui();

        $this->vista('verifica/index', [
            'titolo' => 'Verifica installazione',
            'esiti'  => $esiti,
            'errori' => Diagnostica::errori($esiti),
            'avvisi' => Diagnostica::avvisi($esiti),
        ]);
    }
}
