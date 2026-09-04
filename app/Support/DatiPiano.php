<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Impostazione;
use App\Models\Piano;
use App\Models\Post;

/**
 * Prepara i dati della vista del piano nella forma che vede il cliente.
 * La usano la pagina pubblica, l'anteprima admin e l'export: cosi il
 * documento e identico in tutti e tre i casi.
 */
final class DatiPiano
{
    /**
     * @param  array<string,mixed> $piano
     * @return array<string,mixed>
     */
    public static function perVista(
        array $piano,
        string $token = '',
        bool $anteprima = false,
        bool $stampa = false
    ): array {
        $post = Post::perPiano((int) $piano['id']);

        $nota = trim((string) ($piano['nota_cliente'] ?? ''));
        if ($nota === '') {
            $nota = Impostazione::get('nota_standard');
        }

        // La fase decide che cosa mostra la pagina e su quali colonne
        // agiscono i pulsanti: le idee oppure il post finito.
        $fase = Fasi::normalizza($piano['fase'] ?? 'concept');

        return [
            'titolo'     => $piano['titolo'] . ' · ' . $piano['cliente_nome'],
            'piano'      => $piano,
            'token'      => $token,
            'anteprima'  => $anteprima,
            'stampa'     => $stampa,
            'fase'       => $fase,
            'colonne'    => Fasi::colonne($fase),
            'conteggi'   => Piano::conteggiFase($piano),
            'nota'       => $nota,
            'firma'      => Impostazione::get('firma', Impostazione::get('nome_studio')),
            'nomeStudio' => Impostazione::get('nome_studio', 'Piano'),
            'settimane'  => Periodo::settimane(
                (string) $piano['data_inizio'],
                (string) $piano['data_fine'],
                $post
            ),
        ];
    }

    /** Nome file per gli export: "pasticceria-verdi-ottobre-2026". */
    public static function nomeFile(array $piano): string
    {
        return slugify((string) $piano['cliente_nome'] . '-' . (string) $piano['titolo']);
    }
}
