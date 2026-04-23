<?php
// api/ver_documento_profesor.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

$doc_id = $_GET['id'] ?? null;

if (!$doc_id) {
    die("ID de documento no especificado.");
}

try {
    $stmt = $pdo->prepare("SELECT nombre_archivo, archivo_contenido, mime_type FROM profesorado_documentos WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();

    if (!$doc || !$doc['archivo_contenido']) {
        die("Documento no encontrado o sin contenido.");
    }

    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_end_clean();

    // Servir el archivo con el MIME type correcto
    header("Content-Type: " . ($doc['mime_type'] ?: 'application/octet-stream'));
    header("Content-Disposition: inline; filename=\"" . $doc['nombre_archivo'] . "\"");
    header("Content-Length: " . strlen($doc['archivo_contenido']));
    
    echo $doc['archivo_contenido'];
    exit();

} catch (Exception $e) {
    die("Error al recuperar el documento: " . $e->getMessage());
}
