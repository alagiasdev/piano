<?php

/**
 * Prova dei permessi per cliente.
 *
 *   php prove/permessi.php
 *
 * Un collaboratore vede solo i clienti che gli sono stati assegnati. Il
 * controllo sta in un posto solo — i caricatori di App\Core\Controller — ma
 * i punti che li usano sono una trentina, e basta che uno solo torni a
 * caricare la risorsa per conto suo perché il buco si riapra in silenzio.
 *
 * Questa prova percorre OGNI rotta che porta un id, come collaboratore
 * assegnato a un solo cliente, e pretende 403 su tutto quello che appartiene
 * all'altro. È la differenza fra «credo di non aver dimenticato niente» e
 * «ho verificato che non manca niente».
 *
 * Si crea da sola i dati che le servono (due clienti «ZZ prova …», un piano
 * e un post ciascuno, un collaboratore) e li cancella alla fine, anche se
 * qualcosa fallisce. Esce con codice 1 al primo controllo non superato, così
 * si può incatenare a un comando di deploy.
 *
 * ATTENZIONE: va eseguita su un'installazione di prova, non in produzione.
 * Crea e cancella record veri.
 */

declare(strict_types=1);

$radice = dirname(__DIR__);

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando.');
}

if (!is_file($radice . '/.env')) {
    fwrite(STDERR, "\n  [KO] File .env non trovato in {$radice}\n\n");
    exit(1);
}

require $radice . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Db;
use App\Models\Cliente;
use App\Models\Piano;
use App\Models\Post;
use App\Models\Utente;
use App\Support\Ambito;

/* --------------------------------------------------------------- utilità -- */

$base = rtrim((string) Config::get('APP_URL', 'http://localhost'), '/') . '/';

/** Password usata solo qui dentro, per l'account di prova. */
const PW_PROVA = 'prova-permessi-2026';

$falliti = 0;
$fatti   = 0;

function ok(bool $condizione, string $cosa, string $dettaglio = ''): void
{
    global $falliti, $fatti;
    $fatti++;
    if (!$condizione) {
        $falliti++;
    }
    echo ($condizione ? '  ok  ' : '  KO  ') . $cosa
        . ($dettaglio !== '' ? "  [{$dettaglio}]" : '') . "\n";
}

function sessione(): string
{
    return tempnam(sys_get_temp_dir(), 'prova');
}

/**
 * @param array<string,mixed>|null $modulo
 * @param array<string,mixed>|null $json
 * @return array{0:int,1:string}
 */
function chiama(string $url, string $ck, ?array $modulo = null, ?array $json = null, ?string $csrf = null): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_COOKIEJAR      => $ck,
        CURLOPT_COOKIEFILE     => $ck,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($json !== null) {
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($c, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-CSRF-Token: ' . (string) $csrf,
        ]);
    } elseif ($modulo !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($modulo));
    }

    $corpo  = curl_exec($c);
    $stato  = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);

    return [$stato, (string) $corpo];
}

/** Entra e restituisce il token CSRF da usare per le POST. */
function entra(string $base, string $ck, string $email, string $password): string
{
    [, $pagina] = chiama($base . 'login', $ck);
    preg_match('/name="_token" value="([a-f0-9]{64})"/', $pagina, $m);
    chiama($base . 'login', $ck, ['_token' => $m[1] ?? '', 'email' => $email, 'password' => $password]);

    [, $dentro] = chiama($base . 'piani', $ck);
    preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $dentro, $t);

    return $t[1] ?? ($m[1] ?? '');
}

/* ------------------------------------------------------ dati della prova -- */

/** @var array<string,int> $id */
$id = [];

function pulisci(): void
{
    // Prima i figli, poi i padri: le chiavi esterne sono in cascata ma i
    // clienti vanno via per ultimi comunque, e cosi la pulizia funziona
    // anche se un giorno le cascate cambiano.
    foreach (Db::all("SELECT id FROM clienti WHERE nome LIKE 'ZZ prova %'") as $riga) {
        Cliente::elimina((int) $riga['id']);
    }
    $utente = Utente::perEmail('zz.prova.permessi@example.invalid');
    if ($utente !== null) {
        Utente::elimina((int) $utente['id']);
    }
}

