<?php
// usuarios.php
session_start();
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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
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
        .split-layout { display: flex; gap: 2rem; align-items: flex-start; }
        .list-section { flex: 2; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-section { flex: 1; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; position: sticky; top: 2rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.1); }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-muted); background-color: #f8fafc; }
        
        .badge-role { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #f1f5f9; color: #475569; }
        .status-active { color: #059669; font-weight: 600; }
        .status-inactive { color: #dc2626; font-weight: 600; opacity: 0.6; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Control de Acceso y Usuarios</h1>
                <p>Gestión de personal administrativo y docente</p>
            </div>
        </header>

        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="split-layout">
            <section class="list-section">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr style="<?= !$u['activo'] ? 'opacity: 0.6;' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($u['username']) ?></strong><br>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                            <td><span class="badge-role"><?= htmlspecialchars($u['rol_nombre']) ?></span></td>
                            <td>
                                <span class="<?= $u['activo'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['activo'] ? '0' : '1' ?>">
                                    <button type="submit" class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--border-color);">
                                        <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="form-section">
                <h2 style="margin-top: 0; font-size: 1.1rem; color: var(--primary-color);">Alta de Nuevo Usuario</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="apellidos" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rol del Sistema *</label>
                        <select name="rol_id" class="form-input" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Crear Cuenta de Acceso</button>
                </form>
            </section>
        </div>
    </main>
</div>

</body>
</html>
