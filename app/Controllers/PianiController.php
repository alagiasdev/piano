<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Cliente;
use App\Models\Media;
use App\Models\Piano;
use App\Models\Post;
use App\Support\Fasi;
use App\Support\Ambito;
use App\Support\Periodo;
use RuntimeException;

final class PianiController extends Controller
{
    /** Tutti i piani, di tutti i clienti. */
    public function index(): void
    {
        $this->richiediLogin();

        $this->vista('piani/index', [
            'titolo'  => 'Piani',
            'piani'   => Piano::elenco(),
            'cliente' => null,
        ]);
    }

    /** I piani di un cliente. */
    public function perCliente(string $clienteId): void
    {
        $this->richiediLogin();

        $cliente = $this->cliente((int) $clienteId);

        $this->vista('piani/index', [
            'titolo'  => 'Piani di ' . $cliente['nome'],
            'piani'   => Piano::elenco((int) $clienteId),
            'cliente' => $cliente,
        ]);
    }

    public function nuovo(): void
    {
        $this->richiediLogin();

        $clienti = Cliente::elenco(true);
        if ($clienti === []) {
            Session::flash('Crea prima un cliente: un piano appartiene sempre a un cliente.', 'attenzione');
            Response::redirect('/clienti/nuovo');
        }

        $clienteId = (int) $this->request->input('cliente_id', 0);
        $prossimo  = (new \DateTimeImmutable('first day of next month'))->format('Y-m-d');

        $this->vista('piani/form', [
            'titolo'    => 'Nuovo piano',
            'clienti'   => $clienti,
            'valori'    => Session::get('vecchi_valori') ?: [
                'cliente_id'  => $clienteId,
                'titolo'      => '',
                'modo'        => 'mese',
                'mese'        => (int) date('n', strtotime('first day of next month')),
                'anno'        => (int) date('Y', strtotime('first day of next month')),
                'data_inizio' => $prossimo,
                'data_fine'   => (new \DateTimeImmutable('last day of next month'))->format('Y-m-d'),
            ],
            'errori'    => $this->errori(),
        ]);

        Session::forget('vecchi_valori');
    }

    public function crea(): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $clienteId = (int) $this->request->input('cliente_id', 0);
        $modo      = $this->request->input('modo') === 'date' ? 'date' : 'mese';
        $titolo    = trim((string) $this->request->input('titolo', ''));

        if ($modo === 'mese') {
            $mese = max(1, min(12, (int) $this->request->input('mese', date('n'))));
            $anno = max(2000, min(2100, (int) $this->request->input('anno', date('Y'))));
            [$inizio, $fine] = Periodo::mese($anno, $mese);
            if ($titolo === '') {
                $titolo = mese_it($inizio);
            }
        } else {
            $inizio = (string) $this->request->input('data_inizio', '');
            $fine   = (string) $this->request->input('data_fine', '');
        }

        $errori = [];
        /* Anche il cliente va verificato sull'ambito: la tendina mostra
           solo i clienti assegnati, ma un modulo si manomette in due
           secondi. Stesso messaggio in entrambi i casi, cosi non si
           scopre dall'errore se un cliente esiste o no. */
        if (Cliente::trova($clienteId) === null || !Ambito::permette($clienteId)) {
            $errori['cliente_id'] = 'Scegli un cliente.';
        }
        if ($titolo === '') {
            $errori['titolo'] = 'Dai un titolo al piano (per esempio "Ottobre 2026").';
        }
        if (to_date($inizio) === null || to_date($fine) === null) {
            $errori['data_inizio'] = 'Periodo non valido.';
        } elseif ($fine < $inizio) {
            $errori['data_fine'] = 'La data di fine viene prima di quella di inizio.';
        }

        if ($errori !== []) {
            Session::set('errori', $errori);
            Session::set('vecchi_valori', [
                'cliente_id' => $clienteId, 'titolo' => $titolo, 'modo' => $modo,
                'mese' => (int) $this->request->input('mese', date('n')),
                'anno' => (int) $this->request->input('anno', date('Y')),
                'data_inizio' => $inizio, 'data_fine' => $fine,
            ]);
            Response::redirect('/piani/nuovo');
        }

        $id = Piano::crea([
            'cliente_id'  => $clienteId,
            'titolo'      => $titolo,
            'data_inizio' => $inizio,
            'data_fine'   => $fine,
        ]);

