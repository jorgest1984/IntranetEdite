<?php
// gestion_matriculas.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

$af_id = (int)($_GET['af_id'] ?? 0);
if (!$af_id) die("ID de Acción Formativa no proporcionado.");

// Obtener datos de la Acción Formativa
$af = $pdo->prepare("SELECT af.*, c.nombre_largo as titulo FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
$af->execute([$af_id]);
$accion = $af->fetch();

if (!$accion) die("Acción Formativa no encontrada.");

// Buscar el grupo asociado (o crear uno por defecto si no existe)
$stmt = $pdo->prepare("SELECT id FROM grupos WHERE accion_id = ? LIMIT 1");
$stmt->execute([$af_id]);
$grupo = $stmt->fetch();

if (!$grupo) {
    // Crear grupo automático para esta acción
    $stmt = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, modalidad, horas) VALUES (?, ?, ?, ?)");
    $stmt->execute([$af_id, 'G1', $accion['modalidad'], $accion['duracion']]);
    $grupo_id = $pdo->lastInsertId();
} else {
    $grupo_id = $grupo['id'];
}

// Procesar Alta de Alumno
if (isset($_POST['add_alumno_id']) || !empty($_POST['student_search_text'])) {
    $alumno_id = (int)($_POST['add_alumno_id'] ?? 0);
    $search_text = trim($_POST['student_search_text'] ?? '');

    try {
        if (!$alumno_id && !empty($search_text)) {
            // Buscar por DNI exacto o nombre exacto si no hay ID
            $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? OR CONCAT(nombre, ' ', primer_apellido) = ? LIMIT 1");
            $stmt->execute([$search_text, $search_text]);
            $found = $stmt->fetch();
            if ($found) {
                $alumno_id = $found['id'];
            } else {
                throw new Exception("No se encontró ningún alumno con ese DNI o nombre exacto.");
            }
        }

        if ($alumno_id) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO matriculas (alumno_id, grupo_id, convocatoria_id, estado, fecha_matricula) 
                                   VALUES (?, ?, ?, 'Inscrito', CURDATE())");
            $stmt->execute([$alumno_id, $grupo_id, $accion['plan_id']]);
            header("Location: gestion_matriculas.php?af_id=$af_id&success=1");
            exit();
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Procesar Baja de Alumno
if (isset($_GET['remove_id'])) {
    $matricula_id = (int)$_GET['remove_id'];
    $moodle_error = null;
    
    // Obtener los IDs de Moodle antes de borrar la matrícula
    try {
        $stmtMat = $pdo->prepare("SELECT m.alumno_id, a.moodle_user_id, c.moodle_id as curso_moodle_id
                                  FROM matriculas m 
                                  JOIN alumnos a ON m.alumno_id = a.id
                                  JOIN grupos g ON m.grupo_id = g.id
                                  JOIN acciones_formativas af ON g.accion_id = af.id
                                  JOIN cursos c ON af.curso_id = c.id
                                  WHERE m.id = ? AND m.grupo_id = ?");
        $stmtMat->execute([$matricula_id, $grupo_id]);
        $mat_info = $stmtMat->fetch(PDO::FETCH_ASSOC);

        if ($mat_info && !empty($mat_info['moodle_user_id']) && !empty($mat_info['curso_moodle_id'])) {
            require_once 'includes/moodle_api.php';
            $moodle = new MoodleAPI($pdo);
            if ($moodle->isConfigured()) {
                try {
                    $moodle->unenrolUser($mat_info['moodle_user_id'], $mat_info['curso_moodle_id']);
                } catch (Exception $moodleEx) {
                    $moodle_error = $moodleEx->getMessage();
                }
            }
        }
    } catch (Exception $e) {
        $moodle_error = $e->getMessage();
    }

    $pdo->prepare("DELETE FROM matriculas WHERE id = ? AND grupo_id = ?")->execute([$matricula_id, $grupo_id]);
    
    $redirectUrl = "gestion_matriculas.php?af_id=$af_id&removed=1";
    if ($moodle_error) {
        $redirectUrl .= "&error=" . urlencode("El alumno se borró localmente, pero falló la desmatriculación en Moodle: " . $moodle_error);
    }
    header("Location: $redirectUrl");
    exit();
}

// Obtener alumnos matriculados
$matriculados = $pdo->prepare("SELECT m.id as matricula_id, a.* FROM matriculas m 
                               JOIN alumnos a ON m.alumno_id = a.id 
                               WHERE m.grupo_id = ? ORDER BY a.nombre ASC");
$matriculados->execute([$grupo_id]);
$alumnos = $matriculados->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos - <?= htmlspecialchars($accion['titulo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .gestion-container { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        .student-card {
            background: white;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .student-card:hover { border-color: #3b82f6; transform: translateX(5px); }
        .search-box { position: relative; margin-bottom: 25px; }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 100;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .search-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        .search-item:hover { background: #eff6ff; }
        .badge-count {
            background: #1e3a8a;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header" style="margin-bottom: 30px;">
            <div class="page-title">
                <span style="color: #64748b; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;">Gestión de Matrículas</span>
                <h1 style="margin: 5px 0;"><?= htmlspecialchars($accion['titulo']) ?></h1>
                <p>Grupo ID: <strong><?= $grupo_id ?></strong> | Modalidad: <strong><?= $accion['modalidad'] ?></strong></p>
            </div>
            <div style="display: flex; gap: 15px;">
                <button onclick="window.location.href='api_sync_moodle.php?id=<?= $af_id ?>'" class="btn btn-primary" style="background: #ea580c; border: none;">
                     Volcar al Aula Virtual
                </button>
                <a href="acciones_formativas.php?plan_id=<?= $accion['plan_id'] ?>" class="btn" style="background: #f1f5f9; color: #1e3a8a; text-decoration:none; padding: 10px 20px; border-radius:8px; font-weight:700;">Volver</a>
            </div>
        </header>

        <?php if (!empty($error) || !empty($_GET['error'])): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; border: 1px solid #fca5a5; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($error ?: $_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) || (isset($_GET['removed']) && !isset($_GET['error']))): ?>
            <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= isset($_GET['success']) ? 'Alumno matriculado correctamente.' : 'Matrícula eliminada correctamente de la Intranet y desmatriculado de Moodle.' ?></span>
            </div>
        <?php endif; ?>

        <div class="gestion-container">
            <div class="enrolled-section">
                <h2 style="font-size: 1.1rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    Alumnos Matriculados <span class="badge-count"><?= count($alumnos) ?></span>
                </h2>
                
                <?php if (empty($alumnos)): ?>
                    <div style="padding: 40px; text-align: center; background: white; border-radius: 16px; border: 2px dashed #e2e8f0; color: #94a3b8;">
                        No hay alumnos matriculados en este curso todavía.
                    </div>
                <?php else: ?>
                    <?php foreach($alumnos as $a): ?>
                        <div class="student-card">
                            <div>
                                <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($a['nombre'] . ' ' . ($a['primer_apellido'] ?? '') . ' ' . ($a['segundo_apellido'] ?? '')) ?></div>
                                <small style="color: #64748b; font-weight: 600;"><?= $a['dni'] ?> | <?= $a['email'] ?></small>
                            </div>
                            <a href="?af_id=<?= $af_id ?>&remove_id=<?= $a['matricula_id'] ?>" 
                               onclick="return confirm('¿Dar de baja a este alumno?')"
                               style="color: #ef4444; padding: 8px; border-radius: 8px; transition: background 0.2s;"
                               title="Dar de baja">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="add-section">
                    <!-- Formulario de Búsqueda -->
                    <div id="searchFormContainer" style="margin-bottom: 25px;">
                        <div class="form-group" style="margin-bottom: 15px; position: relative;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a;">DNI:</label>
                            <input type="text" id="searchDni" class="form-control" placeholder="Ej: 12345678X" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" autocomplete="off">
                            <div id="dniAutocomplete" class="search-results"></div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px; position: relative;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a;">Nombre Completo:</label>
                            <input type="text" id="searchNombre" class="form-control" placeholder="Nombre y apellidos..." style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;" autocomplete="off">
                            <div id="nombreAutocomplete" class="search-results"></div>
                        </div>
                        <button type="button" id="btnBuscar" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; background: #1e3a8a; border: none; font-weight: 700;">
                            🔍 Buscar Alumno
                        </button>
                    </div>

                    <!-- Resultado de Búsqueda -->
                    <div id="searchResultArea" style="display: none; background: white; padding: 20px; border-radius: 12px; border: 2px solid #3b82f6; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(59,130,246,0.1);">
                        <h4 style="margin-top: 0; color: #1e40af; font-size: 0.9rem;">Alumno Encontrado:</h4>
                        <div id="foundStudentInfo" style="margin-bottom: 20px;">
                            <div id="foundName" style="font-weight: 700; color: #1e293b;"></div>
                            <div id="foundDni" style="font-size: 0.85rem; color: #64748b;"></div>
                        </div>
                        
                        <form id="addForm" method="POST">
                            <input type="hidden" name="add_alumno_id" id="selectedAlumnoId">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; background: #059669; border: none; font-weight: 700;">
                                ✅ Matricular Alumno
                            </button>
                        </form>
                        
                        <button type="button" onclick="resetSearch()" style="width: 100%; margin-top: 10px; background: none; border: none; color: #64748b; font-size: 0.8rem; cursor: pointer; text-decoration: underline;">
                            Nueva búsqueda
                        </button>
                    </div>

                    <div id="noResultsMsg" style="display: none; background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 12px; border: 1px solid #fecaca; margin-bottom: 20px; font-size: 0.85rem; text-align: center;">
                        ❌ No se encontró ningún alumno con esos datos.
                    </div>

                    <div style="background: #eff6ff; padding: 15px; border-radius: 12px; border: 1px solid #bfdbfe; font-size: 0.8rem; color: #1e40af;">
                        <strong>Instrucciones:</strong> Escribe en cualquiera de los campos anteriores para activar la búsqueda predictiva al instante.
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const btnBuscar = document.getElementById('btnBuscar');
const searchDni = document.getElementById('searchDni');
const searchNombre = document.getElementById('searchNombre');
const resultArea = document.getElementById('searchResultArea');
const noResultsMsg = document.getElementById('noResultsMsg');
const foundName = document.getElementById('foundName');
const foundDni = document.getElementById('foundDni');
const selectedIdInput = document.getElementById('selectedAlumnoId');

const dniAutocomplete = document.getElementById('dniAutocomplete');
const nombreAutocomplete = document.getElementById('nombreAutocomplete');

function setupAutocomplete(inputEl, resultsEl) {
    let debounceTimer;
    
    inputEl.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const val = this.value.trim();
        
        if (val.length === 0) {
            resultsEl.innerHTML = '';
            resultsEl.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetch(`api_buscar_alumnos.php?q=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(data => {
                    resultsEl.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(a => {
                            const item = document.createElement('div');
                            item.className = 'search-item';
                            
                            let fullName = `${a.nombre} ${a.primer_apellido || ''}`.trim();
                            item.textContent = `${fullName} (${a.dni})`;
                            
                            item.addEventListener('click', function() {
                                selectStudent(a);
                                resultsEl.innerHTML = '';
                                resultsEl.style.display = 'none';
                            });
                            
                            resultsEl.appendChild(item);
                        });
                        resultsEl.style.display = 'block';
                    } else {
                        resultsEl.innerHTML = '<div class="search-item" style="color: #ef4444; cursor: default; padding: 12px 15px;">No hay coincidencias</div>';
                        resultsEl.style.display = 'block';
                    }
                })
                .catch(err => console.error("Error autocomplete:", err));
        }, 150); // Fast, responsive debounce
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== inputEl && e.target !== resultsEl && !resultsEl.contains(e.target)) {
            resultsEl.style.display = 'none';
        }
    });
}

function selectStudent(a) {
    foundName.innerText = `${a.nombre} ${a.primer_apellido || ''}`;
    foundDni.innerText = `DNI: ${a.dni}`;
    selectedIdInput.value = a.id;
    
    resultArea.style.display = 'block';
    document.getElementById('searchFormContainer').style.display = 'none';
    noResultsMsg.style.display = 'none';
}

setupAutocomplete(searchDni, dniAutocomplete);
setupAutocomplete(searchNombre, nombreAutocomplete);

btnBuscar.addEventListener('click', function() {
    const dni = searchDni.value.trim();
    const nombre = searchNombre.value.trim();
    
    const q = dni || nombre;
    
    if (q.length === 0) {
        alert("Por favor, introduce el DNI o el nombre para buscar.");
        return;
    }

    this.disabled = true;
    this.innerHTML = "⌛ Buscando...";

    fetch(`api_buscar_alumnos.php?q=${encodeURIComponent(q)}&exact=1`)
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            this.innerHTML = "🔍 Buscar Alumno";
            
            resultArea.style.display = 'none';
            noResultsMsg.style.display = 'none';

            if (data && data.length > 0) {
                selectStudent(data[0]);
            } else {
                noResultsMsg.style.display = 'block';
            }
        })
        .catch(err => {
            this.disabled = false;
            this.innerHTML = "🔍 Buscar Alumno";
            alert("Error al realizar la búsqueda. Por favor, inténtalo de nuevo.");
            console.error(err);
        });
});

function resetSearch() {
    document.getElementById('searchFormContainer').style.display = 'block';
    resultArea.style.display = 'none';
    noResultsMsg.style.display = 'none';
    searchDni.value = '';
    searchNombre.value = '';
    dniAutocomplete.innerHTML = '';
    nombreAutocomplete.innerHTML = '';
}
</script>
</body>
</html>
