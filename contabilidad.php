<?php
// contabilidad.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

// Definición de las secciones de contabilidad según la imagen solicitada
$sections = [
    [
        'title' => 'Facturas',
        'url' => 'facturas.php',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'
    ],
    [
        'title' => 'Justificación económica',
        'url' => 'justificacion_economica.php',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v4z"/><path d="M11.89 15.35c0 1-1.59 1.47-2.34.64l-.14-.14c-.75-.82-1.22-2.34-.14-2.34h1.01c.73 0 1.61.83 1.61 1.84zM13 12h-2V4h2v8z" opacity="0"/><path d="M12.5 15h-1v-4h1v1h1v1h-1v2zm-1-7V4h1v4h-1z" opacity="0"/><text x="10" y="16" fill="white" font-family="Inter" font-weight="bold" font-size="10">$</text></svg>'
    ],
    [
        'title' => 'Transferencias Excel a SEPA XML',
        'url' => 'transferencias_sepa.php',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/><path d="M9.41 12.59L8 14l-4-4 4-4 1.41 1.41L6.83 10l2.58 2.59zm5.18 0l2.58-2.59-2.58-2.59L16 6l4 4-4 4-1.41-1.41z"/></svg>'
    ]
];

// Re-refining Justice icon to be better since I used a text element in a previous thought but want a pure path if possible.
// Actually, I'll use a better SVG for Justice ($)
$sections[1]['icon'] = '<svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/><path d="M12.5 15.12V16h-1v-.88c-.62-.12-1.11-.47-1.38-.88l.68-.32c.16.27.5.53.86.64.44.13.91-.04.91-.49 0-.4-.36-.53-.99-.75-.63-.22-1.29-.46-1.29-1.21 0-.69.52-1.17 1.21-1.31V10h1v.89c.56.12.96.41 1.19.78l-.66.33c-.15-.26-.41-.45-.7-.52-.39-.1-.85.06-.85.46 0 .36.31.48.97.71s1.3.52 1.3 1.25c0 .76-.55 1.25-1.25 1.35z" fill="white"/></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidad - Grupo EFP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/contabilidad.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Área de Contabilidad</h1>
                <p>Gestión de facturación, justificaciones y transferencias bancarias</p>
            </div>
            <div class="page-actions">
                <a href="home.php" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver al Panel
                </a>
            </div>
        </header>

        <section class="contabilidad-dashboard">
            <div class="contabilidad-header-ribbon"></div>
            <div class="contabilidad-grid">
                <?php foreach ($sections as $section): ?>
                    <a href="<?= htmlspecialchars($section['url']) ?>" class="contabilidad-item">
                        <div class="contabilidad-icon-box">
                            <?= $section['icon'] ?>
                        </div>
                        <div class="contabilidad-text">
                            <?= htmlspecialchars($section['title']) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <div style="margin-top: 3rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            <p>Seleccione una opción para acceder a la herramienta correspondiente.</p>
        </div>
    </main>
</div>

</body>
</html>
