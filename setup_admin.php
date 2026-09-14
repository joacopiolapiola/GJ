<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 3 || trim($argv[1]) === '' || $argv[2] === '') {
    fwrite(STDERR, "Uso: php setup_admin.php <email> <password>\n");
    exit(1);
}

$email = trim($argv[1]);
$password = $argv[2];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El email no es válido.\n");
    exit(1);
}

require_once __DIR__ . '/config.php';

try {
    $deleted = $coleccionUsuarios->deleteMany([]);
    $result = $coleccionUsuarios->insertOne([
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'activo' => true,
        'rol' => 'admin',
        'fecha_registro' => new MongoDB\BSON\UTCDateTime(),
    ]);

    printf(
        "Registros eliminados: %d\nAdministrador creado: %s\nID: %s\n",
        $deleted->getDeletedCount(),
        $email,
        (string) $result->getInsertedId()
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo completar la operación: {$exception->getMessage()}\n");
    exit(1);
}
