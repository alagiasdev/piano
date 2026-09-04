<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router minimale. I segnaposto nella rotta hanno la forma {nome} e
 * catturano un segmento di percorso, passato al controller come argomento.
 *
 *   $router->get('/piani/{id}', [PianiController::class, 'mostra']);
 */
final class Router
{
    /** @var array<int,array{metodo:string,regex:string,parametri:array<int,string>,azione:array}> */
    private array $rotte = [];

    public function get(string $percorso, array $azione): void
    {
        $this->aggiungi('GET', $percorso, $azione);
    }

    public function post(string $percorso, array $azione): void
    {
        $this->aggiungi('POST', $percorso, $azione);
    }

    private function aggiungi(string $metodo, string $percorso, array $azione): void
    {
        $parametri = [];
        $regex = preg_replace_callback(
            '#\{([a-z_]+)\}#i',
            static function (array $m) use (&$parametri): string {
                $parametri[] = $m[1];

                return '([^/]+)';
            },
            $percorso
        );

        $this->rotte[] = [
            'metodo'    => $metodo,
            'regex'     => '#^' . $regex . '$#',
            'parametri' => $parametri,
            'azione'    => $azione,
        ];
    }

    public function dispatch(Request $request): void
    {
        $percorsoEsiste = false;

        foreach ($this->rotte as $rotta) {
            if (!preg_match($rotta['regex'], $request->path, $match)) {
                continue;
            }
            if ($rotta['metodo'] !== $request->method) {
                $percorsoEsiste = true; // stessa URL ma verbo diverso
                continue;
            }

            array_shift($match);
            [$classe, $metodo] = $rotta['azione'];
            (new $classe($request))->{$metodo}(...$match);

            return;
        }

        $this->errore($request, $percorsoEsiste ? 405 : 404);
    }

    private function errore(Request $request, int $codice): never
    {
        if ($request->wantsJson()) {
            Response::jsonError($codice === 405 ? 'Metodo non consentito' : 'Risorsa non trovata', $codice);
        }

        http_response_code($codice);
        $titolo = $codice === 405 ? 'Metodo non consentito' : 'Pagina non trovata';
        View::rendiErrore($codice, $titolo);
        exit;
    }
}
