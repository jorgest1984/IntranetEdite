<?php
// comerciales_contactos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 1.5rem; }
        .btn-volver {
            padding: 6px 20px;
            font-size: 0.85rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
            color: #475569;
            font-weight: 500;
        }
        .btn-volver:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 15px;">
                <a href="comerciales.php" class="btn-volver">← Volver a Gestión Comercial</a>
            </div>

            <div style="background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2rem; text-align: center; margin-top: 2rem;">
                <h2 style="color: #0f172a; margin-top: 0;">Gestión de Contactos</h2>
                <p style="color: #64748b;">La interfaz de contactos se implementará aquí.</p>
            </div>
        </main>
    </div>
</body>
</html>
