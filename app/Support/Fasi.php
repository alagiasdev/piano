<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Le due fasi di un piano.
 *
 * "concept" e' quella storica: il cliente approva le idee, cioe' la riga di
 * descrizione di ogni post. "esecutivi" viene dopo: caricati copy finale e
 * immagini, il cliente approva il post come lo vedra' pubblicato.
 *
 * Le due fasi condividono stati, pulsanti e pagina pubblica: cambiano solo
 * le colonne su cui si scrive. La mappa COLONNE e' quello che permette di
 * avere un'implementazione sola invece di due parallele. I nomi qui dentro
 * finiscono dentro SQL, quindi non devono mai arrivare da fuori: si passa
 * sempre per colonne(), che accetta solo le chiavi note.
 */
final class Fasi
{
    /** @var array<string,string> */
    public const ELENCO = [
        'concept'  => 'Concept',
        'esecutivi' => 'Esecutivi',
    ];

    /** @var array<string,array<string,string>> */
    private const COLONNE = [
        'concept' => [
            'stato'        => 'stato',
            'commento'     => 'commento_cliente',
            'commento_il'  => 'commento_il',
            'approvato_il' => 'approvato_il',
        ],
        'esecutivi' => [
            'stato'        => 'stato_esecutivo',
            'commento'     => 'commento_esecutivo',
            'commento_il'  => 'commento_esecutivo_il',
            'approvato_il' => 'approvato_esecutivo_il',
        ],
    ];

    public static function valida(?string $fase): bool
    {
        return $fase !== null && isset(self::ELENCO[$fase]);
    }

    /** Riporta a "concept" qualunque valore non riconosciuto. */
    public static function normalizza(mixed $fase): string
    {
        return is_string($fase) && isset(self::ELENCO[$fase]) ? $fase : 'concept';
    }

    public static function etichetta(string $fase): string
    {
        return self::ELENCO[$fase] ?? $fase;
    }

    /**
     * I nomi delle colonne su cui lavora la fase indicata.
     *
     * @return array{stato:string,commento:string,commento_il:string,approvato_il:string}
     */
    public static function colonne(string $fase): array
    {
        return self::COLONNE[self::normalizza($fase)];
    }
}
