<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stati di piani e post: stessi valori degli ENUM in database,
 * con etichetta e classe CSS per la visualizzazione.
 */
final class Stati
{
    /** @var array<string,string> */
    public const PIANO = [
        'bozza'     => 'Bozza',
        'inviato'   => 'Inviato al cliente',
        'approvato' => 'Approvato',
        'chiuso'    => 'Chiuso',
    ];

    /** @var array<string,string> */
    public const POST = [
        'da_approvare' => 'Da approvare',
        'approvato'    => 'Approvato',
        'da_rivedere'  => 'Da rivedere',
        'pubblicato'   => 'Pubblicato',
    ];

    /**
     * Ordine del clic ciclico sullo stato nell'editor.
     *
     * @var array<int,string>
     */
    public const CICLO_POST = ['da_approvare', 'approvato', 'da_rivedere', 'pubblicato'];

    public static function etichettaPiano(string $stato): string
    {
        return self::PIANO[$stato] ?? $stato;
    }

    public static function etichettaPost(string $stato): string
    {
        return self::POST[$stato] ?? $stato;
    }

    public static function pianoValido(string $stato): bool
    {
        return isset(self::PIANO[$stato]);
    }

    public static function postValido(string $stato): bool
    {
        return isset(self::POST[$stato]);
    }

    /**
     * Un post "pubblicato" conta come approvato: altrimenti un piano gia
     * andato in onda non raggiungerebbe mai lo stato "approvato".
     */
    public static function contaComeApprovato(string $statoPost): bool
    {
        return $statoPost === 'approvato' || $statoPost === 'pubblicato';
    }

    public static function prossimoPost(string $stato): string
    {
        $i = array_search($stato, self::CICLO_POST, true);
        $i = $i === false ? 0 : ((int) $i + 1) % count(self::CICLO_POST);

        return self::CICLO_POST[$i];
    }
}
