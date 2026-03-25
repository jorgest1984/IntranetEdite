<?php
// glosario_moodle.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'glosario_moodle.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Glosarios de Moodle - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #1e40af;
            --bg-light: #f8fafc;
            --border-color: #cbd5e1;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; }
        .main-content { padding: 2rem; }

        .breadcrumb {
            background-color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .breadcrumb a { color: var(--primary-blue); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .glosario-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .glosario-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .glosario-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--secondary-blue);
            margin-bottom: 0.5rem;
        }

        .glosario-header p {
            color: #64748b;
            font-size: 1rem;
        }

        .instructions-box {
            background: #f1f5f9;
            border-left: 4px solid var(--primary-blue);
            padding: 1.5rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .example-box {
            background: #fff;
            padding: 10px 15px;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            margin-top: 10px;
            font-family: 'Consolas', monospace;
            font-size: 0.8rem;
            color: #475569;
        }

        .textarea-container {
            position: relative;
            margin-bottom: 2rem;
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #334155;
        }

        .glosario-textarea {
            width: 100%;
            height: 300px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            outline: none;
            transition: all 0.2s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }
        .glosario-textarea:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: center;
        }

        .btn-generate {
            background: var(--primary-blue);
            color: #fff;
            border: none;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);
        }
        .btn-generate:hover {
            background: var(--secondary-blue);
        }
        .btn-generate svg { width: 20px; }

    </style>
</head>
<body>

    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a> / 
                <a href="formacion_profesional.php">Formación</a> / 
                Generador de Glosarios
            </div>

            <div class="glosario-card">
                <header class="glosario-header">
                    <h1>Generador de Glosarios</h1>
                    <p>Crea archivos XML listos para importar en Moodle</p>
                </header>

                <div class="instructions-box">
                    Introduce cada término siguiendo este formato: <strong>Concepto | Definición #</strong>
                    <div class="example-box">
                        Cookie|Pequeño fichero que almacena información...# <br>
                        Metadatos|Datos relacionados con un documento...#
                    </div>
                </div>

                <form id="glosarioForm">
                    <div class="textarea-container">
                        <label for="glosarioText">Términos y definiciones</label>
                        <textarea id="glosarioText" class="glosario-textarea" placeholder="Escribe aquí los conceptos separados por | y terminados en #..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-generate" onclick="generateXML()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Generar y descargar XML
                        </button>
                    </div>
                </form>
            </div>

            <footer style="text-align: center; margin-top: 3rem; color: #94a3b8; font-size: 0.8rem;">
                © <?= date('Y') ?> Edite Formación | www.editeformacion.com
            </footer>

        </main>
    </div>

    <script>
        function generateXML() {
            const rawText = document.getElementById('glosarioText').value.trim();
            if(!rawText) {
                alert('Por favor, introduce al menos un término.');
                return;
            }

            // Separar por #
            const rawEntries = rawText.split('#').map(e => e.trim()).filter(e => e.length > 0);
            if(rawEntries.length === 0) {
                alert('No se han encontrado entradas válidas. Recuerda finalizar cada una con el símbolo #.');
                return;
            }

            let entriesXML = '';
            rawEntries.forEach(entry => {
                const parts = entry.split('|').map(p => p.trim());
                if(parts.length >= 2) {
                    const concept = parts[0];
                    const definition = parts.slice(1).join('|'); // Por si hay | en la definición
                    
                    entriesXML += `
    <ENTRY>
      <CONCEPT><![CDATA[${concept}]]></CONCEPT>
      <DEFINITION><![CDATA[${definition}]]></DEFINITION>
      <FORMAT>1</FORMAT>
      <USEDYNALINK>0</USEDYNALINK>
      <CASESENSITIVE>0</CASESENSITIVE>
      <FULLMATCH>0</FULLMATCH>
      <TEACHERENTRY>1</TEACHERENTRY>
    </ENTRY>`;
                }
            });

            if(!entriesXML) {
                alert('No se ha podido procesar ninguna entrada. Asegúrate de usar el formato: Concepto | Definición #');
                return;
            }

            const xmlHeader = String.fromCharCode(60, 63, 120, 109, 108) + String.fromCharCode(32, 118, 101, 114, 115, 105, 111, 110, 61, 34, 49, 46, 48, 34, 32, 101, 110, 99, 111, 100, 105, 110, 103, 61, 34, 85, 84, 70, 45, 56, 34) + String.fromCharCode(63, 62);
            const xmlContent = xmlHeader + `
<GLOSSARY>
  <INFO>
    <NAME>Glosario Importado</NAME>
    <INTRO></INTRO>
    <ALLOWDUPLICATEDENTRIES>0</ALLOWDUPLICATEDENTRIES>
    <DISPLAYFORMAT>dictionary</DISPLAYFORMAT>
    <SHOWSPECIAL>1</SHOWSPECIAL>
    <SHOWALPHABET>1</SHOWALPHABET>
    <SHOWALL>1</SHOWALL>
    <ALLOWCOMMENTS>0</ALLOWCOMMENTS>
    <USEDYNALINK>1</USEDYNALINK>
    <DEFAULTAPPROVAL>1</DEFAULTAPPROVAL>
    <GLOBALGLOSSARY>0</GLOBALGLOSSARY>
    <ENTBYPAGE>10</ENTBYPAGE>
  </INFO>
  <ENTRIES>${entriesXML}
  </ENTRIES>
</GLOSSARY>`;

            // Crear Blob y descargar
            const blob = new Blob([xmlContent], { type: 'text/xml' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            const timestamp = new Date().toISOString().slice(0,10);
            
            a.href = url;
            a.download = `glosario_moodle_${timestamp}.xml`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
