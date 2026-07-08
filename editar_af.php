<?php
// editar_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID no válido");

$success = '';
$error = '';

$familias = [
    'Actividades Físicas y Deportivas',
    'Actividades y Competencias Transversales',
    'Administración y Gestión',
    'Agraria',
    'Artes Gráficas',
    'Artes y Artesanías',
    'Comercio y Marketing',
    'Edificación y Obra Civil',
    'Electricidad y Electrónica',
    'Energía y Agua',
    'Fabricación Mecánica',
    'Hostelería y Turismo',
    'Imagen Personal',
    'Imagen y Sonido',
    'Industrias Alimentarias',
    'Industrias Extractivas',
    'Informática y Comunicaciones',
    'Instalación y Mantenimiento',
    'Inteligencia Artificial y Data',
    'Madera, Mueble y Corcho',
    'Marítimo-Pesquera',
    'Química',
    'Sanidad',
    'Seguridad y Medio Ambiente',
    'Servicios Socioculturales y a la Comunidad',
    'Textil, Confección y Piel',
    'Transporte y Mantenimiento de Vehículos',
    'Vidrio y Cerámica',
    'Transversal',
    'Certificado de Profesionalidad',
    'Prevención de Riesgos Laborales',
    'SAP',
    'Seguridad Privada'
];

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/uploads/post_af_debug.txt', print_r($_POST, true));
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Error de seguridad (CSRF). Por favor, refresque la página e inténtelo de nuevo.";
        file_put_contents(__DIR__ . '/uploads/save_log.txt', "CSRF FAILED! POST token: '$csrf_token', SESSION token: '" . ($_SESSION['csrf_token'] ?? '') . "'");
    } else {
        $fields = ['duracion', 'modalidad', 'prioridad', 'estado', 'familia_profesional', 'objetivos', 'contenidos', 'contenidos_breves', 'notas_gestion'];
        $data = [];
        $sql = "UPDATE acciones_formativas SET ";
        $sets = [];
        foreach ($fields as $f) {
            $sets[] = "$f = ?";
            $data[] = $_POST[$f] ?? '';
        }
        $data[] = $id;
        $sql .= implode(", ", $sets) . " WHERE id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            // Actualizar moodle_id en la tabla cursos vinculada a esta Acción Formativa
            $moodle_id = isset($_POST['moodle_id']) && $_POST['moodle_id'] !== '' ? (int)$_POST['moodle_id'] : null;
            
            $logMsg = "Executing update: moodle_id = " . var_export($moodle_id, true) . ", id = $id\n";
            
            $stmtC = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = (SELECT curso_id FROM acciones_formativas WHERE id = ?)");
            $stmtC->execute([$moodle_id, $id]);
            
            $logMsg .= "Rows affected: " . $stmtC->rowCount() . "\n";
            file_put_contents(__DIR__ . '/uploads/save_log.txt', $logMsg);
            
            $success = "Parámetros actualizados con éxito.";
        } catch (Exception $e) {
            $error = $e->getMessage();
            file_put_contents(__DIR__ . '/uploads/save_log.txt', "ERROR: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        }
    }
}

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT af.*, c.nombre_largo as titulo, c.moodle_id as curso_moodle_id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE af.id = ?");
$stmt->execute([$id]);
$af = $stmt->fetch();

