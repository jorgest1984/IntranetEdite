<?php
// editar_centro.php
require_once 'includes/auth.php';

// Solo admin
if (!has_permission([ROLE_ADMIN])) {
    header("Location: index.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("Location: centros.php");
    exit();
}

// Obtener datos del centro
$stmt = $pdo->prepare("SELECT * FROM centros WHERE id = ?");
$stmt->execute([$id]);
$centro = $stmt->fetch();

if (!$centro) {
    header("Location: centros.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $provincia = trim($_POST['provincia']);
    $cp = trim($_POST['cp']);
    $telefono = trim($_POST['telefono']);
    $email_contacto = trim($_POST['email_contacto']);

    if (empty($nombre)) {
        $error = "El nombre del centro es obligatorio.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE centros SET nombre = ?, direccion = ?, provincia = ?, cp = ?, telefono = ?, email_contacto = ? WHERE id = ?");
            $stmt->execute([$nombre, $direccion, $provincia, $cp, $telefono, $email_contacto, $id]);
            
            header("Location: centros.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error al actualizar el centro: " . $e->getMessage();
        }
    }
}

$page_title = "Editar Centro";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .back-link {
            display: inline-flex; align-items: center; gap: 5px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; margin-bottom: 10px; transition: color 0.2s;
        }
        .back-link:hover { color: #1e3a8a; }
        .page-header { margin-bottom: 30px; }
        .page-title { margin: 0; color: #1e3a8a; font-size: 1.8rem; font-weight: 800; }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; transition: all 0.3s; }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; border: none; font-size: 0.95rem; }
        .btn-primary { background: #1e3a8a; color: white; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2); }
        .btn-primary:hover { background: #172554; transform: translateY(-1px); }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto; padding: 2rem;">
        <header class="page-header">
            <div>
                <a href="centros.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver a Centros
                </a>
                <h1 class="page-title">Editar Centro: <?= htmlspecialchars($centro['nombre']) ?></h1>
            </div>
        </header>

        <div class="card form-card fade-in">
            <?php if ($error): ?>
                <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Nombre del Centro *</label>
                        <input type="text" name="nombre" class="form-input" required value="<?= htmlspecialchars($_POST['nombre'] ?? $centro['nombre']) ?>" placeholder="Ej. Sede Central Madrid">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Dirección Completa</label>
                        <input type="text" name="direccion" class="form-input" value="<?= htmlspecialchars($_POST['direccion'] ?? $centro['direccion']) ?>" placeholder="Calle, número, piso...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-input" value="<?= htmlspecialchars($_POST['provincia'] ?? $centro['provincia']) ?>" placeholder="Ej. Madrid">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código Postal</label>
                        <input type="text" name="cp" class="form-input" value="<?= htmlspecialchars($_POST['cp'] ?? $centro['cp']) ?>" placeholder="Ej. 28001">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Teléfono de Contacto</label>
                        <input type="tel" name="telefono" class="form-input" value="<?= htmlspecialchars($_POST['telefono'] ?? $centro['telefono']) ?>" placeholder="Ej. 910000000">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email de Contacto</label>
                        <input type="email" name="email_contacto" class="form-input" value="<?= htmlspecialchars($_POST['email_contacto'] ?? $centro['email_contacto']) ?>" placeholder="sede@ejemplo.com">
                    </div>
                </div>

                <div class="form-actions mt-6">
                    <a href="centros.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Actualizar Centro</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
