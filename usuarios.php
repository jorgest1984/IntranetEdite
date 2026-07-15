<?php
// usuarios.php - Gestión de Usuarios con Modal Premium
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Solo administradores pueden gestionar usuarios (ISO 27001 - A.9)
if (!has_permission([ROLE_ADMIN])) {
    header("Location: home.php");
    exit();
}

$success = '';
$error = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Error de seguridad (CSRF). Por favor, refresque la página e inténtelo de nuevo.";
    } else {
        // Crear Usuario
        if ($_POST['action'] == 'create') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $nombre = trim($_POST['nombre']);
            $apellidos = trim($_POST['apellidos']);
            $dni = trim($_POST['dni']);
            $email = trim($_POST['email']);
            $rol_id = intval($_POST['rol_id']);
            
            if (empty($username) || empty($password) || empty($nombre) || empty($email)) {
                $error = "Faltan campos obligatorios.";
            } else {
                // Validar complejidad de contraseña (ISO 27001)
                $complexity = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/';
                if (!preg_match($complexity, $password)) {
                    $error = "La contraseña provisional debe tener al menos 12 caracteres e incluir al menos una letra mayúscula, una letra minúscula, un número y un carácter especial (@, $, !, %, *, ?, &, #).";
                } else {
                    try {
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, nombre, apellidos, dni, email, rol_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $password_hash, $nombre, $apellidos, $dni, $email, $rol_id]);
                        
                        audit_log($pdo, 'USUARIO_CREADO', 'usuarios', $pdo->lastInsertId(), null, ['username' => $username, 'rol' => $rol_id]);
                        $success = "Usuario '$username' creado correctamente.";
                    } catch (PDOException $e) {
                        $error = "Error: El nombre de usuario o email ya existe.";
                    }
                }
            }
        }
    
    // Cambiar Estado (Activo/Inactivo)
    if ($_POST['action'] == 'toggle_status') {
        $id = intval($_POST['user_id']);
        $status = intval($_POST['status']);
        
        // Evitar desactivarse a sí mismo
        if ($id == $_SESSION['user_id']) {
            $error = "No puedes desactivar tu propia cuenta de administrador.";
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            audit_log($pdo, 'USUARIO_STATUS_TOGGLE', 'usuarios', $id, null, ['nuevo_estado' => $status]);
            $success = "Estado de usuario actualizado.";
        }
    }
    
    // Eliminar Usuario
    if ($_POST['action'] == 'delete') {
        $id = intval($_POST['user_id']);
        
        // Evitar borrarse a sí mismo
        if ($id == $_SESSION['user_id']) {
            $error = "No puedes borrar tu propia cuenta de administrador.";
        } else {
            try {
                $pdo->beginTransaction();

                // Obtener datos del usuario antes de borrar
                $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmtUser->execute([$id]);
                $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$user_data) {
                    throw new Exception("El usuario no existe.");
                }

                // Archivar en la papelera
                require_once 'includes/Papelera.php';
                $datos = ['usuarios' => $user_data];
                $titulo_papelera = $user_data['nombre'] . ' ' . $user_data['apellidos'] . ' (' . $user_data['username'] . ')';
                Papelera::archivar($pdo, 'usuarios', $id, $titulo_papelera, $datos);

                // Intentar eliminación física en BD
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                
                audit_log($pdo, 'USUARIO_ELIMINADO', 'usuarios', $id, null, ['id_eliminado' => $id]);
                $success = "Usuario enviado a la papelera correctamente.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Si falla por clave foránea (SQLSTATE 23000) - común por logs de auditoría o documentos
                if (method_exists($e, 'getCode') && $e->getCode() == '23000') {
                    $error = "No se puede eliminar este usuario porque tiene registros de actividad asociados (logs de auditoría, incidencias, etc.) para cumplir con la normativa ISO 27001. En su lugar, puedes suspender su cuenta para deshabilitar su acceso por completo.";
                } elseif (isset($e->errorInfo) && $e->errorInfo[0] == '23000') {
                    $error = "No se puede eliminar este usuario porque tiene registros de actividad asociados (logs de auditoría, incidencias, etc.) para cumplir con la normativa ISO 27001. En su lugar, puedes suspender su cuenta para deshabilitar su acceso por completo.";
                } else {
                    $error = "Error al eliminar el usuario: " . $e->getMessage();
                }
            }
        }
        }
        
        // Alta en Moodle para Tutores
        if ($_POST['action'] == 'sync_moodle') {
            $id = intval($_POST['user_id']);
            $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmtUser->execute([$id]);
            $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user_data && $user_data['rol_id'] == ROLE_TUTOR) {
                require_once 'includes/moodle_api.php';
                try {
                    $moodle = new MoodleAPI($pdo);
                    if ($moodle->isConfigured()) {
                        // 1. Check if user already exists in Moodle by email
                        $existingUser = null;
                        try {
                            $check = $moodle->getUsersByField('email', [$user_data['email']]);
                            if (!empty($check['users'])) {
                                $existingUser = $check['users'][0];
                            }
                        } catch (Exception $ex) {
                            // ignore check error, might just be empty
                        }

                        if ($existingUser) {
                            $error = "El usuario ya existe en Moodle con ese email (Username: " . $existingUser['username'] . ").";
                        } else {
                            // 2. Create user
                            $newUsers = $moodle->createUser(
                                strtolower($user_data['username']),
                                'MoodleTemp123!', // Contraseña genérica temporal
                                $user_data['nombre'],
                                $user_data['apellidos'],
                                $user_data['email']
                            );
                            
                            if (!empty($newUsers) && isset($newUsers[0]['id'])) {
                                audit_log($pdo, 'USUARIO_MOODLE_ALTA', 'usuarios', $id, null, ['moodle_user_id' => $newUsers[0]['id']]);
                                $success = "El tutor ha sido dado de alta correctamente en Moodle (Contraseña temporal: MoodleTemp123!).";
                            } else {
                                $error = "No se ha podido crear el usuario en Moodle (Respuesta inesperada).";
                            }
                        }
                    } else {
                        $error = "Moodle no está configurado correctamente en el sistema.";
                    }
                } catch (Exception $e) {
                    $error = "Error al comunicar con Moodle: " . $e->getMessage();
                }
            } else {
                $error = "Usuario no válido o no tiene rol de Tutor.";
            }
        }

    }
}

