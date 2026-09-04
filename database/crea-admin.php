<?php
/**
 * crea (o aggiorna) un amministratore del gestionale.
 *
 *   php database/crea-admin.php email@dominio.it "password" "Nome Cognome"
 *
 * Se l'email esiste gia, ne aggiorna password e nome.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Db;

if (PHP_SAPI !== 'cli') {
    exit('Da eseguire da riga di comando.');
}

$email    = $argv[1] ?? '';
$password = $argv[2] ?? '';
$nome     = $argv[3] ?? 'Amministratore';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Uso: php database/crea-admin.php email@dominio.it \"password\" \"Nome Cognome\"\n"
        . "La password deve avere almeno 8 caratteri.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

Db::run(
    'INSERT INTO utenti (nome, email, password_hash, attivo, amministratore)
     VALUES (?, ?, ?, 1, 1)
     ON DUPLICATE KEY UPDATE nome = VALUES(nome), password_hash = VALUES(password_hash),
                             attivo = 1, amministratore = 1',
    [$nome, $email, $hash]
);

echo "Amministratore pronto: {$email}\n";
echo "Gli altri collaboratori si aggiungono dalla schermata «Collaboratori».\n";
