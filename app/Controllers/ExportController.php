<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\View;
use App\Models\Piano;
use App\Models\Post;
use App\Support\DatiPiano;
use App\Support\Ics;

final class ExportController extends Controller
{
    /**
     * PDF del piano.
     *
     * Con dompsdf installato produce un vero PDF; senza, restituisce la
     * pagina print-friendly che apre da sola la finestra di stampa, dove
     * "Salva come PDF" da lo stesso risultato. Il layout e identico perche
     * in entrambi i casi si usa la vista del cliente con gli stili @media print.
     */
    public function pdf(string $id): void
    {
        $this->richiediLogin();

        $piano = Piano::trova((int) $id) ?? $this->nonTrovato('Piano non trovato.');
        $dati  = DatiPiano::perVista($piano, '', true, true);

        $html = View::cattura('pubblico/piano', $dati, 'layouts/stampa');

        if (class_exists('\Dompdf\Dompdf')) {
            $this->pdfConDompdf($html, DatiPiano::nomeFile($piano));
        }

        echo $html;
    }

    /** Un evento per post, da importare in Google Calendar. */
    public function ics(string $id): void
    {
        $this->richiediLogin();

        $piano = Piano::trova((int) $id) ?? $this->nonTrovato('Piano non trovato.');
        $post  = Post::perPiano((int) $id);

        $dominio = parse_url((string) \App\Core\Config::get('APP_URL', 'piano.local'), PHP_URL_HOST) ?: 'piano.local';

        Response::download(
            Ics::perPiano($piano, $post, $dominio),
            DatiPiano::nomeFile($piano) . '.ics',
            'text/calendar; charset=utf-8'
        );
    }

    /* ------------------------------------------------------------------ */

    private function pdfConDompdf(string $html, string $nomeFile): never
    {
        // Il CSS va incorporato: dompdf non ha accesso alla rete.
        $css = (string) file_get_contents(BASE_PATH . '/public/assets/app.css');
        $html = str_replace(
            '</head>',
            '<style>' . $css . '</style></head>',
            preg_replace('#<link[^>]+app\.css[^>]*>#', '', $html) ?? $html
        );

        $classe = '\Dompdf\Dompdf';
        $dompdf = new $classe(['isRemoteEnabled' => false, 'defaultPaperSize' => 'a4']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        Response::download($dompdf->output(), $nomeFile . '.pdf', 'application/pdf');
    }
}
