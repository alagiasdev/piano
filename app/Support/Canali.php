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
     * Etichette HTML dei canali di un post.
     *
     * @param array<int,string> $codici
     */
    public static function tag(array $codici): string
    {
        $html = '';
        foreach ($codici as $codice) {
            if (self::valido($codice)) {
                $html .= '<span class="tag ' . $codice . '">' . e(self::etichetta($codice)) . '</span>';
            }
        }

        return $html;
    }
}
