<?php
// envio_claves.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Solo administradores y tutores pueden gestionar el envío de claves
if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COORD])) {
    header("Location: home.php");
    exit();
}

$current_page = 'envio_claves.php';

// Cargar combos reales de la DB
$planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$modalidades = ['Teleformación', 'Presencial', 'Mixta'];
$estados = ['Activo', 'Admitido', 'Finalizado', 'Baja', 'Inscrito'];
$comerciales = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$tutores = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda en la base de datos real
$alumnos_list = [];
$ha_buscado = isset($_GET['buscar']);

if ($ha_buscado) {
    $where = ["1=1"];
    $params = [];

    if (!empty($_GET['curso'])) {
        $where[] = "(c.nombre_largo LIKE ? OR c.nombre_corto LIKE ?)";
        $params[] = '%' . trim($_GET['curso']) . '%';
        $params[] = '%' . trim($_GET['curso']) . '%';
    }
    if (!empty($_GET['grupo'])) {
        $where[] = "g.numero_grupo LIKE ?";
        $params[] = '%' . trim($_GET['grupo']) . '%';
    }
    if (!empty($_GET['plan'])) {
        $where[] = "af.plan_id = ?";
        $params[] = (int)$_GET['plan'];
    }
    if (!empty($_GET['modalidad'])) {
        $where[] = "COALESCE(af.modalidad, g.modalidad) = ?";
        $params[] = trim($_GET['modalidad']);
    }
    if (!empty($_GET['estado'])) {
        $where[] = "m.estado LIKE ?";
        $params[] = '%' . trim($_GET['estado']) . '%';
    }
    if (!empty($_GET['claves_env'])) {
        if ($_GET['claves_env'] === 'S') {
            $where[] = "m.envio_claves = 1";
        } elseif ($_GET['claves_env'] === 'N') {
            $where[] = "(m.envio_claves IS NULL OR m.envio_claves = 0)";
        }
    }
    if (!empty($_GET['has_email'])) {
        if ($_GET['has_email'] === 'S') {
            $where[] = "(a.email IS NOT NULL AND a.email != '')";
        } elseif ($_GET['has_email'] === 'N') {
            $where[] = "(a.email IS NULL OR a.email = '')";
        }
    }

    $sql = "
        SELECT m.id as matricula_id, m.envio_claves, m.fecha_claves, m.estado as matricula_estado,
               a.id as alumno_id, CONCAT(a.primer_apellido, ' ', COALESCE(a.segundo_apellido, ''), ', ', a.nombre) as alumno_nombre_ap,
               a.nombre as alumno_nombre, a.dni, a.email, a.plat_usuario, a.plat_clave,
               g.id as grupo_id, g.numero_grupo, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin,
               af.num_accion, p.nombre as plan_nombre, c.nombre_largo as curso_titulo, c.nombre_corto as curso_codigo,
               e.nombre as empresa_nombre
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c ON af.curso_id = c.id
        LEFT JOIN planes p ON af.plan_id = p.id
        LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY a.primer_apellido ASC, a.nombre ASC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alumnos_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío de Claves - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 2rem; }

        .breadcrumb {
            background-color: #f8fafc;
            padding: 12px 20px;
            border-radius: 4px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 1.5rem;
        }

        .form-group-col {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group-col label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.85rem;
        }

        .form-control {
            font-size: 0.85rem;
            padding: 6px 10px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
            width: 100%;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.5rem;
            margin-top: 1rem;
        }

        .btn-buscar {
            background: #2563eb;
            color: white;
            border: 1px solid #1d4ed8;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-buscar:hover { background: #1d4ed8; }

        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bulk-actions select {
            width: 250px;
        }

        /* Tabla densa */
        .table-container {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            position: relative;
        }

        .table-custom {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }

        .table-custom th {
            padding: 10px 8px;
            text-align: left;
            color: #fff;
            font-weight: 700;
            background: #333;
            border-bottom: 2px solid #000;
            white-space: nowrap;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            color: #334155;
            white-space: nowrap;
        }

        .table-custom tr:hover { background-color: #f8fafc; }

        .badge-status {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-yes { background: #dcfce7; color: #166534; }
        .status-no { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a> / 
                <a href="formacion_profesional.php">Formación</a> / 
                Envío de claves a plataforma
            </div>

            <div class="search-card">
                <form method="GET">
                    <input type="hidden" name="buscar" value="1">
                    
                    <div class="search-grid">
                        <div class="form-group-col" style="grid-column: span 2;">
                            <label>Curso</label>
                            <input type="text" name="curso" class="form-control" value="<?= htmlspecialchars($_GET['curso'] ?? '') ?>" placeholder="Nombre del curso...">
                        </div>
                        <div class="form-group-col">
                            <label>Código grupo</label>
                            <input type="text" name="grupo" class="form-control" value="<?= htmlspecialchars($_GET['grupo'] ?? '') ?>">
                        </div>
                        <div class="form-group-col">
                            <label>Plan</label>
                            <select name="plan" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($_GET['plan'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control">
                                <option value="">- Todas -</option>
                                <?php foreach($modalidades as $m): ?>
                                    <option value="<?= $m ?>" <?= ($_GET['modalidad'] ?? '') == $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-grid">
                        <div class="form-group-col">
                            <label>Estado</label>
                            <select name="estado" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($estados as $e): ?>
                                    <option value="<?= $e ?>" <?= ($_GET['estado'] ?? '') == $e ? 'selected' : '' ?>><?= $e ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Claves enviadas?</label>
                            <select name="claves_env" class="form-control">
                                <option value="">- Todos -</option>
                                <option value="S" <?= ($_GET['claves_env'] ?? '') == 'S' ? 'selected' : '' ?>>Sí</option>
                                <option value="N" <?= ($_GET['claves_env'] ?? '') == 'N' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>E-mail?</label>
                            <select name="has_email" class="form-control">
                                <option value="">- Todos -</option>
                                <option value="S" <?= ($_GET['has_email'] ?? '') == 'S' ? 'selected' : '' ?>>Sí</option>
                                <option value="N" <?= ($_GET['has_email'] ?? '') == 'N' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        
                        <div class="bulk-actions">
                            <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">Con seleccionados:</span>
                            <select id="bulkActionSelect" class="form-control" style="width: 220px;">
                                <option value="">- Elegir acción -</option>
                                <option value="send">Enviar Claves por Email</option>
                            </select>
                            <button type="button" class="btn-buscar" style="padding: 6px 15px; font-size: 0.8rem; background: #64748b; border-color: #475569;" onclick="ejecutarAccionMasiva();">Aceptar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="background: #333; color: white; padding: 10px 15px; font-size: 1rem; font-weight: 700; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center;">
                ALUMNOS Y GESTIÓN DE CLAVES
                <span style="font-size: 0.8rem; font-weight: 400;"><?= count($alumnos_list) ?> registros encontrados</span>
            </div>
            
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this);"></th>
                            <th>Curso</th>
                            <th>Plan</th>
                            <th>Alumno</th>
                            <th>NIF</th>
                            <th>Grupo</th>
                            <th>Empresa</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Claves</th>
                            <th>Fecha Envío</th>
                            <th>Usuario</th>
                            <th>Clave</th>
                            <th>Email</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ha_buscado): ?>
                            <?php if (empty($alumnos_list)): ?>
                                <tr>
                                    <td colspan="15" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                                        No se encontraron matrículas con los filtros seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alumnos_list as $row): ?>
                                    <tr id="row-<?= $row['matricula_id'] ?>">
                                        <td>
                                            <input type="checkbox" class="student-checkbox" value="<?= $row['matricula_id'] ?>" data-nombre="<?= htmlspecialchars($row['alumno_nombre_ap']) ?>" data-envio="<?= (int)($row['envio_claves'] ?? 0) ?>" data-fecha="<?= $row['fecha_claves'] ? date('d/m/Y', strtotime($row['fecha_claves'])) : '' ?>">
                                        </td>
                                        <td><?= htmlspecialchars($row['curso_codigo'] ?? $row['curso_titulo'] ?? 'Curso') ?></td>
                                        <td><?= htmlspecialchars($row['plan_nombre'] ?? 'Plan') ?></td>
                                        <td style="font-weight:700; color:#1e40af;"><?= htmlspecialchars($row['alumno_nombre_ap']) ?></td>
                                        <td><?= htmlspecialchars($row['dni'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($row['numero_grupo'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($row['empresa_nombre'] ?? '—') ?></td>
                                        <td><?= !empty($row['grupo_inicio']) ? date('d/m/Y', strtotime($row['grupo_inicio'])) : '—' ?></td>
                                        <td><?= !empty($row['grupo_fin']) ? date('d/m/Y', strtotime($row['grupo_fin'])) : '—' ?></td>
                                        <td>
                                            <?php if (!empty($row['envio_claves'])): ?>
                                                <span class="badge-status status-yes">SI</span>
                                            <?php else: ?>
                                                <span class="badge-status status-no">NO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fecha-col"><?= !empty($row['fecha_claves']) ? date('d/m/Y', strtotime($row['fecha_claves'])) : '—' ?></td>
                                        <td><?= htmlspecialchars($row['plat_usuario'] ?? '—') ?></td>
                                        <td><?= !empty($row['plat_clave']) ? '******' : '—' ?></td>
                                        <td><?= htmlspecialchars($row['email'] ?? 'Sin e-mail') ?></td>
                                        <td>
                                            <button type="button" class="btn-buscar" style="padding: 4px 10px; font-size: 0.72rem; background: #0284c7; border-color: #0369a1;" onclick="enviarClavesSingle(<?= $row['matricula_id'] ?>, '<?= htmlspecialchars(addslashes($row['alumno_nombre_ap']), ENT_QUOTES) ?>', <?= (int)($row['envio_claves'] ?? 0) ?>, '<?= !empty($row['fecha_claves']) ? date('d/m/Y', strtotime($row['fecha_claves'])) : '' ?>')">
                                                Enviar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                                    Realice una búsqueda para listar los alumnos y gestionar sus claves de acceso.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

<script>
function toggleSelectAll(master) {
    var checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = master.checked;
    });
}

function enviarClavesSingle(matriculaId, alumnoNombre, yaEnviado, fechaEnviado) {
    if (yaEnviado == 1) {
        var msg = "¡ATENCIÓN!\n\nLas claves ya fueron enviadas previamente a " + alumnoNombre + (fechaEnviado ? " el " + fechaEnviado : "") + ".\n\n¿Estás seguro de que deseas volver a enviar las claves por correo electrónico?";
        if (!confirm(msg)) {
            return;
        }
    }

    var defaultSubject = "Acceso a Aula Virtual / Plataforma";
    var defaultBody = "Estimado/a {nombre},\n\nLe enviamos las credenciales de acceso para su curso {curso}:\n\nURL: {url}\nUsuario: {usuario}\nContraseña: {contrasena}\n\nUn cordial saludo.";

    var formData = new FormData();
    formData.append('matricula_id', matriculaId);
    formData.append('subject', defaultSubject);
    formData.append('body', defaultBody);

    fetch('api_send_matricula_keys.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert("✓ " + data.message);
            location.reload();
        } else {
            alert("❌ Error: " + (data.error || "No se pudo enviar el correo."));
        }
    })
    .catch(err => {
        alert("❌ Error de comunicación: " + err);
    });
}

function ejecutarAccionMasiva() {
    var action = document.getElementById('bulkActionSelect').value;
    if (!action) {
        alert("Por favor, selecciona una acción a realizar.");
        return;
    }

    var checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert("Por favor, selecciona al menos un alumno de la lista.");
        return;
    }

    if (action === 'send') {
        var countTotal = checkedBoxes.length;
        var countAlreadySent = 0;
        var alreadySentNames = [];

        checkedBoxes.forEach(function(cb) {
            if (cb.getAttribute('data-envio') == '1') {
                countAlreadySent++;
                var name = cb.getAttribute('data-nombre');
                var date = cb.getAttribute('data-fecha');
                alreadySentNames.push(name + (date ? " (" + date + ")" : ""));
            }
        });

        if (countAlreadySent > 0) {
            var warnMsg = "¡ATENCIÓN: CLAVES PREVIAMENTE ENVIADAS!\n\n";
            warnMsg += "Has seleccionado " + countTotal + " alumno(s), de los cuales " + countAlreadySent + " YA RECIBIERON sus claves por correo previamente:\n\n";
            warnMsg += alreadySentNames.slice(0, 5).join("\n");
            if (alreadySentNames.length > 5) {
                warnMsg += "\n...y " + (alreadySentNames.length - 5) + " más.";
            }
            warnMsg += "\n\n¿Estás seguro de que deseas volver a enviar las claves por correo electrónico a estos alumnos?";
            
            if (!confirm(warnMsg)) {
                return;
            }
        } else {
            if (!confirm("¿Deseas enviar las claves por e-mail a los " + countTotal + " alumno(s) seleccionados?")) {
                return;
            }
        }

        // Ejecución en lote
        var defaultSubject = "Acceso a Aula Virtual / Plataforma";
        var defaultBody = "Estimado/a {nombre},\n\nLe enviamos las credenciales de acceso para su curso {curso}:\n\nURL: {url}\nUsuario: {usuario}\nContraseña: {contrasena}\n\nUn cordial saludo.";

        var enviados = 0;
        var errores = 0;
        var promises = [];

        checkedBoxes.forEach(function(cb) {
            var matriculaId = cb.value;
            var formData = new FormData();
            formData.append('matricula_id', matriculaId);
            formData.append('subject', defaultSubject);
            formData.append('body', defaultBody);

            var p = fetch('api_send_matricula_keys.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    enviados++;
                } else {
                    errores++;
                }
            })
            .catch(err => {
                errores++;
            });
            promises.push(p);
        });

        Promise.all(promises).then(function() {
            alert("✓ Proceso completado:\n\n- Envíos exitosos: " + enviados + "\n- Fallidos: " + errores);
            location.reload();
        });
    }
}
</script>
</body>
</html>
