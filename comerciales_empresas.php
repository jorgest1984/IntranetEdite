<?php
// comerciales_empresas.php
require_once 'includes/auth.php';

// Verificar permisos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL])) {
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
    <title>Área de Empresas - <?= APP_NAME ?></title>
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
                <h1>Área de Empresas</h1>
                <p>Gestión y herramientas relacionadas con empresas</p>
            </div>
            <!-- Botón volver -->
            <a href="comerciales.php" class="btn" style="background: #f1f5f9; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>
        </header>

        <div class="home-container" style="padding: 0; margin-top: 2rem;">
            <div class="home-section">
                <h2 class="home-section-title" style="font-size: 1.1rem; color: var(--text-color); margin-bottom: 1rem;">Herramientas para gestionar empresas en intranet para comerciales</h2>
                <div class="tiles-grid">
                    
                    <!-- Tarjeta BUSCAR EMPRESA -->
                    <a href="buscar_empresas.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        </div>
                        <div class="tile-title">Buscar Empresa</div>
                    </a>

                    <!-- Puedes agregar más funcionalidades en el futuro -->

                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
