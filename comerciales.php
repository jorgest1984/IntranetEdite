<?php
// comerciales.php
require_once 'includes/auth.php';

// Verificar permisos (por ejemplo, Admin, Administrativo o Comercial)
if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Comercial - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Gestión Comercial</h1>
                <p>Panel de herramientas para consultores y ventas</p>
            </div>
        </header>

        <div class="home-container" style="padding: 0; margin-top: 2rem;">
            
            <div class="home-section">
                <h2 class="home-section-title">Funcionalidades</h2>
                <div class="tiles-grid">
                    
                    <!-- Tarjeta EMPRESAS -->
                    <a href="comerciales_empresas.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                        </div>
                        <div class="tile-title">Empresas</div>
                    </a>

                    <!-- Tarjeta TRABAJADORES -->
                    <a href="comerciales_trabajadores.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </div>
                        <div class="tile-title">Trabajadores</div>
                    </a>

                    <!-- Tarjeta ACCIONES FORMATIVAS -->
                    <a href="#" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                        </div>
                        <div class="tile-title">Acciones Formativas</div>
                    </a>

                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>
