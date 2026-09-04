<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Impostazione;

/**
 * Suggerimenti per i campi "formato" e "call to action".
 *
 * Non sono un insieme chiuso come i canali: servono a scegliere in fretta
 * il valore che si usa nove volte su dieci, ma il campo resta a scrittura
 * libera. Il giorno che serve una voce nuova la si scrive e basta.
 *
 * I valori di partenza sono quelli che ricorrono nei piani; da
 * Impostazioni si sostituiscono con i propri, uno per riga.
 */
final class Elenchi
{
    /** @var array<int,string> */
    private const FORMATI = [
        'Carosello',
        'Foto singola',
        'Reel',
        'Storie',
        'Video',
        'Testo + foto',
        'Solo testo',
        'PDF / documento',
        'Email',
        'Sondaggio',
        'Diretta',
    ];

    /**
     * Inviti all'azione scritti nella caption, non i pulsanti di Meta per
     * i post sponsorizzati: sono due cose diverse.
     *
     * @var array<int,string>
     */
    private const CTA = [
        'Salva il post',
        'Commenta',
        'Condividi',
        'Scrivici in DM',
        'Prenota dal link in bio',
        'Scopri di più',
        'Contattaci',
        'Seguici',
        'Iscriviti alla newsletter',
        'Lascia una recensione',
        'Fai una domanda',
        'Vota',
    ];

    /** @return array<int,string> */
    public static function formati(): array
    {
        return self::daImpostazione('elenco_formati', self::FORMATI);
    }

    /** @return array<int,string> */
    public static function cta(): array
    {
        return self::daImpostazione('elenco_cta', self::CTA);
    }

    /** I valori di partenza, per precompilare la casella nelle impostazioni. */
    public static function formatiPredefiniti(): string
    {
        return implode("\n", self::FORMATI);
    }

    public static function ctaPredefinite(): string
    {
        return implode("\n", self::CTA);
    }

    /**
     * Legge l'elenco personalizzato, una voce per riga. Se la casella e
     * vuota si torna ai valori di partenza: cosi svuotarla non lascia
     * l'utente senza suggerimenti.
     *
     * @param  array<int,string> $predefiniti
     * @return array<int,string>
     */
    private static function daImpostazione(string $chiave, array $predefiniti): array
    {
        $righe = preg_split('/\R/', Impostazione::get($chiave, '')) ?: [];

        $voci = [];
        foreach ($righe as $riga) {
            $voce = trim($riga);
            if ($voce !== '' && !in_array($voce, $voci, true)) {
                $voci[] = mb_substr($voce, 0, 60);
            }
        }

        return $voci === [] ? $predefiniti : $voci;
    }
}
