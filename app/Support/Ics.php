<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generatore di file ICS (RFC 5545) per importare il piano in Google
 * Calendar. Un evento per post: 30 minuti se c'e l'ora, altrimenti
 * un evento di giornata.
 */
final class Ics
{
    private const DURATA_MINUTI = 30;

    /**
     * @param array<string,mixed>            $piano
     * @param array<int,array<string,mixed>> $post
     */
    public static function perPiano(array $piano, array $post, string $dominio): string
    {
        $righe = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Piano//Piani editoriali//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::testo($piano['cliente_nome'] . ' — ' . $piano['titolo']),
            'X-WR-TIMEZONE:Europe/Rome',
        ];

        // Definizione del fuso: senza, Google interpreta le ore come UTC.
        $righe = array_merge($righe, self::fusoRoma());

        $adesso = gmdate('Ymd\THis\Z');

        foreach ($post as $p) {
            $righe = array_merge($righe, self::evento($p, $piano, $dominio, $adesso));
        }

        $righe[] = 'END:VCALENDAR';

        // Le righe ICS si separano con CRLF e si spezzano a 75 ottetti
        return implode("\r\n", array_map([self::class, 'piega'], $righe)) . "\r\n";
    }

    /**
     * @param  array<string,mixed> $post
     * @param  array<string,mixed> $piano
     * @return array<int,string>
     */
    private static function evento(array $post, array $piano, string $dominio, string $adesso): array
    {
        $data = (string) $post['data'];
        $ora  = $post['ora'] ?? null;

        $canali = array_map(
            static fn (string $c) => Canali::etichetta($c),
            is_array($post['canali']) ? $post['canali'] : []
        );

        $contenuto  = trim((string) ($post['contenuto'] ?? ''));
        $primaRiga  = trim(explode("\n", $contenuto)[0]);
        $etichetta  = $canali === [] ? 'Post' : implode(', ', $canali);
        $riepilogo  = '[' . $etichetta . '] ' . ($primaRiga !== '' ? $primaRiga : 'Post senza testo');

        $descrizione = [];
        if ($contenuto !== '') {
            $descrizione[] = $contenuto;
        }
        foreach ([
            'Formato'  => $post['formato'] ?? null,
            'CTA'      => $post['cta'] ?? null,
            'Pilastro' => $post['pilastro'] ?? null,
            'Visual'   => $post['visual_url'] ?? null,
        ] as $chiave => $valore) {
            if (($valore ?? '') !== '') {
                $descrizione[] = $chiave . ': ' . $valore;
            }
        }
        $descrizione[] = 'Stato: ' . Stati::etichettaPost((string) $post['stato']);
        $descrizione[] = 'Piano: ' . $piano['titolo'] . ' — ' . $piano['cliente_nome'];

        $righe = [
            'BEGIN:VEVENT',
            'UID:post-' . (int) $post['id'] . '-piano-' . (int) $piano['id'] . '@' . $dominio,
            'DTSTAMP:' . $adesso,
            'SUMMARY:' . self::testo($riepilogo),
            'DESCRIPTION:' . self::testo(implode("\n", $descrizione)),
            'CATEGORIES:' . self::testo($etichetta),
        ];

        if ($ora === null || $ora === '') {
            // Evento di giornata: DTEND e il giorno successivo (fine esclusa)
            $righe[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $data);
            $righe[] = 'DTEND;VALUE=DATE:' . (new \DateTimeImmutable($data))->modify('+1 day')->format('Ymd');
        } else {
            $inizio = new \DateTimeImmutable($data . ' ' . $ora, new \DateTimeZone('Europe/Rome'));
            $fine   = $inizio->modify('+' . self::DURATA_MINUTI . ' minutes');
            $righe[] = 'DTSTART;TZID=Europe/Rome:' . $inizio->format('Ymd\THis');
            $righe[] = 'DTEND;TZID=Europe/Rome:' . $fine->format('Ymd\THis');
        }

        $righe[] = 'END:VEVENT';

        return $righe;
    }

    /** Blocco VTIMEZONE per l'ora legale italiana. */
    private static function fusoRoma(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Rome',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /** Escape dei caratteri speciali ICS: barra, virgola, punto e virgola, newline. */
    private static function testo(string $valore): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'],
            $valore
        );
    }

    /**
     * Piegatura delle righe lunghe: massimo 75 ottetti, le successive
     * iniziano con uno spazio. Si conta in byte, non in caratteri, ma senza
     * spezzare un carattere UTF-8 a meta.
     */
    private static function piega(string $riga): string
    {
        if (strlen($riga) <= 75) {
            return $riga;
        }

        $pezzi = [];
        $corrente = '';
        $limite = 75;

        foreach (mb_str_split($riga) as $carattere) {
            if (strlen($corrente) + strlen($carattere) > $limite) {
                $pezzi[] = $corrente;
                $corrente = $carattere;
                $limite = 74; // le righe di continuazione perdono un ottetto per lo spazio
                continue;
            }
            $corrente .= $carattere;
        }
        $pezzi[] = $corrente;

        return implode("\r\n ", $pezzi);
    }
}
