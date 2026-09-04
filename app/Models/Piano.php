<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Support\Fasi;
use App\Support\Periodo;
use App\Support\Stati;
use RuntimeException;

final class Piano
{
    /** Colonne di conteggio riusate da tutte le query di elenco. */
    private const CONTEGGI = "
        (SELECT COUNT(*) FROM post s WHERE s.piano_id = p.id) AS post_totali,
        (SELECT COUNT(*) FROM post s WHERE s.piano_id = p.id AND s.stato IN ('approvato','pubblicato')) AS post_approvati,
        (SELECT COUNT(*) FROM post s WHERE s.piano_id = p.id AND s.stato = 'da_rivedere') AS post_da_rivedere,
        (SELECT COUNT(*) FROM post s WHERE s.piano_id = p.id AND s.stato_esecutivo IN ('approvato','pubblicato')) AS esecutivi_approvati,
        (SELECT COUNT(*) FROM post s WHERE s.piano_id = p.id AND s.stato_esecutivo = 'da_rivedere') AS esecutivi_da_rivedere,
        (SELECT COUNT(*) FROM post_media m JOIN post s ON s.id = m.post_id WHERE s.piano_id = p.id) AS media_totali";

    /**
     * Elenco dei piani, dal piu recente. Con $clienteId filtra su un cliente.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function elenco(?int $clienteId = null): array
    {
        $sql = 'SELECT p.*, c.nome AS cliente_nome, c.logo_path AS cliente_logo, ' . self::CONTEGGI . '
                FROM piani p
                JOIN clienti c ON c.id = p.cliente_id';
        $parametri = [];

        if ($clienteId !== null) {
            $sql .= ' WHERE p.cliente_id = ?';
            $parametri[] = $clienteId;
        }

        $sql .= ' ORDER BY p.data_inizio DESC, p.id DESC';

        return Db::all($sql, $parametri);
    }

    public static function trova(int $id): ?array
    {
        return Db::first(
            'SELECT p.*, c.nome AS cliente_nome, c.slug AS cliente_slug,
                    c.logo_path AS cliente_logo, c.contatto_nome, c.contatto_email, ' . self::CONTEGGI . '
             FROM piani p
             JOIN clienti c ON c.id = p.cliente_id
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function perToken(string $token): ?array
    {
        return Db::first(
            'SELECT p.*, c.nome AS cliente_nome, c.logo_path AS cliente_logo, ' . self::CONTEGGI . '
             FROM piani p
             JOIN clienti c ON c.id = p.cliente_id
             WHERE p.token_pubblico = ?',
            [$token]
        );
    }

    /**
     * Il piano che copre la data indicata. Se ce ne sono piu di uno
     * sovrapposti vince quello che inizia piu tardi.
     */
    public static function corrente(int $clienteId, string $data): ?array
    {
        return Db::first(
            'SELECT p.*, ' . self::CONTEGGI . '
             FROM piani p
             WHERE p.cliente_id = ? AND ? BETWEEN p.data_inizio AND p.data_fine
             ORDER BY p.data_inizio DESC
             LIMIT 1',
            [$clienteId, $data]
        );
    }

    /** @param array<string,mixed> $dati */
    public static function crea(array $dati): int
    {
        Db::run(
            'INSERT INTO piani (cliente_id, titolo, data_inizio, data_fine, stato, nota_cliente)
             VALUES (:cliente_id, :titolo, :data_inizio, :data_fine, :stato, :nota_cliente)',
            [
                'cliente_id'   => $dati['cliente_id'],
                'titolo'       => $dati['titolo'],
                'data_inizio'  => $dati['data_inizio'],
                'data_fine'    => $dati['data_fine'],
                'stato'        => $dati['stato'] ?? 'bozza',
                'nota_cliente' => ($dati['nota_cliente'] ?? '') !== '' ? $dati['nota_cliente'] : null,
            ]
        );

        return Db::lastId();
    }

    /** @param array<string,mixed> $dati */
    public static function aggiorna(int $id, array $dati): void
    {
        Db::run(
            'UPDATE piani SET titolo = :titolo, data_inizio = :data_inizio,
                    data_fine = :data_fine, nota_cliente = :nota_cliente
             WHERE id = :id',
            [
                'id'           => $id,
                'titolo'       => $dati['titolo'],
                'data_inizio'  => $dati['data_inizio'],
                'data_fine'    => $dati['data_fine'],
                'nota_cliente' => ($dati['nota_cliente'] ?? '') !== '' ? $dati['nota_cliente'] : null,
            ]
        );
    }

    public static function elimina(int $id): void
    {
        // Prima i file delle immagini: la cascata toglie le righe, non i file.
        Media::eliminaPerPiano($id);

        Db::run('DELETE FROM piani WHERE id = ?', [$id]);
    }

