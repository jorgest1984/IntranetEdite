<?php
// formacion_profesional.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Títulos formativos de ejemplo (basados en la imagen)
$titulos = [
    ['titulo' => ' Técnico en Emergencias Sanitarias', 'codigo' => 'CINE-3', 'duracion' => 2000, 'creditos' => 0],
    ['titulo' => ' Técnico Superior en Educación Infantil', 'codigo' => 'CINE-5b', 'duracion' => 2000, 'creditos' => 100],
    ['titulo' => 'AFDA0109 Guía por itinerarios en bicicleta', 'codigo' => 'AFDA0109', 'duracion' => 420, 'creditos' => 0],
    ['titulo' => 'AFDA0110 Acondicionamiento físico en grupo con soporte musical', 'codigo' => 'AFDA0110', 'duracion' => 590, 'creditos' => 0],
    ['titulo' => 'AFDA0112 Guía por barrancos secos o acuáticos', 'codigo' => 'AFDA0112', 'duracion' => 660, 'creditos' => 0],
    ['titulo' => 'AFDA0209 Guía por itinerarios ecuestres en el medio natural', 'codigo' => 'AFDA0209', 'duracion' => 580, 'creditos' => 0],
    ['titulo' => 'AFDA0210 Acondicionamiento físico en sala de entrenamiento polivalente', 'codigo' => 'AFDA0210', 'duracion' => 590, 'creditos' => 0],
    ['titulo' => 'AFDA0211 Animación físico-deportiva y recreativa', 'codigo' => 'AFDA0211', 'duracion' => 590, 'creditos' => 0],
    ['titulo' => 'AFDA0310 Actividades de Natación', 'codigo' => 'AFDA0310', 'duracion' => 750, 'creditos' => 0],
    ['titulo' => 'AFDA0311 Instrucción de Yoga', 'codigo' => 'AFDA0311', 'duracion' => 550, 'creditos' => 0],
    ['titulo' => 'AFDP0109 Socorrismo en instalaciones acuáticas', 'codigo' => 'AFDP0109', 'duracion' => 370, 'creditos' => 0],
    ['titulo' => 'SSCS0108 Atención Sociosanitaria a Personas en el Domicilio', 'codigo' => 'SSCS0108', 'duracion' => 600, 'creditos' => 0],
    ['titulo' => 'SSCS0208 Atención Sociosanitaria a Personas Dependientes en Instituciones Sociales', 'codigo' => 'SSCS0208', 'duracion' => 450, 'creditos' => 0]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formación Profesional - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .fp-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        /* Sidebar Específico */
        .fp-sidebar {
            background: #f1f5f9;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 10px;
            height: fit-content;
        }

        .fp-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .fp-menu li {
            margin-bottom: 5px;
        }

        .fp-menu a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            text-decoration: none;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .fp-menu a:hover {
            background: #e2e8f0;
            color: var(--primary-color);
        }

        .fp-menu a.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
        }

        .fp-menu-icon {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            opacity: 0.7;
        }

        /* Tabla de Títulos */
        .fp-content {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .fp-header {
            background: #334155;
            color: white;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
        }

        .fp-table-container {
            overflow-x: auto;
        }

        .fp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .fp-table tr.table-subheader {
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
        }

        .fp-table th {
            text-align: left;
            padding: 10px 15px;
            background: #0ea5e9;
            color: white;
            font-weight: 600;
        }

        .fp-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        .fp-table tr:hover td {
            background: #f8fcfd;
        }

        .row-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .action-btn:hover { background: #f1f5f9; }
        .action-btn.edit { color: #f59e0b; }
        .action-btn.delete { color: #ef4444; }

        /* Buscador Inferior */
        .fp-search-section {
            padding: 20px;
            background: #fff;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .fp-search-title {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-decoration: underline;
        }

        .fp-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .fp-checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .fp-actions {
            display: flex;
            gap: 10px;
        }

        .btn-fp {
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-fp-search { background: #0ea5e9; color: white; }
        .btn-fp-search:hover { background: #0284c7; }
        .btn-fp-new { background: #1e293b; color: white; }
        .btn-fp-new:hover { background: #0f172a; }

    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Formación Profesional</h1>
                <p>Gestión de Títulos Formativos y Certificados</p>
            </div>
        </header>

        <div class="fp-layout">
            <!-- Sidebar Izquierdo -->
            <aside class="fp-sidebar">
                <ul class="fp-menu">
                    <li><a href="#" class="active">Títulos formativos</a></li>
                    <li><a href="#">Asignaturas / acciones "abuela"</a></li>
                    <li><a href="#">Contenidos acciones / acciones "madre"</a></li>
                    <li><a href="#">Acc. Formativas / Acc. "hija"</a></li>
                    <li><a href="#">Grupos</a></li>
                    <li><a href="#">Inscripciones</a></li>
                    <li><a href="#">Alumnos</a></li>
                </ul>
            </aside>

            <!-- Contenido Principal -->
            <section class="fp-content">
                <div class="fp-header">Títulos Formativos</div>
                
                <div class="fp-table-container">
                    <table class="fp-table">
                        <thead>
                            <tr class="table-subheader">
                                <td colspan="5" style="text-align: center; color: #64748b; font-size: 0.7rem;">Listado</td>
                            </tr>
                            <tr>
                                <th style="width: 50%;">Título</th>
                                <th style="width: 15%;">Código</th>
                                <th style="width: 15%;">Duración</th>
                                <th style="width: 10%;">Créditos</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($titulos as $t): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($t['titulo']) ?></td>
                                <td><?= htmlspecialchars($t['codigo']) ?></td>
                                <td><?= htmlspecialchars($t['duracion']) ?></td>
                                <td><?= htmlspecialchars($t['creditos']) ?></td>
                                <td class="row-actions">
                                    <button class="action-btn edit" title="Editar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                    <button class="action-btn delete" title="Eliminar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Parámetros de Búsqueda -->
                <div class="fp-search-section">
                    <div class="fp-search-title">Parámetros de búsqueda</div>
                    <div class="fp-checkbox-group">
                        <label class="fp-checkbox-item">
                            <input type="checkbox"> formación profesional
                        </label>
                        <label class="fp-checkbox-item">
                            <input type="checkbox"> certificados de profesionalidad
                        </label>
                    </div>
                    <div class="fp-actions">
                        <button class="btn-fp btn-fp-search">Buscar</button>
                        <button class="btn-fp btn-fp-new">Nuevo</button>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

</body>
</html>
