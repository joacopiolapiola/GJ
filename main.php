<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de certificados</title>
</head>
<body>
<h1>Panel de certificados</h1>
</body>
</html>