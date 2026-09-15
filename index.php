<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);

session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: main.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $identificador = trim(is_string($_POST['identificador'] ?? null) ? $_POST['identificador'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'No se pudo validar la solicitud. Inténtalo de nuevo.';
    } elseif ($identificador === '' || $password === '') {
        $error = 'Introduce tu correo y contraseña.';
    } else {
        try {
            $usuario = $coleccionUsuarios->findOne([
                'email' => $identificador,
                'activo' => ['$ne' => false],
            ]);

            $passwordHash = $usuario?->password;
            $autenticado = is_string($passwordHash) && password_verify($password, $passwordHash);

            if (!$autenticado) {
                $error = 'Las credenciales no son válidas.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = (string) $usuario->_id;
                $_SESSION['usuario_email'] = (string) $usuario->email;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: main.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Error durante el inicio de sesión: ' . $exception->getMessage());
            $error = 'No se pudo iniciar sesión en este momento.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main>
        <h1>Iniciar sesión</h1>

        <?php if ($error !== null): ?>
            <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="identificador">Correo electrónico</label>
            <input type="email" id="identificador" name="identificador" required autocomplete="username" value="<?= htmlspecialchars($identificador ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>