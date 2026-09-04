<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cliente;
use App\Models\Piano;
use App\Models\Post;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->richiediLogin();

        // Una query per cliente per trovare il piano del periodo: con una
        // manciata di clienti non vale la pena complicare la query unica.
        $righe = [];
        foreach (Cliente::elenco(true) as $cliente) {
            $righe[] = [
                'cliente' => $cliente,
                'piano'   => Piano::corrente((int) $cliente['id'], date('Y-m-d')),
            ];
        }

        $this->vista('dashboard/index', [
            'titolo'   => 'Dashboard',
            'righe'    => $righe,
            'prossimi' => Post::prossimi(7),
            'novita'   => Post::novita(10),
            'oggi'     => date('Y-m-d'),
        ]);
    }
}