function prepara(): array
{
    pulisci();

    $suo    = Cliente::crea(campiCliente('ZZ prova suo', 'zz-prova-suo'));
    $altrui = Cliente::crea(campiCliente('ZZ prova altrui', 'zz-prova-altrui'));

    $pianoSuo    = Piano::crea(campiPiano($suo, 'ZZ piano suo'));
    $pianoAltrui = Piano::crea(campiPiano($altrui, 'ZZ piano altrui'));

    // Serve per l'ultimo controllo: il link pubblico del cliente non deve
    // dipendere dall'ambito di nessun utente, perche' li non c'e' un utente.
    Piano::rigeneraToken($pianoAltrui);

    $postSuo    = Post::crea($pianoSuo, ['data' => date('Y-m-d'), 'contenuto' => 'idea sua']);
    $postAltrui = Post::crea($pianoAltrui, ['data' => date('Y-m-d'), 'contenuto' => 'idea altrui']);

    // Un'immagine finta sul post altrui, per provare le rotte /media/{id}.
    // Il file non esiste: a queste rotte serve solo la riga in database.
    Db::run(
        'INSERT INTO post_media (post_id, percorso, ordine) VALUES (?, ?, 0)',
        [$postAltrui, 'uploads/post/zz-prova.jpg']
    );
    $mediaAltrui = Db::lastId();

    $mario = Utente::crea([
        'nome'           => 'ZZ Mario Rossi',
        'email'          => 'zz.prova.permessi@example.invalid',
        'password'       => PW_PROVA,
        'attivo'         => 1,
        'amministratore' => 0,
    ]);
    Ambito::assegna($mario, [$suo]);

    return compact('suo', 'altrui', 'pianoSuo', 'pianoAltrui', 'postSuo', 'postAltrui', 'mediaAltrui', 'mario');
}

/** @return array<string,mixed> */
function campiCliente(string $nome, string $slug): array
{
    return [
        'nome' => $nome, 'slug' => $slug, 'logo_path' => null,
        'contatto_nome' => null, 'contatto_email' => null, 'canali' => [],
        'tono_di_voce' => null, 'note' => null, 'attivo' => 1,
    ];
}

/** @return array<string,mixed> */
function campiPiano(int $clienteId, string $titolo): array
{
    return [
        'cliente_id' => $clienteId, 'titolo' => $titolo,
        'data_inizio' => date('Y-m-01'), 'data_fine' => date('Y-m-t'),
        'stato' => 'bozza', 'nota_cliente' => null,
    ];
}

/* ------------------------------------------------------------- la prova -- */

echo "\n  Permessi per cliente\n";
echo '  ' . str_repeat('-', 62) . "\n\n";

