<?php
// comerciales_acciones.php
require_once 'includes/auth.php';

// Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL])) {
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
    <title>Área de Acciones Formativas - <?= APP_NAME ?></title>
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
                <h1>Área de Acciones Formativas</h1>
                <p>Gestión y herramientas de acciones formativas</p>
            </div>
            <a href="comerciales.php" class="btn" style="background: #f1f5f9; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>
        </header>

        <div class="home-container" style="padding: 0; margin-top: 2rem;">
            <div class="home-section">
                <h2 class="home-section-title" style="font-size: 1.1rem; color: var(--text-color); margin-bottom: 1rem;">Herramientas para gestionar acciones formativas en intranet para comerciales</h2>
                <div class="tiles-grid">
                    
                    <!-- Tarjeta BUSCAR ACCIÓN FORMATIVA -->
                    <a href="acciones_formativas.php?context=comercial" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                        </div>
                        <div class="tile-title">Buscar AF</div>
                    </a>

                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
