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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Título Formativo - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .edit-container {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }

        .edit-header-black {
            background: #1e293b;
            color: white;
            padding: 10px 20px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .edit-header-blue {
            background: #0ea5e9;
            color: white;
            padding: 8px 20px;
            text-align: center;
            font-weight: 600;
            text-transform: lowercase;
            font-size: 0.9rem;
        }

        /* Tabs */
        .tabs-container {
            display: flex;
            gap: 5px;
            padding: 15px 20px 0 20px;
            background: #f8fafc;
        }

        .tab {
            padding: 8px 15px;
            background: #e2e8f0;
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab.active {
            background: white;
            color: var(--primary-color);
            border-color: var(--border-color);
            position: relative;
            z-index: 1;
            margin-bottom: -1px;
            padding-bottom: 9px;
        }

        /* Form Content */
        .form-content {
            padding: 30px;
            border-top: 1px solid var(--border-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .form-control-edit {
            width: 100%;
            max-width: 400px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control-edit:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .form-control-small { width: 80px; }
        .form-control-medium { width: 140px; }

        /* Rich Text Editor Mockup */
        .editor-mockup {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }

        .editor-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .tool-btn {
            background: none;
            border: 1px solid transparent;
            padding: 4px;
            border-radius: 3px;
            cursor: pointer;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tool-btn:hover { background: #e2e8f0; border-color: #cbd5e1; }

        .tool-btn svg { width: 16px; height: 16px; }

        .tool-separator {
            width: 1px;
            height: 20px;
            background: #cbd5e1;
            margin: 0 5px;
        }

        .editor-area {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: none;
            resize: vertical;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
        }

        .form-footer {
            padding: 20px 30px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-save {
            background: #0ea5e9;
            color: white;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save:hover { background: #0284c7; }

        .btn-cancel {
            background: white;
            color: #64748b;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-cancel:hover { background: #f1f5f9; color: #1e293b; }

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
                <a href="formacion_profesional.php" class="btn-cancel">
                    <svg style="margin-right: 8px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver al Listado
                </a>
            </div>
        </header>

        <section class="edit-container">
            <div class="edit-header-black">Títulos Formativos</div>
            <div class="edit-header-blue">formación profesional</div>

            <div class="tabs-container">
                <div class="tab active">Datos Generales</div>
                <div class="tab">Curso 1</div>
                <div class="tab">Curso 2</div>
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

                <div class="form-footer">
                    <a href="formacion_profesional.php" class="btn-cancel">Descartar</a>
                    <button type="submit" class="btn-save">Guardar Cambios</button>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
