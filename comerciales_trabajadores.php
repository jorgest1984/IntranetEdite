<?php
// comerciales_trabajadores.php
require_once 'includes/auth.php';

// Verificar permisos
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
    <title>Área de Trabajadores - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Área de Trabajadores</h1>
                <p>Gestión y herramientas relacionadas con trabajadores (alumnos)</p>
            </div>
            <a href="comerciales.php" class="btn" style="background: #f1f5f9; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>
        </header>

        <div class="home-container" style="padding: 0; margin-top: 2rem;">
            <div class="home-section">
                <h2 class="home-section-title" style="font-size: 1.1rem; color: var(--text-color); margin-bottom: 1rem;">Herramientas para gestionar trabajadores en intranet para comerciales</h2>
                <div class="tiles-grid">
                    
                    <!-- Tarjeta BUSCAR ALUMNOS -->
                    <a href="buscar_alumnos.php?context=comercial" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </div>
                        <div class="tile-title">Buscar Alumnos</div>
                    </a>

                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