    /**
     * Cambia lo stato del piano. Passando a "inviato" genera il token
     * pubblico se manca e registra la data di invio.
     */
    public static function cambiaStato(int $id, string $stato): void
    {
        if (!Stati::pianoValido($stato)) {
            return;
        }

        if ($stato === 'inviato') {
            $piano = Db::first('SELECT token_pubblico FROM piani WHERE id = ?', [$id]);
            $token = ($piano['token_pubblico'] ?? null) ?: token_casuale();

            Db::run(
                'UPDATE piani SET stato = ?, token_pubblico = ?, inviato_il = COALESCE(inviato_il, NOW()) WHERE id = ?',
                [$stato, $token, $id]
            );

            return;
        }

        Db::run('UPDATE piani SET stato = ? WHERE id = ?', [$stato, $id]);
    }

    /** Nuovo token: il link precedente smette di funzionare. */
    public static function rigeneraToken(int $id): string
    {
        $token = token_casuale();
        Db::run('UPDATE piani SET token_pubblico = ? WHERE id = ?', [$token, $id]);

        return $token;
    }

    /**
     * Sposta il piano fra concept ed esecutivi. Non tocca gli stati dei post:
     * quelli della fase nuova partono da "da approvare" per conto loro.
     */
    public static function cambiaFase(int $id, string $fase): void
    {
        Db::run('UPDATE piani SET fase = ? WHERE id = ?', [Fasi::normalizza($fase), $id]);
    }

    /**
     * Conteggi della fase in corso, per barre di avanzamento ed etichette.
     * Evita che ogni vista debba sapere quali colonne guardare.
     *
     * @param  array<string,mixed> $piano
     * @return array{totali:int,approvati:int,da_rivedere:int,fase:string}
     */
    public static function conteggiFase(array $piano): array
    {
        $fase = Fasi::normalizza($piano['fase'] ?? 'concept');
        $esecutivi = $fase === 'esecutivi';

        return [
            'fase'        => $fase,
            'totali'      => (int) ($piano['post_totali'] ?? 0),
            'approvati'   => (int) ($esecutivi ? ($piano['esecutivi_approvati'] ?? 0) : ($piano['post_approvati'] ?? 0)),
            'da_rivedere' => (int) ($esecutivi ? ($piano['esecutivi_da_rivedere'] ?? 0) : ($piano['post_da_rivedere'] ?? 0)),
        ];
    }

    /**
     * Se tutti i post risultano approvati (o gia pubblicati) il piano passa
     * ad "approvato". Chiamata dopo ogni azione del cliente.
     */
    public static function ricalcolaStato(int $id, string $fase = 'concept'): void
    {
        // Il conteggio guarda la fase in corso: nella fase esecutivi un piano
        // torna "approvato" solo quando sono approvati gli esecutivi.
        $colonnaStato = Fasi::colonne($fase)['stato'];

        $riga = Db::first(
            "SELECT COUNT(*) AS totali,
                    SUM({$colonnaStato} IN ('approvato','pubblicato')) AS approvati
             FROM post WHERE piano_id = ?",
            [$id]
        );

        $totali = (int) ($riga['totali'] ?? 0);
        if ($totali === 0 || (int) ($riga['approvati'] ?? 0) !== $totali) {
            return;
        }

        // Solo da "inviato": un piano chiuso o in bozza non si muove da solo.
        Db::run("UPDATE piani SET stato = 'approvato' WHERE id = ? AND stato = 'inviato'", [$id]);
    }

    /**
     * Duplica il piano su un nuovo periodo, spostando le date dei post e
     * riportando tutti gli stati a "da approvare".
     *
     * Copia solo il concept: copy finale e immagini non si portano dietro,
     * e il piano nuovo riparte dalla fase "concept". Un mese nuovo vuole
     * esecutivi nuovi; ricopiarli darebbe l'illusione di averli gia pronti.
     *
     * @param  string $modo "settimane" oppure "mesi"
     * @return int    id del nuovo piano
     */
    public static function duplica(int $id, string $titolo, string $modo, int $quantita): int
    {
        $origine = Db::first('SELECT * FROM piani WHERE id = ?', [$id]);
        if ($origine === null) {
            throw new RuntimeException('Piano da duplicare non trovato.');
        }

        $modo = $modo === 'mesi' ? 'mesi' : 'settimane';

        $nuovoId = self::crea([
            'cliente_id'   => (int) $origine['cliente_id'],
            'titolo'       => $titolo,
            'data_inizio'  => Periodo::sposta((string) $origine['data_inizio'], $modo, $quantita),
            'data_fine'    => Periodo::sposta((string) $origine['data_fine'], $modo, $quantita),
            'stato'        => 'bozza',
            'nota_cliente' => $origine['nota_cliente'],
        ]);

        foreach (Db::all('SELECT * FROM post WHERE piano_id = ? ORDER BY data, ordine, id', [$id]) as $post) {
            Db::run(
                'INSERT INTO post (piano_id, data, ora, canali, contenuto, formato, cta, pilastro, visual_url, stato, ordine)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $nuovoId,
                    Periodo::sposta((string) $post['data'], $modo, $quantita),
                    $post['ora'],
                    $post['canali'],
                    $post['contenuto'],
                    $post['formato'],
                    $post['cta'],
                    $post['pilastro'],
                    $post['visual_url'],
                    'da_approvare',
                    (int) $post['ordine'],
                ]
            );
        }

        return $nuovoId;
    }
}
