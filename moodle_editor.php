<?php
// moodle_editor.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'moodle_editor.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Evaluaciones de Moodle - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #1e40af;
            --bg-light: #f8fafc;
            --border-color: #cbd5e1;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --gift-bg: #f1f5f9;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: var(--text-dark); margin: 0; }
        .main-content { padding: 0; display: flex; flex-direction: column; height: 100vh; }

        /* Toolbar Superior */
        .editor-toolbar {
            background: #fff;
            padding: 10px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .editor-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-actions { display: flex; gap: 10px; }

        .btn-tool {
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-dark);
            transition: all 0.2s;
        }
        .btn-tool:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-tool.primary { background: var(--primary-blue); color: #fff; border-color: var(--secondary-blue); }
        .btn-tool.primary:hover { background: var(--secondary-blue); }

        /* Layout con Paneles */
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            flex: 1;
            overflow: hidden;
        }

        .editor-viewport {
            padding: 2.5rem;
            overflow-y: auto;
            background: #fff;
        }

        .side-viewport {
            background: #f8fafc;
            border-left: 1px solid var(--border-color);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
        }

        /* Bloques de Contenido */
        .content-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title svg { width: 20px; color: var(--primary-blue); }

        .info-grid {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 10px;
            font-size: 0.95rem;
        }
        .info-label { font-weight: 700; color: var(--text-muted); }

        .list-style {
            padding: 0;
            list-style: none;
        }
        .list-style li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            line-height: 1.6;
            color: var(--text-dark);
        }
        .list-style li::before {
            content: "→";
            position: absolute;
            left: 0;
            color: var(--primary-blue);
            font-weight: 800;
        }

        .highlight-blue { color: var(--primary-blue); font-weight: 600; }
        .highlight-red { color: #ef4444; font-weight: 600; }

        /* Notas y GIFT */
        .side-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: block;
        }

        .textarea-custom {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            font-family: 'Consolas', monospace;
            font-size: 0.85rem;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }
        .textarea-custom:focus { border-color: var(--primary-blue); }

        .gift-area {
            background: #1e293b;
            color: #f8fafc;
            border: none;
            height: 250px;
        }

        .notes-area {
            background: #fff;
            height: 350px;
        }

    </style>
</head>
<body>

    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1;">
            
            <header class="editor-toolbar">
                <div class="editor-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Editor de Evaluaciones
                </div>
                <div class="toolbar-actions">
                    <button class="btn-tool primary">+ Nueva pregunta</button>
                    <button class="btn-tool">📄 Nuevo test</button>
                    <button class="btn-tool">📂 Abrir</button>
                    <button class="btn-tool">💾 Guardar</button>
                    <button class="btn-tool">Download Descargar GIFT</button>
                </div>
            </header>

            <div class="editor-container">
                <div class="editor-viewport">
                    
                    <div class="content-card">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            Información del cuestionario
                        </div>
                        <div class="info-grid">
                            <span class="info-label">Curso:</span> <span class="highlight-blue">Seleccione un curso...</span>
                            <span class="info-label">Título:</span> <span>Evaluación General</span>
                            <span class="info-label">Prefijo:</span> <span style="font-family: monospace;">EV0-</span>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-title">Instrucciones de uso</div>
                        <ul class="list-style">
                            <li>Clic en el <span class="highlight-blue">título del curso</span> para modificarlo.</li>
                            <li>Añada preguntas usando el botón superior.</li>
                            <li>Tipos disponibles: <span class="highlight-blue">Ensayo, Verdadero/Falso, Respuesta corta, Relacionar columnas y Numérico.</span></li>
                            <li>Utilice <span class="highlight-red">-></span> para relacionar columnas (Ej: España -> Madrid).</li>
                            <li>Guarde su trabajo periódicamente pulsando en <span class="highlight-blue">Guardar</span>.</li>
                        </ul>
                    </div>

                    <div class="content-card" style="border-left: 4px solid #ef4444;">
                        <div class="card-title" style="color: #ef4444;">Importante</div>
                        <ul class="list-style" style="font-size: 0.8rem;">
                            <li>Evite prefijos como <span class="highlight-red">a), 1.-</span> ya que Moodle lo gestiona automáticamente.</li>
                            <li>La puntuación máxima por pregunta es de <span class="highlight-blue">1.0</span>.</li>
                            <li>Puede usar etiquetas <span class="highlight-blue">&lt;strong&gt;</span> para negritas.</li>
                        </ul>
                    </div>

                </div>

                <div class="side-viewport">
                    <div>
                        <span class="side-label">Campo GIFT (Previsualización)</span>
                        <textarea class="textarea-custom gift-area" readonly placeholder="// El código GIFT aparecerá aquí..."></textarea>
                        <p style="font-size: 0.7rem; color: var(--text-muted); margin-top: 5px;">Este código es el que se importará en el banco de preguntas de Moodle.</p>
                    </div>

                    <div>
                        <span class="side-label">Bloc de Notas</span>
                        <textarea class="textarea-custom notes-area" placeholder="Escriba aquí sus notas, textos para copiar/pegar, etc..."></textarea>
                    </div>
                </div>
            </div>

        </main>
    </div>

</body>
</html>
