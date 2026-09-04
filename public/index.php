<?php
/**
 * Front controller. Unico punto di ingresso dell'applicazione:
 * il .htaccess manda qui tutto cio che non e un file reale.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ClientiController;
use App\Controllers\DashboardController;
use App\Controllers\ExportController;
use App\Controllers\ImpostazioniController;
use App\Controllers\MediaController;
use App\Controllers\PianiController;
use App\Controllers\PostController;
use App\Controllers\PubblicoController;
use App\Controllers\UtentiController;
use App\Controllers\VerificaController;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

Session::start();

$router = new Router();

/* --------------------------------------------------------------- accesso -- */
$router->get('/login', [AuthController::class, 'mostraLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

/* ------------------------------------------------------------------ admin -- */
$router->get('/', [DashboardController::class, 'index']);

/* --------------------------------------------------------------- clienti -- */
$router->get('/clienti', [ClientiController::class, 'index']);
$router->get('/clienti/nuovo', [ClientiController::class, 'nuovo']);
$router->post('/clienti', [ClientiController::class, 'crea']);
$router->get('/clienti/{id}/modifica', [ClientiController::class, 'modifica']);
$router->post('/clienti/{id}', [ClientiController::class, 'aggiorna']);
$router->post('/clienti/{id}/elimina', [ClientiController::class, 'elimina']);
$router->get('/clienti/{id}/piani', [PianiController::class, 'perCliente']);

/* ----------------------------------------------------------------- piani -- */
// "/piani/nuovo" va dichiarato prima di "/piani/{id}", altrimenti
// finirebbe catturato dal segnaposto.
$router->get('/piani', [PianiController::class, 'index']);
$router->get('/piani/nuovo', [PianiController::class, 'nuovo']);
$router->post('/piani', [PianiController::class, 'crea']);
$router->get('/piani/{id}', [PianiController::class, 'mostra']);
$router->get('/piani/{id}/calendario', [PianiController::class, 'calendario']);
$router->get('/piani/{id}/esecutivi', [PianiController::class, 'esecutivi']);
$router->post('/piani/{id}', [PianiController::class, 'aggiorna']);
$router->post('/piani/{id}/stato', [PianiController::class, 'cambiaStato']);
$router->post('/piani/{id}/fase', [PianiController::class, 'cambiaFase']);
$router->post('/piani/{id}/token', [PianiController::class, 'rigeneraToken']);
$router->post('/piani/{id}/duplica', [PianiController::class, 'duplica']);
$router->post('/piani/{id}/elimina', [PianiController::class, 'elimina']);

/* ------------------------------------------------- post (endpoint JSON) -- */
$router->post('/piani/{id}/post', [PostController::class, 'crea']);
$router->post('/post/{id}', [PostController::class, 'aggiorna']);
$router->post('/post/{id}/elimina', [PostController::class, 'elimina']);
$router->post('/post/{id}/duplica', [PostController::class, 'duplica']);
$router->post('/post/{id}/sposta', [PostController::class, 'sposta']);

/* --------------------------------- immagini dei post (multipart + JSON) -- */
$router->post('/post/{id}/media', [MediaController::class, 'carica']);
$router->post('/media/{id}/elimina', [MediaController::class, 'elimina']);
$router->post('/media/{id}/sposta', [MediaController::class, 'sposta']);

/* ---------------------------------------- anteprima della vista cliente -- */
$router->get('/piani/{id}/anteprima', [PubblicoController::class, 'anteprima']);

/* ------------------------------------------ area pubblica (senza login) -- */
$router->get('/p/{token}', [PubblicoController::class, 'mostra']);
$router->post('/p/{token}/approva-tutti', [PubblicoController::class, 'approvaTutti']);
$router->post('/p/{token}/post/{id}/approva', [PubblicoController::class, 'approva']);
$router->post('/p/{token}/post/{id}/modifica', [PubblicoController::class, 'chiediModifica']);

/* ---------------------------------------------------------------- export -- */
$router->get('/piani/{id}/pdf', [ExportController::class, 'pdf']);
$router->get('/piani/{id}/ics', [ExportController::class, 'ics']);

/* --------------------------------------- collaboratori (solo admin) -- */
$router->get('/utenti', [UtentiController::class, 'index']);
$router->post('/utenti', [UtentiController::class, 'crea']);
$router->get('/utenti/{id}', [UtentiController::class, 'modifica']);
$router->post('/utenti/{id}', [UtentiController::class, 'aggiorna']);
$router->post('/utenti/{id}/elimina', [UtentiController::class, 'elimina']);

/* ------------------------------------- verifica installazione (admin) -- */
$router->get('/verifica', [VerificaController::class, 'index']);

/* ---------------------------------------------------------- impostazioni -- */
$router->get('/impostazioni', [ImpostazioniController::class, 'index']);
$router->post('/impostazioni', [ImpostazioniController::class, 'salva']);
$router->post('/impostazioni/password', [ImpostazioniController::class, 'cambiaPassword']);

$router->dispatch(new Request());
