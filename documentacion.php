<?php
// documentacion.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$convocatoria_id = isset($_GET['convocatoria_id']) ? intval($_GET['convocatoria_id']) : 0;
$grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : 0;
$accion_id = 0;
$plan_id = 0;

if ($grupo_id) {
    $stmtGrupo = $pdo->prepare("
        SELECT g.accion_id, af.plan_id, p.convocatoria_id 
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes p ON af.plan_id = p.id
        WHERE g.id = ?
    ");
    $stmtGrupo->execute([$grupo_id]);
    if ($row = $stmtGrupo->fetch()) {
        $accion_id = $row['accion_id'];
        $plan_id = $row['plan_id'];
        $convocatoria_id = $row['convocatoria_id'];
    }
}

// Cargar convocatorias para el selector
$stmtConvs = $pdo->query("SELECT id, codigo_expediente, nombre FROM convocatorias ORDER BY creado_en DESC");
$convocatorias = $stmtConvs->fetchAll();

$alumnos = [];
$convocatoriaInfo = null;

if ($convocatoria_id) {
    $stmtConv = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmtConv->execute([$convocatoria_id]);
    $convocatoriaInfo = $stmtConv->fetch();
    
    $stmtAlumnos = $pdo->prepare("
        SELECT a.* 
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        WHERE m.convocatoria_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
        ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre
    ");
    $stmtAlumnos->execute([$convocatoria_id]);
    $alumnos = $stmtAlumnos->fetchAll();
}

// Configuración Global para Inyección en JS
$stmtConf = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'empresa_nombre'");
$stmtConf->execute();
$empresaNombre = $stmtConf->fetchColumn() ?: APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador Documental - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <!-- Incluir librería jsPDF para generación en cliente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <style>
        .filter-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .form-group { margin-bottom: 0; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background-color: white; min-width: 350px;}
        
        .docs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .doc-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.2s; }
        .doc-card:hover { border-color: var(--primary-color); transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(220,38,38,0.1); }
        .doc-icon { width: 48px; height: 48px; fill: var(--primary-color); margin-bottom: 1rem; }
        .doc-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem; }
        .doc-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; flex: 1; }
        
        /* Modal Generator */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal.active { display: flex; animation: fadeInBody 0.2s; }
        .modal-content { background: var(--card-bg); border-radius: 12px; padding: 2rem; width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
        .modal-header h2 { margin: 0; font-size: 1.2rem; color: var(--text-color); }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Documentación y Anexos</h1>
                <p>Generación automatizada de PDF oficiales</p>
            </div>
        </header>

        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Seleccionar Expediente Activo *</label>
                    <select name="convocatoria_id" class="form-input" required onchange="this.form.submit()">
                        <option value="">-- Elige una convocatoria --</option>
                        <?php foreach ($convocatorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($convocatoria_id && $convocatoriaInfo): ?>
            
            <div class="docs-grid">
                
                <!-- Recibí de Material -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    <div class="doc-title">Recibí de Material</div>
                    <div class="doc-desc">Documento SEPE/FUNDAE donde el alumno firma la entrega de manuales, EPIs o tablets.</div>
                    <button class="btn btn-primary" onclick="openDocModal('recibi')">Generar PDF</button>
                </div>
                
                <!-- Hoja de Bienvenida -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    <div class="doc-title">Hoja de Bienvenida</div>
                    <div class="doc-desc">Documento con las instrucciones de acceso, credenciales URL, usuario y contraseña.</div>
                    <button class="btn btn-primary" onclick="openDocModal('bienvenida')">Generar PDF</button>
                </div>
                
                <!-- Diploma Provisional -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    <div class="doc-title">Anexo I: Solicitud</div>
                    <div class="doc-desc">Ficha oficial de solicitud de participación en acciones formativas (Datos personales + situación laboral).</div>
                    <button class="btn btn-primary" onclick="openDocModal('anexo1')">Generar PDF</button>
                </div>

                <!-- Documentación Didáctica -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    <div class="doc-title">Documentación Didáctica</div>
                    <div class="doc-desc">Programaciones, planificaciones y documentos requeridos para el grupo.</div>
                    <button class="btn btn-primary" onclick="openDocModal('didactica')">Acceder</button>
                </div>

                <!-- Informe de Grupo -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                    <div class="doc-title">Informe de Grupo</div>
                    <div class="doc-desc">Informe de seguimiento de evaluaciones y estado de los alumnos en Moodle.</div>
                    <button class="btn btn-primary" onclick="openDocModal('informe')">Generar PDF</button>
                </div>

                <!-- Informe de Tutorías -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 19V5h14v14H5zm12-12H7v2h10V7zm0 4H7v2h10v-2zm0 4H7v2h10v-2z"/></svg>
                    <div class="doc-title">Informe de Tutorías</div>
                    <div class="doc-desc">Exportación en Excel con el registro cronológico del seguimiento de tutorías del grupo.</div>
                    <button class="btn btn-primary" onclick="openDocModal('tutorias')">Generar Excel</button>
                </div>

                <!-- Acta de Evaluación Final -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    <div class="doc-title">Acta de Evaluación Final</div>
                    <div class="doc-desc">Acta oficial de cierre con las calificaciones de los alumnos aptos que finalizaron todo.</div>
                    <button class="btn btn-primary" onclick="openDocModal('acta')">Generar PDF</button>
                </div>

                <!-- Informe Seguimiento Alumno -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    <div class="doc-title">Informe Seguimiento Alumno</div>
                    <div class="doc-desc">Documento de seguimiento individualizado del alumno.</div>
                    <button class="btn btn-primary" onclick="openDocModal('informe_alumno')">Generar PDF</button>
                </div>
                
                <!-- Diploma Provisional -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    <div class="doc-title">Diploma / Certificado</div>
                    <div class="doc-desc">Certificado de asistencia o Diploma de aprovechamiento por alumno.</div>
                    <button class="btn btn-primary" onclick="openDocModal('diploma')">Ver Alumnos</button>
                </div>
                
                <!-- XML Export -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
                    <div class="doc-title">Exportar XML de Grupo</div>
                    <div class="doc-desc">Descarga del XML estructurado con los horarios y datos técnicos del grupo.</div>
                    <button class="btn btn-primary" onclick="openDocModal('xml')">Descargar XML</button>
                </div>
                
                <!-- XML Encuestas Export -->
                <div class="doc-card">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    <div class="doc-title">XML de Encuestas</div>
                    <div class="doc-desc">Exportación XML oficial FUNDAE de las encuestas de satisfacción.</div>
                    <button class="btn btn-primary" onclick="openDocModal('xml_encuestas')">Exportar Encuestas</button>
                </div>

            </div>

        <?php elseif(empty($convocatoria_id)): ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 12px;">
                <p>Selecciona una convocatoria válida para habilitar la firma de documentos.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- Modal Selección de Alumno (Recibí) -->
<div id="docModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar "Recibí de Material"</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelect" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('recibi', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelect" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('recibi', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelect" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAlumnos('recibi', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Alumno Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Alumno Receptor *</label>
            <select id="alumnoSelect" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
            

        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateRecibiPDF()">
            Descargar PDF
        </button>
    </div>
</div>

<!-- Modal Selección Hoja Bienvenida -->
<div id="bienvenidaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar "Hoja de Bienvenida"</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelect_bienvenida" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('bienvenida', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelect_bienvenida" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('bienvenida', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelect_bienvenida" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAlumnos('bienvenida', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Alumno Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Alumno Receptor *</label>
            <select id="alumnoSelect_bienvenida" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateBienvenidaPDF()">
            Descargar PDF
        </button>
    </div>
</div>

<!-- Modal Selección Anexo I -->
<div id="anexoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Anexo I: Solicitud</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectAnexo" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('anexo1', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectAnexo" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('anexo1', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectAnexo" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAlumnos('anexo1', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Alumno Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Seleccionar Alumno *</label>
            <select id="alumnoSelectAnexo" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateAnexo1PDF()">Descargar Solicitudes PDF</button>
    </div>
</div>

<!-- Modal Selección Didáctica -->
<div id="didacticaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Acceder a Documentación Didáctica</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectDidactica" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('didactica', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectDidactica" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('didactica', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectDidactica" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('didactica', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Seleccionar Grupo *</label>
            <select id="grupoSelectDidactica" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="goToDidactica()">
            Ir a Documentación
        </button>
    </div>
</div>

<!-- Modal Selección Informe Grupo -->
<div id="informeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Informe de Grupo</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectInforme" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('informe', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectInforme" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('informe', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectInforme" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('informe', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Grupo *</label>
            <select id="grupoSelectInforme" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateInformeGrupoPDF()">
            Descargar PDF
        </button>
    </div>
</div>

<!-- Modal Selección Informe Tutorías -->
<div id="tutoriasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Informe de Tutorías</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectTutorias" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('tutorias', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectTutorias" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('tutorias', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectTutorias" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('tutorias', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Grupo *</label>
            <select id="grupoSelectTutorias" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateTutoriasExcel()">
            Descargar Excel
        </button>
    </div>
</div>

<!-- Modal Selección Acta de Evaluación -->
<div id="actaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Acta de Evaluación Final</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectActa" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('acta', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectActa" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('acta', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectActa" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('acta', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Grupo *</label>
            <select id="grupoSelectActa" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateActaEvaluacionPDF()">
            Descargar PDF
        </button>
    </div>
</div>

<!-- Modal Selección Informe Seguimiento Alumno -->
<div id="informeAlumnoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Informe de Seguimiento Alumno</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectInformeAlumno" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('informe_alumno', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectInformeAlumno" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('informe_alumno', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectInformeAlumno" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('informe_alumno', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Grupo *</label>
            <select id="grupoSelectInformeAlumno" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAlumnosPorGrupo('informe_alumno', this.value)">
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>

            <!-- Alumno Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Alumno *</label>
            <select id="alumnoSelectInformeAlumno" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Grupo --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateInformeAlumnoPDF()">
            Descargar PDF
        </button>
    </div>
</div>

<!-- Modal Selección Diploma / Certificado -->
<div id="diplomaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Generar Diplomas y Certificados</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <!-- Convocatoria Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectDiploma" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('diploma', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Plan Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectDiploma" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('diploma', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <!-- Acción Formativa Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectDiploma" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('diploma', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <!-- Grupo Selector -->
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Grupo *</label>
            <select id="grupoSelectDiploma" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateDiplomaList()">Ver Listado de Alumnos</button>
    </div>
</div>

<!-- Modal Selección XML -->
<div id="xmlModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Exportar XML de Grupo</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectXml" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('xml', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectXml" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('xml', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectXml" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('xml', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Seleccionar Grupo *</label>
            <select id="grupoSelectXml" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Primero elige Acción Formativa --</option>
            </select>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateXML()">Descargar XML</button>
    </div>
</div>

<!-- Modal Selección XML Encuestas -->
<div id="xmlEncuestasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Exportar XML de Encuestas (FUNDAE)</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Convocatoria *</label>
            <select id="convocatoriaSelectXmlEnc" class="form-input" style="width: 100%; margin-bottom: 1rem;" onchange="loadPlanes('xml_encuestas', this.value)">
                <option value="">-- Selecciona Convocatoria --</option>
                <?php foreach ($convocatorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $convocatoria_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['codigo_expediente']) ?> - <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Plan *</label>
            <select id="planSelectXmlEnc" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadAcciones('xml_encuestas', this.value)">
                <option value="">-- Primero elige Convocatoria --</option>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Acción Formativa *</label>
            <select id="accionSelectXmlEnc" class="form-input" style="width: 100%; margin-bottom: 1rem;" disabled onchange="loadGrupos('xml_encuestas', this.value)">
                <option value="">-- Primero elige Plan --</option>
            </select>

            <label class="form-label" style="display:block; margin-bottom: 0.25rem;">Seleccionar Grupo (Opcional)</label>
            <select id="grupoSelectXmlEnc" class="form-input" style="width: 100%;" disabled>
                <option value="">-- Exportar toda la Acción Formativa --</option>
            </select>
            <small style="color: #64748b; margin-top: 0.5rem; display: block;">Si no seleccionas grupo, se exportarán las encuestas de todos los grupos de la acción.</small>
        </div>
        
        <button class="btn btn-primary" style="width: 100%; justify-content:center; margin-top: 1rem;" onclick="generateXMLEncuestas()">Descargar Encuestas</button>
    </div>
</div>

<script>
// Parse PHP Data to JS
const empresaGlobal = <?= json_encode($empresaNombre) ?>;
const convocatoriaActiva = <?= $convocatoriaInfo ? json_encode($convocatoriaInfo) : 'null' ?>;
const alumnosAcitvos = <?= json_encode($alumnos) ?>;

// State management for dynamically loaded data
const loadedData = {
    recibi: {
        alumnos: [],
        context: null
    },
    anexo1: {
        alumnos: [],
        context: null
    },
    didactica: {
        grupos: [],
        context: null
    },
    informe: {
        grupos: [],
        context: null
    },
    tutorias: {
        grupos: [],
        context: null
    },
    acta: {
        grupos: [],
        context: null
    },
    informe_alumno: {
        grupos: [],
        context: null
    },
    diploma: {
        grupos: [],
        context: null
    },
    xml: {
        grupos: [],
        context: null
    },
    xml_encuestas: {
        grupos: [],
        context: null
    }
};

const PRELOAD = {
    convocatoria_id: <?= $convocatoria_id ?: 'null' ?>,
    plan_id: <?= $plan_id ?: 'null' ?>,
    accion_id: <?= $accion_id ?: 'null' ?>,
    grupo_id: <?= $grupo_id ?: 'null' ?>
};

window.openDocModal = async function(type) {
    let modalId = type === 'recibi' ? 'docModal' : 
                 (type === 'didactica' ? 'didacticaModal' : 
                 (type === 'informe' ? 'informeModal' : 
                 (type === 'tutorias' ? 'tutoriasModal' : 
                 (type === 'acta' ? 'actaModal' : 
                 (type === 'informe_alumno' ? 'informeAlumnoModal' : 
                 (type === 'diploma' ? 'diplomaModal' : 
                 (type === 'xml' ? 'xmlModal' : 
                 (type === 'xml_encuestas' ? 'xmlEncuestasModal' : 'anexoModal'))))))));
                 
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.add('active');
    
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    let convSelect = document.getElementById('convocatoriaSelect' + suffix);
    let planSelect = document.getElementById('planSelect' + suffix);
    let accionSelect = document.getElementById('accionSelect' + suffix);
    let grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    if (PRELOAD.convocatoria_id && convSelect) {
        if (convSelect.value == PRELOAD.convocatoria_id && planSelect && planSelect.value == PRELOAD.plan_id) {
            return; // Already preloaded
        }
        
        convSelect.value = PRELOAD.convocatoria_id;
        
        if (PRELOAD.plan_id && planSelect) {
            planSelect.innerHTML = '<option>Cargando...</option>';
            let resP = await fetch(`api_documentos_cascade.php?action=get_planes&convocatoria_id=${PRELOAD.convocatoria_id}`);
            let planes = await resP.json();
            planSelect.innerHTML = '<option value="">-- Selecciona Plan --</option>';
            planes.forEach(p => planSelect.innerHTML += `<option value="${p.id}">${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}</option>`);
            planSelect.disabled = false;
            planSelect.value = PRELOAD.plan_id;
            
            if (PRELOAD.accion_id && accionSelect) {
                accionSelect.innerHTML = '<option>Cargando...</option>';
                let resA = await fetch(`api_documentos_cascade.php?action=get_acciones&plan_id=${PRELOAD.plan_id}`);
                let acciones = await resA.json();
                accionSelect.innerHTML = '<option value="">-- Selecciona Acción Formativa --</option>';
                acciones.forEach(af => accionSelect.innerHTML += `<option value="${af.id}">${af.num_accion ? '#' + af.num_accion + ' - ' : ''}${af.titulo}</option>`);
                accionSelect.disabled = false;
                accionSelect.value = PRELOAD.accion_id;
                
                // Trigger subsequent loaders manually so we can preselect groups if needed
                let isGlobalAlumno = type === 'recibi';
                if (isGlobalAlumno) {
                    loadAlumnos(type, PRELOAD.accion_id);
                }
                
                if (grupoSelect) {
                    grupoSelect.innerHTML = '<option>Cargando...</option>';
                    let resG = await fetch(`api_documentos_cascade.php?action=get_grupos&accion_id=${PRELOAD.accion_id}`);
                    let grupos = await resG.json();
                    grupoSelect.innerHTML = type === 'xml_encuestas' ? '<option value="">-- Exportar toda la Acción Formativa --</option>' : '<option value="">-- Selecciona Grupo --</option>';
                    grupos.forEach(g => grupoSelect.innerHTML += `<option value="${g.id}">Grupo ${g.numero_grupo}</option>`);
                    grupoSelect.disabled = false;
                    loadedData[type].grupos = grupos;
                    if (PRELOAD.grupo_id) {
                        grupoSelect.value = PRELOAD.grupo_id;
                        if (type === 'informe_alumno' || type === 'anexo') {
                            loadAlumnosPorGrupo(type, PRELOAD.grupo_id);
                        }
                    }
                }
            }
        }
    } else if (convSelect && convSelect.value && planSelect && planSelect.options.length <= 1) {
        // Fallback to normal behavior if just Convocatoria was pre-selected on main page
        loadPlanes(type, convSelect.value);
    }
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
}

function loadPlanes(type, convocatoriaId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const planSelect = document.getElementById('planSelect' + suffix);
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix); // null if didactica/informe/tutorias/acta/diploma
    const grupoSelect = document.getElementById('grupoSelect' + suffix); // null if not didactica/informe/tutorias/acta/diploma/informe_alumno
    
    // Reset options
    planSelect.innerHTML = '<option value="">-- Selecciona Plan --</option>';
    planSelect.disabled = true;
    
    accionSelect.innerHTML = '<option value="">-- Primero elige Plan --</option>';
    accionSelect.disabled = true;
    
    if (alumnoSelect) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        alumnoSelect.disabled = true;
        loadedData[type].alumnos = [];
    }
    if (grupoSelect) {
        grupoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        grupoSelect.disabled = true;
        loadedData[type].grupos = [];
    }
    
    loadedData[type].context = null;
    
    if (!convocatoriaId) return;
    
    fetch(`api_documentos_cascade.php?action=get_planes&convocatoria_id=${convocatoriaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(p => {
                    let opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}`;
                    planSelect.appendChild(opt);
                });
                planSelect.disabled = false;
            } else {
                planSelect.innerHTML = '<option value="">-- No hay planes registrados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching planes:', err);
            alert('Error al cargar planes.');
        });
}

function loadAcciones(type, planId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix);
    const grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    accionSelect.innerHTML = '<option value="">-- Selecciona Acción Formativa --</option>';
    accionSelect.disabled = true;
    
    if (alumnoSelect) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        alumnoSelect.disabled = true;
        loadedData[type].alumnos = [];
    }
    if (grupoSelect) {
        grupoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        grupoSelect.disabled = true;
        loadedData[type].grupos = [];
    }
    
    loadedData[type].context = null;
    
    if (!planId) return;
    
    fetch(`api_documentos_cascade.php?action=get_acciones&plan_id=${planId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(af => {
                    let opt = document.createElement('option');
                    opt.value = af.id;
                    opt.textContent = `${af.num_accion ? '#' + af.num_accion + ' - ' : ''}${af.titulo}`;
                    accionSelect.appendChild(opt);
                });
                accionSelect.disabled = false;
            } else {
                accionSelect.innerHTML = '<option value="">-- No hay acciones registradas --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching acciones:', err);
            alert('Error al cargar acciones formativas.');
        });
}

function loadAlumnos(type, accionId) {
    const alumnoSelect = document.getElementById(type === 'recibi' ? 'alumnoSelect' : 'alumnoSelectAnexo');
    
    alumnoSelect.innerHTML = '<option value="">-- Buscando alumnos... --</option>';
    alumnoSelect.disabled = true;
    
    loadedData[type].alumnos = [];
    loadedData[type].context = null;
    
    if (!accionId) {
        alumnoSelect.innerHTML = '<option value="">-- Primero elige Acción Formativa --</option>';
        return;
    }
    
    fetch(`api_documentos_cascade.php?action=get_alumnos&accion_id=${accionId}`)
        .then(res => res.json())
        .then(data => {
            loadedData[type].alumnos = data.alumnos || [];
            loadedData[type].context = data.context || null;
            
            alumnoSelect.innerHTML = '';
            
            // Default option
            let defOpt = document.createElement('option');
            defOpt.value = '';
            defOpt.textContent = type === 'recibi' ? '-- Todos los alumnos (Generación Masiva) --' : '-- Todos los alumnos matriculados --';
            alumnoSelect.appendChild(defOpt);
            
            if (loadedData[type].alumnos.length > 0) {
                loadedData[type].alumnos.forEach(a => {
                    let opt = document.createElement('option');
                    opt.value = a.id;
                    
                    let nom = `${a.primer_apellido || ''} ${a.segundo_apellido || ''}`.trim() + `, ${a.nombre}`;
                    opt.textContent = `${nom} (${a.dni})`;
                    
                    // Set extra details
                    opt.setAttribute('data-nombre', `${a.nombre} ${a.primer_apellido || ''} ${a.segundo_apellido || ''}`.trim());
                    opt.setAttribute('data-dni', a.dni);
                    
                    alumnoSelect.appendChild(opt);
                });
                alumnoSelect.disabled = false;
            } else {
                alumnoSelect.innerHTML = '<option value="">-- No hay alumnos matriculados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching alumnos:', err);
            alert('Error al cargar alumnos.');
        });
}

function loadGrupos(type, accionId) {
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : (type === 'tutorias' ? 'Tutorias' : (type === 'acta' ? 'Acta' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'diploma' ? 'Diploma' : (type === 'xml' ? 'Xml' : (type === 'xml_encuestas' ? 'XmlEnc' : 'Anexo'))))))));
    const grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    grupoSelect.innerHTML = type === 'xml_encuestas' ? '<option value="">-- Exportar toda la Acción Formativa --</option>' : '<option value="">-- Selecciona Grupo --</option>';
    grupoSelect.disabled = true;
    loadedData[type].grupos = [];
    
    if (!accionId) return;
    
    // For XML surveys and Informe Alumno we only need groups (wait, informe_alumno needs both, but 'recibi' doesn't do loadAlumnos until loadGrupos is missing. Actually wait, we should fetch alumnos for informe_alumno!)
    // Wait, loadGrupos in documentacion.php is called when accion_id changes. Then we must define loadAlumnos!
    
    fetch(`api_documentos_cascade.php?action=get_grupos&accion_id=${accionId}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                loadedData[type].grupos = data;
                data.forEach(g => {
                    let opt = document.createElement('option');
                    opt.value = g.id;
                    opt.textContent = `Grupo ${g.numero_grupo}`;
                    grupoSelect.appendChild(opt);
                });
                grupoSelect.disabled = false;
            } else {
                grupoSelect.innerHTML = '<option value="">-- No hay grupos registrados --</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching grupos:', err);
            alert('Error al cargar grupos.');
        });
}

function goToDidactica() {
    let grupoSelect = document.getElementById('grupoSelectDidactica');
    let grupoId = grupoSelect.value;
    if (!grupoId) {
        alert("Por favor, selecciona un grupo.");
        return;
    }
    window.location.href = `documentacion_didactica.php?grupo_id=${grupoId}`;
}

function generateInformeGrupoPDF() {
    let grupoSelect = document.getElementById('grupoSelectInforme');
    let grupoId = grupoSelect.value;
    if (!grupoId) {
        alert("Por favor, selecciona un grupo.");
        return;
    }
    window.location.href = `pdf_informe_evaluaciones.php?grupo_id=${grupoId}`;
    closeModal();
}

function generateRecibiPDF() {
    let selectAccion = document.getElementById('accionSelect');
    let selectAlumno = document.getElementById('alumnoSelect');
    
    let accionId = selectAccion.value;
    let alumnoId = selectAlumno.value;
    
    if (!accionId) {
        alert("Por favor, selecciona una acción formativa válida.");
        return;
    }
    
    window.location.href = `pdf_recibi_material.php?accion_id=${accionId}&alumno_id=${alumnoId}`;
    closeModal();
}

function generateBienvenidaPDF() {
    let selectAccion = document.getElementById('accionSelect_bienvenida');
    let selectAlumno = document.getElementById('alumnoSelect_bienvenida');
    
    let accionId = selectAccion.value;
    let alumnoId = selectAlumno.value;
    
    if (!accionId) {
        alert("Por favor, selecciona una acción formativa válida.");
        return;
    }
    
    window.location.href = `pdf_hoja_bienvenida.php?accion_id=${accionId}&alumno_id=${alumnoId}`;
    closeModal();
}

function generateAnexo1PDF() {
    let select = document.getElementById('alumnoSelectAnexo');
    let selectAccion = document.getElementById('accionSelectAnexo');
    let alumnoId = select.value;
    let accionId = selectAccion.value;
    
    if (!accionId) {
        alert("Por favor, selecciona al menos una acción formativa.");
        return;
    }
    
    // Mostramos un indicador de carga porque puede tardar si son muchos alumnos
    const btn = document.querySelector("#anexoModal .btn-primary");
    const originalText = btn.innerText;
    btn.innerText = "Generando PDF, por favor espera...";
    btn.disabled = true;

    fetch(`api_anexo1_html.php?accion_id=${accionId}&alumno_id=${alumnoId}`)
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        return response.text();
    })
    .then(htmlStr => {
        if (htmlStr.includes("SQL ERROR:")) {
            throw new Error(htmlStr.replace(/(<([^>]+)>)/gi, ""));
        }
        let fname = alumnoId ? `Anexo1_Alumno_${alumnoId}.pdf` : `Anexo1_Todos.pdf`;
        
        // Creamos un contenedor oculto para que html2canvas pueda renderizar
        const container = document.createElement('div');
        container.style.position = 'absolute';
        container.style.top = '0';
        container.style.left = '0';
        container.style.width = '800px';
        container.style.zIndex = '-9999';
        container.innerHTML = htmlStr;
        
        // Temporarily remove overflow-x: hidden from body to prevent clipping
        const origOverflowX = document.body.style.overflowX;
        document.body.style.overflowX = 'visible';
        
        document.body.appendChild(container);
        
        const students = container.querySelectorAll('.student-wrapper');
        
        if (students.length > 0) {
            // Configuración base de html2pdf
            const opt = {
                margin:       10, // mm
                filename:     fname,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false, windowWidth: 800 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: 'css' }
            };
            
            // Iniciamos la generación con el primer estudiante
            let worker = html2pdf().set(opt).from(students[0]).toPdf();
            
            // Añadimos el resto de estudiantes encadenando páginas
            for (let i = 1; i < students.length; i++) {
                worker = worker.get('pdf').then(pdf => {
                    pdf.addPage();
                }).from(students[i]).toContainer().toCanvas().toPdf();
            }
            
            // Finalmente guardamos el documento
            worker.save().then(() => {
                document.body.removeChild(container);
                document.body.style.overflowX = origOverflowX;
                btn.innerText = originalText;
                btn.disabled = false;
                closeModal();
            }).catch(e => {
                console.error(e);
                alert("Error al generar PDF: " + e.message);
                document.body.removeChild(container);
                document.body.style.overflowX = origOverflowX;
                btn.innerText = originalText;
                btn.disabled = false;
            });
        } else {
            alert("No hay alumnos para generar.");
            document.body.removeChild(container);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error fetching Anexo 1 HTML:', error);
        alert("Error: " + error.message);
        btn.innerText = originalText;
        btn.disabled = false;
    });
}

// Generate Tutorías Excel Export
function generateTutoriasExcel() {
    let accionId = document.getElementById('accionSelectTutorias').value;
    let grupoId = document.getElementById('grupoSelectTutorias').value;

    if (!accionId || !grupoId) {
        alert("Por favor selecciona Acción Formativa y Grupo.");
        return;
    }

    // Redirect to the excel generation endpoint
    window.location.href = `excel_informe_tutorias.php?accion_id=${accionId}&grupo_id=${grupoId}`;
    closeModal();
}
// Generate Acta de Evaluación Final
function generateActaEvaluacionPDF() {
    let accionId = document.getElementById('accionSelectActa').value;
    let grupoId = document.getElementById('grupoSelectActa').value;

    if (!accionId || !grupoId) {
        alert("Por favor selecciona Acción Formativa y Grupo.");
        return;
    }

    // Redirect to the acta generation endpoint
    window.open(`pdf_acta_evaluacion.php?accion_id=${accionId}&grupo_id=${grupoId}`, '_blank');
    closeModal();
}

function loadAlumnosPorGrupo(type, grupoId) {
    let suffix = type === 'recibi' ? '' : (type === 'informe_alumno' ? 'InformeAlumno' : (type === 'anexo' ? 'Anexo' : ''));
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix);
    if (!alumnoSelect) return;
    
    alumnoSelect.innerHTML = '<option value="">-- Selecciona Alumno --</option>';
    alumnoSelect.disabled = true;

    if (!grupoId) return;

    fetch(`api_get_alumnos_by_grupo.php?grupo_id=${grupoId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                data.alumnos.forEach(a => {
                    alumnoSelect.innerHTML += `<option value="${a.id}">${a.apellidos}, ${a.nombre}</option>`;
                });
                alumnoSelect.disabled = false;
            } else {
                alert('Error al cargar alumnos: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión al cargar alumnos.');
        });
}

// Generate Informe Seguimiento Alumno
function generateInformeAlumnoPDF() {
    let accionId = document.getElementById('accionSelectInformeAlumno').value;
    let grupoId = document.getElementById('grupoSelectInformeAlumno').value;
    let alumnoId = document.getElementById('alumnoSelectInformeAlumno').value;

    if (!accionId || !grupoId || !alumnoId) {
        alert("Por favor selecciona Acción Formativa, Grupo y Alumno.");
        return;
    }

    window.open(`pdf_informe_alumno.php?accion_id=${accionId}&grupo_id=${grupoId}&alumno_id=${alumnoId}`, '_blank');
    closeModal();
}

function generateDiplomaList() {
    const accionId = document.getElementById('accionSelectDiploma').value;
    const grupoId = document.getElementById('grupoSelectDiploma').value;
    
    if (!accionId || !grupoId) {
        alert('Por favor, selecciona una acción y un grupo.');
        return;
    }

    window.location.href = `diplomas.php?accion_id=${accionId}&grupo_id=${grupoId}`;
}

    function generateXML() {
        const accionId = document.getElementById('accionSelectXml').value;
        const grupoId = document.getElementById('grupoSelectXml').value;
        
        if (!accionId || !grupoId) {
            alert('Por favor, selecciona una acción y un grupo.');
            return;
        }

        window.location.href = `export_xml_grupo.php?accion_id=${accionId}&grupo_id=${grupoId}`;
    }

    function generateXMLEncuestas() {
        const accionId = document.getElementById('accionSelectXmlEnc').value;
        const grupoId = document.getElementById('grupoSelectXmlEnc').value;
        
        if (!accionId) {
            alert('Por favor, selecciona al menos una acción formativa.');
            return;
        }

        let url = `exportar_encuestas_xml.php?accion_id=${accionId}`;
        if (grupoId) {
            url += `&grupo_id=${grupoId}`;
        }
        
        window.location.href = url;
    }
</script>
</body>
</html>
