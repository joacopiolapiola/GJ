<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adjuntosDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'GJmedicos_adjuntos';
$error = null;
$success = null;
$modoFormulario = ($_GET['formato'] ?? 'normal') === 'json' ? 'json' : 'normal';
$persona = '';
$tipo = '';
$fechaDocumento = '';
$observaciones = '';

function textoSeguro(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function datosComoJson(mixed $datos): string
{
    return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

if (isset($_GET['descargar'])) {
    $id = is_string($_GET['descargar']) ? $_GET['descargar'] : '';

    try {
        $certificado = new MongoDB\BSON\ObjectId($id);
        $registro = $coleccionCertificados->findOne(['_id' => $certificado]);
    } catch (Throwable $exception) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }

    $archivo = $registro?->archivo;
    $ruta = is_object($archivo) && isset($archivo->ruta) ? (string) $archivo->ruta : '';
    $rutaCompleta = $adjuntosDirectory . DIRECTORY_SEPARATOR . basename($ruta);

    if ($registro === null || $ruta === '' || !is_file($rutaCompleta)) {
        http_response_code(404);
        exit('Documento adjunto no encontrado.');
    }

    $nombre = is_object($archivo) && isset($archivo->nombre) ? (string) $archivo->nombre : basename($ruta);
    $mime = is_object($archivo) && isset($archivo->mime) ? (string) $archivo->mime : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($rutaCompleta));
    header('Content-Disposition: inline; filename="' . addcslashes(str_replace(["\r", "\n", '"'], '', $nombre), '"\\') . '"');
    readfile($rutaCompleta);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $modoFormulario = ($_POST['formato'] ?? 'normal') === 'json' ? 'json' : 'normal';
    $jsonTexto = is_string($_POST['datos_json'] ?? null) ? trim($_POST['datos_json']) : '';
    $persona = trim(is_string($_POST['persona'] ?? null) ? $_POST['persona'] : '');
    $tipo = trim(is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '');
    $fechaDocumento = trim(is_string($_POST['fecha_documento'] ?? null) ? $_POST['fecha_documento'] : '');
    $observaciones = trim(is_string($_POST['observaciones'] ?? null) ? $_POST['observaciones'] : '');
    $archivoSubido = $_FILES['archivo'] ?? null;

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'No se pudo validar la solicitud. Inténtalo de nuevo.';
    } elseif ($modoFormulario === 'normal' && $persona === '') {
        $error = 'El campo persona es obligatorio.';
    } elseif ($modoFormulario === 'json' && $jsonTexto === '') {
        $error = 'Introduce la entrada en formato JSON.';
    } else {
        try {
            if ($modoFormulario === 'json') {
                $datos = json_decode($jsonTexto, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $datos = ['persona' => $persona];
                if ($tipo !== '') {
                    $datos['tipo'] = $tipo;
                }
                if ($fechaDocumento !== '') {
                    $datos['fecha_documento'] = $fechaDocumento;
                }
                if ($observaciones !== '') {
                    $datos['observaciones'] = $observaciones;
                }
            }
            if (!is_array($datos)) {
                throw new JsonException('La entrada JSON debe ser un objeto.');
            }

            $archivoMetadata = null;
            if (is_array($archivoSubido) && ($archivoSubido['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($archivoSubido['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('No se pudo cargar el archivo adjunto.');
                }
                if (!is_uploaded_file($archivoSubido['tmp_name'] ?? '')) {
                    throw new RuntimeException('El archivo adjunto no es válido.');
                }
                if (!is_dir($adjuntosDirectory) && !mkdir($adjuntosDirectory, 0750, true) && !is_dir($adjuntosDirectory)) {
                    throw new RuntimeException('No se pudo preparar el almacenamiento de adjuntos.');
                }

                $nombreOriginal = basename((string) $archivoSubido['name']);
                $nombreInterno = bin2hex(random_bytes(16));
                $rutaInterna = $nombreInterno . '-' . $nombreOriginal;
                $rutaCompleta = $adjuntosDirectory . DIRECTORY_SEPARATOR . $rutaInterna;
                if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaCompleta)) {
                    throw new RuntimeException('No se pudo guardar el archivo adjunto.');
                }
                $archivoMetadata = [
                    'nombre' => $nombreOriginal,
                    'ruta' => $rutaInterna,
                    'mime' => (string) (mime_content_type($rutaCompleta) ?: 'application/octet-stream'),
                    'tamano' => (int) filesize($rutaCompleta),
                ];
            }

            $coleccionCertificados->insertOne([
                'datos' => $datos,
                'fecha' => new MongoDB\BSON\UTCDateTime(),
                'archivo' => $archivoMetadata,
                'creado_por' => $_SESSION['usuario_id'],
            ]);
            $success = 'Certificado guardado correctamente.';
        } catch (JsonException) {
            $error = 'La entrada no contiene un JSON válido.';
        } catch (Throwable $exception) {
            error_log('Error al guardar certificado: ' . $exception->getMessage());
            $error = 'No se pudo guardar el certificado.';
        }
    }
}

