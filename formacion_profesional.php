<?php
// formacion_profesional.php
require_once 'includes/auth.php';

// Solo administradores y tutores pueden acceder a formación profesional
if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

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
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formación Profesional - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Search Card Premium */
        .search-card-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }

        .card-header-premium {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 108, 228, 0.15);
        }

        .card-header-premium h2 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: white;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            padding: 2rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom.span-12 { grid-column: span 12; }

        .form-group-custom label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Results Card Layout */
        .results-section-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .results-header-premium {
            background: rgba(0, 108, 228, 0.03);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-header-premium h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Table */
        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-premium th {
            background: rgba(0, 108, 228, 0.04);
            border-bottom: 2px solid var(--border-color);
            padding: 1rem 1.5rem;
            text-align: left;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-premium td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        .table-premium tr:last-child td {
            border-bottom: none;
        }

        .table-premium tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            transition: all 0.2s;
            text-decoration: none;
            padding: 0;
            cursor: pointer;
            box-sizing: border-box;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
            border-color: var(--primary-color);
        }

        .fp-checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            color: var(--text-color);
            cursor: pointer;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .fp-checkbox-item:hover {
            color: var(--primary-color);
        }
        .fp-checkbox-item input {
            width: 17px;
            height: 17px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }
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
            <?php include 'includes/fp_sidebar.php'; ?>

            <!-- Contenido Principal -->
            <section class="fp-content-main" style="padding: 2rem;">
                <div class="results-section-premium">
                    <div class="results-header-premium">
                        <h2>Títulos Formativos</h2>
                    </div>
                    <div class="table-responsive" style="background: transparent; border-radius: 0 0 16px 16px; box-shadow: none; border-bottom: none;">
                        <table class="table-premium">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Título / Denominación</th>
                                    <th style="width: 15%;">Código</th>
                                    <th style="width: 15%;">Duración</th>
                                    <th style="width: 10%;">Créditos</th>
                                    <th style="width: 10%; border-right:none; text-align: right; padding-right: 2rem;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="titulosBody">
                                <?php foreach ($titulos as $t): ?>
                                <tr id="row-<?= htmlspecialchars($t['codigo']) ?>">
                                    <td style="font-weight: 700; color: var(--text-color);"><?= htmlspecialchars($t['titulo']) ?></td>
                                    <td style="font-family: monospace; font-weight: 600; color: var(--primary-color);"><?= htmlspecialchars($t['codigo']) ?></td>
                                    <td style="font-weight: 600; color: var(--text-muted);"><?= htmlspecialchars($t['duracion']) ?>h</td>
                                    <td style="font-weight: 600; color: var(--text-muted);"><?= htmlspecialchars($t['creditos']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: flex-end; padding-right: 1.5rem;">
                                            <a href="editar_titulo_formativo.php?id=<?= urlencode($t['codigo']) ?>" class="btn-action" title="Editar">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <button class="btn-action" style="color: #ef4444;" title="Eliminar" onclick="deleteTitulo('<?= htmlspecialchars($t['codigo']) ?>')">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Parámetros de Búsqueda -->
                <div class="search-card-premium" style="margin-top: 30px;">
                    <div class="card-header-premium">
                        <h2>Parámetros de búsqueda</h2>
                    </div>
                    <div class="form-grid" style="padding: 1.5rem 2rem;">
                        <div class="form-group-custom span-12" style="margin-bottom: 5px;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Seleccionar Categorías</label>
                        </div>
                        <div class="form-group-custom span-12" style="display: flex; flex-direction: row; gap: 25px; flex-wrap: wrap;">
                            <label class="fp-checkbox-item">
                                <input type="checkbox" checked> Formación Profesional
                            </label>
                            <label class="fp-checkbox-item">
                                <input type="checkbox"> Certificados de Profesionalidad
                            </label>
                        </div>
                        <div class="span-12" style="display: flex; gap: 12px; margin-top: 15px;">
                            <button class="btn btn-primary" style="padding: 0.65rem 2rem;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                Buscar Títulos
                            </button>
                            <button class="btn btn-glass" style="border: 1px solid var(--border-color); font-weight: 700;" onclick="window.location.href='nuevo_titulo_formativo.php'">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Añadir Nuevo Título
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
    function deleteTitulo(codigo) {
        if (confirm('¿Estás seguro de que deseas eliminar el título ' + codigo + '?')) {
            const row = document.getElementById('row-' + codigo);
            if (row) {
                // Animación de salida "Premium"
                row.style.transition = 'all 0.4s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                
                setTimeout(() => {
                    row.remove();
                    // Aquí se añadiría el AJAX para eliminar de la DB real
                    console.log('Título ' + codigo + ' eliminado correctamente.');
                }, 400);
            }
        }
    }
</script>

</body>
</html>
