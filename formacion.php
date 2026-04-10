<?php
// formacion.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Define los módulos de la página Formación
$sections = [
    '' => [
        ['title' => 'Convocatorias', 'icon' => '<svg viewBox="0 0 24 24"><path d="M5 4h14v2H5zm0 10h4v6h6v-6h4l-7-7-7 7z"/></svg>', 'url' => 'convocatorias.php', 'color' => 'blue'],
        ['title' => 'Acciones Formativas', 'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>', 'url' => 'acciones_formativas.php?v=' . time(), 'color' => 'blue'],
        ['title' => 'Grupos', 'icon' => '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>', 'url' => 'grupos.php', 'color' => 'blue'],
        ['title' => 'Inscripciones', 'icon' => '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z"/></svg>', 'url' => 'inscripciones.php?v=' . time(), 'color' => 'blue'],
        ['title' => 'Tutorías', 'icon' => '<svg viewBox="0 0 24 24"><path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v4h8v-4h5c1.1 0 1.99-.9 1.99-2L23 5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z"/></svg>', 'url' => 'tutorias.php', 'color' => 'blue'],
        ['title' => 'Calendario de tutorías', 'icon' => '<svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>', 'url' => 'calendario_tutorias.php', 'color' => 'blue'],
        ['title' => 'Email masivo', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>', 'url' => 'email_masivo.php', 'color' => 'blue'],
        ['title' => 'Foros - Mensaje de bienvenida (provisional)', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>', 'url' => '#', 'onclick' => "var id=prompt('Introduce el ID del curso:'); if(id) { window.open('https://www.editeformacion.com/gestion/app/Controllers/Aulavirtual/Foros/Bienvenida.php?id='+id, '_blank'); } return false;", 'color' => 'blue', 'external' => false],
        ['title' => 'Informes', 'icon' => '<svg viewBox="0 0 24 24"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>', 'url' => 'informes.php', 'color' => 'blue'],
        ['title' => 'Envío de claves', 'icon' => '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg>', 'url' => 'envio_claves.php', 'color' => 'purple', 'external' => false],
    ],
    'Herramientas' => [
        ['title' => 'Editor de Evaluaciones de Moodle', 'icon' => '<svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>', 'url' => 'moodle_editor.php', 'color' => 'blue', 'external' => false],
        ['title' => 'Generador de glosarios de Moodle', 'icon' => '<svg viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>', 'url' => 'glosario_moodle.php', 'color' => 'blue', 'external' => false],
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formación - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css?v=<?= time() ?>">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Formación</h1>
                <p>Gestión académica y herramientas de Moodle</p>
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
                <?php if (!empty($sectionTitle)): ?>
                    <div class="home-section" style="margin-top: 2rem;">
                        <h2 class="home-section-title"><?= htmlspecialchars($sectionTitle) ?></h2>
                <?php else: ?>
                    <div class="home-section">
                <?php endif; ?>
                        <div class="tiles-grid">
                            <?php foreach ($tiles as $tile): ?>
                                <?php 
                                    $isExternal = !empty($tile['external']) && $tile['external'] === true;
                                    $target = $isExternal ? '_blank' : '_self';
                                ?>
                                <a href="<?= htmlspecialchars($tile['url']) ?>" 
                                   <?= isset($tile['onclick']) ? 'onclick="' . htmlspecialchars($tile['onclick']) . '"' : '' ?>
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
