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
        /* Estilos específicos para la tabla de Títulos Formativos */
        .fp-table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .fp-table-premium thead tr {
            background: #1e293b;
            color: white;
        }

        .fp-table-premium th {
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }

        .fp-table-premium td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .fp-table-premium tr:hover td {
            background: #f8fafc;
        }

        .fp-table-premium .row-actions {
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

        /* Buscador Inferior Dinámico */
        .fp-search-section {
            margin-top: 30px;
            padding: 25px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .fp-search-title {
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .fp-checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .fp-checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #475569;
            cursor: pointer;
        }

        .fp-checkbox-item input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }

        .fp-actions {
            display: flex;
            gap: 12px;
        }

        .btn-fp-premium {
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        /* Buscador Inferior Dinámico con Estética Premium */
        .fp-search-section {
            margin-top: 30px;
            padding: 25px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .fp-search-title {
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none !important; /* Quitar subrayado del anterior style */
        }

        .fp-checkbox-group {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .fp-checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #475569;
            cursor: pointer;
            transition: color 0.2s;
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

        .fp-actions {
            display: flex;
            gap: 12px;
        }

        /* Botones unificados con el estilo global */
        .btn-fp-premium {
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-fp-search { 
            background: #334155; 
            color: white; 
        }
        .btn-fp-search:hover { 
            background: #1e293b; 
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .btn-fp-new { 
            background: var(--primary-color); 
            color: white; 
        }
        .btn-fp-new:hover { 
            background: var(--primary-hover); 
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px var(--primary-hover-shadow);
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
            <section class="fp-content-main">
                <div class="fp-header" style="background:var(--primary-color);">Títulos Formativos</div>
                
                <div class="fp-table-container">
                    <table class="fp-table-premium">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Título / Denominación</th>
                                <th style="width: 15%;">Código</th>
                                <th style="width: 15%;">Duración</th>
                                <th style="width: 10%;">Créditos</th>
                                <th style="width: 10%; border-right:none;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="titulosBody">
                            <?php foreach ($titulos as $t): ?>
                            <tr id="row-<?= htmlspecialchars($t['codigo']) ?>">
                                <td style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($t['titulo']) ?></td>
                                <td style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($t['codigo']) ?></td>
                                <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($t['duracion']) ?>h</td>
                                <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($t['creditos']) ?></td>
                                <td class="row-actions" style="border-right:none;">
                                    <a href="editar_titulo_formativo.php?id=<?= urlencode($t['codigo']) ?>" class="action-btn edit" title="Editar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <button class="action-btn delete" title="Eliminar" onclick="deleteTitulo('<?= htmlspecialchars($t['codigo']) ?>')">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Parámetros de Búsqueda -->
                <div class="fp-search-section">
                    <div class="fp-search-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        Parámetros de búsqueda
                    </div>
                    <div class="fp-checkbox-group">
                        <label class="fp-checkbox-item">
                            <input type="checkbox" checked> Formación Profesional
                        </label>
                        <label class="fp-checkbox-item">
                            <input type="checkbox"> Certificados de Profesionalidad
                        </label>
                    </div>
                    <div class="fp-actions">
                        <button class="btn-fp-premium btn-fp-search">Buscar Títulos</button>
                        <button class="btn-fp-premium btn-fp-new">Añadir Nuevo Título</button>
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
