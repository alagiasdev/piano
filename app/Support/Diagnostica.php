<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Db;
use PDO;
use Throwable;

/**
 * Controlli di installazione: si eseguono prima di aprire il sottodominio
 * e ogni volta che qualcosa non torna.
 *
 * La logica sta qui e non nello script perche serve in due posti: da riga
 * di comando (database/verifica.php) e dalla pagina /verifica, che e' l'unica
 * via su quegli hosting cPanel dove il Terminal non c'e'.
 *
 * Ogni controllo restituisce:
 *   titolo     cosa si sta verificando
 *   stato      ok | avviso | errore | info
 *   dettaglio  cosa si e' trovato
 *   soluzione  che fare, solo quando c'e' qualcosa da fare
 */
final class Diagnostica
{
    private const PHP_MINIMA = '8.2.0';

    private const ESTENSIONI_OBBLIGATORIE = ['pdo_mysql', 'mbstring', 'json', 'fileinfo'];

    /** @var array<int,array<string,string>> */
    private array $esiti = [];

    /** @return array<int,array<string,string>> */
    public static function esegui(): array
    {
        $d = new self();

        $d->php();
        $d->estensioni();

        // Senza .env non si va oltre: tutto il resto dipende da li.
        if (!$d->ambiente()) {
            return $d->esiti;
        }

        $d->urlBase();
        $d->fusoOrario();
        $d->database();
        $d->migrazioni();
        $d->amministratori();
        $d->cartellaUpload();
        $d->sessioni();
        $d->raggiungibilita();
        $d->fileRiservati();
        $d->composer();
        $d->spazioDisco();

        return $d->esiti;
    }

    /** Quanti controlli sono in errore: e' il codice di uscita dello script. */
    public static function errori(array $esiti): int
    {
        return count(array_filter($esiti, static fn (array $e) => $e['stato'] === 'errore'));
    }

    public static function avvisi(array $esiti): int
    {
        return count(array_filter($esiti, static fn (array $e) => $e['stato'] === 'avviso'));
    }

    /* ------------------------------------------------------- i controlli -- */

    private function php(): void
    {
        $versione = PHP_VERSION;

        if (version_compare($versione, self::PHP_MINIMA, '<')) {
            $this->ko('Versione PHP', "PHP {$versione}", 'Serve almeno PHP ' . self::PHP_MINIMA
                . '. Su cPanel si cambia da «Select PHP Version».');

            return;
        }

        $this->ok('Versione PHP', "PHP {$versione}");
    }

    private function estensioni(): void
    {
        $mancanti = array_values(array_filter(
            self::ESTENSIONI_OBBLIGATORIE,
            static fn (string $e) => !extension_loaded($e)
        ));

        if ($mancanti !== []) {
            $this->ko(
                'Estensioni PHP',
                'Mancano: ' . implode(', ', $mancanti),
                'Attivale da «Select PHP Version» → Extensions.'
            );
        } else {
            $this->ok('Estensioni PHP', implode(', ', self::ESTENSIONI_OBBLIGATORIE) . ' presenti');
        }

        // gd serve solo a dompdf per le immagini; senza, resta il PDF da stampa
        if (!extension_loaded('gd')) {
            $this->info('Estensione gd', 'Assente', 'Serve a dompdf per le immagini. '
                . 'Senza, l\'export PDF usa la pagina print-friendly del browser: va bene lo stesso.');
        } else {
            $this->ok('Estensione gd', 'Presente');
        }
    }

    /** @return bool false se manca il .env: senza, gli altri controlli non hanno senso */
    private function ambiente(): bool
    {
        $percorso = BASE_PATH . '/.env';

        if (!is_file($percorso)) {
            $this->ko('File .env', 'Non trovato in ' . BASE_PATH,
                'Copia .env.example in .env e compila i dati del database.');

            return false;
        }

        $vuote = [];
        foreach (['DB_NAME', 'DB_USER', 'APP_URL'] as $chiave) {
            if ((string) Config::get($chiave, '') === '') {
                $vuote[] = $chiave;
            }
        }

        if ($vuote !== []) {
            $this->ko('File .env', 'Valori non compilati: ' . implode(', ', $vuote),
                'Aprilo e completali.');

            return false;
        }

        $this->ok('File .env', 'Presente e compilato');

        return true;
    }

    private function urlBase(): void
    {
        $url = (string) Config::get('APP_URL', '');
        $parti = parse_url($url);

        if ($parti === false || !isset($parti['scheme'], $parti['host'])) {
            $this->ko('APP_URL', $url, 'Deve essere un indirizzo completo, per esempio '
                . 'https://piano.iltuodominio.it, senza slash finale.');

            return;
        }

        if (str_ends_with($url, '/')) {
            $this->avviso('APP_URL', $url, 'Togli lo slash finale: i link pubblici verrebbero con un doppio slash.');

            return;
        }

        // In locale http va bene; online il link va al cliente, e va in https
        $locale = in_array($parti['host'], ['localhost', '127.0.0.1'], true);
        if ($parti['scheme'] !== 'https' && !$locale) {
            $this->avviso('APP_URL', $url, 'Usa https: questo indirizzo finisce nei link mandati ai clienti.');

            return;
        }

        $this->ok('APP_URL', $url);
    }

