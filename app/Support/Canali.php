<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Unica definizione dei canali social: codice, etichetta e colori.
 * I colori sono gli stessi del prototipo calendario-editabile.html e
 * vengono esposti al CSS come variabili (--ig, --ig-soft, ...).
 */
final class Canali
{
    /** @var array<string,array{etichetta:string,colore:string,soft:string}> */
    public const ELENCO = [
        'ig' => ['etichetta' => 'Instagram', 'colore' => '#C2338B', 'soft' => '#FBE7F3'],
        'fb' => ['etichetta' => 'Facebook',  'colore' => '#1D5FCC', 'soft' => '#E4EDFB'],
        'li' => ['etichetta' => 'LinkedIn',  'colore' => '#0A6BAA', 'soft' => '#E1F0F8'],
        'tt' => ['etichetta' => 'TikTok',    'colore' => '#111827', 'soft' => '#ECEEF1'],
        'yt' => ['etichetta' => 'YouTube',   'colore' => '#B91C1C', 'soft' => '#FDE8E8'],
        'nl' => ['etichetta' => 'Newsletter','colore' => '#B7690B', 'soft' => '#FBF0DE'],
    ];

    /** @return array<int,string> */
    public static function codici(): array
    {
        return array_keys(self::ELENCO);
    }

    public static function valido(string $codice): bool
    {
        return isset(self::ELENCO[$codice]);
    }

    public static function etichetta(string $codice): string
    {
        return self::ELENCO[$codice]['etichetta'] ?? strtoupper($codice);
    }

    /**
     * Ripulisce una lista di canali in arrivo da form o JSON: tiene solo
     * i codici noti, senza duplicati, nell'ordine canonico.
     *
     * @param  mixed $valore array, stringa "ig,fb" o JSON
     * @return array<int,string>
     */
    public static function normalizza(mixed $valore): array
    {
        if (is_string($valore)) {
            $decodificato = json_decode($valore, true);
            $valore = is_array($decodificato) ? $decodificato : explode(',', $valore);
        }
        if (!is_array($valore)) {
            return [];
        }

        $puliti = array_filter(
            array_map(static fn ($c) => strtolower(trim((string) $c)), $valore),
            static fn (string $c) => self::valido($c)
        );

        // array_values su un intersect mantiene l'ordine dell'ELENCO
        return array_values(array_intersect(self::codici(), array_unique($puliti)));
    }

    /** Le variabili CSS dei colori, stampate nel <head> dei layout. */
    public static function variabiliCss(): string
    {
        $righe = [];
        foreach (self::ELENCO as $codice => $dati) {
            $righe[] = "--{$codice}:{$dati['colore']}";
            $righe[] = "--{$codice}-soft:{$dati['soft']}";
        }

        return implode(';', $righe) . ';';
    }

    /**
     * Il segno di ciascun canale, come SVG in linea.
     *
     * In linea e non come file: sono sei icone di poche centinaia di byte
     * l'una, e messe nel CSS o in <img> costerebbero sei richieste in piu'
     * per ogni pagina, oppure uno sprite da tenere allineato a mano.
     *
     * Il colore lo prendono dalla pastiglia (`currentColor`), quindi non
     * c'e' una seconda definizione dei colori dei canali che possa
     * scostarsi da ELENCO.
     *
     * Sono i segni veri dei social, ridisegnati in forma semplice: a
     * sedici pixel i dettagli non si vedono comunque, e quello che conta
     * e' riconoscerli con la coda dell'occhio.
     */
    private const SEGNI = [
        'ig' => '<rect x="3" y="3" width="18" height="18" rx="5.5" fill="none" stroke="currentColor" stroke-width="2"/>'
              . '<circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/>'
              . '<circle cx="17.2" cy="6.8" r="1.4"/>',

        'fb' => '<path d="M13.6 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.8-.05-1.8-.1-2.9-.1'
              . '-2.3 0-3.9 1.4-3.9 4v2.4H7.3V13h2.7v8h3.6z"/>',

        'li' => '<circle cx="5" cy="5" r="2.4"/>'
              . '<rect x="3" y="9" width="4" height="12" rx="0.4"/>'
              . '<path d="M9.2 9H13v1.75h.05c.53-1 1.85-2.05 3.8-2.05 4.06 0 4.8 2.3 4.8 5.5V21h-4v-5.9'
              . 'c0-1.5-.03-3.45-2.1-3.45-2.1 0-2.42 1.6-2.42 3.35V21h-4z"/>',

        'tt' => '<path d="M16.4 5.6A4.3 4.3 0 0 1 15.4 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.28 0 .54.05.79.13V9.7'
              . 'a5.9 5.9 0 0 0-.79-.05 5.8 5.8 0 1 0 5.8 5.8V8.9a7.4 7.4 0 0 0 4.3 1.38V7.15a4.3 4.3 0 0 1-3.4-1.55z"/>',

        'yt' => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.77-1.77C18.26 5 12 5 12 5s-6.26 0-7.83.43A2.5 2.5 0 0 0 2.4 7.2'
              . ' 26.4 26.4 0 0 0 2 12a26.4 26.4 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.77 1.77C5.74 19 12 19 12 19s6.26 0 7.83-.43'
              . 'A2.5 2.5 0 0 0 21.6 16.8 26.4 26.4 0 0 0 22 12a26.4 26.4 0 0 0-.4-4.8zM10 15.5v-7l6 3.5-6 3.5z"/>',

        'nl' => '<rect x="2.6" y="5" width="18.8" height="14" rx="2.4" fill="none" stroke="currentColor" stroke-width="2"/>'
              . '<path d="m3.6 7.6 8.4 5.6 8.4-5.6" fill="none" stroke="currentColor" stroke-width="2"'
              . ' stroke-linecap="round" stroke-linejoin="round"/>',
    ];

    /** L'SVG di un canale, o stringa vuota se il codice non è noto. */
    public static function segno(string $codice): string
    {
        if (!isset(self::SEGNI[$codice])) {
            return '';
        }

        // aria-hidden: l'etichetta accanto dice già di che canale si tratta,
        // e farlo leggere due volte a chi usa uno screen reader è peggio.
        return '<svg class="tag-segno" viewBox="0 0 24 24" width="13" height="13"'
            . ' fill="currentColor" aria-hidden="true" focusable="false">'
            . self::SEGNI[$codice] . '</svg>';
    }

    /**
     * Etichette HTML dei canali di un post.
     *
     * @param array<int,string> $codici
     */
    public static function tag(array $codici): string
    {
        $html = '';
        foreach ($codici as $codice) {
            if (self::valido($codice)) {
                $html .= '<span class="tag ' . $codice . '">'
                    . self::segno($codice)
                    . '<span class="tag-nome">' . e(self::etichetta($codice)) . '</span>'
                    . '</span>';
            }
        }

        return $html;
    }
}
