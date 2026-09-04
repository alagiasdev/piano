<?php
/**
 * Avvio dell'applicazione: autoload, .env, timezone, gestione errori.
 * Incluso una sola volta da public/index.php e dagli script CLI in database/.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoload: usiamo Composer se il vendor/ e presente, altrimenti un PSR-4
// minimale scritto a mano. Cosi l'app gira anche dove Composer non c'e.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, 4));
        $file = BASE_PATH . '/app/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require BASE_PATH . '/app/Support/helpers.php';
}

App\Core\Config::load(BASE_PATH . '/.env');

date_default_timezone_set(App\Core\Config::get('APP_TIMEZONE', 'Europe/Rome'));
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'it_IT.UTF-8', 'it_IT', 'Italian_Italy');

// In produzione gli errori si scrivono nel log, non a schermo.
$debug = App\Core\Config::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