    private function fusoOrario(): void
    {
        $fuso = date_default_timezone_get();
        $atteso = (string) Config::get('APP_TIMEZONE', 'Europe/Rome');

        if ($fuso !== $atteso) {
            $this->avviso('Fuso orario', "In uso {$fuso}, atteso {$atteso}",
                'Date, ore e file ICS userebbero il fuso sbagliato.');

            return;
        }

        $this->ok('Fuso orario', $fuso . ' · adesso sono le ' . date('H:i'));
    }

    private function database(): void
    {
        try {
            $pdo = Db::pdo();
            $versione = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $charset = (string) Db::value("SELECT @@character_set_database");

            if (!str_starts_with($charset, 'utf8mb4')) {
                $this->avviso('Database', "Connesso a {$versione}, charset {$charset}",
                    'Con un charset diverso da utf8mb4 le emoji nei post vengono perse.');

                return;
            }

            $this->ok('Database', "Connesso · {$versione} · {$charset}");
        } catch (Throwable $e) {
            $this->ko('Database', 'Connessione fallita: ' . $e->getMessage(),
                'Controlla i valori DB_* nel file .env e che l\'utente abbia i privilegi sul database.');
        }
    }

    private function migrazioni(): void
    {
        $file = array_map('basename', glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
        sort($file);

        try {
            $applicate = Db::run('SELECT file FROM migrazioni')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $this->ko('Migrazioni', 'Tabella «migrazioni» assente: nessuna migrazione è stata applicata',
                'Esegui: php database/migrate.php');

            return;
        }

        $mancanti = array_values(array_diff($file, $applicate));

        if ($mancanti !== []) {
            $this->ko('Migrazioni', 'Da applicare: ' . implode(', ', $mancanti),
                'Esegui: php database/migrate.php');

            return;
        }

        $this->ok('Migrazioni', count($file) . ' applicate, nessuna in sospeso');
    }

    private function amministratori(): void
    {
        try {
            $quanti = (int) Db::value('SELECT COUNT(*) FROM utenti WHERE amministratore = 1 AND attivo = 1');
        } catch (Throwable) {
            $this->ko('Amministratori', 'Tabella «utenti» non leggibile', 'Applica prima le migrazioni.');

            return;
        }

        if ($quanti === 0) {
            $this->ko('Amministratori', 'Nessun amministratore attivo: non si potrebbe entrare',
                'Esegui: php database/crea-admin.php tua@email.it "unapasswordlunga" "Nome Cognome"');

            return;
        }

        $this->ok('Amministratori', $quanti === 1 ? '1 account attivo' : "{$quanti} account attivi");
    }

    private function cartellaUpload(): void
    {
        $cartella = BASE_PATH . '/public/uploads';

        if (!is_dir($cartella)) {
            $this->ko('Cartella uploads', 'Non esiste', 'Creala: public/uploads/');

            return;
        }

        // Non basta is_writable: su alcuni hosting mente. Si prova davvero.
        $prova = $cartella . '/.verifica-' . bin2hex(random_bytes(4));
        if (@file_put_contents($prova, 'x') === false) {
            $this->ko('Cartella uploads', 'Non scrivibile dal processo PHP',
                'Dal File Manager di cPanel dai i permessi 755 a public/uploads/.');

            return;
        }
        @unlink($prova);

        $this->ok('Cartella uploads', 'Scrivibile');
    }

    private function sessioni(): void
    {
        $cartella = session_save_path();

        if ($cartella === '' || !is_dir($cartella)) {
            // Vuoto significa che PHP usa il default di sistema: di solito va bene
            $this->info('Sessioni', 'Cartella di sistema',
                'Se il login non tiene, imposta una session.save_path scrivibile.');

            return;
        }

        if (!is_writable($cartella)) {
            $this->ko('Sessioni', "Cartella non scrivibile: {$cartella}",
                'Senza, il login non funziona.');

            return;
        }

        $this->ok('Sessioni', 'Cartella scrivibile');
    }

    /** Il rewrite funziona? Si chiede all'app una pagina che esiste solo grazie a lui. */
    private function raggiungibilita(): void
    {
        $url = rtrim((string) Config::get('APP_URL', ''), '/') . '/login';
        $risposta = $this->chiedi($url);

        if ($risposta === null) {
            $this->info('Rewrite e raggiungibilità', 'Non verificabile da qui',
                'Il server non riesce a chiamare se stesso via HTTP. '
                . 'Apri ' . $url . ' dal browser: se vedi il modulo di accesso, va tutto bene.');

            return;
        }

        [$codice, $corpo] = $risposta;

        if ($codice === 404) {
            $this->ko('Rewrite e raggiungibilità', "404 su {$url}",
                'mod_rewrite non è attivo oppure il .htaccess in public/ non viene letto '
                . '(serve AllowOverride All).');

            return;
        }

        if ($codice !== 200) {
            $this->ko('Rewrite e raggiungibilità', "HTTP {$codice} su {$url}",
                'L\'app risponde ma non come dovrebbe: guarda il log degli errori.');

            return;
        }

        if (!str_contains($corpo, 'name="password"')) {
            $this->avviso('Rewrite e raggiungibilità', "200 su {$url}, ma non è la pagina di accesso",
                'Controlla che APP_URL punti davvero a questa installazione.');

            return;
        }

        $this->ok('Rewrite e raggiungibilità', 'La pagina di accesso risponde correttamente');
    }

    /** Il .env e il codice sorgente non devono essere scaricabili dal web. */
    private function fileRiservati(): void
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');

        $esposti = [];
        foreach (['/.env' => 'DB_', '/app/bootstrap.php' => 'BASE_PATH'] as $percorso => $spia) {
            $risposta = $this->chiedi($base . $percorso);
            if ($risposta === null) {
                continue;
            }
            [$codice, $corpo] = $risposta;
            if ($codice === 200 && str_contains($corpo, $spia)) {
                $esposti[] = $percorso;
            }
        }

        if ($esposti !== []) {
            $this->ko('File riservati', 'Scaricabili dal web: ' . implode(', ', $esposti),
                'Il document root deve puntare a public/, non alla cartella del progetto. '
                . 'Finché è così, chiunque può leggere le credenziali del database.');

            return;
        }

        $this->ok('File riservati', '.env e codice sorgente non raggiungibili dal web');
    }

