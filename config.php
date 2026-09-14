<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$mongoUri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
$mongoDatabaseName = getenv('MONGODB_DATABASE') ?: 'mi_proyecto_db';
$mongoCollectionName = getenv('MONGODB_USERS_COLLECTION') ?: 'usuarios';

try {
    $cliente = new MongoDB\Client($mongoUri);
    $baseDeDatos = $cliente->selectDatabase($mongoDatabaseName);
    $coleccionUsuarios = $baseDeDatos->selectCollection($mongoCollectionName);
} catch (Throwable $exception) {
    error_log('No se pudo inicializar la conexión con MongoDB: ' . $exception->getMessage());
    throw new RuntimeException('No se pudo inicializar la conexión con la base de datos.', 0, $exception);
}