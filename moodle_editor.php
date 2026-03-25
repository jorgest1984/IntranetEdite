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
                    <button class="btn-tool primary" onclick="addQuestion()">+ Nueva pregunta</button>
                    <button class="btn-tool" onclick="alert('Funcionalidad en desarrollo')">📄 Nuevo test</button>
                    <button class="btn-tool">📂 Abrir</button>
                    <button class="btn-tool">💾 Guardar</button>
                    <button class="btn-tool">📥 Descargar GIFT</button>
                </div>
            </header>

            <div class="editor-container">
                <div class="editor-viewport" id="questionsContainer">
                    
                    <div class="content-card" id="introCard">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            Información del cuestionario
                        </div>
                        <div class="info-grid">
                            <span class="info-label">Curso:</span> <input type="text" class="form-control-minimal" value="Nombre del curso...">
                            <span class="info-label">Título:</span> <input type="text" class="form-control-minimal" value="Evaluación General">
                            <span class="info-label">Prefijo:</span> <input type="text" class="form-control-minimal" style="font-family: monospace;" value="EV0-">
                        </div>
                    </div>

                    <!-- Las preguntas se añadirán aquí -->

                </div>

                <div class="side-viewport">
                    <div>
                        <span class="side-label">Campo GIFT (Previsualización)</span>
                        <textarea class="textarea-custom gift-area" id="giftPreview" readonly placeholder="// El código GIFT aparecerá aquí conforme añadas preguntas..."></textarea>
                    </div>

                    <div style="margin-top: 20px;">
                        <span class="side-label">Ayuda Rápida</span>
                        <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.8rem;">
                            <p><strong>Truco:</strong> Pulsa en el número de pregunta para colapsarla.</p>
                            <p style="margin-bottom:0;"><strong>Sintaxis:</strong> Escribe respuestas y asigna 1.0 a la correcta.</p>
                        </div>
                    </div>

                    <div style="margin-top: auto; padding-top: 20px;">
                        <span class="side-label">Bloc de Notas</span>
                        <textarea class="textarea-custom notes-area" placeholder="Escriba aquí sus notas..."></textarea>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <style>
        /* Estilos adicionales para los bloques dinámicos */
        .form-control-minimal {
            border: none;
            border-bottom: 1px dashed var(--border-color);
            background: transparent;
            font-size: 0.95rem;
            padding: 2px 5px;
            color: var(--primary-blue);
            font-weight: 600;
            outline: none;
            width: 100%;
        }
        .form-control-minimal:focus { border-bottom-style: solid; border-color: var(--primary-blue); }

        .question-block {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .question-header {
            background: #f8fafc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
        }

        .question-number {
            background: var(--primary-blue);
            color: #fff;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .question-body { padding: 20px; }

        .question-textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            font-size: 0.95rem;
            min-height: 80px;
            margin-bottom: 15px;
            outline: none;
            display: block;
        }

        .responses-container {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .response-item {
            background: #fff;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .response-text {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            font-size: 0.85rem;
            outline: none;
            min-height: 40px;
        }

        .score-select {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
            background: #fff;
        }

        .btn-delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .btn-delete:hover { background: #ef4444; color: #fff; }

        .btn-add-response {
            background: #fff;
            color: var(--primary-blue);
            border: 1px dashed var(--primary-blue);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 5px;
            transition: all 0.2s;
        }
        .btn-add-response:hover { background: var(--primary-blue); color: #fff; border-style: solid; }

    </style>

    <script>
        let questionCount = 0;

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questionsContainer');
            
            // Ocultar la tarjeta de intro si hay preguntas
            const intro = document.getElementById('introCard');
            if(intro) intro.style.display = 'none';

            const qBlock = document.createElement('div');
            qBlock.className = 'question-block';
            qBlock.id = `q-block-${questionCount}`;
            
            qBlock.innerHTML = `
                <div class="question-header">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="question-number">${questionCount}</div>
                        <span style="font-weight: 700; font-size: 0.85rem; color: #475569;">Puntuación Total: <span id="total-q-${questionCount}">0.00</span></span>
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <button class="btn-tool" onclick="saveQuestion(${questionCount})" style="padding: 4px 8px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg></button>
                        <button class="btn-delete" onclick="removeQuestion(${questionCount})"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                    </div>
                </div>
                <div class="question-body">
                    <textarea class="question-textarea" placeholder="Escriba aquí el enunciado de la pregunta..." oninput="updateGIFT()"></textarea>
                    
                    <div class="responses-container" id="responses-q-${questionCount}">
                        <div style="font-weight: 700; font-size: 0.8rem; color: #64748b; margin-bottom: 10px; display: flex; justify-content: space-between;">
                            RESPUESTAS
                            <span style="font-weight: 400; font-style: italic;">Asigna puntuación a cada una</span>
                        </div>
                        <!-- Respuestas dinámicas -->
                    </div>
                    
                    <button class="btn-add-response" onclick="addResponse(${questionCount})">+ Nueva respuesta</button>
                    
                    <div style="margin-top: 15px;">
                        <textarea class="textarea-custom" style="height: 40px; font-size: 0.8rem;" placeholder="Comentario general para esta pregunta..." oninput="updateGIFT()"></textarea>
                    </div>
                </div>
            `;
            
            container.appendChild(qBlock);
            addResponse(questionCount); // Añadir una respuesta por defecto
            updateGIFT();
            
            // Scroll al final
            qBlock.scrollIntoView({ behavior: 'smooth' });
        }

        function addResponse(qId) {
            const resContainer = document.getElementById(`responses-q-${qId}`);
            const resId = resContainer.children.length;
            
            const resItem = document.createElement('div');
            resItem.className = 'response-item';
            resItem.innerHTML = `
                <textarea class="response-text" placeholder="Opción ${resId}" oninput="updateGIFT()"></textarea>
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <select class="score-select" onchange="updateTotal(${qId}); updateGIFT();">
                        <option value="0">0.0</option>
                        <option value="1">1.0 (Correcta)</option>
                        <option value="0.5">0.5</option>
                        <option value="0.33">0.33</option>
                        <option value="-0.25">-0.25</option>
                    </select>
                    <button class="btn-delete" onclick="this.parentElement.parentElement.remove(); updateTotal(${qId}); updateGIFT();" style="padding: 4px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                </div>
            `;
            resContainer.appendChild(resItem);
            updateTotal(qId);
        }

        function removeQuestion(id) {
            if(confirm('¿Estás seguro de eliminar esta pregunta?')) {
                document.getElementById(`q-block-${id}`).remove();
                updateGIFT();
            }
        }

        function updateTotal(qId) {
            const container = document.getElementById(`responses-q-${qId}`);
            let total = 0;
            const selects = container.querySelectorAll('.score-select');
            selects.forEach(s => total += parseFloat(s.value));
            document.getElementById(`total-q-${qId}`).innerText = total.toFixed(2);
        }

        function updateGIFT() {
            const preview = document.getElementById('giftPreview');
            let gift = "// Evaluación Generada\n\n";
            
            const questions = document.querySelectorAll('.question-block');
            questions.forEach((q, index) => {
                const text = q.querySelector('.question-textarea').value || "Pregunta sin texto";
                gift += `::Pregunta ${index+1}:: ${text} {\n`;
                
                const responses = q.querySelectorAll('.response-item');
                responses.forEach(r => {
                    const rText = r.querySelector('.response-text').value || "Opción";
                    const score = r.querySelector('.score-select').value;
                    if (score == 1) gift += `  =${rText}\n`;
                    else gift += `  ~%${score*100}%${rText}\n`;
                });
                
                gift += `}\n\n`;
            });
            
            preview.value = gift;
        }

        function saveQuestion(id) {
            alert('Pregunta ' + id + ' guardada temporalmente en memoria.');
        }
    </script>
</body>
</html>
