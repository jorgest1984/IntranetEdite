<?php
// editar_accion_madre.php
require_once 'includes/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Simulación de carga de datos (en producción esto vendría de la DB)
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

// Datos del registro a editar (Simulados)
$data = [
    'id' => $id,
    'asignatura' => 'Mantenimiento mecánico preventivo del vehículo',
    'denominacion' => 'Sistemas de seguridad y confortabilidad',
    'codigo_intranet' => 'M-001',
    'codigo_externo' => 'EXT-778',
    'abreviatura' => 'SSC',
    'sector' => 'Automoción',
    'estado' => 'Activo',
    'h_presencial' => 20,
    'h_distancia' => 10,
    'h_tele' => 10,
    'h_mixta' => 5,
    'total_horas' => 45,
    'descripcion' => 'Este módulo cubre los sistemas de seguridad activa y pasiva del vehículo.',
    'objetivos' => 'Conocer los componentes de los sistemas de seguridad.',
    'formacion_requerida' => 'Conocimientos básicos de mecánica.'
];

// Si es el ID 2, cargamos otros datos para que se vea dinámico
if ($id == 2) {
    $data = [
        'id' => 2,
        'asignatura' => 'Didáctica de la educación infantil',
        'denominacion' => 'Planificación de la intervención educativa',
        'codigo_intranet' => 'M-002',
        'codigo_externo' => 'EXT-990',
        'abreviatura' => 'PIE',
        'sector' => 'Educación y Formación',
        'estado' => 'Activo',
        'h_presencial' => 30,
        'h_distancia' => 15,
        'h_tele' => 10,
        'h_mixta' => 5,
        'total_horas' => 60,
        'descripcion' => 'Planificación de actividades educativas para niños de 0 a 6 años.',
        'objetivos' => 'Diseñar unidades didácticas adaptadas.',
        'formacion_requerida' => 'Grado en Magisterio o similar.'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Contenido (Madre) - <?= APP_NAME ?></title>
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
            background: #1e3a8a; /* Color diferente para distinguir Edición */
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
        .btn-guardar {
            background: #1e3a8a;
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
        .btn-guardar:hover { background: #1e40af; transform: translateY(-1px); }
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
                    <h1>Editar Contenido (&ldquo;Madre&rdquo;)</h1>
                    <p>Modificando: <strong><?= htmlspecialchars($data['denominacion']) ?></strong></p>
                </div>
                <a href="acciones_madre.php" style="display:flex; align-items:center; gap:8px; text-decoration:none; background:#1e293b; color:white; border-radius:0; padding:6px 14px; font-weight:700; font-size:0.75rem; border:1px solid #0f172a; white-space:nowrap;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER AL LISTADO
                </a>
            </div>
        </header>

        <form action="acciones_madre.php" method="POST" class="ficha-container">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <div class="ficha-header">Edición de Contenido — ID: <?= $data['id'] ?></div>

            <div class="ficha-body">

                <!-- Fila 1: Asignatura y Denominación -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 7;">
                        <label class="label-ficha">Asignatura a la que pertenece (Abuela):</label>
                        <select class="input-ficha" name="asignatura_id">
                            <option value="1" <?= $data['asignatura'] == 'Mantenimiento mecánico preventivo del vehículo' ? 'selected' : '' ?>>Mantenimiento mecánico preventivo del vehículo</option>
                            <option value="2" <?= $data['asignatura'] == 'Didáctica de la educación infantil' ? 'selected' : '' ?>>Didáctica de la educación infantil</option>
                            <option value="3" <?= $data['asignatura'] == 'Técnicas de conducción de bicicletas' ? 'selected' : '' ?>>Técnicas de conducción de bicicletas</option>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 5;">
                        <label class="label-ficha">Denominación del Contenido:</label>
                        <input type="text" class="input-ficha" name="denominacion" value="<?= htmlspecialchars($data['denominacion']) ?>">
                    </div>
                </div>

                <!-- Fila 2: Códigos, Abreviatura y Estado -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código Intranet:</label>
                        <input type="text" class="input-ficha" name="codigo_intranet" value="<?= htmlspecialchars($data['codigo_intranet']) ?>">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código Externo:</label>
                        <input type="text" class="input-ficha" name="codigo_externo" value="<?= htmlspecialchars($data['codigo_externo']) ?>">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Abreviatura:</label>
                        <input type="text" class="input-ficha" name="abreviatura" value="<?= htmlspecialchars($data['abreviatura']) ?>">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 3;">
                        <label class="label-ficha">Área Temática (Sector):</label>
                        <select class="input-ficha" name="sector">
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= $s ?>" <?= $data['sector'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 3;">
                        <label class="label-ficha">Estado:</label>
                        <select class="input-ficha" name="estado">
                            <option value="Activo" <?= $data['estado'] == 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Inactivo" <?= $data['estado'] == 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
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
                            <td><input type="number" class="input-hora" name="h_presencial" value="<?= $data['h_presencial'] ?>" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_distancia" value="<?= $data['h_distancia'] ?>" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_tele" value="<?= $data['h_tele'] ?>" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" name="h_mixta" value="<?= $data['h_mixta'] ?>" oninput="calcTotal()"></td>
                            <td><input type="number" class="input-hora" id="total-horas" name="total_horas" value="<?= $data['total_horas'] ?>" readonly style="background:#f1f5f9; font-weight:800; color:#1e293b;"></td>
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
                    </div>
                    <textarea class="editor-area" name="descripcion"><?= htmlspecialchars($data['descripcion']) ?></textarea>
                </div>

                <label class="editor-label">Objetivos Específicos:</label>
                <div class="editor-mockup">
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn"><strong>B</strong></button>
                    </div>
                    <textarea class="editor-area" name="objetivos"><?= htmlspecialchars($data['objetivos']) ?></textarea>
                </div>

                <label class="editor-label">Formación Requerida del Alumno:</label>
                <div class="editor-mockup">
                    <div class="editor-toolbar">
                        <button type="button" class="tool-btn"><strong>B</strong></button>
                    </div>
                    <textarea class="editor-area" name="formacion_requerida"><?= htmlspecialchars($data['formacion_requerida']) ?></textarea>
                </div>

            </div>

            <div class="ficha-footer">
                <a href="acciones_madre.php" class="btn-cancelar-ficha">Cancelar</a>
                <button type="submit" class="btn-guardar">Actualizar Registro</button>
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
    document.getElementById('total-horas').value = total || 0;
}
</script>

</body>
</html>
