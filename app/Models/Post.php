<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Conflitto;
use App\Core\Db;
use App\Support\Canali;
use App\Support\Fasi;
use App\Support\Stati;
use InvalidArgumentException;

final class Post
{
    /**
     * Campi che l'editor puo modificare inline. Fa da whitelist: qualunque
     * altro nome di colonna in arrivo viene rifiutato.
     *
     * @var array<int,string>
     */
    public const CAMPI_MODIFICABILI = [
        'data', 'ora', 'canali', 'contenuto', 'formato', 'cta', 'pilastro', 'visual_url', 'stato',
        // Fase esecutivi: il testo che verra pubblicato davvero e il suo stato
        'copy_finale', 'stato_esecutivo',
    ];

    /**
     * I post di un piano, con le immagini gia attaccate.
     * Le immagini si caricano con una query sola per tutto il piano.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function perPiano(int $pianoId): array
    {
        $media = Media::perPiano($pianoId);

        return array_map(
            static function (array $riga) use ($media): array {
                $riga = self::espandi($riga);
                $riga['media'] = $media[(int) $riga['id']] ?? [];

                return $riga;
            },
            Db::all('SELECT * FROM post WHERE piano_id = ? ORDER BY data, ordine, id', [$pianoId])
        );
    }

    public static function trova(int $id): ?array
    {
        $riga = Db::first('SELECT * FROM post WHERE id = ?', [$id]);

        return $riga === null ? null : self::espandi($riga);
    }

    /** Il post con il piano a cui appartiene, per i controlli di accesso. */
    public static function conPiano(int $id): ?array
    {
        $riga = Db::first(
            'SELECT s.*, p.stato AS piano_stato, p.cliente_id
             FROM post s JOIN piani p ON p.id = s.piano_id
             WHERE s.id = ?',
            [$id]
        );

        return $riga === null ? null : self::espandi($riga);
    }

    /** @param array<string,mixed> $dati */
    public static function crea(int $pianoId, array $dati): int
    {
        $data = (string) $dati['data'];

        Db::run(
            'INSERT INTO post (piano_id, data, ora, canali, contenuto, formato, cta, pilastro, visual_url, stato, ordine)
             VALUES (:piano_id, :data, :ora, :canali, :contenuto, :formato, :cta, :pilastro, :visual_url, :stato, :ordine)',
            [
                'piano_id'   => $pianoId,
                'data'       => $data,
                'ora'        => self::oraOppureNull($dati['ora'] ?? null),
                'canali'     => json_encode(Canali::normalizza($dati['canali'] ?? []), JSON_UNESCAPED_UNICODE),
                'contenuto'  => self::testoOppureNull($dati['contenuto'] ?? null),
                'formato'    => self::testoOppureNull($dati['formato'] ?? null),
                'cta'        => self::testoOppureNull($dati['cta'] ?? null),
                'pilastro'   => self::testoOppureNull($dati['pilastro'] ?? null),
                'visual_url' => self::testoOppureNull($dati['visual_url'] ?? null),
                'stato'      => Stati::postValido((string) ($dati['stato'] ?? '')) ? $dati['stato'] : 'da_approvare',
                'ordine'     => self::prossimoOrdine($pianoId, $data),
            ]
        );

        return Db::lastId();
    }

