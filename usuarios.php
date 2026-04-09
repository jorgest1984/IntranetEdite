<?php
// usuarios.php - Gestión de Usuarios con Modal Premium
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Solo administradores pueden gestionar usuarios (ISO 27001 - A.9)
if (!has_permission([ROLE_ADMIN])) {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Crear Usuario
    if ($_POST['action'] == 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $nombre = trim($_POST['nombre']);
        $apellidos = trim($_POST['apellidos']);
        $email = trim($_POST['email']);
        $rol_id = intval($_POST['rol_id']);
        
        if (empty($username) || empty($password) || empty($nombre) || empty($email)) {
            $error = "Faltan campos obligatorios.";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, nombre, apellidos, email, rol_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password_hash, $nombre, $apellidos, $email, $rol_id]);
                
                audit_log($pdo, 'USUARIO_CREADO', 'usuarios', $pdo->lastInsertId(), null, ['username' => $username, 'rol' => $rol_id]);
                $success = "Usuario '$username' creado correctamente.";
            } catch (PDOException $e) {
                $error = "Error: El nombre de usuario o email ya existe.";
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
}

// Listado de usuarios
$stmt = $pdo->query("SELECT u.*, r.nombre as rol_nombre 
                     FROM usuarios u 
                     JOIN roles r ON u.rol_id = r.id 
                     ORDER BY u.activo DESC, u.username ASC");
$usuarios = $stmt->fetchAll();

// Listado de roles para el combo
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-gray: #f8fafc;
        }

        .list-section {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-gray);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-top: 1rem;
        }

        .section-header-premium {
            background: #f8fafc;
            padding: 15px 25px;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header-premium h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--label-blue);
            text-transform: uppercase;
        }

        /* Tabla Estilizada */
        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .premium-table th {
            text-align: left;
            padding: 15px 25px;
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .premium-table td {
            padding: 18px 25px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .premium-table tr:hover { background-color: #fef2f2; }

        .user-info-cell .username { font-weight: 700; color: var(--text-color); font-size: 0.95rem; display: block; }
        .user-info-cell .email { font-size: 0.8rem; color: var(--text-muted); }

        .badge-premium {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-role-admin { background: #fee2e2; color: #b91c1c; }
        .badge-role-default { background: #f1f5f9; color: #475569; }

        .status-badge { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 500; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-dot.active { background: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .status-dot.inactive { background: #94a3b8; }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal-container {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            padding: 20px 25px;
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--title-red);
            font-weight: 800;
            text-transform: uppercase;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .modal-close:hover { background: #f1f5f9; color: var(--primary-color); }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
        }

        .premium-field { margin-bottom: 20px; }
        .premium-field label {
            display: block;
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.75rem;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .premium-field input, .premium-field select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .premium-field input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.05);
            outline: none;
        }

        .btn-create-full {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            width: 100%;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }

        .btn-create-full:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Alertas Premium */
        .premium-alert {
            padding: 15px 25px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        .premium-alert-success { background: #d1fae5; color: #065f46; border-left: 5px solid #10b981; }
        .premium-alert-error { background: #fee2e2; color: #991b1b; border-left: 5px solid #ef4444; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Usuarios y Permisos</h1>
                <p>Administración de acceso al sistema corporativo</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Nuevo Usuario
            </button>
        </header>

        <?php if ($success): ?>
            <div class="premium-alert premium-alert-success">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="premium-alert premium-alert-error">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <section class="list-section">
            <div class="section-header-premium">
                <h2>Listado de Personal</h2>
                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= count($usuarios) ?> usuarios registrados</div>
            </div>
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Identidad Accesso</th>
                        <th>Nombre y Apellidos</th>
                        <th>Nivel de Acceso</th>
                        <th>Estado Actual</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <div class="user-info-cell">
                                <span class="username"><?= htmlspecialchars($u['username']) ?></span>
                                <span class="email"><?= htmlspecialchars($u['email']) ?></span>
                            </div>
                        </td>
                        <td style="font-weight: 500;"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                        <td>
                            <span class="badge-premium <?= ($u['rol_id'] == ROLE_ADMIN) ? 'badge-role-admin' : 'badge-role-default' ?>">
                                <?= htmlspecialchars($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="status-badge">
                                <span class="status-dot <?= $u['activo'] ? 'active' : 'inactive' ?>"></span>
                                <span style="color: <?= $u['activo'] ? '#065f46' : '#64748b' ?>;">
                                    <?= $u['activo'] ? 'Habilitado' : 'Suspendido' ?>
                                </span>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="status" value="<?= $u['activo'] ? '0' : '1' ?>">
                                <button type="submit" class="btn" style="padding: 0.4rem 0.8rem; border: 1px solid var(--border-color); font-size: 0.8rem; background: #fff;">
                                    <?= $u['activo'] ? 'Revocar Acceso' : 'Rehabilitar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<!-- MODAL ALTA USUARIO -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Alta de Nuevo Usuario</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="premium-field">
                    <label>Nombre de Usuario (Log-in)</label>
                    <input type="text" name="username" placeholder="p.ej. j.garcia" required>
                </div>
                
                <div class="premium-field">
                    <label>Contraseña Provisional</label>
                    <input type="password" name="password" placeholder="Mínimo 8 caracteres" required>
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
                    <label>E-mail Corporativo</label>
                    <input type="email" name="email" placeholder="usuario@grupoefp.es" required>
                </div>
                
                <div class="premium-field">
                    <label>Rol y Atribuciones</label>
                    <select name="rol_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-create-full">Generar Credenciales de Acceso</button>
                <p style="font-size: 0.7rem; color: var(--text-muted); text-align: center; margin-top: 15px;">
                    ISO 27001: El usuario será notificado tras la creación exitosa siguiendo los protocolos de seguridad.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Evita scroll de fondo
    }

    function closeModal() {
        document.getElementById('modalOverlay').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Cerrar al pulsar fuera del contenedor
    window.onclick = function(event) {
        const overlay = document.getElementById('modalOverlay');
        if (event.target == overlay) {
            closeModal();
        }
    }
</script>

</body>
</html>
