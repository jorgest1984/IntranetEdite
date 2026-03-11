<?php
// subir_documento.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo'])) {
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $tipo_doc = $_POST['tipo_documento'] ?? 'General';
    $usuario_id = $_SESSION['user_id'] ?? null;
    
    if ($alumno_id <= 0) {
        die("Error: ID de alumno no válido.");
    }

    if (!$usuario_id) {
        die("Error: Sesión de usuario no válida. Por favor, vuelva a iniciar sesión.");
    }

    // Verificar que el alumno existe para evitar fallos de clave foránea
    $stmtCheck = $pdo->prepare("SELECT id FROM alumnos WHERE id = ?");
    $stmtCheck->execute([$alumno_id]);
    if (!$stmtCheck->fetch()) {
        die("Error: El alumno con ID $alumno_id no existe en la base de datos.");
    }
    
    $file = $_FILES['archivo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Error en la subida del archivo: Code " . $file['error']);
    }

    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $upload_dir = 'uploads/alumnos/' . $alumno_id . '/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO documentos_alumno (alumno_id, usuario_id, nombre_archivo, ruta_archivo, tipo_documento) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$alumno_id, $usuario_id, basename($file['name']), $target_path, $tipo_doc]);
            
            // Registrar en audit log
            audit_log($pdo, 'SUBIDA_DOC', 'documentos_alumno', $pdo->lastInsertId(), null, ['archivo' => basename($file['name'])]);
            
            header("Location: ficha_alumno.php?id=$alumno_id&tab=documentacion&upload_success=1");
            exit();
        } catch (Exception $e) {
            die("Error DB (Integridad): " . $e->getMessage());
        }
    } else {
        die("Error al mover el archivo al servidor.");
    }
}
?>
