<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/db.php';

$user = $argv[1] ?? 'admin';
$pass = $argv[2] ?? '';
if ($pass === '') {
  fwrite(STDERR, "Usage: php tools/reset_admin_password.php admin NewPassword\n");
  exit(1);
}

$pdo = db();
$hash = password_hash($pass, PASSWORD_DEFAULT);

$st = $pdo->prepare("UPDATE admins SET password_hash = :h WHERE username = :u");
$st->execute([':h' => $hash, ':u' => $user]);

echo "OK: password updated for user={$user}\n";
