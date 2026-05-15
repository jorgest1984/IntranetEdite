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
if (isset($_POST['add_alumno_id'])) {
    $alumno_id = (int)$_POST['add_alumno_id'];
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO matriculas (alumno_id, grupo_id, convocatoria_id, estado, fecha_matricula) 
                               VALUES (?, ?, ?, 'Inscrito', CURDATE())");
        $stmt->execute([$alumno_id, $grupo_id, $accion['plan_id']]); // Usamos plan_id como fallback de convocatoria si es necesario
        header("Location: gestion_matriculas.php?af_id=$af_id&success=1");
        exit();
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Procesar Baja de Alumno
if (isset($_GET['remove_id'])) {
    $matricula_id = (int)$_GET['remove_id'];
    $pdo->prepare("DELETE FROM matriculas WHERE id = ? AND grupo_id = ?")->execute([$matricula_id, $grupo_id]);
    header("Location: gestion_matriculas.php?af_id=$af_id&removed=1");
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
                    🚀 Volcar al Aula Virtual
                </button>
                <a href="acciones_formativas.php?plan_id=<?= $accion['plan_id'] ?>" class="btn" style="background: #f1f5f9; color: #1e3a8a; text-decoration:none; padding: 10px 20px; border-radius:8px; font-weight:700;">Volver</a>
            </div>
        </header>

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
                <div style="background: #f8fafc; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; position: sticky; top: 20px;">
                    <h3 style="margin-top: 0; font-size: 1rem; color: #1e3a8a;">Matricular Alumno</h3>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 20px;">Busca por nombre o DNI para añadir al curso.</p>
                    
                    <div class="search-box">
                        <input type="text" id="studentSearch" class="form-control" placeholder="Buscar por nombre o DNI..." style="width: 100%; padding: 12px; border-radius: 8px; border: 2px solid #e2e8f0; font-size: 0.9rem;">
                        <div id="searchResults" class="search-results"></div>
                    </div>

                    <form id="addForm" method="POST">
                        <input type="hidden" name="add_alumno_id" id="selectedAlumnoId">
                        <button type="submit" id="enrollBtn" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; background: #1e3a8a; border: none; font-weight: 700; margin-bottom: 20px; opacity: 0.5; pointer-events: none;">
                            Matricular Alumno
                        </button>
                    </form>

                    <div style="background: #eff6ff; padding: 15px; border-radius: 12px; border: 1px solid #bfdbfe; font-size: 0.8rem; color: #1e40af;">
                        <strong>Tip:</strong> Al matricular a un alumno, el sistema lo preparará automáticamente para la próxima sincronización con Moodle.
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const searchInput = document.getElementById('studentSearch');
const resultsDiv = document.getElementById('searchResults');
const addForm = document.getElementById('addForm');
const selectedIdInput = document.getElementById('selectedAlumnoId');
const enrollBtn = document.getElementById('enrollBtn');

searchInput.addEventListener('input', function() {
    const q = this.value;
    
    // Clear selection if input changes
    selectedIdInput.value = '';
    enrollBtn.style.opacity = '0.5';
    enrollBtn.style.pointerEvents = 'none';

    if (q.length < 3) {
        resultsDiv.style.display = 'none';
        return;
    }

    fetch('api_buscar_alumnos.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            resultsDiv.innerHTML = '';
            if (data.length > 0) {
                data.forEach(a => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<strong>${a.nombre} ${a.primer_apellido || ''}</strong><br><small>${a.dni}</small>`;
                    div.onclick = () => {
                        selectedIdInput.value = a.id;
                        searchInput.value = `${a.nombre} ${a.primer_apellido || ''} (${a.dni})`;
                        resultsDiv.style.display = 'none';
                        enrollBtn.style.opacity = '1';
                        enrollBtn.style.pointerEvents = 'auto';
                    };
                    resultsDiv.appendChild(div);
                });
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div style="padding:10px; color:#94a3b8;">No se encontraron alumnos.</div>';
                resultsDiv.style.display = 'block';
            }
        });
});

document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
        resultsDiv.style.display = 'none';
    }
});
</script>
</body>
</html>