if (!$af) die("No se encontró la Acción Formativa");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Acción Formativa - <?= htmlspecialchars($af['titulo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .edit-container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #1e3a8a; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 0.9rem; }
        .form-control:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .full-width { grid-column: span 2; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>
    <main class="main-content">
        <div class="edit-container">
            <header style="margin-bottom: 35px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="background: #eff6ff; color: #1e40af; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">Parámetros del Curso</span>
                    <h1 style="margin: 10px 0; color: #1e293b; font-size: 1.5rem;"><?= htmlspecialchars($af['titulo']) ?></h1>
                    <p style="color: #64748b; font-size: 0.9rem;">ID Acción: #<?= $af['id'] ?> | Plan ID: <?= $af['plan_id'] ?></p>
                </div>
                <a href="acciones_formativas.php?plan_id=<?= $af['plan_id'] ?>" style="text-decoration:none; color:#64748b; font-weight:700; font-size:0.9rem;">✕ Cerrar</a>
            </header>

            <?php if ($success): ?>
                <div style="background: #f0fdf4; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #bbf7d0; font-weight: 600;">
                    ✓ <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Duración (Horas)</label>
                        <input type="number" name="duracion" class="form-control" value="<?= $af['duracion'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Modalidad</label>
                        <select name="modalidad" class="form-control">
                            <option value="TELEFORMACIÓN" <?= $af['modalidad'] == 'TELEFORMACIÓN' ? 'selected' : '' ?>>TELEFORMACIÓN</option>
                            <option value="PRESENCIAL" <?= $af['modalidad'] == 'PRESENCIAL' ? 'selected' : '' ?>>PRESENCIAL</option>
                            <option value="MIXTA" <?= $af['modalidad'] == 'MIXTA' ? 'selected' : '' ?>>MIXTA</option>
                            <option value="AULA VIRTUAL" <?= $af['modalidad'] == 'AULA VIRTUAL' ? 'selected' : '' ?>>AULA VIRTUAL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prioridad</label>
                        <select name="prioridad" class="form-control">
                            <option value="Alta" <?= $af['prioridad'] == 'Alta' ? 'selected' : '' ?>>Alta</option>
                            <option value="Media" <?= $af['prioridad'] == 'Media' ? 'selected' : '' ?>>Media</option>
                            <option value="Baja" <?= $af['prioridad'] == 'Baja' ? 'selected' : '' ?>>Baja</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" class="form-control">
                            <option value="ACTIVA" <?= $af['estado'] == 'ACTIVA' ? 'selected' : '' ?>>ACTIVA</option>
                            <option value="EN CURSO" <?= $af['estado'] == 'EN CURSO' ? 'selected' : '' ?>>EN CURSO</option>
                            <option value="FINALIZADA" <?= $af['estado'] == 'FINALIZADA' ? 'selected' : '' ?>>FINALIZADA</option>
                            <option value="PENDIENTE" <?= $af['estado'] == 'PENDIENTE' ? 'selected' : '' ?>>PENDIENTE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Familia Profesional</label>
                        <select name="familia_profesional" class="form-control">
                            <option value=""></option>
                            <?php foreach($familias as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>" <?= ($af['familia_profesional'] ?? '') == $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="color: #ea580c; font-weight: 800;">ID Curso Moodle (Plataforma)</label>
                        <input type="number" name="moodle_id" class="form-control" style="border: 2px solid #fdba74;" value="<?= htmlspecialchars($af['curso_moodle_id'] ?? '') ?>" placeholder="Ej: 48 (Dejar vacío si no existe)">
                    </div>
                    <div class="form-group full-width">
                        <label>Objetivos Generales</label>
                        <textarea name="objetivos" class="form-control rte-textarea" rows="6"><?= htmlspecialchars($af['objetivos'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Contenidos Completos</label>
                        <textarea name="contenidos" class="form-control rte-textarea" rows="10"><?= htmlspecialchars($af['contenidos'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Contenidos Resumidos</label>
                        <textarea name="contenidos_breves" class="form-control rte-textarea" rows="8"><?= htmlspecialchars($af['contenidos_breves'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Notas de Gestión</label>
                        <textarea name="notas_gestion" class="form-control" rows="2" placeholder="Notas internas..."><?= htmlspecialchars($af['notas_gestion'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <button type="submit" class="btn btn-primary" style="padding: 15px 40px; border-radius: 12px; font-weight: 800; background: #1e3a8a; border: none; color: white; cursor: pointer;">
                        Guardar Cambios
                    </button>
                    <a href="acciones_formativas.php?plan_id=<?= $af['plan_id'] ?>" class="btn" style="padding: 15px 30px; text-decoration:none; color:#64748b; font-weight:700;">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="/js/tinymce/tinymce.min.js"></script>
<script>
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '.rte-textarea',
            height: 350,
            menubar: true,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            font_size_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
            content_style: 'body { font-family:Inter,Arial,sans-serif; font-size:14px }'
        });
    } else {
        console.error("TinyMCE no pudo cargar.");
        alert("El editor avanzado no pudo cargar. Compruebe los permisos o recargue forzadamente con Ctrl+F5.");
    }
</script>
</body>
</html>