try {
    $id = prepara();

    $ckMario = sessione();
    $csrf    = entra($base, $ckMario, 'zz.prova.permessi@example.invalid', PW_PROVA);

    [$stato] = chiama($base . 'piani', $ckMario);
    ok($stato === 200, 'il collaboratore entra', "stato {$stato}");

    if ($stato !== 200) {
        fwrite(STDERR, "\n  Il collaboratore non riesce a entrare: il resto non ha senso.\n");
        pulisci();
        exit(1);
    }

    echo "\n  Lettura di roba che non è sua\n";
    foreach ([
        'clienti/' . $id['altrui'] . '/modifica',
        'clienti/' . $id['altrui'] . '/piani',
        'piani/' . $id['pianoAltrui'],
        'piani/' . $id['pianoAltrui'] . '/calendario',
        'piani/' . $id['pianoAltrui'] . '/esecutivi',
        'piani/' . $id['pianoAltrui'] . '/anteprima',
        'piani/' . $id['pianoAltrui'] . '/pdf',
        'piani/' . $id['pianoAltrui'] . '/ics',
    ] as $rotta) {
        [$s] = chiama($base . $rotta, $ckMario);
        ok($s === 403, "GET /{$rotta}", "stato {$s}");
    }

    echo "\n  Scrittura da modulo su roba che non è sua\n";
    foreach ([
        'clienti/' . $id['altrui'],
        'clienti/' . $id['altrui'] . '/elimina',
        'piani/' . $id['pianoAltrui'],
        'piani/' . $id['pianoAltrui'] . '/stato',
        'piani/' . $id['pianoAltrui'] . '/fase',
        'piani/' . $id['pianoAltrui'] . '/token',
        'piani/' . $id['pianoAltrui'] . '/duplica',
        'piani/' . $id['pianoAltrui'] . '/elimina',
    ] as $rotta) {
        [$s] = chiama($base . $rotta, $ckMario, [
            '_token' => $csrf, 'nome' => 'x', 'stato' => 'bozza', 'fase' => 'concept',
        ]);
        ok($s === 403, "POST /{$rotta}", "stato {$s}");
    }

    echo "\n  Chiamate JSON dell'editor su roba che non è sua\n";
    foreach ([
        ['piani/' . $id['pianoAltrui'] . '/post', ['data' => date('Y-m-d')]],
        ['post/' . $id['postAltrui'], ['campo' => 'contenuto', 'valore' => 'INTRUSO']],
        ['post/' . $id['postAltrui'] . '/elimina', []],
        ['post/' . $id['postAltrui'] . '/duplica', []],
        ['post/' . $id['postAltrui'] . '/sposta', ['direzione' => 'su']],
        ['post/' . $id['postAltrui'] . '/media', []],
        ['media/' . $id['mediaAltrui'] . '/elimina', []],
        ['media/' . $id['mediaAltrui'] . '/sposta', ['direzione' => 'su']],
    ] as [$rotta, $corpo]) {
        [$s] = chiama($base . $rotta, $ckMario, null, $corpo, $csrf);
        ok($s === 403, "POST /{$rotta}", "stato {$s}");
    }

    echo "\n  Nessuna di quelle chiamate deve aver cambiato qualcosa\n";
    $contenuto = (string) Db::value('SELECT contenuto FROM post WHERE id = ?', [$id['postAltrui']]);
    ok($contenuto === 'idea altrui', 'il post altrui è intatto', "vale «{$contenuto}»");
    ok(
        (int) Db::value('SELECT COUNT(*) FROM post WHERE piano_id = ?', [$id['pianoAltrui']]) === 1,
        'nessun post aggiunto o tolto al piano altrui'
    );
    ok(
        (int) Db::value('SELECT COUNT(*) FROM post_media WHERE id = ?', [$id['mediaAltrui']]) === 1,
        'l\'immagine altrui non è stata eliminata'
    );

    echo "\n  Gli elenchi non devono nemmeno nominarlo\n";
    foreach ([
        ['clienti', 'ZZ prova altrui', '/clienti'],
        ['piani', 'ZZ piano altrui', '/piani'],
        ['', 'ZZ prova altrui', 'la dashboard'],
    ] as [$rotta, $ago, $etichetta]) {
        [, $corpo] = chiama($base . $rotta, $ckMario);
        ok(strpos($corpo, $ago) === false, "{$etichetta} non nomina «{$ago}»");
    }
    [, $corpo] = chiama($base . 'clienti', $ckMario);
    ok(strpos($corpo, 'ZZ prova suo') !== false, '/clienti mostra invece il cliente suo');

    echo "\n  Sul cliente che è suo deve poter lavorare\n";
    foreach ([
        'clienti/' . $id['suo'] . '/modifica',
        'clienti/' . $id['suo'] . '/piani',
        'piani/' . $id['pianoSuo'],
        'piani/' . $id['pianoSuo'] . '/calendario',
        'piani/' . $id['pianoSuo'] . '/esecutivi',
        'piani/' . $id['pianoSuo'] . '/anteprima',
        'piani/' . $id['pianoSuo'] . '/ics',
    ] as $rotta) {
        [$s] = chiama($base . $rotta, $ckMario);
        ok($s === 200, "GET /{$rotta}", "stato {$s}");
    }

    echo "\n  Prendere un cliente è cosa da amministratore\n";
    [$s] = chiama($base . 'clienti/nuovo', $ckMario);
    ok($s === 403, 'GET /clienti/nuovo', "stato {$s}");
    [$s] = chiama($base . 'clienti', $ckMario, ['_token' => $csrf, 'nome' => 'ZZ prova intruso']);
    ok($s === 403, 'POST /clienti', "stato {$s}");

    $prima = (int) Db::value('SELECT COUNT(*) FROM piani WHERE cliente_id = ?', [$id['altrui']]);
    chiama($base . 'piani', $ckMario, [
        '_token' => $csrf, 'cliente_id' => $id['altrui'], 'titolo' => 'ZZ intruso',
        'modo' => 'mese', 'mese' => 1, 'anno' => 2030,
    ]);
    $dopo = (int) Db::value('SELECT COUNT(*) FROM piani WHERE cliente_id = ?', [$id['altrui']]);
    ok($prima === $dopo, 'un modulo manomesso non crea piani su clienti altrui', "prima {$prima}, dopo {$dopo}");

    echo "\n  Senza nessun cliente assegnato non si vede niente\n";
    Ambito::assegna($id['mario'], []);
    $ckVuoto = sessione();
    entra($base, $ckVuoto, 'zz.prova.permessi@example.invalid', PW_PROVA);
    [, $corpo] = chiama($base . 'clienti', $ckVuoto);
    ok(strpos($corpo, 'ZZ prova suo') === false, 'sparisce anche il cliente che prima era suo');
    [$s] = chiama($base . 'piani/' . $id['pianoSuo'], $ckVuoto);
    ok($s === 403, 'e il suo piano non si apre più', "stato {$s}");
    Ambito::assegna($id['mario'], [$id['suo']]);

    echo "\n  L'amministratore non perde niente\n";
    $ckAdmin = sessione();
    $admin   = Db::first('SELECT email FROM utenti WHERE amministratore = 1 AND attivo = 1 ORDER BY id LIMIT 1');
    $pwAdmin = getenv('PROVA_PW_ADMIN') ?: '';

    if ($admin === null || $pwAdmin === '') {
        echo "  --  saltata: serve PROVA_PW_ADMIN con la password di un amministratore\n";
    } else {
        entra($base, $ckAdmin, (string) $admin['email'], $pwAdmin);
        foreach ([
            'clienti/' . $id['altrui'] . '/modifica',
            'piani/' . $id['pianoAltrui'],
            'piani/' . $id['pianoAltrui'] . '/esecutivi',
            'clienti/nuovo',
        ] as $rotta) {
            [$s] = chiama($base . $rotta, $ckAdmin);
            ok($s === 200, "amministratore GET /{$rotta}", "stato {$s}");
        }
        [, $corpo] = chiama($base . 'clienti', $ckAdmin);
        ok(
            strpos($corpo, 'ZZ prova altrui') !== false && strpos($corpo, 'ZZ prova suo') !== false,
            'l\'amministratore vede entrambi i clienti'
        );
    }

    echo "\n  Il link pubblico del cliente non passa di qui\n";
    $token = (string) Db::value('SELECT token_pubblico FROM piani WHERE id = ?', [$id['pianoAltrui']]);
    if ($token === '') {
        echo "  --  il piano di prova non ha un token pubblico\n";
    } else {
        [$s] = chiama($base . 'p/' . $token, sessione());
        ok($s === 200, 'si apre senza login, come deve', "stato {$s}");
    }
} finally {
    pulisci();
}

echo "\n  " . str_repeat('-', 62) . "\n";

if ($falliti === 0) {
    echo "  Tutti i {$fatti} controlli superati.\n\n";
    exit(0);
}

echo "  {$falliti} controlli falliti su {$fatti}.\n\n";
exit(1);
