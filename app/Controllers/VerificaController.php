<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Support\Diagnostica;
use App\Support\Migratore;
use Throwable;

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
            'titolo'    => 'Verifica installazione',
            'esiti'     => $esiti,
            'errori'    => Diagnostica::errori($esiti),
            'avvisi'    => Diagnostica::avvisi($esiti),
            'inSospeso' => Migratore::inSospeso(),
        ]);
    }

    /**
     * Applica le migrazioni in sospeso.
     *
     * Serve perche' su questo hosting non c'e' ne' SSH ne' Terminal: la
     * pagina di installazione sa gia' migrare, ma si chiude per sempre
     * appena esiste un account, e ogni migrazione successiva restava da
     * incollare a mano in phpMyAdmin -- ricordandosi anche di aggiungere
     * la riga nel registro, che e' il pezzo che si dimentica.
     *
     * Chiusa da tre lati: amministratore, token CSRF, e il pulsante
     * compare solo quando c'e' davvero qualcosa da applicare. Il
     * Migratore e' lo stesso di database/migrate.php, quindi non c'e'
     * una seconda logica che puo' divergere.
     */
    public function migra(): void
    {
        $this->richiediAmministratore();
        $this->verificaCsrf();

        if (Migratore::inSospeso() === []) {
            Session::flash('Non c\'era niente da applicare.');
            Response::redirect('/verifica');
        }

        try {
            $fatte = Migratore::applica();
        } catch (Throwable $e) {
            // Il messaggio del Migratore dice quale file ha fallito e
            // perche': serve per capire, quindi si mostra intero.
            Session::flash($e->getMessage(), 'errore');
            Response::redirect('/verifica');
        }

        Session::flash(count($fatte) === 1
            ? 'Applicata 1 migrazione: ' . $fatte[0]
            : 'Applicate ' . count($fatte) . ' migrazioni: ' . implode(', ', $fatte));

        Response::redirect('/verifica');
    }
}