// Listado de usuarios
$stmt = $pdo->query("SELECT u.*, r.nombre as rol_nombre 
                     FROM usuarios u 
                     JOIN roles r ON u.rol_id = r.id 
                     ORDER BY u.activo DESC, u.username ASC");
$usuarios = $stmt->fetchAll();

// Listado de roles para el combo (excluyendo Solo Lectura)
$roles = $pdo->query("SELECT * FROM roles WHERE id != " . ROLE_LECTURA . " ORDER BY id ASC")->fetchAll();

// Moodle Sync Data
$syncedUserIds = [];
try {
    $stmtLog = $pdo->query("SELECT DISTINCT entidad_id FROM audit_log WHERE accion = 'USUARIO_MOODLE_ALTA'");
    while ($row = $stmtLog->fetch()) {
        if (!empty($row['entidad_id'])) {
            $syncedUserIds[] = $row['entidad_id'];
        }
    }
} catch (Exception $e) {
    // Silently ignore if audit_log table query fails
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            /* Palette tailored to Intranet EFP */
            --primary-rose: #e11d48;
            --primary-rose-hover: #be123c;
            --admin-gradient: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
            --adm-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --tutor-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
            --com-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --lec-gradient: linear-gradient(135deg, #6b7280 0%, #374151 100%);
            
            --title-blue: #1e3a8a;
            --label-blue: #1e40af;
            --border-gray: #e2e8f0;
            --bg-gray: #f8fafc;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-premium: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Dashboard Stats Custom Style overrides */
        .stats-grid {
            margin-bottom: 2.5rem;
        }

        .stat-card-premium {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: background 0.3s;
        }

        .stat-card-premium.total::before { background: #3b82f6; }
        .stat-card-premium.active::before { background: #10b981; }
        .stat-card-premium.suspended::before { background: #f59e0b; }
        .stat-card-premium.admin::before { background: #f43f5e; }

        .stat-card-premium:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: #cbd5e1;
        }

        .stat-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-wrapper.blue { background: #eff6ff; color: #2563eb; }
        .stat-icon-wrapper.green { background: #ecfdf5; color: #059669; }
        .stat-icon-wrapper.amber { background: #fffbeb; color: #d97706; }
        .stat-icon-wrapper.rose { background: #fff1f2; color: #e11d48; }

        /* Search Section */
        .search-filter-card {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: border-color 0.2s;
        }

        .search-filter-card:focus-within {
            border-color: #93c5fd;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.05);
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: #94a3b8;
            pointer-events: none;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 46px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            background-color: #f8fafc;
            color: var(--text-color);
            transition: all 0.2s;
        }

        .search-input-wrapper input:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            outline: none;
        }

        /* List Section Redesign */
        .list-section-premium {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-gray);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-top: 1rem;
        }

        .section-header-premium {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header-premium h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--title-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Premium Table and Rows */
        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.925rem;
        }

        .premium-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.75px;
            border-bottom: 1px solid var(--border-gray);
        }

        .premium-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: background-color 0.2s;
        }

        .premium-table tr {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .premium-table tr:hover td {
            background-color: #eff6ff;
        }

        /* User identity & dynamic avatar cell */
        .identity-flex {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-gradient {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.05rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .avatar-admin { background: var(--admin-gradient); box-shadow: 0 4px 8px rgba(244, 63, 94, 0.25); }
        .avatar-adm { background: var(--adm-gradient); box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25); }
        .avatar-tutor { background: var(--tutor-gradient); box-shadow: 0 4px 8px rgba(16, 185, 129, 0.25); }
        .avatar-com { background: var(--com-gradient); box-shadow: 0 4px 8px rgba(245, 158, 11, 0.25); }
        .avatar-lec { background: var(--lec-gradient); box-shadow: 0 4px 8px rgba(107, 114, 128, 0.25); }

        .user-info-text {
            display: flex;
            flex-direction: column;
        }

        .user-info-text .username {
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.975rem;
        }

        .user-info-text .email {
            font-size: 0.775rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        /* Badges */
        .badge-premium-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
        }

        .badge-admin { background: #ffe4e6; color: #be123c; border: 1px solid #fecdd3; }
        .badge-adm { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-tutor { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-comercial { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-default { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Pulsing Status Dot & Badge */
        .status-badge-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .status-dot-pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            position: relative;
        }

        .status-dot-pulse.active {
            background: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            animation: pulseEmerald 2s infinite;
        }

        .status-dot-pulse.inactive {
            background: #94a3b8;
            box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.1);
        }

        @keyframes pulseEmerald {
            0% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0); }
        }

        /* Action Buttons */
        .btn-action-premium {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.775rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .btn-action-premium.profile {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .btn-action-premium.profile:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.15);
        }

        .btn-action-premium.suspend {
            background: #fff5f5;
            color: #e11d48;
            border-color: #fecdd3;
        }

        .btn-action-premium.suspend:hover {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(225, 29, 72, 0.15);
        }

        .btn-action-premium.activate {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        .btn-action-premium.activate:hover {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(22, 163, 74, 0.15);
        }

        .btn-action-premium.delete-btn {
            background: #fff1f2;
            color: #e11d48;
            border-color: #fecdd3;
        }

        .btn-action-premium.delete-btn:hover {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(225, 29, 72, 0.15);
        }

        /* Premium Modal Design */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(12px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
            transition: opacity 0.3s ease;
        }

        .modal-container {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: 20px;
            box-shadow: var(--shadow-premium);
            overflow: hidden;
            border: 1px solid var(--border-gray);
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-overlay.open .modal-container {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            padding: 24px 28px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--title-blue);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-close {
            background: #e2e8f0;
            border: none;
            color: #475569;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #cbd5e1;
            color: #0f172a;
        }

        .modal-body {
            padding: 28px;
            overflow-y: auto;
        }

        .premium-field {
            margin-bottom: 1.25rem;
        }

        .premium-field label {
            display: block;
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.725rem;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.75px;
        }

        .premium-field input, .premium-field select {
            width: 100%;
            padding: 11px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.925rem;
            transition: all 0.2s;
            box-sizing: border-box;
            background-color: #ffffff;
            color: var(--text-color);
        }

        .premium-field input:focus, .premium-field select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn-create-premium {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            width: 100%;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.75px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(30, 64, 175, 0.15);
            margin-top: 0.5rem;
        }

        .btn-create-premium:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(30, 64, 175, 0.25);
        }

        /* Custom Alert styling */
        .premium-alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            border-left: 6px solid transparent;
        }
        .premium-alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; border: 1px solid #a7f3d0; }
        .premium-alert-error { background: #fff1f2; color: #991b1b; border-left-color: #ef4444; border: 1px solid #fecdd3; }
        .premium-alert svg { flex-shrink: 0; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Usuarios y Permisos</h1>
                <p>Administración del acceso y control de seguridad corporativo (ISO 27001)</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()" style="border-radius: 10px; padding: 11px 20px;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nuevo Usuario
            </button>
        </header>

        <?php if ($success): ?>
            <div class="premium-alert premium-alert-success">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="premium-alert premium-alert-error">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <!-- CALCULATE DASHBOARD COUNTS -->
        <?php
        $total_users = count($usuarios);
        $active_users = 0;
        $suspended_users = 0;
        $admin_users = 0;
        foreach ($usuarios as $u) {
            if ($u['activo']) {
                $active_users++;
            } else {
                $suspended_users++;
            }
            if ($u['rol_id'] == ROLE_ADMIN) {
                $admin_users++;
            }
        }
        ?>

        <!-- DASHBOARD STATS SECTION -->
        <section class="stats-grid">
            <div class="stat-card-premium total">
                <div class="stat-icon-wrapper blue">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-label">Personal Total</div>
                </div>
            </div>
            
            <div class="stat-card-premium active">
                <div class="stat-icon-wrapper green">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $active_users ?></div>
                    <div class="stat-label">Habilitados</div>
                </div>
            </div>

            <div class="stat-card-premium suspended">
                <div class="stat-icon-wrapper amber">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $suspended_users ?></div>
                    <div class="stat-label">Suspendidos</div>
                </div>
            </div>

            <div class="stat-card-premium admin">
                <div class="stat-icon-wrapper rose">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $admin_users ?></div>
                    <div class="stat-label">Administradores</div>
                </div>
            </div>
        </section>

        <!-- INTERACTIVE LIVE FILTER SEARCH BAR -->
        <section class="search-filter-card">
            <div class="search-input-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="userSearchInput" placeholder="Buscar personal por nombre, usuario, email o rol en tiempo real..." onkeyup="filterUsers()">
            </div>
        </section>

        <!-- TABLE SECTION -->
        <section class="list-section-premium">
            <div class="section-header-premium">
                <h2>Listado de Personal Registrado</h2>
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); background: #f1f5f9; padding: 6px 14px; border-radius: 8px;" id="userCounter">
                    <?= $total_users ?> usuarios
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="premium-table" id="usersTable">
                    <thead>
                    <tr>
                        <th>Identidad Acceso</th>
                        <th>Nombre y Apellidos</th>
                        <th>Nivel de Acceso</th>
                        <th>Estado Actual</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <?php
                    // Dynamic class selection for background gradients based on role
                    $avatar_class = 'avatar-lec';
                    $badge_class = 'badge-default';
                    switch ($u['rol_id']) {
                        case ROLE_ADMIN:
                            $avatar_class = 'avatar-admin';
                            $badge_class = 'badge-admin';
                            break;
                        case ROLE_COORD:
                            $avatar_class = 'avatar-adm';
                            $badge_class = 'badge-adm';
                            break;
                        case ROLE_ADMINISTRATIVO:
                            $avatar_class = 'avatar-adm';
                            $badge_class = 'badge-adm';
                            break;
                        case ROLE_TUTOR:
                            $avatar_class = 'avatar-tutor';
                            $badge_class = 'badge-tutor';
                            break;
                        case ROLE_COMERCIAL:
                            $avatar_class = 'avatar-com';
                            $badge_class = 'badge-comercial';
                            break;
                    }
                    
                    // Initials for avatar
                    $iniciales = strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellidos'] ?: $u['username'], 0, 1));
                    ?>
                    <tr class="user-row-item">
                        <td>
                            <div class="identity-flex">
                                <div class="user-avatar-gradient <?= $avatar_class ?>">
                                    <?= $iniciales ?>
                                </div>
                                <div class="user-info-text">
                                    <span class="username"><?= htmlspecialchars($u['username']) ?></span>
                                    <span class="email"><?= htmlspecialchars($u['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                        <td>
                            <span class="badge-premium-pill <?= $badge_class ?>">
                                <?= htmlspecialchars($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="status-badge-premium">
                                <span class="status-dot-pulse <?= $u['activo'] ? 'active' : 'inactive' ?>"></span>
                                <span style="color: <?= $u['activo'] ? '#059669' : '#64748b' ?>;">
                                    <?= $u['activo'] ? 'Habilitado' : 'Suspendido' ?>
                                </span>
                            </div>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                <a href="ficha_trabajador.php?id=<?= $u['id'] ?>" class="btn-action-premium profile">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    Perfil
                                </a>
                                <?php if ($u['rol_id'] == ROLE_TUTOR && $u['activo']): ?>
                                    <?php if (in_array($u['id'], $syncedUserIds)): ?>
                                        <button type="button" class="btn-action-premium" style="background: #ecfdf5; color: #059669; border-color: #a7f3d0; cursor: default;" title="Ya dado de alta en Moodle">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                            Alta en Moodle
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Dar de alta a este tutor en Moodle? Se le asignará una contraseña temporal genérica.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <input type="hidden" name="action" value="sync_moodle">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-action-premium" style="background: #fff8e1; color: #d97706; border-color: #fde68a;">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                                Alta en Moodle
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que desea cambiar el estado de este usuario?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['activo'] ? '0' : '1' ?>">
                                    
                                    <?php if ($u['activo']): ?>
                                        <button type="submit" class="btn-action-premium suspend">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
                                            Suspender
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn-action-premium activate">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                            Activar
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que desea eliminar de forma permanente a este usuario? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-action-premium delete-btn">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                            Borrar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            
            <div id="noResults" style="display: none; padding: 3rem; text-align: center; color: #64748b; font-weight: 500;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" style="margin: 0 auto 15px auto; color: #cbd5e1;"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                No se encontraron usuarios que coincidan con la búsqueda.
            </div>
        </section>
    </main>
</div>

<!-- MODAL ALTA USUARIO -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Alta de Nuevo Usuario</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="premium-field">
                    <label>Nombre de Usuario (Log-in)</label>
                    <input type="text" name="username" placeholder="p.ej. j.garcia" required>
                </div>
                
                <div class="premium-field">
                    <label>Contraseña Provisional</label>
                    <div style="display: flex; gap: 10px; align-items: center; position: relative;">
                        <input type="password" name="password" id="user_password" placeholder="Mínimo 8 caracteres" style="flex: 1; padding: 0.75rem;" required>
                        <button type="button" class="btn-outline" onclick="togglePasswordVisibility()" style="padding: 0.6rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; background: white; border: 1px solid #cbd5e1; color: #475569;" title="Mostrar/Ocultar contraseña">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                        <button type="button" class="btn-outline" onclick="generateSecurePassword()" style="padding: 0.6rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; background: #2563eb; border: 1px solid #2563eb; color: white;" title="Generar contraseña segura">
                            🔑 Generar
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="premium-field">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="premium-field">
                        <label>Apellidos</label>
                        <input type="text" name="apellidos">
                    </div>
                </div>
                
                <div class="premium-field">
                    <label>DNI / NIF</label>
                    <input type="text" name="dni" placeholder="12345678Z">
                </div>
                
                <div class="premium-field">
                    <label>E-mail Corporativo</label>
                    <input type="email" name="email" placeholder="usuario@grupoefp.es" required>
                </div>
                
                <div class="premium-field">
                    <label>Rol y Atribuciones de Acceso</label>
                    <select name="rol_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-create-premium">Generar Credenciales de Acceso</button>
                <p style="font-size: 0.725rem; color: var(--text-muted); text-align: center; margin-top: 15px; font-weight: 500; line-height: 1.4;">
                    🔒 ISO 27001: El usuario será notificado tras la creación exitosa siguiendo los protocolos de seguridad de la información corporativa.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    // Live client-side user search and filtering
    function filterUsers() {
        const query = document.getElementById('userSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#usersTable tbody tr.user-row-item');
        const noResults = document.getElementById('noResults');
        const table = document.querySelector('.premium-table');
        const counter = document.getElementById('userCounter');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update matches counter dynamically
        counter.textContent = `${visibleCount} de ${rows.length} usuarios`;

        if (visibleCount === 0) {
            table.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            table.style.display = 'table';
            noResults.style.display = 'none';
        }
    }

    // Modal Operations with smooth backdrop scaling transitions
    function openModal() {
        const overlay = document.getElementById('modalOverlay');
        overlay.style.display = 'flex';
        // Force reflow
        overlay.offsetHeight;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden'; 
    }

    function closeModal() {
        const overlay = document.getElementById('modalOverlay');
        overlay.classList.remove('open');
        setTimeout(() => {
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }, 300);
    }

    // Close when clicking outside modal box
    window.onclick = function(event) {
        const overlay = document.getElementById('modalOverlay');
        if (event.target == overlay) {
            closeModal();
        }
    }

    // Mostrar/Ocultar contraseña
    function togglePasswordVisibility() {
        const input = document.getElementById('user_password');
        const icon = document.getElementById('togglePasswordIcon');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    // Generar contraseña segura y válida según requisitos de complejidad (mínimo 12 caracteres, especiales: @$!%*?&#)
    function generateSecurePassword() {
        const uppers = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lowers = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const specials = "@$!%*?&#";
        const all = uppers + lowers + numbers + specials;
        
        let password = "";
        password += uppers.charAt(Math.floor(Math.random() * uppers.length));
        password += lowers.charAt(Math.floor(Math.random() * lowers.length));
        password += numbers.charAt(Math.floor(Math.random() * numbers.length));
        password += specials.charAt(Math.floor(Math.random() * specials.length));
        
        // Generar el resto de caracteres (12 en total)
        for (let i = 0; i < 8; i++) {
            password += all.charAt(Math.floor(Math.random() * all.length));
        }
        
        // Mezclar los caracteres
        password = password.split('').sort(() => 0.5 - Math.random()).join('');
        
        const input = document.getElementById('user_password');
        input.value = password;
        
        // Mostrar la contraseña en texto plano para que el usuario la vea y la copie
        input.type = 'text';
        const icon = document.getElementById('togglePasswordIcon');
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
</script>

</body>
</html>