    private function composer(): void
    {
        if (!is_file(BASE_PATH . '/vendor/autoload.php')) {
            $this->info('Composer', 'vendor/ assente',
                'Non serve: l\'app usa un autoloader interno. Installalo solo se vuoi dompdf.');

            return;
        }

        $dompdf = is_dir(BASE_PATH . '/vendor/dompdf');
        $this->ok('Composer', 'vendor/ presente' . ($dompdf ? ', dompdf disponibile' : ', senza dompdf'));
    }

    private function spazioDisco(): void
    {
        $libero = @disk_free_space(BASE_PATH);

        if ($libero === false) {
            return;   // su hosting con quota non e' leggibile: non e' un problema
        }

        $gb = $libero / 1073741824;
        $testo = $gb >= 1 ? round($gb, 1) . ' GB liberi' : round($libero / 1048576) . ' MB liberi';

        if ($gb < 0.5) {
            $this->avviso('Spazio su disco', $testo, 'Gli upload dei loghi potrebbero fallire.');

            return;
        }

        $this->info('Spazio su disco', $testo);
    }

    /* ------------------------------------------------------------ utilita -- */

    /**
     * Una GET breve, con curl se c'e' altrimenti con i flussi.
     *
     * @return array{0:int,1:string}|null null se la richiesta non e' proprio partita
     */
    private function chiedi(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false,   // certificati interni: qui non e' il punto
                CURLOPT_USERAGENT      => 'Piano/verifica',
            ]);
            $corpo = curl_exec($ch);
            $codice = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return $corpo === false || $codice === 0 ? null : [$codice, (string) $corpo];
        }

        $contesto = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true, 'user_agent' => 'Piano/verifica'],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $corpo = @file_get_contents($url, false, $contesto);
        if ($corpo === false) {
            return null;
        }

        $codice = 0;
        foreach ($http_response_header ?? [] as $riga) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $riga, $m)) {
                $codice = (int) $m[1];
            }
        }

        return $codice === 0 ? null : [$codice, $corpo];
    }

    private function ok(string $titolo, string $dettaglio): void
    {
        $this->esiti[] = ['titolo' => $titolo, 'stato' => 'ok', 'dettaglio' => $dettaglio, 'soluzione' => ''];
    }

    private function info(string $titolo, string $dettaglio, string $soluzione = ''): void
    {
        $this->esiti[] = ['titolo' => $titolo, 'stato' => 'info', 'dettaglio' => $dettaglio, 'soluzione' => $soluzione];
    }

    private function avviso(string $titolo, string $dettaglio, string $soluzione = ''): void
    {
        $this->esiti[] = ['titolo' => $titolo, 'stato' => 'avviso', 'dettaglio' => $dettaglio, 'soluzione' => $soluzione];
    }

    private function ko(string $titolo, string $dettaglio, string $soluzione = ''): void
    {
        $this->esiti[] = ['titolo' => $titolo, 'stato' => 'errore', 'dettaglio' => $dettaglio, 'soluzione' => $soluzione];
    }
}
