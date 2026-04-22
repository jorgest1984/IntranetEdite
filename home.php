<?php
// home.php - VERSION 2.0 BRACE_SYNTAX
require_once 'includes/auth.php'; // Verifica login y permisos

// --- LÓGICA DASHBOARD (NUEVO) ---
// Obtener estadísticas rápidas
$stats = [
    'alumnos_activos' => $pdo->query("SELECT COUNT(*) FROM alumnos WHERE baja = 0")->fetchColumn(),
    'cursos_moodle' => 14, // Podría dinamizarse también
    'certificados_pendientes' => 2,
    'ingresos_mes' => '12.450 €'
];

// Obtener últimos logs de auditoría si es Admin
$logs = [];
if (has_permission([ROLE_ADMIN, ROLE_LECTURA, ROLE_COORD])) {
    $stmt = $pdo->query("SELECT al.*, u.username FROM audit_log al LEFT JOIN usuarios u ON al.usuario_id = u.id ORDER BY al.fecha DESC LIMIT 10");
    $logs = $stmt->fetchAll();
}
// --------------------------------

// Define los módulos de la página de inicio con filtrado por rol
$sections = [
    'Inicio' => array_filter([
        ['title' => 'Grupo EFP', 'icon' => '<svg viewBox="0 0 24 24"><path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/></svg>', 'url' => 'edite_formacion.php', 'color' => 'blue'],
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'Formación', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'formacion.php', 'color' => 'blue'] : null),
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'Formación Profesional', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'formacion_profesional.php', 'color' => 'blue'] : null),
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'Cursos Moodle', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>', 'url' => 'cursos.php', 'color' => 'blue'] : null),
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'Tutorías', 'icon' => '<svg viewBox="0 0 24 24"><path d="M15 8c0-1.42-.5-2.73-1.33-3.76.42-.14.86-.24 1.33-.24 2.21 0 4 1.79 4 4s-1.79 4-4 4c-.43 0-.84-.09-1.23-.21-.03-.01-.06-.02-.1-.03A5.98 5.98 0 0 0 15 8zm1.66 5.11C18.1 13.88 21 15.6 21 17v2h2v-2c0-2.12-3.32-3.6-5.34-3.89zM9 4c2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4 1.79-4 4-4zm6 13c0-2.67-5.33-4-8-4s-8 1.33-8 4v2h16v-2z"/></svg>', 'url' => 'tutorias.php', 'color' => 'blue'] : null),
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'Envío de claves', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>', 'url' => 'envio_claves.php', 'color' => 'black'] : null),
        (has_permission([ROLE_ADMIN]) ? ['title' => 'Usuarios', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>', 'url' => 'usuarios.php', 'color' => 'black'] : null),
    ]),
    'Áreas' => array_filter([
        (has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO]) ? ['title' => 'Contabilidad', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-6 2h4v4h-4V5zm-2 4H7V5h4v4zm-4 2h4v4H7v-4zm6 0h4v4h-4v-4zm-6 6h4v4H7v-4zm6 0h4v4h-4v-4z"/></svg>', 'url' => 'contabilidad.php', 'color' => 'blue'] : null),
        (has_permission([ROLE_ADMIN, ROLE_TUTOR]) ? ['title' => 'RRHH', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>', 'url' => '#', 'color' => 'blue'] : null),
        ['title' => 'Ventas', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 1.95c-5.52 0-10 4.48-10 10s4.48 10 10 10h5v-2h-5c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8v1.43c0 .79-.71 1.57-1.5 1.57s-1.5-.78-1.5-1.57v-1.43c0-2.76-2.24-5-5-5s-5 2.24-5 5 2.24 5 5 5c1.38 0 2.64-.56 3.54-1.47.65.89 1.77 1.47 2.96 1.47 1.97 0 3.5-1.53 3.5-3.5v-1.43c0-5.52-4.48-10-10-10zm0 13c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/></svg>', 'url' => '#', 'color' => 'blue'],
        ['title' => 'Informática', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>', 'url' => '#', 'color' => 'blue']
    ]),
    'Sitios y aplicaciones' => [
        ['title' => 'Web Grupo EFP', 'icon' => '<svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>', 'url' => 'https://escueladeformacionprofesional.com/', 'color' => 'blue', 'external' => true],
        ['title' => 'Webmail', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>', 'url' => 'https://webmail.grupoefp.es/SOGo/', 'color' => 'blue', 'external' => true],
        ['title' => 'NAS', 'icon' => '<svg viewBox="0 0 24 24"><path d="M2 20h20v-4H2v4zm2-3h2v2H4v-2zM2 4v4h20V4H2zm4 3H4V5h2v2zm-4 7h20v-4H2v4zm2-3h2v2H4v-2z"/></svg>', 'url' => 'https://editeformacion.fr1.quickconnect.to/#/signin', 'color' => 'blue', 'external' => true],
        ['title' => 'Aula Virtual', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'https://aulavirtual.grupoefp.es/', 'color' => 'blue', 'external' => true]
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupo EFP - Panel Principal</title>
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
                <h1>Panel Principal</h1>
                <p>Navegación y resumen general de actividad</p>
            </div>
            <div class="header-search">
                <form action="buscador_global.php" method="GET" class="search-form">
                    <div class="search-input-wrapper">
                        <svg viewBox="0 0 24 24" class="search-icon"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <input type="text" name="q" placeholder="Búsqueda global (alumnos, empresas, cursos...)" aria-label="Buscador global">
                        <button type="submit" class="btn-search-submit">Buscar</button>
                    </div>
                </form>
            </div>
        </header>

        <!-- TABS NAVIGATION -->
        <nav class="tabs-header">
            <button class="tab-btn active" onclick="switchTab('inicio')">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                Inicio
            </button>
            <button class="tab-btn" onclick="switchTab('panel')">
                <svg viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm-1-5h2v2h-2v-2zm0-8h2v6h-2V7z"/></svg>
                Panel de Control
            </button>
            <button class="tab-btn" onclick="switchTab('areas')">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Áreas
            </button>
            <button class="tab-btn" onclick="switchTab('apps')">
                <svg viewBox="0 0 24 24"><path d="M4 8h4V4H4v4zm6 12h4v-4h-4v4zm-6 0h4v-4H4v4zm0-6h4v-4H4v4zm6 0h4v-4h-4v4zm6-10v4h4V4h-4zm-6 4h4V4h-4v4zm6 6h4v-4h-4v4zm0 6h4v-4h-4v4z"/></svg>
                Aplicaciones
            </button>
        </nav>

        <div id="tab-inicio" class="tab-pane active">
            <div class="home-container" style="padding: 0;">
                <div class="home-section">
                    <h2 class="home-section-title">Accesos Directos</h2>
                    <div class="tiles-grid">
                        <?php foreach ($sections['Inicio'] as $tile) { 
                                $isExternal = !empty($tile['external']) && $tile['external'] === true;
                                $target = $isExternal ? '_blank' : '_self';
                            ?>
                            <a href="<?= htmlspecialchars($tile['url']) ?>" 
                               class="tile tile-<?= htmlspecialchars($tile['color']) ?>" 
                               target="<?= $target ?>">
                                <div class="tile-icon">
                                    <?= $tile['icon'] ?>
                                </div>
                                <div class="tile-title">
                                    <?= htmlspecialchars($tile['title']) ?>
                                </div>
                                <?php if ($isExternal) { ?>
                                    <svg class="tile-external-icon" viewBox="0 0 24 24">
                                        <path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                                    </svg>
                                <?php } ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: PANEL DE CONTROL -->
        <div id="tab-panel" class="tab-pane">
            <!-- SECCIÓN INTRANET (UTLLIDADES) -->
            <?php if (has_permission([ROLE_ADMIN])) { ?>
            <div class="home-section" style="margin-bottom: 2rem;">
                <h2 class="home-section-title">Intranet</h2>
                <div class="tiles-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <!-- Logs -->
                    <a href="auditoria.php" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2c5.52 0 10 4.48 10 10s-4.48 10-10 10S2 17.52 2 12 6.48 2 12 2zm0 18c4.42 0 8-3.58 8-8s-3.58-8-8-8-8 3.58-8 8 3.58 8 8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                        </div>
                        <div class="tile-title">Logs de usuario</div>
                    </a>
                    <!-- Calcular Llamadas -->
                    <a href="tutorias.php?view=calcular" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        </div>
                        <div class="tile-title">Calcular Llamadas de los tutores*</div>
                        <svg class="tile-external-icon" viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    </a>
                    <!-- Generar Claves -->
                    <a href="envio_claves.php" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2l2.45 7.55L22 12l-7.55 2.45L12 22l-2.45-7.55L2 12l7.55-2.45L12 2z"/></svg>
                        </div>
                        <div class="tile-title">Generar claves para nuevos alumnos</div>
                        <svg class="tile-external-icon" viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    </a>
                    <!-- Volcar Inscripciones -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
                        </div>
                        <div class="tile-title">Volcar inscripciones pendientes de la web</div>
                    </a>
                    <!-- Copia Seguridad -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2C9.24 2 7 3.34 7 5s2.24 3 5 3 5-1.34 5-3-2.24-3-5-3zm0 14c-2.76 0-5-1.34-5-3v2c0 1.66 2.24 3 5 3s5-1.34 5-3v-2c0 1.66-2.24 3-5 3zm0-5c-2.76 0-5-1.34-5-3v2c0 1.66 2.24 3 5 3s5-1.34 5-3V9c0 1.66-2.24 3-5 3z"/></svg>
                        </div>
                        <div class="tile-title">Copia de seguridad de BDs (Via MySQLDump)*</div>
                        <svg class="tile-external-icon" viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    </a>
                    <!-- Libreta vCard -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm6 4c0-2-4-3.1-6-3.1s-6 1.1-6 3.1v1h12v-1z"/></svg>
                        </div>
                        <div class="tile-title">Libreta de direcciones vCard</div>
                    </a>
                    <!-- Permisos -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        </div>
                        <div class="tile-title">Restablecer permisos de carpetas</div>
                    </a>
                </div>
            </div>
            <?php } ?>

            <!-- SECCIÓN AULA VIRTUAL -->
            <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])) { ?>
            <div class="home-section" style="margin-bottom: 2rem;">
                <h2 class="home-section-title">Aula Virtual</h2>
                <div class="tiles-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <!-- Actualizar -->
                    <a href="#" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        </div>
                        <div class="tile-title">Actualizar datos desde Aula Virtual</div>
                        <svg class="tile-external-icon" viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    </a>
                    <!-- Inspectores -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="tile-title">Conexión de Inspectores</div>
                    </a>
                    <!-- SCORM -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 16.5C21 16.88 20.79 17.21 20.47 17.38L12.57 21.82C12.41 21.94 12.21 22 12 22C11.79 22 11.59 21.94 11.43 21.82L3.53 17.38C3.21 17.21 3 16.88 3 16.5V7.5C3 7.12 3.21 6.79 3.53 6.62L11.43 2.18C11.59 2.06 11.79 2 12 2C12.21 2 12.41 2.06 12.57 2.18L20.47 6.62C20.79 6.79 21 7.12 21 7.5V16.5Z" stroke="white" stroke-width="2" fill="none"/></svg>
                        </div>
                        <div class="tile-title">Control de licencias SCORM</div>
                    </a>
                    <!-- Sincronizar -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 6v3l4-4-4-4v3c-4.42 0-8 3.58-8 8 0 1.57.46 3.03 1.24 4.26L6.7 14.8c-.45-.83-.7-1.79-.7-2.8 0-3.31 2.69-6 6-6zm6.76 1.74L17.3 9.2c.44.84.7 1.79.7 2.8 0 3.31-2.69 6-6 6v-3l-4 4 4 4v-3c4.42 0 8-3.58 8-8 0-1.57-.46-3.03-1.24-4.26z"/></svg>
                        </div>
                        <div class="tile-title">Sincronizar datos de los alumnos</div>
                    </a>
                </div>
            </div>
            <?php } ?>

            <!-- SECCIÓN E-MAIL -->
            <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])) { ?>
            <div class="home-section" style="margin-bottom: 2.5rem;">
                <h2 class="home-section-title">E-mail</h2>
                <div class="tiles-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <!-- Plantillas -->
                    <a href="email_masivo.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        </div>
                        <div class="tile-title">Ver plantillas de emails</div>
                    </a>
                    <!-- Emails Admision -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z"/></svg>
                        </div>
                        <div class="tile-title">Enviar emails de admisión</div>
                    </a>
                    <!-- Envío de claves -->
                    <a href="envio_claves.php" class="tile tile-blue">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                        </div>
                        <div class="tile-title">Envío de claves</div>
                    </a>
                </div>
            </div>
            <?php } ?>

            <!-- SECCIÓN ARQUITECTURA -->
            <?php if (has_permission([ROLE_ADMIN])) { ?>
            <div class="home-section" style="margin-bottom: 2rem;">
                <h2 class="home-section-title">Arquitectura de la intranet</h2>
                <div class="tiles-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <!-- Controladores -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
                        </div>
                        <div class="tile-title">Controladores</div>
                    </a>
                    <!-- Controladores Antiguos -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg>
                        </div>
                        <div class="tile-title">Controladores antiguos</div>
                    </a>
                    <!-- Scripts Antiguos -->
                    <a href="#" class="tile tile-danger">
                        <div class="tile-icon">
                            <svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.84,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
                        </div>
                        <div class="tile-title">Scripts antiguos</div>
                    </a>
                </div>
                <p style="margin-top: 1.5rem; font-size: 0.85rem; color: #64748b; font-style: italic;">
                    (*) Estos procesos pueden tardar en ejecutarse. Se recomienda usarlos con precaución.
                </p>
            </div>
            <?php } ?>

            <!-- SECCIÓN ESTADÍSTICAS -->
            <section class="stats-grid">
                <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])) { ?>
                <a href="buscar_alumnos.php?filter=activos" class="stat-card">
                    <div class="stat-icon primary">
                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= number_format($stats['alumnos_activos'], 0, ',', '.') ?></div>
                        <div class="stat-label">Alumnos Activos</div>
                    </div>
                </a>
                
                <a href="cursos.php" class="stat-card">
                    <div class="stat-icon success">
                        <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['cursos_moodle'] ?></div>
                        <div class="stat-label">Cursos Moodle</div>
                    </div>
                </a>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['certificados_pendientes'] ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                </div>
                <?php } ?>
            </section>

            <!-- WIDGETS -->
            <section class="dashboard-widgets">
                <div class="widget">
                    <div class="widget-header">
                        <h2 class="widget-title">Convocatorias Activas</h2>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Expediente</th>
                                <th>Denominación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: 600;">2024/001</td>
                                <td>Competencias Digitales</td>
                                <td><span class="badge" style="background: #dcfce7; color: #166534;">En curso</span></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">2024/002</td>
                                <td>Gestión Administrativa</td>
                                <td><span class="badge" style="background: #fef9c3; color: #854d0e;">Próximo</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="widget">
                    <div class="widget-header">
                        <h2 class="widget-title">Auditoría Reciente (ISO)</h2>
                    </div>
                    <?php if (empty($logs)) { ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">Sin registros recientes.</p>
                    <?php } else { ?>
                        <table class="data-table" style="font-size: 0.75rem;">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['username'] ?: 'Sis') ?></td>
                                    <td><?= htmlspecialchars($log['accion']) ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </section>
        </div>

        <!-- TAB: ÁREAS -->
        <div id="tab-areas" class="tab-pane">
            <div class="home-container" style="padding: 0;">
                <div class="home-section">
                    <h2 class="home-section-title">Gestión por Departamentos</h2>
                    <div class="tiles-grid">
                        <?php foreach ($sections['Áreas'] as $tile) { ?>
                            <a href="<?= htmlspecialchars($tile['url']) ?>" class="tile tile-<?= htmlspecialchars($tile['color']) ?>">
                                <div class="tile-icon"><?= $tile['icon'] ?></div>
                                <div class="tile-title"><?= htmlspecialchars($tile['title']) ?></div>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: APLICACIONES -->
        <div id="tab-apps" class="tab-pane">
            <div class="home-container" style="padding: 0;">
                <div class="home-section">
                    <h2 class="home-section-title">Enlaces Externos y Apps</h2>
                    <div class="tiles-grid">
                        <?php foreach ($sections['Sitios y aplicaciones'] as $tile) { ?>
                            <a href="<?= htmlspecialchars($tile['url']) ?>" class="tile tile-<?= htmlspecialchars($tile['color']) ?>" target="_blank">
                                <div class="tile-icon"><?= $tile['icon'] ?></div>
                                <div class="tile-title"><?= htmlspecialchars($tile['title']) ?></div>
                                <svg class="tile-external-icon" viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function switchTab(tabId) {
    // Ocultar todas las pestañas
    document.querySelectorAll('.tab-pane').forEach(tab => {
        tab.classList.remove('active');
    });
    // Desactivar botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar la seleccionada
    document.getElementById('tab-' + tabId).classList.add('active');
    // Activar botón
    event.currentTarget.classList.add('active');
    
    // Guardar preferencia
    localStorage.setItem('activeHomeTab', tabId);
}

// Recuperar pestaña activa
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('activeHomeTab');
    if (savedTab && document.getElementById('tab-' + savedTab)) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.getAttribute('onclick').includes(savedTab)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById('tab-' + savedTab).classList.add('active');
    }
});
</script>

</body>
</html>