        Session::flash('Piano creato. Aggiungi i post.');
        Response::redirect('/piani/' . $id);
    }

    /** Editor del piano: la schermata principale. */
    public function mostra(string $id): void
    {
        $this->richiediLogin();

        $piano = $this->piano((int) $id);
        $post  = Post::perPiano((int) $id);

        $this->vista('piani/editor', [
            'titolo'    => $piano['titolo'] . ' · ' . $piano['cliente_nome'],
            'piano'     => $piano,
            'settimane' => Periodo::settimane((string) $piano['data_inizio'], (string) $piano['data_fine'], $post),
            'errori'    => $this->errori(),
        ]);
    }

    /**
     * Schermata degli esecutivi: copy finale e immagini, un post per scheda.
     *
     * E' separata dall'editor del concept perche' sono due lavori diversi:
     * li' si ragiona sul calendario, qui su un post alla volta. Stiparli
     * nella stessa tabella avrebbe reso illeggibili tutti e due.
     */
    public function esecutivi(string $id): void
    {
        $this->richiediLogin();

        $piano = $this->piano((int) $id);
        $post  = Post::perPiano((int) $id);

        $this->vista('piani/esecutivi', [
            'titolo'    => 'Esecutivi · ' . $piano['titolo'],
            'piano'     => $piano,
            'settimane' => Periodo::settimane((string) $piano['data_inizio'], (string) $piano['data_fine'], $post),
            'conteggi'  => Piano::conteggiFase($piano),
            'massimo'   => Media::MASSIMO_PER_POST,
        ]);
    }

    /** Vista calendario mensile, sola lettura. */
    public function calendario(string $id): void
    {
        $this->richiediLogin();

        $piano = $this->piano((int) $id);
        $post  = Post::perPiano((int) $id);

        // Un post per giorno, raggruppato per data
        $perGiorno = [];
        foreach ($post as $p) {
            $perGiorno[(string) $p['data']][] = $p;
        }

        $this->vista('piani/calendario', [
            'titolo'    => 'Calendario · ' . $piano['titolo'],
            'piano'     => $piano,
            'perGiorno' => $perGiorno,
            'mesi'      => $this->mesiDelPeriodo((string) $piano['data_inizio'], (string) $piano['data_fine']),
        ]);
    }

    public function aggiorna(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $piano = $this->piano((int) $id);

        $titolo = trim((string) $this->request->input('titolo', ''));
        $inizio = (string) $this->request->input('data_inizio', '');
        $fine   = (string) $this->request->input('data_fine', '');

        $errori = [];
        if ($titolo === '') {
            $errori['titolo'] = 'Il titolo è obbligatorio.';
        }
        if (to_date($inizio) === null || to_date($fine) === null) {
            $errori['periodo'] = 'Periodo non valido.';
        } elseif ($fine < $inizio) {
            $errori['periodo'] = 'La data di fine viene prima di quella di inizio.';
        }

        if ($errori !== []) {
            Session::set('errori', $errori);
            Response::redirect('/piani/' . (int) $id);
        }

        Piano::aggiorna((int) $id, [
            'titolo'       => $titolo,
            'data_inizio'  => $inizio,
            'data_fine'    => $fine,
            'nota_cliente' => trim((string) $this->request->input('nota_cliente', '')),
        ]);

        Session::flash('Piano aggiornato.');
        Response::redirect('/piani/' . (int) $id);
    }

    public function cambiaStato(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $this->piano((int) $id);

        $stato = (string) $this->request->input('stato', '');
        Piano::cambiaStato((int) $id, $stato);

        Session::flash($stato === 'inviato'
            ? 'Piano segnato come inviato. Copia il link e mandalo al cliente.'
            : 'Stato aggiornato.');

        Response::redirect('/piani/' . (int) $id);
    }

    /**
     * Passa il piano dal concept agli esecutivi, o torna indietro.
     *
     * Non tocca nessuno stato: gli stati degli esecutivi vivono su colonne
     * loro e partono da "da approvare", quindi tornare al concept e poi
     * di nuovo agli esecutivi non fa perdere niente.
     */
    public function cambiaFase(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $piano = $this->piano((int) $id);

        $fase = Fasi::normalizza($this->request->input('fase'));
        Piano::cambiaFase((int) $id, $fase);

        if ($fase === 'esecutivi') {
            $avvertenze = [];

            $senzaOk = (int) $piano['post_totali'] - (int) $piano['post_approvati'];
            if ($senzaOk > 0) {
                $avvertenze[] = "{$senzaOk} post non hanno ancora l'ok sul concept";
            }

            // Passare agli esecutivi con le schede vuote significa mandare al
            // cliente un link su cui non c'e' niente da guardare.
            $vuoti = Post::senzaEsecutivo((int) $id);
            if ($vuoti > 0) {
                $avvertenze[] = "{$vuoti} post non hanno ancora né copy né immagini";
            }

            Session::flash(
                $avvertenze === []
                    ? 'Piano passato agli esecutivi: il cliente ora vede il post come sarà pubblicato.'
                    : 'Piano passato agli esecutivi. Attenzione: ' . implode(', ', $avvertenze) . '.',
                $avvertenze === [] ? 'ok' : 'attenzione'
            );
        } else {
            Session::flash('Piano tornato alla fase concept.');
        }

        Response::redirect('/piani/' . (int) $id);
    }

    public function rigeneraToken(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $this->piano((int) $id);
        Piano::rigeneraToken((int) $id);

        Session::flash('Nuovo link generato: quello precedente non funziona più.');
        Response::redirect('/piani/' . (int) $id);
    }

    /**
     * Duplica il piano. Senza parametri usa i valori di default
     * (4 settimane in avanti), cosi dalla lista basta un clic.
     */
    public function duplica(string $id): void
    {
        $this->richiediLogin();
        $this->verificaCsrf();

        $piano = $this->piano((int) $id);

        $modo      = $this->request->input('modo') === 'mesi' ? 'mesi' : 'settimane';
        $quantita  = (int) ($this->request->input('quantita') ?? ($modo === 'mesi' ? 1 : 4));
        $quantita  = max(-60, min(60, $quantita)) ?: ($modo === 'mesi' ? 1 : 4);

        $titolo = trim((string) $this->request->input('titolo', ''));
        if ($titolo === '') {
            // Titolo suggerito: il mese in cui cade il centro del nuovo
            // periodo. Con lo spostamento a settimane l'inizio puo restare
            // nel mese precedente (29 ottobre - 28 novembre e "novembre"),
            // quindi il punto medio descrive il piano meglio dell'inizio.
            $nuovoInizio = Periodo::sposta((string) $piano['data_inizio'], $modo, $quantita);
            $nuovaFine   = Periodo::sposta((string) $piano['data_fine'], $modo, $quantita);
            $centro      = (int) ((strtotime($nuovoInizio) + strtotime($nuovaFine)) / 2);
            $titolo      = mese_it(date('Y-m-d', $centro));
        }

        try {
            $nuovoId = Piano::duplica((int) $id, $titolo, $modo, $quantita);
        } catch (RuntimeException $e) {
            Session::flash($e->getMessage(), 'errore');
            Response::redirect('/piani/' . (int) $id);
        }

        Session::flash(sprintf(
            'Piano duplicato in "%s": %d post spostati di %d %s, tutti da approvare.',
            $titolo,
            (int) $piano['post_totali'],
            abs($quantita),
            $modo === 'mesi' ? ($quantita === 1 ? 'mese' : 'mesi') : ($quantita === 1 ? 'settimana' : 'settimane')
        ));

        Response::redirect('/piani/' . $nuovoId);
    }

    public function elimina(string $id): void
    {
        // Elimina anche tutti i post del piano: riservata agli amministratori
        $this->richiediAmministratore();
        $this->verificaCsrf();

        $piano = $this->piano((int) $id);
        Piano::elimina((int) $id);

        Session::flash('Piano eliminato.');
        Response::redirect('/clienti/' . (int) $piano['cliente_id'] . '/piani');
    }

    /* ------------------------------------------------------------------ */

    /**
     * I mesi toccati dal periodo, come primo giorno di ciascuno.
     *
     * @return array<int,\DateTimeImmutable>
     */
    private function mesiDelPeriodo(string $inizio, string $fine): array
    {
        $cursore = (new \DateTimeImmutable($inizio))->modify('first day of this month')->setTime(0, 0);
        $ultimo  = (new \DateTimeImmutable($fine))->modify('first day of this month')->setTime(0, 0);

        $mesi = [];
        while ($cursore <= $ultimo) {
            $mesi[] = $cursore;
            $cursore = $cursore->modify('+1 month');
        }

        return $mesi;
    }

    /** @return array<string,string> */
    private function errori(): array
    {
        $errori = Session::get('errori', []);
        Session::forget('errori');

        return is_array($errori) ? $errori : [];
    }
}
