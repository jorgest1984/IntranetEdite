<?php
// editar_titulo_formativo.php
require_once 'includes/auth.php'; // Verifica login y permisos

// Simulación de datos cargados (normalmente vendrían de $_GET['id'])
$id = $_GET['id'] ?? 1;
$titulo = [
    'titulo' => ' Técnico en Emergencias Sanitarias',
    'codigo' => 'CINE-3',
    'duracion' => 2000,
    'creditos' => 0,
    'precio' => 0,
    'familia' => 'Sanidad',
    'area' => 'Emergencias',
    'mostrar_web' => true,
    'prioridad' => 1,
    'resumen' => 'Texto de resumen para la web...',
    'nivel' => '3',
    'cualificacion' => 'Cualificación de referencia...'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Título Formativo - <?= APP_NAME ?></title>
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
            margin-top: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }

        .card-header-premium {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 1.25rem 2rem;
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

        /* Tabs FP Style */
        .tabs-header-fp {
            display: flex;
            gap: 8px;
            margin-bottom: 0px;
        }

        .tab-fp-btn {
            padding: 10px 24px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tab-fp-btn:hover {
            color: var(--primary-color);
            border-color: var(--card-hover-border);
            background: rgba(0, 108, 228, 0.02);
            transform: translateY(-1px);
        }

        .tab-fp-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.2);
        }

        /* Form Content */
        .form-content {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control-edit {
            width: 100%;
            max-width: 500px;
            padding: 0.65rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--input-bg);
            color: var(--text-color);
            outline: none;
            transition: all 0.25s ease;
            box-sizing: border-box;
        }

        .form-control-edit:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.15);
            background-color: var(--input-bg);
        }

        .form-control-small { width: 100px; }
        .form-control-medium { width: 200px; }

        /* Rich Text Editor Mockup */
        .editor-mockup {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            max-width: 700px;
            background: var(--input-bg);
        }

        .editor-toolbar {
            background: rgba(0, 108, 228, 0.02);
            border-bottom: 1px solid var(--border-color);
            padding: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .tool-btn {
            background: none;
            border: 1px solid transparent;
            padding: 5px;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            width: 28px;
            height: 28px;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        .tool-btn:hover { 
            background: rgba(0, 108, 228, 0.08); 
            color: var(--primary-color);
        }

        .tool-btn svg { width: 15px; height: 15px; }

        .tool-separator {
            width: 1px;
            height: 18px;
            background: var(--border-color);
            margin: 0 4px;
        }

        .editor-area {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: none;
            resize: vertical;
            font-family: inherit;
            font-size: 0.92rem;
            outline: none;
            background: transparent;
            color: var(--text-color);
            box-sizing: border-box;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Gestión de Títulos</h1>
                <p>Edición de parámetros académicos y visibilidad web</p>
            </div>
            <div class="page-actions">
                <a href="formacion_profesional.php" class="btn btn-glass" style="border: 1px solid var(--border-color); font-weight: 700;">
                    <svg style="margin-right: 8px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver al Listado
                </a>
            </div>
        </header>

        <section class="search-card-premium">
            <div class="card-header-premium">
                <h2>Editar Título Formativo: <?= htmlspecialchars($titulo['titulo']) ?></h2>
            </div>

            <div class="tabs-header-fp" style="display: flex; gap: 8px; padding: 1.5rem 2rem 0.5rem 2rem; border-bottom: 1px solid var(--border-color);">
                <button type="button" class="tab-fp-btn active">Datos Generales</button>
                <button type="button" class="tab-fp-btn">Curso 1</button>
                <button type="button" class="tab-fp-btn">Curso 2</button>
            </div>

            <form class="form-content">
                <!-- Título -->
                <div class="form-grid">
                    <label class="form-label">Título</label>
                    <input type="text" class="form-control-edit" value="<?= htmlspecialchars($titulo['titulo']) ?>">
                </div>

                <!-- Código -->
                <div class="form-grid">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control-edit form-control-medium" value="<?= htmlspecialchars($titulo['codigo']) ?>">
                </div>

                <!-- Duración -->
                <div class="form-grid">
                    <label class="form-label">Duración</label>
                    <input type="number" class="form-control-edit form-control-small" value="<?= htmlspecialchars($titulo['duracion']) ?>">
                </div>

                <!-- Créditos (ECTS) -->
                <div class="form-grid">
                    <label class="form-label">Créditos (ECTS)</label>
                    <input type="number" class="form-control-edit form-control-small" value="<?= htmlspecialchars($titulo['creditos']) ?>">
                </div>

                <!-- Precio de venta -->
                <div class="form-grid">
                    <label class="form-label">Precio de venta</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" class="form-control-edit form-control-small" value="<?= htmlspecialchars($titulo['precio']) ?>"> €
                    </div>
                </div>

                <!-- Familia Profesional -->
                <div class="form-grid">
                    <label class="form-label">Familia Profesional</label>
                    <select class="form-control-edit">
                        <option value="Sanidad" selected>Sanidad</option>
                        <option value="Informatica">Informática y Comunicaciones</option>
                        <option value="Administracion">Administración y Gestión</option>
                    </select>
                </div>

                <!-- Área Profesional -->
                <div class="form-grid">
                    <label class="form-label">Área Profesional</label>
                    <input type="text" class="form-control-edit" style="max-width: 600px;" value="<?= htmlspecialchars($titulo['area']) ?>">
                </div>

                <!-- Mostrar en la web de EFP -->
                <div class="form-grid">
                    <label class="form-label">Mostrar en la web de EFP</label>
                    <input type="checkbox" <?= $titulo['mostrar_web'] ? 'checked' : '' ?> style="width: 20px; height: 20px; cursor: pointer;">
                </div>

                <!-- Prioridad en la web de EFP -->
                <div class="form-grid">
                    <label class="form-label">Prioridad en la web de EFP:</label>
                    <input type="number" class="form-control-edit form-control-small" value="<?= htmlspecialchars($titulo['prioridad']) ?>">
                </div>

                <!-- Texto resumen en la web de EFP -->
                <div class="form-grid" style="align-items: flex-start;">
                    <label class="form-label">Texto resumen en la web de EFP</label>
                    <div class="editor-mockup">
                        <div class="editor-toolbar">
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <div class="tool-separator"></div>
                            <button type="button" class="tool-btn" style="font-weight: bold;">B</button>
                            <button type="button" class="tool-btn" style="font-style: italic;">I</button>
                            <button type="button" class="tool-btn">X²</button>
                            <div class="tool-separator"></div>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></button>
                        </div>
                        <textarea class="editor-area"><?= htmlspecialchars($titulo['resumen']) ?></textarea>
                    </div>
                </div>

                <!-- Nivel de cualificación profesional -->
                <div class="form-grid">
                    <label class="form-label">Nivel de cualificación profesional</label>
                    <input type="text" class="form-control-edit form-control-small" value="<?= htmlspecialchars($titulo['nivel']) ?>">
                </div>

                <!-- Cualificación profesional de referencia -->
                <div class="form-grid" style="align-items: flex-start;">
                    <label class="form-label">Cualificación profesional de referencia</label>
                    <div class="editor-mockup">
                        <div class="editor-toolbar">
                            <button type="button" class="tool-btn" style="font-weight: bold;">B</button>
                            <button type="button" class="tool-btn" style="font-style: italic;">I</button>
                            <div class="tool-separator"></div>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
                        </div>
                        <textarea class="editor-area"><?= htmlspecialchars($titulo['cualificacion']) ?></textarea>
                    </div>
                </div>

                <div class="form-footer" style="padding: 1.5rem 2rem; background: rgba(0, 108, 228, 0.02); border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px;">
                    <a href="formacion_profesional.php" class="btn btn-glass" style="border: 1px solid var(--border-color); font-weight: 700; padding: 0.65rem 2rem;">Descartar</a>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 2.5rem;">Guardar Cambios</button>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
