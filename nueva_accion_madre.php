<?php
// nueva_accion_madre.php
require_once 'includes/auth.php';

$sectores = [
    'Abogados', 'Acción e Intervención Social', 'Administracion y gestion', 'Agencias de Viaje',
    'Alimentación', 'Ambulancias', 'Arquitectura', 'Artes Gráficas', 'Asesorías',
    'Asociaciones', 'Atención a personas con discapacidad', 'Atención Domiciliaria',
    'Automoción', 'Banca', 'Centros de día', 'Comercio', 'Construcción',
    'Consultoría', 'Contact Center', 'Educación y Formación', 'Farmacia',
    'Gimnasios', 'Hostelería', 'Imagen y sonido', 'Industria manufacturera',
    'Inmobiliarias', 'Madera y Mueble', 'Metal', 'Peluquería y Estética',
    'Producción Audiovisual', 'Publicidad', 'Sanidad', 'Seguridad Privada',
    'Seguros', 'Servicios Sociales', 'Telecomunicaciones', 'Transporte',
    'Turismo', 'Universidades'
];
sort($sectores);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Contenido (Madre) - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .ficha-container {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .ficha-header {
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        .ficha-body { padding: 25px; }

        .ficha-section {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            margin-bottom: 22px;
            align-items: start;
        }

        .form-group-ficha {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .label-ficha {
            font-weight: 700;
            font-size: 0.78rem;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .input-ficha {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            width: 100%;
            background: #f8fafc;
            font-family: inherit;
            box-sizing: border-box;
        }
        .input-ficha:focus {
            outline: none;
            border-color: #475569;
            background: white;
        }

        /* Tabla de Horas */
        .horas-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .horas-table th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: left;
            color: #1e3a8a;
            font-weight: 700;
        }
        .horas-table td { border: 1px solid #cbd5e1; padding: 0; }
        .input-hora {
            width: 100%; border: none; padding: 10px 12px;
            text-align: center; font-weight: 600;
            outline: none; background: transparent;
        }

        /* Separador de sección */
        .section-divider {
            border: none;
            border-top: 2px dashed #e2e8f0;
            margin: 20px 0;
        }
        .section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }

        /* Editores RTF */
        .editor-label {
            color: #c2410c;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 8px;
            display: block;
            text-decoration: underline;
        }
        .editor-mockup {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .editor-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        .tool-btn {
            background: none;
            border: 1px solid transparent;
            padding: 4px 6px;
            border-radius: 3px;
            cursor: pointer;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
        }
        .tool-btn:hover { background: #e2e8f0; border-color: #cbd5e1; }
        .tool-btn svg { width: 14px; height: 14px; }
        .toolbar-sep { width: 1px; height: 18px; background: #cbd5e1; margin: 0 3px; }
        .editor-area {
            width: 100%;
            min-height: 110px;
            padding: 14px;
            border: none;
            resize: vertical;
            font-family: inherit;
            font-size: 0.85rem;
            outline: none;
            box-sizing: border-box;
        }

        /* Footer */
        .ficha-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .btn-insertar {
            background: #1e293b;
            color: white;
            padding: 9px 35px;
            border-radius: 4px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }
        .btn-insertar:hover { background: #0f172a; transform: translateY(-1px); }
        .btn-cancelar-ficha {
            padding: 9px 25px;
            background: white;
            border: 1px solid #cbd5e1;
            color: #64748b;
            border-radius: 4px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
        }
        .btn-cancelar-ficha:hover { background: #f1f5f9; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title" style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:20px;">
                <div>
                    <h1>Nuevo Contenido (&ldquo;Madre&rdquo;)</h1>
                    <p>Alta de módulo de contenido vinculado a una asignatura</p>
                </div>
                <a href="acciones_madre.php" style="display:flex; align-items:center; gap:8px; text-decoration:none; background:#1e293b; color:white; border-radius:0; padding:6px 14px; font-weight:700; font-size:0.75rem; border:1px solid #0f172a; white-space:nowrap;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER AL LISTADO
                </a>
            </div>
        </header>

        <form action="acciones_madre.php" method="POST" class="ficha-container">
            <div class="ficha-header">Ficha de Contenido — Nivel Madre</div>

            <div class="ficha-body">

                <!-- Fila 1: Asignatura y Denominación -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 7;">
                        <label class="label-ficha">Asignatura a la que pertenece (Abuela):</label>
                        <select class="input-ficha" name="asignatura_id">
                            <option value="">Seleccione la asignatura...</option>
                            <option>Mantenimiento mecánico preventivo del vehículo</option>
                            <option>Didáctica de la educación infantil</option>
                            <option>Técnicas de conducción de bicicletas</option>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 5;">
                        <label class="label-ficha">Denominación del Contenido:</label>
                        <input type="text" class="input-ficha" name="denominacion" placeholder="Ej: Sistemas de seguridad y confortabilidad">
                    </div>
                </div>

                <!-- Fila 2: Códigos, Abreviatura y Estado -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código Intranet:</label>
                        <input type="text" class="input-ficha" name="codigo_intranet" placeholder="M-001">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código Externo:</label>
                        <input type="text" class="input-ficha" name="codigo_externo">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Abreviatura:</label>
                        <input type="text" class="input-ficha" name="abreviatura">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 3;">
                        <label class="label-ficha">Área Temática (Sector):</label>
                        <select class="input-ficha" name="sector">
                            <option value="">Seleccione sector...</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 3;">
                        <label class="label-ficha">Estado:</label>
                        <select class="input-ficha" name="estado">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla de Horas -->
                <hr class="section-divider">
                <p class="section-title">Distribución de Horas</p>
                <table class="horas-table" style="margin-bottom: 22px;">
                    <thead>
                        <tr>
                            <th>Horas Presenciales</th>
                            <th>Horas a Distancia</th>
                            <th>Horas Teleformación</th>
                            <th>Horas Mixtas</th>
                            <th style="background:#e2e8f0; color:#1e293b;">Total Horas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="number" class="input-hora" name="h_presencial" placeholder="0" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_distancia" placeholder="0" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_tele" placeholder="0" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_mixta" placeholder="0" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" id="total-horas" name="total_horas" placeholder="0" readonly style="background:#f1f5f9; font-weight:800; color:#1e293b;"></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Editores RTF -->
                <hr class="section-divider">
                <label class="editor-label">Descripción y Contenidos:</label>
                <div class="editor-mockup">
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn">Párrafo</button>
                        <div class="toolbar-sep"></div>
                        <button type="button" class="tool-btn"><strong>B</strong></button>
                        <button type="button" class="tool-btn"><em>I</em></button>
                        <button type="button" class="tool-btn">X²</button>
                        <button type="button" class="tool-btn">X₂</button>
                        <div class="toolbar-sep"></div>
                        <button type="button" class="tool-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        </button>
                        <button type="button" class="tool-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        </button>
                    </div>
                    <textarea class="editor-area" name="descripcion" placeholder="Describa los contenidos del módulo..."></textarea>
                </div>

                <label class="editor-label">Objetivos Específicos:</label>
                <div class="editor-mockup">
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn"><strong>B</strong></button>
                        <button type="button" class="tool-btn"><em>I</em></button>
                        <button type="button" class="tool-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                        </button>
                    </div>
                    <textarea class="editor-area" name="objetivos" placeholder="Especifique los objetivos de aprendizaje..."></textarea>
                </div>

                <label class="editor-label">Formación Requerida del Alumno:</label>
                <div class="editor-mockup">
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn"><strong>B</strong></button>
                        <button type="button" class="tool-btn"><em>I</em></button>
                    </div>
                    <textarea class="editor-area" name="formacion_requerida" placeholder="Indique los conocimientos previos necesarios..."></textarea>
                </div>

            </div>

            <div class="ficha-footer">
                <a href="acciones_madre.php" class="btn-cancelar-ficha">Cancelar</a>
                <button type="submit" class="btn-insertar">Insertar Registro</button>
            </div>
        </form>
    </main>
</div>

<script>
function calcTotal() {
    const fields = ['h_presencial', 'h_distancia', 'h_tele', 'h_mixta'];
    let total = 0;
    fields.forEach(f => {
        const val = parseInt(document.querySelector('[name="' + f + '"]').value) || 0;
        total += val;
    });
    document.getElementById('total-horas').value = total || '';
}
</script>

</body>
</html>