$busqueda = trim(is_string($_GET['persona'] ?? null) ? $_GET['persona'] : '');
$orden = ($_GET['orden'] ?? 'desc') === 'asc' ? 1 : -1;
$certificados = [];

try {
    $filtro = [];
    if ($busqueda !== '') {
        $filtro['datos.persona'] = ['$regex' => preg_quote($busqueda, '/'), '$options' => 'i'];
    }
    $cursor = $coleccionCertificados->find($filtro, [
        'sort' => ['fecha' => $orden],
    ]);
    foreach ($cursor as $certificado) {
        $certificados[] = $certificado;
    }
} catch (Throwable $exception) {
    error_log('Error al consultar certificados: ' . $exception->getMessage());
    $error = 'No se pudieron cargar los certificados.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de certificados</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Panel de certificados</h1>

<?php if ($error !== null): ?><p class="mensaje error" role="alert"><?= textoSeguro($error) ?></p><?php endif; ?>
<?php if ($success !== null): ?><p class="mensaje exito" role="status"><?= textoSeguro($success) ?></p><?php endif; ?>

<section aria-labelledby="nuevo-certificado">
    <h2 id="nuevo-certificado"><?= $modoFormulario === 'json' ? 'Guardar certificado desde JSON' : 'Guardar certificado' ?></h2>
    <?php if ($modoFormulario === 'normal'): ?>
    <p>Completa los datos del certificado. Se guardarán automáticamente como JSON.</p>
    <?php else: ?>
    <p>Introduce directamente una entrada JSON válida.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= textoSeguro($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="formato" value="<?= textoSeguro($modoFormulario) ?>">
    <?php if ($modoFormulario === 'normal'): ?>
        <label for="persona">Persona <strong>(obligatorio)</strong></label>
        <input type="text" id="persona" name="persona" required value="<?= textoSeguro($persona) ?>">
        <label for="tipo">Tipo de certificado</label>
        <input type="text" id="tipo" name="tipo" value="<?= textoSeguro($tipo) ?>">
        <label for="fecha_documento">Fecha del documento</label>
        <input type="date" id="fecha_documento" name="fecha_documento" value="<?= textoSeguro($fechaDocumento) ?>">
        <label for="observaciones">Observaciones</label>
        <textarea id="observaciones" name="observaciones"><?= textoSeguro($observaciones) ?></textarea>
    <?php else: ?>
        <label for="datos_json">Entrada JSON (incluye el campo <code>persona</code>)</label>
        <textarea id="datos_json" name="datos_json" required placeholder='{"persona":"Nombre y apellido","tipo":"Certificado"}'><?= textoSeguro($jsonTexto ?? '') ?></textarea>
    <?php endif; ?>
    <label for="archivo">Documento adjunto (opcional, cualquier formato)</label>
    <input type="file" id="archivo" name="archivo">
    <button type="submit">Guardar certificado</button>
    </form>
    <?php if ($modoFormulario === 'normal'): ?>
    <form method="get">
        <input type="hidden" name="formato" value="json">
        <button type="submit">Usar formulario de entrada JSON</button>
    </form>
    <?php else: ?>
    <form method="get">
        <button type="submit">Volver al formulario normal</button>
    </form>
    <?php endif; ?>
</section>

<section aria-labelledby="lista-certificados">
    <h2 id="lista-certificados">Certificados</h2>
    <form method="get" class="filtros">
        <label for="persona_busqueda">Buscar por persona
            <input type="search" id="persona_busqueda" name="persona" value="<?= textoSeguro($busqueda) ?>">
        </label>
        <label for="orden">Ordenar por fecha
            <select id="orden" name="orden">
                <option value="desc"<?= $orden === -1 ? ' selected' : '' ?>>Más recientes primero</option>
                <option value="asc"<?= $orden === 1 ? ' selected' : '' ?>>Más antiguos primero</option>
            </select>
        </label>
        <button type="submit">Aplicar filtros</button>
        <a href="main.php">Limpiar</a>
    </form>

    <?php if ($certificados === []): ?>
        <p>No hay certificados que coincidan con la búsqueda.</p>
    <?php endif; ?>
    <?php foreach ($certificados as $certificado): ?>
        <?php
        $datos = $certificado->datos ?? [];
        $fecha = $certificado->fecha instanceof MongoDB\BSON\UTCDateTime
            ? $certificado->fecha->toDateTime()->format('d/m/Y H:i')
            : 'Sin fecha';
        $archivo = $certificado->archivo ?? null;
        $tieneArchivo = is_object($archivo) && isset($archivo->ruta) && (string) $archivo->ruta !== '';
        ?>
        <article>
            <h3><?= textoSeguro(is_array($datos) ? ($datos['persona'] ?? 'Sin persona') : 'Certificado') ?></h3>
            <p><strong>Fecha:</strong> <?= textoSeguro($fecha) ?></p>
            <pre><?= textoSeguro(datosComoJson($datos)) ?></pre>
            <?php if ($tieneArchivo): ?>
                <a href="main.php?descargar=<?= textoSeguro((string) $certificado->_id) ?>" target="_blank" rel="noopener">Ver documento adjunto</a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
</body>
</html>