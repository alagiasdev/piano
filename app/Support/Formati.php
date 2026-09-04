<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Che forma ha il riquadro dell'anteprima, dato il formato dichiarato.
 *
 * L'anteprima non ritaglia mai: la grafica ci sta dentro intera, e se non
 * riempie la cornice restano delle bande. Quelle bande sono il segnale che
 * il file non ha la forma giusta per il posto in cui andrà, e devono
 * comparire solo quando è vero — altrimenti diventano rumore e nessuno le
 * guarda più.
 *
 * Per questo la regola non è «un formato, una proporzione»:
 *
 * - storie e reel occupano lo schermo intero, e lì 9:16 è esatto: qualunque
 *   altra forma è un errore, e le bande ci vogliono;
 * - nel feed non esiste una forma giusta sola. Instagram pubblica senza
 *   toccare niente tutto quello che sta fra 4:5 e 1.91:1, quindi dentro
 *   quell'intervallo la cornice segue la grafica e non c'è nessuna banda.
 *   Fuori, la cornice è 4:5 e le bande dicono che qualcosa non torna;
 * - i formati che non c'entrano con le immagini, o che non conosciamo,
 *   non impongono niente: comanda la grafica.
 *
 * I formati sono testo libero e personalizzabili dalle impostazioni, quindi
 * si riconoscono per parola contenuta e non per uguaglianza: «Reel», «reel
 * 30s» e «Reel + storia» sono tutti verticali.
 */
final class Formati
{
    /** A schermo intero: 9:16 esatto. */
    private const VERTICALI = '/stori|reel|tiktok|short|verticale/i';

    /** Nel feed, dove vale un intervallo invece di un valore. */
    private const FEED = '/carosell|foto|immagin|testo \+|video|pdf|documento|grafica/i';

    private const FEED_MIN = 0.8;   // 4:5, il più stretto accettato
    private const FEED_MAX = 1.91;  // 1.91:1, il più largo accettato

    /**
     * La proporzione da dare al riquadro, pronta per il CSS («9 / 16»),
     * oppure null quando la forma la detta la grafica.
     *
     * @param float|null $immagine proporzione del file (larghezza / altezza)
     */
    public static function cornice(string $formato, ?float $immagine): ?string
    {
        if (preg_match(self::VERTICALI, $formato) === 1) {
            return '9 / 16';
        }

        if (preg_match(self::FEED, $formato) !== 1) {
            return null;
        }

        // Senza il file sotto mano non possiamo dire se sta nell'intervallo:
        // meglio nessuna cornice che una cornice sbagliata.
        if ($immagine === null || $immagine <= 0) {
            return null;
        }

        if ($immagine >= self::FEED_MIN && $immagine <= self::FEED_MAX) {
            return null;
        }

        return '4 / 5';
    }
}
