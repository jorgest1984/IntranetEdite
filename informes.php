<?php
// informes.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Define los módulos de la página de Informes basados en la captura
$informes = [
    ['title' => 'Listado de DS15 ampliado del plan',  
     'icon' => '<svg viewBox="0 0 24 24"><path d="M7.75 3c-1.52 0-2.75 1.23-2.75 2.75s1.23 2.75 2.75 2.75 2.75-1.23 2.75-2.75S9.27 3 7.75 3zm0 4c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zm8.5 8.5c-1.52 0-2.75 1.23-2.75 2.75s1.23 2.75 2.75 2.75 2.75-1.23 2.75-2.75-1.23-2.75-2.75-2.75zm0 4c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zM4 18.94L18.94 4 20 5.06 5.06 20 4 18.94z"/></svg>', 
     'url' => 'informe_ds15_ampliado.php', 'color' => 'blue'],
     
    ['title' => 'Informe de ejecución de plan', 
     'icon' => '<svg viewBox="0 0 24 24"><path d="M7.75 3c-1.52 0-2.75 1.23-2.75 2.75s1.23 2.75 2.75 2.75 2.75-1.23 2.75-2.75S9.27 3 7.75 3zm0 4c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zm8.5 8.5c-1.52 0-2.75 1.23-2.75 2.75s1.23 2.75 2.75 2.75 2.75-1.23 2.75-2.75-1.23-2.75-2.75-2.75zm0 4c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zM4 18.94L18.94 4 20 5.06 5.06 20 4 18.94z"/></svg>', 
     'url' => 'informe_ejecucion_plan.php', 'color' => 'blue'],
     
    ['title' => 'Informe de grupos', 
     'icon' => '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>', 
     'url' => 'informe_grupos.php', 'color' => 'blue'],
     
    ['title' => 'Informe de comunicaciones', 
     'icon' => '<svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>', 
     'url' => 'informe_comunicaciones.php', 'color' => 'blue'],
     
    ['title' => 'Cambios de estado', 
     'icon' => '<svg viewBox="0 0 24 24"><path d="M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"/></svg>', 
     'url' => 'informe_cambios_estado.php', 'color' => 'blue']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css?v=<?= time() ?>">
    <style>
        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .tile-blue {
            background-color: #0284c7; /* Azul a juego con la Intranet */
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.2);
        }
        
        .tile-blue:hover {
            background-color: #0369a1;
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);
        }

        /* Ajustes específicos para igualar visualmente los informes de la captura */
        .tile {
            padding: 2.5rem 1.5rem;
        }
        .tile-icon svg {
            width: 42px;
            height: 42px;
            fill: #ffffff;
            opacity: 0.9;
        }
        .tile-title {
            font-size: 1.05rem;
            font-weight: 500;
            color: #ffffff;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Informes Generales</h1>
                <p>Generación y consulta de listados de información del sistema</p>
            </div>
            <div class="page-actions">
                <a href="formacion_profesional.php" class="btn btn-primary" style="background:#2563eb; color:#fff; padding:6px 12px; text-decoration:none; border-radius:4px; font-weight:600;">
                    Volver a Formación Profesional
                </a>
            </div>
        </header>

        <div class="home-container" style="padding-top: 2rem;">
            <div class="home-section">
                <div class="tiles-grid">
                    <?php foreach ($informes as $tile): ?>
                        <a href="<?= htmlspecialchars($tile['url']) ?>" class="tile tile-<?= htmlspecialchars($tile['color']) ?>">
                            <div class="tile-icon">
                                <?= $tile['icon'] ?>
                            </div>
                            <div class="tile-title">
                                <?= htmlspecialchars($tile['title']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
