<?php
// ficha_accion_formativa.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes. Su rol actual es: " . ($_SESSION['rol_nombre'] ?? 'Desconocido'));
}

// Fetch plans for the dropdown
$planes = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, codigo FROM planes ORDER BY nombre ASC");
    if ($stmt) { $planes = $stmt->fetchAll(); }
} catch (Throwable $e) { }

$modalidades = ['Teleformacion', 'Presencial', 'Mixta', 'Aula Virtual'];
$niveles = ['Básico', 'Medio', 'Medio-superior', 'Superior'];
$prioridades = ['Alta', 'Media', 'Baja'];
$estados = ['No programable', 'Programable', 'En curso', 'Finalizado'];

// Base list for families (reused from catalog)
$familias = [
    'Certificado de Profesionalidad', 'Familia- Actividades Físicas y Deportivas',
    'Familia- Administración y Gestión', 'Familia- Agraria', 'Familia- Artes graficas',
    'Familia- Comercio y Marketing', 'Familia- Edificación y Obra Civil',
    'Familia- Energía y Agua', 'Familia- Hostelería y Turismo', 'Familia- Imagen Personal',
    'Familia- Imagen y Sonido', 'Familia- Industria alimentaria',
    'Familia- Informática y Comunicaciones', 'Familia- Seguridad y Medioambiente',
    'Familia: Sevicios socioculturales y a la comunidad', 'Oferta 1.Appforbrands',
    'Oferta 2.Appforbrands', 'Oferta 3. Hosteleria y Restauracion',
    'Prevención de Riesgos Laborales', 'SAP', 'Seguridad Privada', 'Transversal'
];
sort($familias);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Acción Formativa | Intranet Edite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 1rem 2rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header-title h1 {
            color: #b91c1c;
            font-size: 1.25rem;
            margin: 0;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .btn-group-header {
            display: flex;
            gap: 10px;
        }

        .btn-header {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-header:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        .course-title-display {
            text-align: center;
            font-weight: 800;
            font-size: 1.1rem;
            margin: 20px 0;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tabs-container {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .tabs-header {
            display: flex;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            background: #e2e8f0;
        }

        .tab-btn.active {
            background: #fff;
            color: #b91c1c;
            border-bottom: 2px solid #b91c1c;
        }

        .tab-content {
            padding: 2rem;
        }

        .form-section-title {
            text-align: center;
            color: #b91c1c;
            font-weight: 800;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.5rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px 1.25rem -10px;
        }

        .form-col {
            padding: 0 10px;
            box-sizing: border-box;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            color: #1e3a8a; /* Azul corporativo para labels */
            text-transform: uppercase;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            color: #334155;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 100%;
            padding-top: 1.5rem;
        }

        .checkbox-group input {
            width: auto;
        }

        .btn-footer-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .btn-save {
            background: #b91c1c;
            border: 1px solid #991b1b;
            padding: 0.6rem 2rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            color: #fff;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: #991b1b;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .btn-back {
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 2rem;
            border-radius: 6px;
            text-decoration: none;
            color: #475569;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #f1f5f9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-col { width: 100% !important; }
            .tabs-header { flex-wrap: wrap; }
        }

        .sectores-table-container {
            margin-top: 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .sectores-table-header {
            background: #1e293b;
            padding: 12px;
            text-align: center;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .sectores-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sectores-table th {
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px;
            font-size: 0.8rem;
            color: #475569;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sectores-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem;
            text-align: center;
            color: #334155;
        }

        .btn-add-sector {
            padding: 0.5rem 1.5rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e3a8a;
            transition: all 0.2s;
        }

        .btn-add-sector:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-title">
                <h1>FICHA DE ACCIÓN FORMATIVA - CONTRATOS PROGRAMA</h1>
            </div>
            <div class="btn-group-header">
                <a href="#" class="btn-header">Duplicar Acción Formativa</a>
                <a href="#" class="btn-header">Duplicar en Bonificados</a>
                <a href="#" class="btn-header">Peticiones</a>
                <button type="submit" form="main-form" class="btn-header">Guardar registro</button>
            </div>
        </header>

        <div class="course-title-display">
            ACREDITACIÓN DOCENTE PARA TELEFORMACIÓN (ADT)
        </div>

        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('datos-generales')">Datos Generales</button>
                <button class="tab-btn" onclick="switchTab('grupos')">Grupos</button>
                <button class="tab-btn" onclick="switchTab('contenidos')">Contenidos</button>
                <button class="tab-btn" onclick="switchTab('material')">Material</button>
                <button class="tab-btn" onclick="switchTab('gestion')">Gestión</button>
                <button class="tab-btn" onclick="switchTab('ejecucion')">Ejecución</button>
                <button class="tab-btn" onclick="switchTab('instalacion')">Instalación</button>
            </div>

            <div class="tab-content" id="datos-generales">
                <div class="form-section-title">Datos Generales</div>
                
                <form id="main-form">
                    <div class="form-row">
                        <div class="form-group form-col" style="width: 60%;">
                            <label>Plan:</label>
                            <select name="plan_id">
                                <option value="">Seleccione un plan...</option>
                                <?php foreach ($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['codigo']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 25%;">
                            <label>Nivel:</label>
                            <select name="nivel">
                                <?php foreach ($niveles as $n): ?>
                                    <option value="<?= $n ?>"><?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Prioridad:</label>
                            <select name="prioridad">
                                <option value=""></option>
                                <?php foreach ($prioridades as $pr): ?>
                                    <option value="<?= $pr ?>"><?= $pr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 20%;">
                            <label>Estado de la acción:</label>
                            <select name="estado">
                                <?php foreach ($estados as $e): ?>
                                    <option value="<?= $e ?>"><?= $e ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 20%;">
                            <label>Destacar en la web:</label>
                            <select name="destacar_web">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <div class="checkbox-group">
                                <label>Últimas plazas</label>
                                <input type="checkbox" name="ultimas_plazas">
                            </div>
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>id plataforma:</label>
                            <input type="text" name="id_plataforma">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Título:</label>
                            <input type="text" name="titulo" value="ACREDITACIÓN DOCENTE PARA TELEFORMACIÓN">
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Abreviatura:</label>
                            <input type="text" name="abreviatura" value="ADT">
                        </div>
                        <div class="form-group form-col" style="width: 15%;">
                            <label>Nº Acción:</label>
                            <input type="number" name="num_accion" value="0">
                        </div>
                        <div class="form-group form-col" style="width: 20%;">
                            <label>Grupos anteriores:</label>
                            <span style="font-size: 0.9rem; color: #00008b;">0</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 10%;">
                            <label>Duración:</label>
                            <input type="number" name="duracion" value="60">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>P.:</label>
                            <input type="number" name="p" value="0">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>D.:</label>
                            <input type="number" name="d" value="0">
                        </div>
                        <div class="form-group form-col" style="width: 10%;">
                            <label>T.:</label>
                            <input type="number" name="t" value="60">
                        </div>
                        <div class="form-group form-col" style="width: 30%;">
                            <label>Modalidad:</label>
                            <select name="modalidad">
                                <?php foreach ($modalidades as $m): ?>
                                    <option value="<?= $m ?>" <?= $m == 'Teleformacion' ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 40%;">
                            <label>Área temática (a eliminar):</label>
                            <div style="display: flex; gap: 5px;">
                                <select name="area_tematica">
                                    <option value=""></option>
                                </select>
                                <button type="button" class="btn-add-sector">...</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 100%;">
                            <label>Familia profesional:</label>
                            <select name="familia_profesional">
                                <option value=""></option>
                                <?php foreach ($familias as $fam): ?>
                                    <option value="<?= htmlspecialchars($fam) ?>"><?= htmlspecialchars($fam) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Para presenciales:</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.85rem;">Horas teóricas:</span>
                                <input type="number" name="horas_teoricas" value="0" style="width: 80px;">
                                <span style="font-size: 0.85rem;">Horas prácticas:</span>
                                <input type="number" name="horas_practicas" value="0" style="width: 80px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-col" style="width: 50%;">
                            <label>Para cursos cortos:</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.85rem;">Días extra sin tutorización:</span>
                                <input type="number" name="dias_extra" value="0" style="width: 80px;">
                                <span style="font-size: 0.85rem;">Asignación:</span>
                                <select name="asignacion" style="width: 150px;">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="grupos" style="display: none;">
                <div class="form-section-title">Grupos vinculados a esta acción</div>
                <div class="table-responsive">
                    <style>
                        .table-grupos-ficha { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
                        .table-grupos-ficha th { background: #1e293b; color: white; padding: 10px; text-align: left; text-transform: uppercase; font-size: 0.7rem; }
                        .table-grupos-ficha td { padding: 10px; border-bottom: 1px solid #e2e8f0; color: #334155; }
                        .table-grupos-ficha tr:hover td { background: #f8fafc; }
                        .badge-ficha { padding: 2px 8px; border-radius: 10px; font-weight: 700; font-size: 0.65rem; text-transform: uppercase; }
                        .badge-valido { background: #dcfce7; color: #166534; }
                        .badge-progra { background: #dbeafe; color: #1e40af; }
                    </style>
                    <table class="table-grupos-ficha">
                        <thead>
                            <tr>
                                <th>Nº Grupo</th>
                                <th>Código Plataforma</th>
                                <th>Centro Impartición</th>
                                <th>F. Inicio</th>
                                <th>F. Fin</th>
                                <th>Alumnos (I/A/F)</th>
                                <th>Tutor / Docente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight:700;">G1</td>
                                <td style="font-family:monospace;">ADT-2024-01</td>
                                <td>EDITEFORMACION (Madrid)</td>
                                <td>15/04/2024</td>
                                <td>30/05/2024</td>
                                <td style="text-align:center;"><span style="color:#1e40af;">25</span> / <span style="color:#166534;">20</span> / <span style="color:#64748b;">0</span></td>
                                <td style="font-weight:600;">JUAN PÉREZ</td>
                                <td><span class="badge-ficha badge-valido">Válido</span></td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <a href="ficha_grupo_edicion.php?id=1" style="color:#64748b;" title="Ver/Editar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top:20px; text-align:center;">
                        <button class="btn-add-sector" style="background:var(--primary-color); color:white; border:none; padding:10px 20px;">+ Crear Nuevo Grupo para esta Acción</button>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="contenidos" style="display: none;">
                <div class="form-section-title">Contenidos, Objetivos y Unidades</div>
                <div style="display: flex; gap: 20px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:700; color:#1e3a8a;">
                        Módulo Sensib.: <input type="checkbox">
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:700; color:#1e3a8a;">
                        Módulo Alfab.: <input type="checkbox">
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.8rem; font-weight:700; color:#1e3a8a;">
                        Encuesta post.: <input type="checkbox">
                    </label>
                </div>
                
                <div class="form-row">
                    <div class="form-group form-col" style="width: 50%;">
                        <label>Duración del Módulo Int. Empresas:</label>
                        <input type="number" value="0">
                    </div>
                    <div class="form-group form-col" style="width: 50%;">
                        <label>Duración del Módulo emprendimiento:</label>
                        <input type="number" value="0">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Objetivos:</label>
                    <textarea style="width:100%; height:100px; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-family:inherit; font-size:0.85rem;" placeholder="Introduzca los objetivos del curso..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Objetivos específicos:</label>
                    <textarea style="width:100%; height:100px; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-family:inherit; font-size:0.85rem;" placeholder="Saber hacer X, Conocer Y..."></textarea>
                    <a href="#" style="font-size:0.75rem; color:#b91c1c; font-weight:700; text-decoration:none; display:block; margin-top:5px;">Ver / Editar Unidades</a>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Contenidos:</label>
                    <textarea style="width:100%; height:150px; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-family:inherit; font-size:0.85rem;" placeholder="Desglose de contenidos teóricos y prácticos..."></textarea>
                </div>

                <div class="form-group">
                    <label>Contenidos breves:</label>
                    <p style="font-size:0.7rem; color:#64748b; margin-bottom:5px;">(Se mostrará en el apartado "Contenidos" en la ficha)</p>
                    <textarea style="width:100%; height:80px; border:1px solid #cbd5e1; border-radius:4px; padding:10px; font-family:inherit; font-size:0.85rem;"></textarea>
                </div>
            </div>

            <div class="tab-content" id="material" style="display: none;">
                <div class="form-section-title">Material Didáctico y Recursos</div>
                <p style="text-align:center; color:#64748b;">Sección en desarrollo...</p>
            </div>
            
            <div class="tab-content" id="gestion" style="display: none;">
                <div class="form-section-title">Gestión Administrativa</div>
                <p style="text-align:center; color:#64748b;">Sección en desarrollo...</p>
            </div>
        </div>

        <div class="sectores-table-container">
            <div class="sectores-table-header">SECTORES</div>
            <table class="sectores-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">SECTOR</th>
                        <th style="width: 35%;">SOLICITANTE</th>
                        <th style="width: 35%;">CONVOCATORIA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3">
                            <button class="btn-add-sector">Añadir nuevo sector</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected tab content
            document.getElementById(tabId).style.display = 'block';
            
            // Activate the clicked button
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