    /**
     * Aggiorna un singolo campo (salvataggio inline).
     * Restituisce il valore normalizzato, cosi il client mostra esattamente
     * cio che e finito in database.
     */
    public static function aggiornaCampo(
        int $id,
        string $campo,
        mixed $valore,
        mixed $atteso = null,
        ?int $utenteId = null
    ): mixed {
        if (!in_array($campo, self::CAMPI_MODIFICABILI, true)) {
            throw new InvalidArgumentException('Campo non modificabile.');
        }

        // Se il client dice quale valore credeva di avere sotto, si controlla
        // che sia ancora quello: altrimenti un collega ha scritto nel
        // frattempo e sovrascriverlo in silenzio sarebbe la cosa peggiore.
        if ($atteso !== null) {
            self::verificaNessunConflitto($id, $campo, $atteso);
        }

        $normalizzato = match ($campo) {
            'data'                     => self::dataValida($valore),
            'ora'                      => self::oraOppureNull($valore),
            'canali'                   => Canali::normalizza($valore),
            'stato', 'stato_esecutivo'  => self::statoValido($valore),
            'contenuto', 'copy_finale' => self::testoOppureNull($valore, 65535),
            default                    => self::testoOppureNull($valore, 500),
        };

        $daScrivere = $campo === 'canali'
            ? json_encode($normalizzato, JSON_UNESCAPED_UNICODE)
            : $normalizzato;

        // Il nome del campo viene dalla whitelist, non dall'input.
        Db::run(
            "UPDATE post SET {$campo} = ?, modificato_da = ? WHERE id = ?",
            [$daScrivere, $utenteId, $id]
        );

        // Se l'admin riporta il post fuori da "approvato", la data di
        // approvazione del cliente non vale piu: altrimenti la dashboard
        // continuerebbe a elencarlo fra gli approvati.
        if (($campo === 'stato' || $campo === 'stato_esecutivo')
            && !Stati::contaComeApprovato((string) $normalizzato)) {
            $colonna = $campo === 'stato' ? 'approvato_il' : 'approvato_esecutivo_il';
            Db::run("UPDATE post SET {$colonna} = NULL WHERE id = ?", [$id]);
        }

        // Cambiare data significa entrare in un altro giorno: si va in coda.
        if ($campo === 'data') {
            $post = Db::first('SELECT piano_id FROM post WHERE id = ?', [$id]);
            if ($post !== null) {
                Db::run(
                    'UPDATE post SET ordine = ? WHERE id = ?',
                    [self::prossimoOrdine((int) $post['piano_id'], (string) $normalizzato, $id), $id]
                );
            }
        }

        return $normalizzato;
    }

    /**
     * Il valore in database e ancora quello che il client crede? Se no,
     * qualcun altro ha scritto nel frattempo e si alza un Conflitto con
     * dentro il valore vero e il nome di chi l'ha messo.
     */
    private static function verificaNessunConflitto(int $id, string $campo, mixed $atteso): void
    {
        $riga = Db::first(
            "SELECT p.{$campo} AS valore, p.updated_at, u.nome AS chi
             FROM post p LEFT JOIN utenti u ON u.id = p.modificato_da
             WHERE p.id = ?",
            [$id]
        );

        if ($riga === null) {
            return;   // il post non c'e piu: se ne accorge l'UPDATE
        }

        if (self::confrontabile($campo, $riga['valore']) === self::confrontabile($campo, $atteso)) {
            return;
        }

        throw new Conflitto(
            self::perIlClient($campo, $riga['valore']),
            $riga['chi'] !== null ? (string) $riga['chi'] : null,
            $riga['updated_at'] !== null ? (string) $riga['updated_at'] : null
        );
    }

    /**
     * Riduce un valore alla forma con cui ha senso confrontarlo.
     * Serve perche database e client vedono lo stesso dato scritto in modo
     * diverso: i canali sono JSON di qua e array di la, l'ora e "18:30:00"
     * contro "18:30", e il nulla puo essere NULL oppure stringa vuota.
     */
    private static function confrontabile(string $campo, mixed $valore): string
    {
        if ($campo === 'canali') {
            return implode(',', Canali::normalizza($valore));
        }

        if ($campo === 'ora') {
            return substr((string) ($valore ?? ''), 0, 5);
        }

        return trim((string) ($valore ?? ''));
    }

    /** Il valore del database nella forma in cui il client se lo aspetta. */
    private static function perIlClient(string $campo, mixed $valore): mixed
    {
        return match ($campo) {
            'canali' => Canali::normalizza($valore),
            'ora'    => substr((string) ($valore ?? ''), 0, 5),
            default  => (string) ($valore ?? ''),
        };
    }

    /**
     * Un post ha un esecutivo quando ha almeno il copy finale o un'immagine.
     * Sotto, la stessa condizione scritta in SQL: le due devono restare
     * d'accordo, perche una decide cosa si vede e l'altra cosa si puo fare.
     */
    public static function haEsecutivo(array $post): bool
    {
        return trim((string) ($post['copy_finale'] ?? '')) !== ''
            || ($post['media'] ?? []) !== [];
    }

