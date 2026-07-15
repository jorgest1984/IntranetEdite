<?php
// documentacion.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$convocatoria_id = isset($_GET['convocatoria_id']) ? intval($_GET['convocatoria_id']) : 0;

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

                <!-- Diploma Provisional -->
                <div class="doc-card" style="opacity: 0.6;">
                    <svg class="doc-icon" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    <div class="doc-title">Diploma / Certificado</div>
                    <div class="doc-desc">Certificado de aprovechamiento con las horas y fechas de la convocatoria.</div>
                    <button class="btn" disabled>Próximamente</button>
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
    }
};

function openDocModal(type) {
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
    
    let modalId = type === 'recibi' ? 'docModal' : (type === 'didactica' ? 'didacticaModal' : (type === 'informe' ? 'informeModal' : 'anexoModal'));
    let modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.classList.add('active');
    
    // Auto-load cascade if Convocatoria is pre-selected on main page
    let convSelectId = 'convocatoriaSelect' + (type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : 'Anexo')));
    let planSelectId = 'planSelect' + (type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : 'Anexo')));
    
    let convSelect = document.getElementById(convSelectId);
    if (convSelect && convSelect.value) {
        let planSelect = document.getElementById(planSelectId);
        if (planSelect && planSelect.options.length <= 1) {
            loadPlanes(type, convSelect.value);
        }
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
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : 'Anexo'));
    const planSelect = document.getElementById('planSelect' + suffix);
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix); // null if didactica/informe
    const grupoSelect = document.getElementById('grupoSelect' + suffix); // null if not didactica/informe
    
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
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : 'Anexo'));
    const accionSelect = document.getElementById('accionSelect' + suffix);
    const alumnoSelect = document.getElementById('alumnoSelect' + suffix);
    const grupoSelect = document.getElementById('grupoSelect' + suffix); // null if not didactica
    
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
    let suffix = type === 'recibi' ? '' : (type === 'didactica' ? 'Didactica' : (type === 'informe' ? 'Informe' : 'Anexo'));
    const grupoSelect = document.getElementById('grupoSelect' + suffix);
    
    grupoSelect.innerHTML = '<option value="">-- Selecciona Grupo --</option>';
    grupoSelect.disabled = true;
    loadedData[type].grupos = [];
    
    if (!accionId) return;
    
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
        // Create a temporary container
        const container = document.createElement('div');
        container.style.position = 'absolute';
        container.style.left = '-9999px';
        container.innerHTML = htmlStr;
        document.body.appendChild(container);
        
        let fname = alumnoId ? `Anexo1_Alumno_${alumnoId}.pdf` : `Anexo1_Todos.pdf`;
        
        // Configuración para html2pdf
        const opt = {
            margin:       0,
            filename:     fname,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generate PDF
        html2pdf().set(opt).from(container).save().then(() => {
            document.body.removeChild(container);
            btn.innerText = originalText;
            btn.disabled = false;
            closeModal();
        }).catch(e => {
            document.body.removeChild(container);
            console.error(e);
            alert("Error al generar PDF.");
            btn.innerText = originalText;
            btn.disabled = false;
        });
    })
    .catch(error => {
        console.error('Error fetching Anexo 1 HTML:', error);
        alert("Error: " + error.message);
        btn.innerText = originalText;
        btn.disabled = false;
    });
}
</script>
</body>
</html>
