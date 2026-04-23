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

// Leer el contenido del archivo y convertirlo a Base64
$contenido = base64_encode(file_get_contents($file['tmp_name']));
$mime_type = $file['type'];

try {
    // Usamos parámetros nombrados para mayor seguridad con el puente
    $sql = "INSERT INTO profesorado_documentos 
            (usuario_id, nombre_archivo, archivo_contenido, mime_type, ruta_archivo, tipo_documento) 
            VALUES (:uid, :nom, :cont, :mime, :ruta, :tipo)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'  => $usuario_id,
        ':nom'  => $nuevo_nombre,
        ':cont' => $contenido,
        ':mime' => $mime_type,
        ':ruta' => $ruta_relativa,
        ':tipo' => $tipo_doc
    ]);
    
    ob_end_clean();
    header("Location: ../ficha_trabajador.php?id=$usuario_id&tab=profesorado&success=upload");
    exit();
} catch (Exception $e) {
    ob_end_clean();
    die("Error en base de datos al registrar: " . $e->getMessage());
}
?>
