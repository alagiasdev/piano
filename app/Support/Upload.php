<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Upload dei loghi dei clienti in public/uploads/.
 * Unico posto in cui si scrivono file caricati dall'utente.
 */
final class Upload
{
    private const MAX_BYTE = 1048576; // 1 MB

    /** @var array<string,string> mime accettato => estensione */
    private const TIPI = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/svg+xml' => 'svg',
    ];

    public static function cartella(): string
    {
        return BASE_PATH . '/public/uploads';
    }

    /**
     * Salva il file caricato e restituisce il percorso relativo da mettere
     * in database ("uploads/nome.png"), oppure null se non e stato caricato
     * niente. Lancia RuntimeException con un messaggio in italiano se il
     * file non va bene.
     *
     * @param array<string,mixed>|null $file la voce di $_FILES
     */
    public static function logo(?array $file, string $nomeBase): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $errore = (int) $file['error'];
        if ($errore !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($errore) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Il logo supera la dimensione massima consentita.',
                UPLOAD_ERR_PARTIAL                        => 'Il caricamento del logo si è interrotto, riprova.',
                default                                   => 'Non è stato possibile caricare il logo.',
            });
        }

        $percorsoTemp = (string) $file['tmp_name'];
        if (!is_uploaded_file($percorsoTemp)) {
            throw new RuntimeException('File non valido.');
        }

        if ((int) $file['size'] > self::MAX_BYTE) {
            throw new RuntimeException('Il logo non può superare 1 MB.');
        }

        // Il tipo si legge dal contenuto, non da quello dichiarato dal browser.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($percorsoTemp);
        if (!isset(self::TIPI[$mime])) {
            throw new RuntimeException('Formato non ammesso: usa JPG, PNG o SVG.');
        }

        $estensione = self::TIPI[$mime];

        // Un SVG e codice: se contiene script o handler di eventi verrebbe
        // eseguito nell'origine del sito quando lo si apre. Meglio rifiutarlo.
        if ($estensione === 'svg' && self::svgPericoloso((string) file_get_contents($percorsoTemp))) {
            throw new RuntimeException('Questo SVG contiene codice eseguibile: esportalo senza script.');
        }

        $cartella = self::cartella();
        if (!is_dir($cartella) && !mkdir($cartella, 0755, true) && !is_dir($cartella)) {
            throw new RuntimeException('Cartella uploads non scrivibile.');
        }

        $nomeFile = slugify($nomeBase) . '-' . bin2hex(random_bytes(4)) . '.' . $estensione;
        if (!move_uploaded_file($percorsoTemp, $cartella . '/' . $nomeFile)) {
            throw new RuntimeException('Non è stato possibile salvare il logo.');
        }

        return 'uploads/' . $nomeFile;
    }

    /* --------------------------------------- immagini dei post (esecutivi) -- */

    private const MEDIA_MAX_BYTE = 5242880;   // 5 MB per immagine

    /** @var array<string,string> Niente SVG qui: le immagini dei post sono foto. */
    private const MEDIA_TIPI = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Salva una immagine di un post.
     *
     * I video non si caricano: su cPanel non c'e modo di generarne
     * l'anteprima e pesano troppo per un hosting condiviso. Per quelli
     * resta il campo "visual_url" con un link a Drive o YouTube.
     *
     * @param  array<string,mixed> $file una voce singola di $_FILES
     * @return array{percorso:string,nome_originale:string,mime:string,byte:int}
     */
    public static function immaginePost(array $file, int $postId): array
    {
        $errore = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errore !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($errore) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Immagine troppo grande: il massimo è 5 MB.',
                UPLOAD_ERR_PARTIAL                        => 'Il caricamento si è interrotto, riprova.',
                UPLOAD_ERR_NO_FILE                        => 'Nessun file selezionato.',
                default                                   => 'Non è stato possibile caricare l\'immagine.',
            });
        }

        $percorsoTemp = (string) $file['tmp_name'];
        if (!is_uploaded_file($percorsoTemp)) {
            throw new RuntimeException('File non valido.');
        }

        if ((int) $file['size'] > self::MEDIA_MAX_BYTE) {
            throw new RuntimeException('Immagine troppo grande: il massimo è 5 MB.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($percorsoTemp);
        if (!isset(self::MEDIA_TIPI[$mime])) {
            throw new RuntimeException('Formato non ammesso: usa JPG, PNG, WebP o GIF.');
        }

        // Deve essere davvero un'immagine leggibile, non solo avere il mime giusto
        if (@getimagesize($percorsoTemp) === false) {
            throw new RuntimeException('Il file non è un\'immagine valida.');
        }

        $cartella = self::cartellaPost($postId);
        if (!is_dir($cartella) && !mkdir($cartella, 0755, true) && !is_dir($cartella)) {
            throw new RuntimeException('Cartella uploads non scrivibile.');
        }

        $nomeFile = bin2hex(random_bytes(8)) . '.' . self::MEDIA_TIPI[$mime];
        if (!move_uploaded_file($percorsoTemp, $cartella . '/' . $nomeFile)) {
            throw new RuntimeException('Non è stato possibile salvare l\'immagine.');
        }

        return [
            'percorso'       => 'uploads/post/' . $postId . '/' . $nomeFile,
            'nome_originale' => mb_substr((string) ($file['name'] ?? ''), 0, 255),
            'mime'           => $mime,
            'byte'           => (int) $file['size'],
        ];
    }

    /** Una cartella per post: eliminandolo si porta via tutto in un colpo. */
    public static function cartellaPost(int $postId): string
    {
        return self::cartella() . '/post/' . $postId;
    }

    /** Cancella una immagine di un post. */
    public static function eliminaMedia(?string $percorsoRelativo): void
    {
        if ($percorsoRelativo === null || !str_starts_with($percorsoRelativo, 'uploads/post/')) {
            return;
        }

        // realpath impedisce che un valore manipolato esca dalla cartella
        $reale = realpath(BASE_PATH . '/public/' . $percorsoRelativo);
        $radice = realpath(self::cartella());

        if ($reale !== false && $radice !== false && str_starts_with($reale, $radice) && is_file($reale)) {
            @unlink($reale);
        }
    }

    /** Svuota e rimuove la cartella di un post eliminato. */
    public static function eliminaCartellaPost(int $postId): void
    {
        $cartella = self::cartellaPost($postId);
        if (!is_dir($cartella)) {
            return;
        }

        foreach (glob($cartella . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($cartella);
    }

    /** Cancella un logo sostituito o rimosso. Silenzioso se il file non c'e piu. */
    public static function elimina(?string $percorsoRelativo): void
    {
        if ($percorsoRelativo === null || !str_starts_with($percorsoRelativo, 'uploads/')) {
            return;
        }

        // basename impedisce che un valore manipolato esca dalla cartella
        $file = self::cartella() . '/' . basename($percorsoRelativo);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function svgPericoloso(string $contenuto): bool
    {
        $minuscolo = strtolower($contenuto);

        foreach (['<script', '<foreignobject', 'javascript:', '<!entity', '<iframe', '<embed'] as $sospetto) {
            if (str_contains($minuscolo, $sospetto)) {
                return true;
            }
        }

        // Handler di evento: onload=, onclick=, ...
        return (bool) preg_match('/\son[a-z]+\s*=/i', $contenuto);
    }
}
