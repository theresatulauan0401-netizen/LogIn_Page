<?php

declare(strict_types=1);

/*
 * Secure first-administrator setup.  Run ONCE from a terminal:
 *
 *     php scripts/create_admin.php
 *
 * - CLI only (refuses to run through the web server).
 * - You type the name, e-mail and password; nothing is hard-coded or stored in SQL.
 * - Password policy: 12+ characters with upper-case, lower-case and a digit.
 * - Stored with password_hash(). Refuses to run if a Super Admin already exists,
 *   unless you pass --allow-additional.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

use App\Core\Validator;

$container = require dirname(__DIR__) . '/app/bootstrap.php';
/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$allowAdditional = in_array('--allow-additional', $argv, true);

$existing = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
if ($existing > 0 && !$allowAdditional) {
    fwrite(STDERR, "A Super Admin already exists. Use the Users page to manage accounts,\n"
        . "or re-run with --allow-additional if you really need another Super Admin.\n");
    exit(1);
}

function ask(string $label, bool $hidden = false): string
{
    echo $label;
    $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;
    if ($hidden && !$isWindows) {
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        shell_exec('stty echo');
        echo PHP_EOL;
    } else {
        if ($hidden) {
            echo '(input is visible on Windows - make sure nobody is watching) ';
        }
        $value = fgets(STDIN);
    }
    return trim((string) $value);
}

echo "=== Create Super Admin ===\n";
$name  = ask('Full name: ');
$email = strtolower(ask('E-mail (used to log in): '));
$pass  = ask('Password (min 12 chars, upper+lower+digit): ', true);
$pass2 = ask('Repeat password: ', true);

$validator = Validator::make(
    ['full_name' => $name, 'email' => $email, 'password' => $pass, 'password_confirmation' => $pass2],
    [
        'full_name' => 'required|max:150',
        'email' => 'required|email|max:190',
        'password' => 'required|password',
        'password_confirmation' => 'required|same:password',
    ]
);
if ($validator->fails()) {
    foreach ($validator->firstErrors() as $message) {
        fwrite(STDERR, " - $message\n");
    }
    exit(1);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
if ((int) $stmt->fetchColumn() > 0) {
    fwrite(STDERR, "That e-mail address is already registered.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        "INSERT INTO users (full_name, email, password, role, status)
         VALUES (:name, :email, :hash, 'super_admin', 'active')"
    );
    $insert->execute([':name' => $name, ':email' => $email, ':hash' => password_hash($pass, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();

    $log = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, module, description, ip_address)
         VALUES (:uid, 'create_super_admin', 'users', :desc, 'cli')"
    );
    $log->execute([':uid' => $userId, ':desc' => 'Super Admin account created via scripts/create_admin.php']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Could not create the account: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nSuper Admin created. You can now log in with the e-mail you just entered.\n";
