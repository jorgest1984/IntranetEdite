<?php
// subir_documento.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die("Error: Token CSRF no válido o expirado.");
    }
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
            $accion_id = isset($_POST['accion_id']) && $_POST['accion_id'] !== '' ? (int)$_POST['accion_id'] : null;
            if ($accion_id <= 0) {
                $accion_id = null;
            }

            // 1. Buscar si ya existe un documento del mismo tipo y clasificación para este alumno
            if ($accion_id) {
                $stmtCheckDup = $pdo->prepare("SELECT id, ruta_archivo FROM documentos_alumno WHERE alumno_id = ? AND tipo_documento = ? AND accion_id = ?");
                $stmtCheckDup->execute([$alumno_id, $tipo_doc, $accion_id]);
            } else {
                $stmtCheckDup = $pdo->prepare("SELECT id, ruta_archivo FROM documentos_alumno WHERE alumno_id = ? AND tipo_documento = ? AND accion_id IS NULL");
                $stmtCheckDup->execute([$alumno_id, $tipo_doc]);
            }
            $dup = $stmtCheckDup->fetch(PDO::FETCH_ASSOC);

            if ($dup) {
                // Borrar el archivo anterior del disco
                if (!empty($dup['ruta_archivo']) && file_exists(__DIR__ . '/' . $dup['ruta_archivo'])) {
                    @unlink(__DIR__ . '/' . $dup['ruta_archivo']);
                }
                
                // Actualizar el registro existente
                $stmtUpdateDoc = $pdo->prepare("UPDATE documentos_alumno SET usuario_id = ?, nombre_archivo = ?, ruta_archivo = ?, fecha_subida = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpdateDoc->execute([$usuario_id, basename($file['name']), $target_path, $dup['id']]);
                
                $docId = $dup['id'];
                $auditAction = 'SUSTITUCION_DOC';
            } else {
                // Insertar nuevo registro
                $stmt = $pdo->prepare("INSERT INTO documentos_alumno (alumno_id, usuario_id, nombre_archivo, ruta_archivo, tipo_documento, accion_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$alumno_id, $usuario_id, basename($file['name']), $target_path, $tipo_doc, $accion_id]);
                
                $docId = $pdo->lastInsertId();
                $auditAction = 'SUBIDA_DOC';
            }
            
            // Actualizar de forma inteligente los campos de "documentos entregados" en la tabla matriculas
            if ($tipo_doc === 'DNI') {
                $stmtUpdate = $pdo->prepare("UPDATE matriculas SET dni_entregado = 1 WHERE alumno_id = ?");
                $stmtUpdate->execute([$alumno_id]);
            } elseif (in_array($tipo_doc, ['Cabecera_Nomina', 'Recibo_Autonomo', 'Vida_Laboral', 'Contrato'])) {
                $stmtUpdate = $pdo->prepare("UPDATE matriculas SET nomina_entregada = 1 WHERE alumno_id = ?");
                $stmtUpdate->execute([$alumno_id]);
            } elseif ($tipo_doc === 'Anexo1') {
                if ($accion_id) {
                    $stmtUpdate = $pdo->prepare("UPDATE matriculas SET anexo1_entregado = 'SI' WHERE alumno_id = ? AND grupo_id IN (SELECT id FROM grupos WHERE accion_id = ?)");
                    $stmtUpdate->execute([$alumno_id, $accion_id]);
                } else {
                    $stmtUpdate = $pdo->prepare("UPDATE matriculas SET anexo1_entregado = 'SI' WHERE alumno_id = ?");
                    $stmtUpdate->execute([$alumno_id]);
                }
            }

            // Registrar en audit log
            audit_log($pdo, $auditAction, 'documentos_alumno', $docId, null, ['archivo' => basename($file['name'])]);
            
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
