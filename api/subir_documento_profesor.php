<?php
// api/subir_documento_profesor.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

// Desactivar cualquier salida previa
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../ficha_trabajador.php");
    exit();
}

$usuario_id = $_POST['usuario_id'] ?? null;
$tipo_doc = $_POST['tipo_doc'] ?? 'OTRO';

if (!$usuario_id || !isset($_FILES['documento'])) {
    ob_end_clean();
    die("Error: Datos del formulario incompletos.");
}

$file = $_FILES['documento'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    die("Error de subida PHP: Código " . $file['error']);
}

// Obtener NIF para el nombre
try {
    $stmt = $pdo->prepare("SELECT dni FROM profesorado_detalles WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $prof = $stmt->fetch();
    $nif = $prof['dni'] ?? 'SINFUN';
} catch (Exception $e) {
    $nif = 'ERR_DB';
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$nuevo_nombre = $nif . "-" . $tipo_doc . "." . $ext;
$base_dir = realpath(__DIR__ . '/..');
$ruta_relativa = "docs/profesorado/" . $nuevo_nombre;
$ruta_absoluta = $base_dir . DIRECTORY_SEPARATOR . "docs" . DIRECTORY_SEPARATOR . "profesorado" . DIRECTORY_SEPARATOR . $nuevo_nombre;

// Crear directorio si no existe (intentar)
if (!is_dir($base_dir . "/docs/profesorado")) {
    @mkdir($base_dir . "/docs/profesorado", 0777, true);
}

if (move_uploaded_file($file['tmp_name'], $ruta_absoluta)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO profesorado_documentos (usuario_id, nombre_archivo, ruta_archivo, tipo_documento) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $nuevo_nombre, $ruta_relativa, $tipo_doc]);
        
        ob_end_clean();
        header("Location: ../ficha_trabajador.php?id=$usuario_id&tab=profesorado&success=upload");
        exit();
    } catch (Exception $e) {
        ob_end_clean();
        die("Error en base de datos al registrar: " . $e->getMessage());
    }
} else {
    ob_end_clean();
    // En Vercel esto fallará porque el FS es de solo lectura.
    // Mostramos un mensaje más técnico para confirmar.
    die("Error: Vercel no permite escritura local. No se pudo mover a: " . $ruta_relativa);
}
?>
