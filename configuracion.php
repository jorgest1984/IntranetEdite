<?php
// configuracion.php
session_start();
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN])) {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_moodle'])) {
    $url = trim($_POST['moodle_url']);
    $token = trim($_POST['moodle_token']);
    
    try {
        $stmtUrl = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'moodle_url'");
        $stmtUrl->execute([$url]);
        
        $stmtToken = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'moodle_token'");
        $stmtToken->execute([$token]);
        
        audit_log($pdo, 'CONFIG_MOODLE_UPDATED', 'configuracion', null, null, null);
        $success = "Configuración de Moodle actualizada correctamente.";
    } catch (Exception $e) {
        $error = "Error al actualizar la base de datos.";
    }
}

// Cargar config actual
$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            max-width: 800px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.95rem; font-weight: 500; }
        .form-input {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color);
            border-radius: 6px; font-family: inherit; font-size: 1rem; box-sizing: border-box; background: var(--input-bg);
        }
        .form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); background: var(--input-focus-bg); }
        .help-text { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; display: block; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
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
                <h1>Configuración General</h1>
                <p>Parámetros y conexiones del sistema</p>
            </div>
        </header>

        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="settings-card">
            <h2 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.2rem; color: var(--primary-color); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Capa de Integración Moodle (API REST)</h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">URL del Servidor Moodle</label>
                    <input type="url" name="moodle_url" class="form-input" value="<?= htmlspecialchars($config['moodle_url'] ?? '') ?>" placeholder="https://mi-aula.com" required>
                    <span class="help-text">Ejemplo: https://campus.editeformacion.es</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Token WebService</label>
                    <input type="text" name="moodle_token" class="form-input" value="<?= htmlspecialchars($config['moodle_token'] ?? '') ?>">
                    <span class="help-text">Token generado desde Moodle (Administración > Extensiones > Servicios Web > Gestionar tokens)</span>
                </div>
                
                <button type="submit" name="save_moodle" class="btn btn-primary">Guardar Credenciales Moodle</button>
            </form>
        </div>
    </main>
</div>

</body>
</html>