    private const SQL_HA_ESECUTIVO = "(COALESCE(TRIM(copy_finale), '') <> ''
        OR EXISTS (SELECT 1 FROM post_media m WHERE m.post_id = post.id))";

    public static function esecutivoPronto(int $id): bool
    {
        return (bool) Db::value(
            'SELECT ' . self::SQL_HA_ESECUTIVO . ' FROM post WHERE id = ?',
            [$id]
        );
    }

    /** Quanti post del piano non hanno ancora né copy finale né immagini. */
    public static function senzaEsecutivo(int $pianoId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM post WHERE piano_id = ? AND NOT ' . self::SQL_HA_ESECUTIVO,
            [$pianoId]
        );
    }

    public static function elimina(int $id): void
    {
        // Prima le immagini: la foreign key porterebbe via le righe di
        // post_media senza cancellare i file, che resterebbero sul disco.
        Media::eliminaPerPost($id);

        Db::run('DELETE FROM post WHERE id = ?', [$id]);
    }

    /** Copia il post nello stesso giorno, subito dopo l'originale. */
    public static function duplica(int $id): ?int
    {
        $post = Db::first('SELECT * FROM post WHERE id = ?', [$id]);
        if ($post === null) {
            return null;
        }

        Db::run(
            'INSERT INTO post (piano_id, data, ora, canali, contenuto, formato, cta, pilastro, visual_url, stato, ordine)
             SELECT piano_id, data, ora, canali, contenuto, formato, cta, pilastro, visual_url,
                    :stato, :ordine
             FROM post WHERE id = :id',
            [
                'id'     => $id,
                'stato'  => 'da_approvare',
                'ordine' => (int) $post['ordine'] + 1,
            ]
        );

        $nuovoId = Db::lastId();
        self::rinumera((int) $post['piano_id'], (string) $post['data']);

        return $nuovoId;
    }

    /**
     * Sposta il post su o giu fra quelli dello stesso giorno.
     * Restituisce false se e gia in cima (o in fondo).
     */
    public static function sposta(int $id, string $direzione): bool
    {
        $post = Db::first('SELECT piano_id, data FROM post WHERE id = ?', [$id]);
        if ($post === null) {
            return false;
        }

        $fratelli = Db::all(
            'SELECT id FROM post WHERE piano_id = ? AND data = ? ORDER BY ordine, id',
            [$post['piano_id'], $post['data']]
        );
        $ids = array_map(static fn (array $r) => (int) $r['id'], $fratelli);
        $posizione = array_search($id, $ids, true);

        if ($posizione === false) {
            return false;
        }

        $destinazione = $direzione === 'su' ? $posizione - 1 : $posizione + 1;
        if ($destinazione < 0 || $destinazione >= count($ids)) {
            return false;
        }

        [$ids[$posizione], $ids[$destinazione]] = [$ids[$destinazione], $ids[$posizione]];

        foreach ($ids as $indice => $idPost) {
            Db::run('UPDATE post SET ordine = ? WHERE id = ?', [$indice, $idPost]);
        }

        return true;
    }

    /* ------------------------------------------------- azioni del cliente -- */

    /** Approvazione dalla pagina pubblica. */
    /*
     * Approvazione del cliente.
     *
     * Le tre operazioni sono identiche nelle due fasi: cambiano solo le
     * colonne su cui scrivono, che arrivano da Fasi::colonne(). I nomi
     * vengono da una costante, mai dall'input: sono sicuri da interpolare.
     */

    public static function approva(int $id, string $fase = 'concept'): void
    {
        $c = Fasi::colonne($fase);

        Db::run(
            "UPDATE post SET {$c['stato']} = 'approvato', {$c['approvato_il']} = NOW(),
                    {$c['commento']} = NULL, {$c['commento_il']} = NULL
             WHERE id = ? AND {$c['stato']} IN ('da_approvare','da_rivedere')",
            [$id]
        );
    }

    /**
     * Approva in blocco i post ancora in attesa. Restituisce quanti ne ha toccati.
     *
     * Nella fase esecutivi salta quelli senza copy ne immagini: il cliente
     * non li ha visti, e un'approvazione alla cieca non vale niente.
     */
    public static function approvaInAttesa(int $pianoId, string $fase = 'concept'): int
    {
        $c = Fasi::colonne($fase);
        $soloPronti = $fase === 'esecutivi' ? ' AND ' . self::SQL_HA_ESECUTIVO : '';

        $stmt = Db::run(
            "UPDATE post SET {$c['stato']} = 'approvato', {$c['approvato_il']} = NOW(),
                    {$c['commento']} = NULL, {$c['commento_il']} = NULL
             WHERE piano_id = ? AND {$c['stato']} IN ('da_approvare','da_rivedere'){$soloPronti}",
            [$pianoId]
        );

        return $stmt->rowCount();
    }

    public static function chiediModifica(int $id, string $commento, string $fase = 'concept'): void
    {
        $c = Fasi::colonne($fase);

        Db::run(
            "UPDATE post SET {$c['stato']} = 'da_rivedere', {$c['commento']} = ?,
                    {$c['commento_il']} = NOW(), {$c['approvato_il']} = NULL
             WHERE id = ? AND {$c['stato']} IN ('da_approvare','approvato','da_rivedere')",
            [mb_substr(trim($commento), 0, 2000), $id]
        );
    }

    /* --------------------------------------------------------- dashboard -- */

    /**
     * Post in pubblicazione nei prossimi $giorni giorni, di tutti i clienti.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function prossimi(int $giorni = 7): array
    {
        return array_map(
            [self::class, 'espandi'],
            Db::all(
                'SELECT s.*, p.titolo AS piano_titolo, p.id AS piano_id, c.nome AS cliente_nome
                 FROM post s
                 JOIN piani p ON p.id = s.piano_id
                 JOIN clienti c ON c.id = p.cliente_id
                 WHERE c.attivo = 1
                   AND s.data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                 ORDER BY s.data, s.ordine, s.id',
                [$giorni]
            )
        );
    }

    /**
     * Ultime azioni dei clienti (approvazioni e richieste di modifica).
     * Sostituisce le notifiche via email: si guardano entrando in piattaforma.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function novita(int $limite = 12): array
    {
        return array_map(
            [self::class, 'espandi'],
            Db::all(
                'SELECT s.*, p.titolo AS piano_titolo, p.id AS piano_id, c.nome AS cliente_nome,
                        GREATEST(COALESCE(s.approvato_il, 0), COALESCE(s.commento_il, 0)) AS quando
                 FROM post s
                 JOIN piani p ON p.id = s.piano_id
                 JOIN clienti c ON c.id = p.cliente_id
                 WHERE s.approvato_il IS NOT NULL OR s.commento_il IS NOT NULL
                 ORDER BY quando DESC
                 LIMIT ' . max(1, min(50, $limite))
            )
        );
    }

    /* ------------------------------------------------------------ interni -- */

    /** Prossima posizione libera nel giorno indicato. */
    private static function prossimoOrdine(int $pianoId, string $data, ?int $escludi = null): int
    {
        $max = Db::value(
            'SELECT MAX(ordine) FROM post WHERE piano_id = ? AND data = ? AND id <> ?',
            [$pianoId, $data, $escludi ?? 0]
        );

        return $max === null ? 0 : (int) $max + 1;
    }

    /** Riporta gli ordini di un giorno a 0,1,2,... senza buchi. */
    private static function rinumera(int $pianoId, string $data): void
    {
        $righe = Db::all(
            'SELECT id FROM post WHERE piano_id = ? AND data = ? ORDER BY ordine, id',
            [$pianoId, $data]
        );

        foreach ($righe as $indice => $riga) {
            Db::run('UPDATE post SET ordine = ? WHERE id = ?', [$indice, $riga['id']]);
        }
    }

    private static function espandi(array $riga): array
    {
        $riga['canali'] = Canali::normalizza($riga['canali'] ?? []);
        $riga['media'] = $riga['media'] ?? [];

        return $riga;
    }

    private static function dataValida(mixed $valore): string
    {
        $data = to_date(is_string($valore) ? $valore : '');
        if ($data === null) {
            throw new InvalidArgumentException('Data non valida.');
        }

        return $data->format('Y-m-d');
    }

    private static function statoValido(mixed $valore): string
    {
        $stato = is_string($valore) ? $valore : '';
        if (!Stati::postValido($stato)) {
            throw new InvalidArgumentException('Stato non valido.');
        }

        return $stato;
    }

    private static function oraOppureNull(mixed $valore): ?string
    {
        $ora = is_string($valore) ? trim($valore) : '';
        if ($ora === '') {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $ora)) {
            throw new InvalidArgumentException('Ora non valida: usa il formato 18:30.');
        }

        return $ora . ':00';
    }

    private static function testoOppureNull(mixed $valore, int $max = 500): ?string
    {
        $testo = is_string($valore) ? trim($valore) : '';

        return $testo === '' ? null : mb_substr($testo, 0, $max);
    }
}
