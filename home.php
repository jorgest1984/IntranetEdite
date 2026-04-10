<?php
// home.php
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
if (has_permission([ROLE_ADMIN, ROLE_LECTURA])) {
    $stmt = $pdo->query("SELECT al.*, u.username FROM audit_log al LEFT JOIN usuarios u ON al.usuario_id = u.id ORDER BY al.fecha DESC LIMIT 5");
    $logs = $stmt->fetchAll();
}
// --------------------------------

// Define los módulos de la página de inicio
$sections = [
    'Inicio' => [
        ['title' => 'Grupo EFP', 'icon' => '<svg viewBox="0 0 24 24"><path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/></svg>', 'url' => 'edite_formacion.php', 'color' => 'red'],
        ['title' => 'Formación', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'formacion.php', 'color' => 'red'],
        ['title' => 'Formación Profesional', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'formacion_profesional.php', 'color' => 'red'],
        ['title' => 'Cursos Moodle', 'icon' => '<svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>', 'url' => 'cursos.php', 'color' => 'red'],
        ['title' => 'Formación Bonificada', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>', 'url' => 'formacion_bonificada.php', 'color' => 'red'], 
        ['title' => 'Formación Subvencionada', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Plan FIP Madrid', 'icon' => '<svg viewBox="0 0 24 24"><path d="M5 21V3h14v18l-7-3-7 3zm2-14.99V18l5-2.15L17 18V6.01H7z"/></svg>', 'url' => '#', 'color' => 'red'], 
        ['title' => 'Centros', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-8h8v8zm-2-6h-4v4h4v-4z"/></svg>', 'url' => 'empresas.php', 'color' => 'red'],
        ['title' => 'Tutorías', 'icon' => '<svg viewBox="0 0 24 24"><path d="M15 8c0-1.42-.5-2.73-1.33-3.76.42-.14.86-.24 1.33-.24 2.21 0 4 1.79 4 4s-1.79 4-4 4c-.43 0-.84-.09-1.23-.21-.03-.01-.06-.02-.1-.03A5.98 5.98 0 0 0 15 8zm1.66 5.11C18.1 13.88 21 15.6 21 17v2h2v-2c0-2.12-3.32-3.6-5.34-3.89zM9 4c2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4 1.79-4 4-4zm6 13c0-2.67-5.33-4-8-4s-8 1.33-8 4v2h16v-2z"/></svg>', 'url' => 'tutorias.php', 'color' => 'red'],
        ['title' => 'Buscador', 'icon' => '<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Envío de claves', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>', 'url' => 'envio_claves.php', 'color' => 'black'],
        ['title' => 'Usuarios', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>', 'url' => 'usuarios.php', 'color' => 'black'],
    ],
    'Áreas' => [
        ['title' => 'Contabilidad', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-6 2h4v4h-4V5zm-2 4H7V5h4v4zm-4 2h4v4H7v-4zm6 0h4v4h-4v-4zm-6 6h4v4H7v-4zm6 0h4v4h-4v-4z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'RRHH', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Ventas', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 1.95c-5.52 0-10 4.48-10 10s4.48 10 10 10h5v-2h-5c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8v1.43c0 .79-.71 1.57-1.5 1.57s-1.5-.78-1.5-1.57v-1.43c0-2.76-2.24-5-5-5s-5 2.24-5 5 2.24 5 5 5c1.38 0 2.64-.56 3.54-1.47.65.89 1.77 1.47 2.96 1.47 1.97 0 3.5-1.53 3.5-3.5v-1.43c0-5.52-4.48-10-10-10zm0 13c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Informática', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>', 'url' => '#', 'color' => 'red']
    ],
    'Sitios y aplicaciones' => [
        ['title' => 'Web Grupo EFP', 'icon' => '<svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>', 'url' => 'https://escueladeformacionprofesional.com/', 'color' => 'red', 'external' => true],
        ['title' => 'Webmail', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>', 'url' => 'https://webmail.grupoefp.es/SOGo/', 'color' => 'red', 'external' => true],
        ['title' => 'NAS', 'icon' => '<svg viewBox="0 0 24 24"><path d="M2 20h20v-4H2v4zm2-3h2v2H4v-2zM2 4v4h20V4H2zm4 3H4V5h2v2zm-4 7h20v-4H2v4zm2-3h2v2H4v-2z"/></svg>', 'url' => 'https://editeformacion.fr1.quickconnect.to/#/signin', 'color' => 'red', 'external' => true],
        ['title' => 'FTP NAS Backup', 'icon' => '<svg viewBox="0 0 24 24"><path d="M15 13l-4 4-4-4h3V4h2v9h3zm4-2v6H5v-6H3v8h18v-8h-2z"/></svg>', 'url' => '#', 'color' => 'red', 'external' => true],
        ['title' => 'Sistema de tickets', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 1.95c-5.52 0-10 4.48-10 10s4.48 10 10 10h5v-2h-5c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8v1.43c0 .79-.71 1.57-1.5 1.57s-1.5-.78-1.5-1.57v-1.43c0-2.76-2.24-5-5-5s-5 2.24-5 5 2.24 5 5 5c1.38 0 2.64-.56 3.54-1.47.65.89 1.77 1.47 2.96 1.47 1.97 0 3.5-1.53 3.5-3.5v-1.43c0-5.52-4.48-10-10-10zm0 13c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/></svg>', 'url' => '#', 'color' => 'red', 'external' => true],
        ['title' => 'Wiki', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/></svg>', 'url' => '#', 'color' => 'red', 'external' => true],
        ['title' => 'Aula Virtual', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>', 'url' => 'https://aulavirtual.grupoefp.es/', 'color' => 'red', 'external' => true]
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
        </header>

        <!-- SECCIÓN 1: BALDOSAS (HOME) -->
        <div class="home-container" style="padding: 0;">
            <?php foreach ($sections as $sectionTitle => $tiles): ?>
                <div class="home-section">
                    <h2 class="home-section-title"><?= htmlspecialchars($sectionTitle) ?></h2>
                    <div class="tiles-grid">
                        <?php foreach ($tiles as $tile): ?>
                            <?php 
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
                                <?php if ($isExternal): ?>
                                    <svg class="tile-external-icon" viewBox="0 0 24 24">
                                        <path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                                    </svg>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="section-separator"></div>

        <!-- SECCIÓN 2: ESTADÍSTICAS (DASHBOARD) -->
        <section class="stats-grid">
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
        </section>

        <!-- SECCIÓN 3: WIDGETS (DASHBOARD) -->
        <section class="dashboard-widgets">
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">Convocatorias Activas</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expediente</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>SEPE-2023-01</td>
                            <td>Desempleados</td>
                            <td><span class="badge badge-success">Activo</span></td>
                            <td>45%</td>
                        </tr>
                        <tr>
                            <td>FUN-2024-B1</td>
                            <td>Empresas</td>
                            <td><span class="badge badge-warning">Aprobada</span></td>
                            <td>0%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (has_permission([ROLE_ADMIN, ROLE_LECTURA])): ?>
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">Auditoría (ISO)</h2>
                </div>
                <?php if (empty($logs)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 1rem; font-size: 0.8rem;">Sin registros.</p>
                <?php else: ?>
                    <table class="data-table" style="font-size: 0.75rem;">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['username'] ?: 'Sis') ?></td>
                                <td><?= htmlspecialchars($log['accion']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
