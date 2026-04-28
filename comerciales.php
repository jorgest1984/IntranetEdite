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
                    <a href="buscar_empresas.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <!-- Icono de empresa / edificio -->
                            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                        </div>
                        <div class="tile-title">Buscar Empresa</div>
                    </a>
                    
                    <!-- Aquí se irán añadiendo más funcionalidades en el futuro -->
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
