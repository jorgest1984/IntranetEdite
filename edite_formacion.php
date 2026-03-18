<?php
// edite_formacion.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Define los módulos de la página Edite Formación
$sections = [
    'Bienvenido/a a Edite Formación' => [
        ['title' => 'Manual de bienvenida', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 14H8v-2h5v2zm3-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>', 'url' => 'docs/manual_bienvenida.pdf', 'color' => 'red', 'external' => true],
        ['title' => 'Código de conducta', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 14H8v-2h5v2zm3-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>', 'url' => 'docs/codigo_conducta.pdf', 'color' => 'red', 'external' => true],
        ['title' => 'Medidas de conciliación', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 14H8v-2h5v2zm3-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>', 'url' => 'docs/medidas_conciliacion.pdf', 'color' => 'red', 'external' => true],
        ['title' => 'Política de calidad', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 14H8v-2h5v2zm3-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>', 'url' => 'docs/politica_calidad.pdf', 'color' => 'red', 'external' => true],
    ],
    'Ayúdanos a mejorar' => [
        ['title' => 'Buzón de sugerencias', 'icon' => '<svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6A4.997 4.997 0 0 1 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.63-.8 3.16-2.15 4.1z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Política de igualdad', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 10H5v-2h14v2zm0 6H5v-2h14v2z"/></svg>', 'url' => '#', 'color' => 'red'],
    ],
    'Otra información de interés' => [
        ['title' => 'Teléfonos', 'icon' => '<svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Redes WiFi', 'icon' => '<svg viewBox="0 0 24 24"><path d="M1 9l2 2c5.33-5.33 13.67-5.33 19 0l2-2c-6.67-6.67-17.33-6.67-24 0zm8 8l3 3 3-3c-1.66-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Calendario laboral', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>', 'url' => '#', 'color' => 'red'],
        ['title' => 'Modelo solicitud vacaciones', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>', 'url' => '#', 'color' => 'red', 'external' => true],
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edite Formación - <?= APP_NAME ?></title>
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
                <h1>Edite Formación</h1>
                <p>Información corporativa y de interés común</p>
            </div>
            <div class="page-actions">
                <a href="home.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver al Inicio
                </a>
            </div>
        </header>

        <div class="home-container" style="padding-top: 0;">
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
    </main>
</div>

</body>
</html>
