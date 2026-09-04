<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Calcoli sui periodi: raggruppamento dei post per settimana e spostamento
 * delle date quando si duplica un piano.
 */
final class Periodo
{
    /** Lunedi della settimana in cui cade la data. */
    public static function lunedi(string $data): DateTimeImmutable
    {
        $d = new DateTimeImmutable($data);
        $giorno = (int) $d->format('N'); // 1 = lunedi

        return $d->modify('-' . ($giorno - 1) . ' days')->setTime(0, 0);
    }

    /**
     * Le settimane (lunedi-domenica) coperte dal periodo, con i post di
     * ciascuna. Le settimane senza post vengono restituite comunque, cosi
     * nell'editor si vede il buco e ci si puo aggiungere un post.
     *
     * @param  array<int,array<string,mixed>> $post ordinati per data
     * @return array<int,array{numero:int,inizio:DateTimeImmutable,fine:DateTimeImmutable,post:array<int,array<string,mixed>>}>
     */
    public static function settimane(string $dataInizio, string $dataFine, array $post): array
    {
        $cursore = self::lunedi($dataInizio);
        $ultimo  = self::lunedi($dataFine);

        // Raggruppa una volta sola: chiave = lunedi della settimana del post
        $perSettimana = [];
        foreach ($post as $p) {
            $chiave = self::lunedi((string) $p['data'])->format('Y-m-d');
            $perSettimana[$chiave][] = $p;
        }

        $settimane = [];
        $numero = 1;

        while ($cursore <= $ultimo) {
            $chiave = $cursore->format('Y-m-d');
            $settimane[] = [
                'numero' => $numero++,
                'inizio' => $cursore,
                'fine'   => $cursore->modify('+6 days'),
                'post'   => $perSettimana[$chiave] ?? [],
            ];
            $cursore = $cursore->modify('+7 days');
        }

        // Post fuori dal periodo dichiarato (capita dopo aver spostato una
        // data): li mostriamo in coda invece di farli sparire.
        $chiaviMostrate = array_map(static fn (array $s) => $s['inizio']->format('Y-m-d'), $settimane);
        foreach ($perSettimana as $chiave => $gruppo) {
            if (!in_array($chiave, $chiaviMostrate, true)) {
                $inizio = new DateTimeImmutable($chiave);
                $settimane[] = [
                    'numero' => 0, // 0 = fuori periodo
                    'inizio' => $inizio,
                    'fine'   => $inizio->modify('+6 days'),
                    'post'   => $gruppo,
                ];
            }
        }

        usort($settimane, static fn (array $a, array $b) => $a['inizio'] <=> $b['inizio']);

        return $settimane;
    }

    /**
     * Sposta una data in avanti (o indietro) per la duplicazione di un piano.
     *
     * modo "settimane": somma multipli di 7 giorni, quindi il giorno della
     * settimana resta lo stesso. E' il default, perche un piano editoriale e
     * costruito sui giorni della settimana ("il martedi e il giovedi").
     *
     * modo "mesi": tiene lo stesso giorno del mese, riportandolo all'ultimo
     * giorno disponibile quando non esiste (31 gennaio + 1 mese = 28 febbraio).
     */
    public static function sposta(string $data, string $modo, int $quantita): string
    {
        $d = new DateTimeImmutable($data);

        if ($modo === 'settimane') {
            return $d->modify(sprintf('%+d days', $quantita * 7))->format('Y-m-d');
        }

        $giorno = (int) $d->format('j');
        // Primo del mese di partenza, poi sposta i mesi: cosi non c'e overflow
        $meseTarget = $d->modify('first day of this month')->modify(sprintf('%+d months', $quantita));
        $giorniNelMese = (int) $meseTarget->format('t');

        return $meseTarget->setDate(
            (int) $meseTarget->format('Y'),
            (int) $meseTarget->format('n'),
            min($giorno, $giorniNelMese)
        )->format('Y-m-d');
    }

    /** Primo e ultimo giorno del mese indicato. @return array{0:string,1:string} */
    public static function mese(int $anno, int $mese): array
    {
        $primo = (new DateTimeImmutable())->setDate($anno, $mese, 1)->setTime(0, 0);

        return [$primo->format('Y-m-d'), $primo->modify('last day of this month')->format('Y-m-d')];
    }

    /** Quante settimane servono per coprire il periodo, arrotondate per eccesso. */
    public static function settimaneNel(string $inizio, string $fine): int
    {
        $a = self::lunedi($inizio);
        $b = self::lunedi($fine);

        return intdiv((int) $a->diff($b)->days, 7) + 1;
    }
}
