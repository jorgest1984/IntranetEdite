<?php
// formacion_subvencionada.php
require_once 'includes/auth.php';

// Definición de las secciones según la imagen solicitada (Reutilizando estructura de bonificada)
$sections = [
    [
        'title' => 'Empresas',
        'url' => 'buscar_empresas.php?context=subvencionada',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M2 22h20V10L12 2 2 10v12zm2-2v-9l8-6.4L20 11v9H4zM7 15h2v2H7v-2zm0-4h2v2H7v-2zm4 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2z"/></svg>'
    ],
    [
        'title' => 'Alumnos',
        'url' => 'buscar_alumnos.php?context=subvencionada',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>'
    ],
    [
        'title' => 'Acciones Formativas',
        'url' => 'acciones_formativas.php?context=subvencionada',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formación Subvencionada - Grupo EFP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/formacion_bonificada.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Formación Subvencionada</h1>
                <p>Gestión de expedientes, alumnos y acciones formativas de planes públicos</p>
            </div>
            <div class="page-actions">
                <a href="home.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver al Panel
                </a>
            </div>
        </header>

        <section class="bonificada-dashboard">
            <div class="bonificada-header-ribbon"></div>
            <div class="bonificada-grid">
                <?php foreach ($sections as $section): ?>
                    <a href="<?= htmlspecialchars($section['url']) ?>" class="bonificada-item">
                        <div class="bonificada-icon-box">
                            <?= $section['icon'] ?>
                        </div>
                        <div class="bonificada-text">
                            <?= htmlspecialchars($section['title']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <div style="margin-top: 3rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            <p>Seleccione una categoría para acceder a la gestión correspondiente de formación subvencionada.</p>
        </div>
    </main>
</div>

</body>
</html>
