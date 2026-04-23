<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../ficha_trabajador.php");
    exit();
}

$usuario_id = $_POST['usuario_id'] ?? null;
$tipo_doc = $_POST['tipo_doc'] ?? 'OTRO';

if (!$usuario_id || !isset($_FILES['documento'])) {
    die("Datos incompletos.");
}

$file = $_FILES['documento'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Error al subir el archivo.");
}

// Obtener NIF para el nombre (opcional si ya viene nombrado, pero el sistema lo fuerza)
$stmt = $pdo->prepare("SELECT dni FROM profesorado_detalles WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$prof = $stmt->fetch();
$nif = $prof['dni'] ?? 'SINFUN';

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$nuevo_nombre = $nif . "-" . $tipo_doc . "." . $ext;
$ruta_destino = "docs/profesorado/" . $nuevo_nombre;

if (move_uploaded_file($file['tmp_name'], __DIR__ . "/../" . $ruta_destino)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO profesorado_documentos (usuario_id, nombre_archivo, ruta_archivo, tipo_documento) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $nuevo_nombre, $ruta_destino, $tipo_doc]);
        
        header("Location: ../ficha_trabajador.php?id=$usuario_id&tab=profesorado&success=upload");
    } catch (Exception $e) {
        die("Error en base de datos: " . $e->getMessage());
    }
} else {
    die("Error al mover el archivo al servidor.");
}
