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
    <style>
        :root {
            --dark-bg: #1a1a1a;
            --panel-bg: #262626;
            --text-main: #e5e5e5;
            --text-dim: #a3a3a3;
            --accent-gold: #d4af37;
            --accent-red: #ef4444;
            --border-color: #404040;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Bar */
        .top-bar {
            background-color: #333;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            z-index: 100;
        }

        .brand {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .button-group {
            display: flex;
            gap: 8px;
        }

        .btn-moodle {
            background: #4a4a4a;
            color: #fff;
            border: 1px solid #555;
            padding: 6px 12px;
            font-size: 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-moodle:hover {
            background: #5a5a5a;
        }
        .btn-moodle.primary {
            background: #d4af37;
            color: #000;
            border-color: #b8962e;
            font-weight: 700;
        }
        .btn-moodle.primary:hover {
            background: #e5be42;
        }

        /* Layout */
        .editor-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .main-editor {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
        }

        .notes-panel {
            width: 350px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Content Blocks */
        section {
            margin-bottom: 3rem;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .info-block p {
            margin: 8px 0;
            font-size: 0.95rem;
        }
        .info-label {
            font-weight: 700;
            color: #fff;
        }

        hr {
            border: 0;
            border-top: 1px solid var(--border-color);
            margin: 2rem 0;
        }

        .bullet-list {
            list-style: none;
            padding: 0;
        }
        .bullet-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--text-dim);
        }
        .bullet-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--accent-gold);
        }

        .sub-list {
            margin-top: 10px;
            padding-left: 20px;
        }

        .important-text {
            color: var(--accent-red);
            font-weight: 500;
        }

        .highlight-gold {
            color: var(--accent-gold);
        }

        /* Notes Area */
        .notes-header {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .notes-textarea {
            flex: 1;
            background-color: #000;
            color: #fff;
            border: 1px solid var(--border-color);
            padding: 15px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: none;
            outline: none;
        }
        .notes-textarea:focus {
            border-color: var(--accent-gold);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .notes-panel { width: 250px; }
        }
    </style>
</head>
<body>

    <header class="top-bar">
        <div class="brand">Editor de Evaluaciones de Moodle</div>
        <div class="button-group">
            <button class="btn-moodle primary">+ Nueva pregunta</button>
            <button class="btn-moodle">📄 Nuevo test</button>
            <button class="btn-moodle">📂 Abrir</button>
            <button class="btn-moodle">💾 Guardar</button>
            <button class="btn-moodle"> Filtrar HTML</button>
            <button class="btn-moodle">📥 Descargar GIFT</button>
        </div>
    </header>

    <div class="editor-layout">
        <main class="main-editor">
            
            <section class="info-block">
                <h2>Información del cuestionario</h2>
                <p><span class="info-label">Curso:</span> Curso</p>
                <p><span class="info-label">Título:</span> Evaluación</p>
                <p><span class="info-label">Prefijo del código de pregunta:</span> <span style="color: #666;">EV0-</span></p>
            </section>

            <hr>

            <section>
                <h2 style="font-size: 1.5rem;">Instrucciones</h2>
                <ul class="bullet-list">
                    <li>Clicar en el <span class="highlight-gold">título del curso</span> para modificarlo.</li>
                    <li>Clicar en el <span class="highlight-gold">título del test</span> para modificarlo.</li>
                    <li>Clicar en el <span class="highlight-gold">prefijo de pregunta</span> para modificarlo. Este prefijo se utiliza para generar los códigos de las nuevas preguntas. Ejemplo: para una evaluación inicial, si se introduce <span class="important-text">EVi-</span>, las preguntas tendrán los códigos <span class="important-text">EVi-1, EVi-2</span>, etc. Al importar en el banco de preguntas de Moodle se podrán identificar por este código.</li>
                    <li>Se puede usar el área de <span class="highlight-gold">Notas</span> como ayuda, por ejemplo para copiar el texto de las preguntas desde un archivo de Word.</li>
                    <li>Clicar en el <span class="highlight-gold">área vacía del encabezado</span> de la pregunta para ocultar o mostrar las respuestas.</li>
                    <li>Tipos de preguntas:
                        <ul class="bullet-list sub-list">
                            <li>Una pregunta sin respuestas se considera de tipo <span class="highlight-gold">Ensayo</span>.</li>
                            <li>Una pregunta con una única respuesta, con el texto <span class="highlight-gold">Verdadero</span> o <span class="highlight-gold">Falso</span> se considera de tipo <span class="highlight-gold">Verdadero/Falso</span>. No es necesario marcar la puntuación. Se pueden utilizar los botones correspondientes para marcar automáticamente la respuesta como verdadera o falsa.</li>
                            <li>Para crear una pregunta de tipo <span class="important-text">Repuesta corta</span> todas las respuestas tienen que ser correctas (puntuación 1). Las respuestas pueden ser variaciones de la respuesta correcta. Ejemplo: para la pregunta <span class="important-text">¿A quién se atribuye la ecuación E=mc²?, las respuestas podrían ser Einstein, Albert Einstein, A. Einstein.</span></li>
                            <li>Para crear una pregunta de tipo <span class="important-text">Relacionar columnas</span> todas las respuestas tienen que ser correctas (puntuación 1), deben separarse los valores de las columnas con un <span class="highlight-gold">-></span> y debe haber al menos 3 respuestas. Ejemplo: para una pregunta de relacionar países y capitales las respuestas podrían ser <span class="important-text">Japón -> Tokio, Francia -> París y Rusia -> Moscú.</span></li>
                            <li>Una pregunta de tipo <span class="important-text">Numérico</span> puede contener como respuestas valores numéricos exactos <span class="important-text">(3.1415, 1892...)</span>, intervalos de valores <span class="important-text">(3.1415...3.1416, 1890...1892)</span> o valores con un margen de error o tolerancia <span class="important-text">(3.1415:0.0001, 1890:2).</span></li>
                        </ul>
                    </li>
                    <li>Para guardar el documento en formato JSON pulsa el botón <span class="highlight-gold">Descargar</span>. Al guardarlo de esta forma podrás volver a cargarlo en el editor en otro momento para modificarlo.</li>
                    <li>Para finalizar hay que descargar el documento GIFT e importarlo en el banco de preguntas de Moodle.</li>
                </ul>
            </section>

            <section>
                <h2 style="font-size: 1.5rem;">Importante</h2>
                <ul class="bullet-list">
                    <li>Evitar incluir prefijos y numeraciones en los enunciados de las preguntas y en las respuestas, como <span class="important-text">a) Respuesta, 1.- Respuesta</span>, etc. <span class="highlight-gold">Moodle</span> ya ofrece este tipo de funcionalidad de forma automática. Sólo hay que introducir el texto de las preguntas y respuestas.</li>
                    <li>Evitar los comentarios de tipo <span class="important-text">Respuesta correcta, La respuesta es incorrecta</span> o que sean una copia de la respuesta correcta. <span class="highlight-gold">Moodle</span> ya ofrece este tipo de retroalimentación de forma automática. Si la retroalimentación no aporta ninguna información es mejor dejarla en blanco.</li>
                    <li>Evitar la creación de preguntas de opción simple donde una de las respuestas sea <span class="important-text">Todas las anteriores son correctas</span>. Es preferible otorgar a cada opción correcta una puntuación parcial y que la pregunta sea de tipo opción múltiple.</li>
                    <li>La puntuación máxima de cada pregunta es <span class="important-text">1.0</span>. Si se desea ponderar, por ejemplo, porque sea un cuestionario de 5 preguntas y se quiera evaluar sobre 10, esto se hace al editar el cuestionario en Moodle.</li>
                    <li>Las preguntas, respuestas y comentarios pueden incluir código HTML. Por ejemplo, para destacar una palabra en negrita se pueden usar las etiquetas <span class="important-text">&lt;strong&gt;</span> y <span class="important-text">&lt;/strong&gt;</span>.</li>
                    <li>Si deseas utilizar los símbolos especiales <span class="important-text">&, <, ></span> sin que sean interpretados por el navegador como código HTML, selecciona el texto que contiene estos símbolos y pulsa en el botón <span class="highlight-gold">Filtrar HTML</span> para codificarlos.</li>
                </ul>
            </section>

        </main>

        <aside class="notes-panel">
            <div class="notes-header">Notas</div>
            <textarea class="notes-textarea" placeholder="Escribe aquí tus notas..."></textarea>
        </aside>
    </div>

</body>
</html>
