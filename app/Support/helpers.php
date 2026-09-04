<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;

/**
 * Funzioni globali usate soprattutto nelle viste.
 * Regola: qualunque valore stampato in una vista passa da e().
 */

if (!function_exists('e')) {
    /** Escape HTML di ogni output. */
    function e(mixed $valore): string
    {
        return htmlspecialchars((string) ($valore ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** URL interno a partire dalla radice dell'app: url('/clienti'). */
    function url(string $percorso = '/'): string
    {
        $percorso = '/' . ltrim($percorso, '/');

        return Request::$base . ($percorso === '/' ? '/' : rtrim($percorso, '/'));
    }
}

if (!function_exists('url_assoluta')) {
    /** URL completa, per i link pubblici da mandare al cliente. */
    function url_assoluta(string $percorso = '/'): string
    {
        return rtrim((string) Config::get('APP_URL', ''), '/') . '/' . ltrim($percorso, '/');
    }
}

if (!function_exists('asset')) {
    /** File statico con marcatore di versione, per evitare la cache del browser. */
    function asset(string $percorso): string
    {
        $percorso = ltrim($percorso, '/');
        $file = BASE_PATH . '/public/' . $percorso;
        $v = is_file($file) ? (string) filemtime($file) : '1';

        return url('/' . $percorso) . '?v=' . $v;
    }
}

if (!function_exists('csrf_field')) {
    /** Campo nascosto da mettere in ogni form POST. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('attiva_se')) {
    /** Classe "attiva" per la voce di menu corrispondente alla pagina corrente. */
    function attiva_se(string $prefisso): string
    {
        $corrente = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
        $base = Request::$base;
        if ($base !== '' && str_starts_with($corrente, $base)) {
            $corrente = '/' . trim(substr($corrente, strlen($base)), '/');
        }

        if ($prefisso === '/') {
            return $corrente === '/' ? ' class="attiva"' : '';
        }

        return str_starts_with($corrente, $prefisso) ? ' class="attiva"' : '';
    }
}

/* ---------------------------------------------------------------- date --- */

const GIORNI_IT = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
const GIORNI_IT_BREVI = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const MESI_IT = [
    1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
    'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
];

if (!function_exists('data_it')) {
    /** "2026-10-03" -> "3 ottobre 2026" (o "3 ottobre" senza anno). */
    function data_it(string|DateTimeInterface|null $data, bool $conAnno = true): string
    {
        $d = to_date($data);
        if ($d === null) {
            return '';
        }

        return (int) $d->format('j') . ' ' . MESI_IT[(int) $d->format('n')]
            . ($conAnno ? ' ' . $d->format('Y') : '');
    }
}

if (!function_exists('giorno_it')) {
    /** "2026-10-03" -> "Sab 3" (breve) oppure "Sabato 3" (esteso). */
    function giorno_it(string|DateTimeInterface|null $data, bool $breve = true): string
    {
        $d = to_date($data);
        if ($d === null) {
            return '';
        }
        $indice = (int) $d->format('N') - 1;

        return ($breve ? GIORNI_IT_BREVI[$indice] : GIORNI_IT[$indice]) . ' ' . (int) $d->format('j');
    }
}

if (!function_exists('nome_giorno_it')) {
    /** Solo il nome del giorno: "Giovedì". Senza numero, per non ripeterlo. */
    function nome_giorno_it(string|DateTimeInterface|null $data): string
    {
        $d = to_date($data);

        return $d === null ? '' : GIORNI_IT[(int) $d->format('N') - 1];
    }
}

if (!function_exists('mese_it')) {
    /** "2026-10-03" -> "Ottobre 2026". */
    function mese_it(string|DateTimeInterface|null $data): string
    {
        $d = to_date($data);

        return $d === null ? '' : ucfirst(MESI_IT[(int) $d->format('n')]) . ' ' . $d->format('Y');
    }
}

if (!function_exists('ora_it')) {
    /** "18:30:00" -> "18:30"; vuoto se l'ora non e impostata. */
    function ora_it(?string $ora): string
    {
        return $ora === null || $ora === '' ? '' : substr($ora, 0, 5);
    }
}

if (!function_exists('periodo_it')) {
    /** Periodo compatto: "1 – 31 ottobre 2026" oppure "28 settembre – 4 ottobre 2026". */
    function periodo_it(string|DateTimeInterface|null $inizio, string|DateTimeInterface|null $fine): string
    {
        $a = to_date($inizio);
        $b = to_date($fine);
        if ($a === null || $b === null) {
            return '';
        }
        if ($a->format('Y-m') === $b->format('Y-m')) {
            return (int) $a->format('j') . ' – ' . data_it($b);
        }
        if ($a->format('Y') === $b->format('Y')) {
            return data_it($a, false) . ' – ' . data_it($b);
        }

        return data_it($a) . ' – ' . data_it($b);
    }
}

if (!function_exists('to_date')) {
    function to_date(string|DateTimeInterface|null $valore): ?DateTimeImmutable
    {
        if ($valore === null || $valore === '') {
            return null;
        }
        if ($valore instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($valore);
        }
        try {
            return new DateTimeImmutable($valore);
        } catch (Exception) {
            return null;
        }
    }
}

if (!function_exists('token_casuale')) {
    /** Token per i link pubblici: 64 caratteri esadecimali. */
    function token_casuale(int $byte = 32): string
    {
        return bin2hex(random_bytes($byte));
    }
}

if (!function_exists('misura_immagine')) {
    /**
     * Dimensioni di un'immagine caricata, per la miniatura: «2161×2700 · 4:5».
     *
     * Serve a vedere subito la forma del file, che e' l'unica cosa che
     * l'anteprima non puo' piu' dire da sola: da quando non ritaglia piu',
     * una grafica sbagliata si vede intera invece che tagliata, il che va
     * benissimo per il cliente ma toglie a noi il campanello d'allarme.
     *
     * La proporzione si scrive per esteso solo quando cade su una di quelle
     * che si usano davvero: ridurre 2161×2700 col massimo comun divisore
     * darebbe «2161:2700», che non dice niente a nessuno.
     *
     * @param string $percorso relativo a public/, come sta in database
     */
    function misura_immagine(string $percorso): ?string
    {
        $file = BASE_PATH . '/public/' . ltrim($percorso, '/');
        if (!is_file($file)) {
            return null;
        }

        $dati = @getimagesize($file);
        if ($dati === false || $dati[0] <= 0 || $dati[1] <= 0) {
            return null;
        }

        [$larghezza, $altezza] = $dati;
        $misura = $larghezza . '×' . $altezza;

        $note = ['1:1' => 1, '4:5' => 0.8, '3:4' => 0.75, '2:3' => 2 / 3,
                 '9:16' => 0.5625, '16:9' => 16 / 9, '4:3' => 4 / 3, '3:2' => 1.5];

        $propria = $larghezza / $altezza;
        foreach ($note as $etichetta => $valore) {
            if (abs($propria - $valore) / $valore < 0.02) {
                return $misura . ' · ' . $etichetta;
            }
        }

        return $misura;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $testo): string
    {
        $testo = strtr(mb_strtolower(trim($testo)), [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'ç' => 'c', "'" => ' ',
        ]);
        $testo = preg_replace('/[^a-z0-9]+/u', '-', $testo) ?? '';

        return trim($testo, '-') ?: 'cliente';
    }
}
