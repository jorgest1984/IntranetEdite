<?php
// importar_matriculas_rapido.php - Importación y Matriculación Rápida de Alumnos
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';
$resultados = [];
$total_procesados = 0;
$total_alumnos_creados = 0;
$total_alumnos_actualizados = 0;
$total_empresas_creadas = 0;
$total_matriculados = 0;
$total_moodle_synced = 0;

// Cargar Acciones Formativas y Grupos
$stmtAAFF = $pdo->query("SELECT af.id, af.titulo, af.abreviatura, af.num_accion, af.id_plataforma, c.nombre_corto as curso_codigo
                         FROM acciones_formativas af 
                         LEFT JOIN cursos c ON af.curso_id = c.id 
                         ORDER BY af.id DESC");
$acciones_formativas = $stmtAAFF ? $stmtAAFF->fetchAll(PDO::FETCH_ASSOC) : [];

$stmtGrupos = $pdo->query("SELECT g.id, g.numero_grupo, g.accion_id, af.titulo as accion_titulo, af.abreviatura
                           FROM grupos g 
                           JOIN acciones_formativas af ON g.accion_id = af.id 
                           ORDER BY g.id DESC");
$grupos = $stmtGrupos ? $stmtGrupos->fetchAll(PDO::FETCH_ASSOC) : [];

// Procesar Formulario de Importación Rápida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_importacion'])) {
    $accion_id = !empty($_POST['accion_id']) ? (int)$_POST['accion_id'] : null;
    $grupo_id = !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
    $estado_matricula = !empty($_POST['estado_matricula']) ? trim($_POST['estado_matricula']) : 'Admitido';
    $sincronizar_moodle = isset($_POST['sincronizar_moodle']);
    $crear_empresas_auto = isset($_POST['crear_empresas_auto']);

    $raw_data = trim($_POST['datos_texto'] ?? '');
    
    // Si se subió archivo CSV/TXT
    if (!empty($_FILES['archivo_csv']['tmp_name']) && is_uploaded_file($_FILES['archivo_csv']['tmp_name'])) {
        $raw_data = file_get_contents($_FILES['archivo_csv']['tmp_name']);
        // Quitar BOM
        if (substr($raw_data, 0, 3) === "\xEF\xBB\xBF") {
            $raw_data = substr($raw_data, 3);
        }
    }

    if (!$accion_id && !$grupo_id) {
        $error = "Debes seleccionar al menos una Acción Formativa o un Grupo.";
    } elseif (empty($raw_data)) {
        $error = "No se han introducido datos para procesar. Pega los datos o sube un archivo.";
    } else {
        // Resolver Acción y Grupo si solo vino uno de ellos
        if ($grupo_id && !$accion_id) {
            $stmtG = $pdo->prepare("SELECT accion_id FROM grupos WHERE id = ?");
            $stmtG->execute([$grupo_id]);
            $accion_id = $stmtG->fetchColumn();
        } elseif ($accion_id && !$grupo_id) {
            // Buscar o crear grupo 1
            $stmtG = $pdo->prepare("SELECT id FROM grupos WHERE accion_id = ? ORDER BY numero_grupo ASC LIMIT 1");
            $stmtG->execute([$accion_id]);
            $grupo_id = $stmtG->fetchColumn();
            if (!$grupo_id) {
                $stmtInsG = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, fecha_inicio, fecha_fin, estado, created_at) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'En curso', NOW())");
                $stmtInsG->execute([$accion_id]);
                $grupo_id = $pdo->lastInsertId();
            }
        }

        // Obtener datos de la acción formativa para Moodle
        $stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
        $stmtAF->execute([$accion_id]);
        $af = $stmtAF->fetch(PDO::FETCH_ASSOC);

        $moodle = new MoodleAPI($pdo);
        $moodle_course_id = $af['id_plataforma'] ?? null;

        // Parsear líneas
        $lines = preg_split('/\r\n|\r|\n/', $raw_data);
        
        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Ignorar cabeceras típicas
            if (preg_match('/(alumno|nombre|dni|nif|empresa|apellidos)/i', $line) && $lineIndex === 0) {
                continue;
            }

            // Separar por tabulación, punto y coma, coma o múltiples espacios
            if (strpos($line, "\t") !== false) {
                $cols = explode("\t", $line);
            } elseif (strpos($line, ";") !== false) {
                $cols = str_getcsv($line, ';');
            } elseif (strpos($line, ",") !== false) {
                $cols = str_getcsv($line, ',');
            } else {
                $cols = preg_split('/\s{2,}/', $line); // 2 o más espacios
            }

            $cols = array_map('trim', $cols);
            if (empty(array_filter($cols))) continue;

            $total_procesados++;

            // Mapeo inteligente de columnas
            // Intentar detectar DNI/NIF (8 dígitos + letra o NIE)
            $dni = null;
            $nombre_completo = '';
            $fecha_nac = null;
            $empresa_nombre = '';
            $cif = '';
            $localidad = '';
            $provincia = '';
            $email = '';
            $telefono = '';

            foreach ($cols as $col) {
                $colClean = trim($col);
                if (empty($colClean)) continue;

                // DNI / NIE detector
                if (!$dni && preg_match('/^[0-9XYZxyz][0-9]{7}[A-Za-z]$/', str_replace([' ', '-', '.'], '', $colClean))) {
                    $dni = strtoupper(str_replace([' ', '-', '.'], '', $colClean));
                    continue;
                }

                // Email detector
                if (!$email && filter_var($colClean, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower($colClean);
                    continue;
                }

                // Fecha nacimiento detector (DD/MM/YYYY o YYYY-MM-DD)
                if (!$fecha_nac && preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $colClean, $mF)) {
                    $fecha_nac = sprintf('%04d-%02d-%02d', $mF[3], $mF[2], $mF[1]);
                    continue;
                } elseif (!$fecha_nac && preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $colClean, $mF)) {
                    $fecha_nac = sprintf('%04d-%02d-%02d', $mF[1], $mF[2], $mF[3]);
                    continue;
                }

                // Teléfono detector
                if (!$telefono && preg_match('/^(?:\+34|0034)?[6789]\d{8}$/', preg_replace('/[^0-9]/', '', $colClean))) {
                    $telefono = preg_replace('/[^0-9]/', '', $colClean);
                    continue;
                }

                // Empresa entre corchetes o con CIF (ej: "LUMER S.L. [B23864903]")
                if (preg_match('/^(.*?)(?:\[|\()([A-Za-z0-9]+)(?:\]|\))$/', $colClean, $mEmp)) {
                    $empresa_nombre = trim($mEmp[1]);
                    $cif = strtoupper(trim($mEmp[2]));
                    continue;
                }

                // Si no es ninguno de los anteriores, asignar a Nombre, Empresa, Localidad o Provincia
                if (empty($nombre_completo)) {
                    // Si la columna es el nombre del curso (ej CTRD0016 - TIK TOK), ignorar
                    if (stripos($colClean, 'CTRD') !== false || stripos($colClean, 'TIK TOK') !== false || stripos($colClean, 'CURSO') !== false) {
                        continue;
                    }
                    $nombre_completo = $colClean;
                } elseif (empty($empresa_nombre)) {
                    $empresa_nombre = $colClean;
                } elseif (empty($localidad)) {
                    $localidad = $colClean;
                } elseif (empty($provincia)) {
                    $provincia = $colClean;
                }
            }

            if (empty($dni) && empty($nombre_completo)) {
                $resultados[] = [
                    'status' => 'error',
                    'mensaje' => "Fila $total_procesados no válida o vacía."
                ];
                continue;
            }

            // Desglosar Nombre y Apellidos si vienen juntos
            $parts = preg_split('/\s+/', trim($nombre_completo));
            if (count($parts) >= 3) {
                $nombre = $parts[0] . ' ' . $parts[1];
                $primer_apellido = $parts[2];
                $segundo_apellido = isset($parts[3]) ? implode(' ', array_slice($parts, 3)) : '';
                if (count($parts) == 4) {
                    $nombre = $parts[0] . ' ' . $parts[1];
                    $primer_apellido = $parts[2];
                    $segundo_apellido = $parts[3];
                }
            } elseif (count($parts) == 2) {
                $nombre = $parts[0];
                $primer_apellido = $parts[1];
                $segundo_apellido = '';
            } else {
                $nombre = $parts[0] ?? 'Alumno';
                $primer_apellido = '—';
                $segundo_apellido = '';
            }

            // Gestionar Empresa
            $empresa_id = null;
            if (!empty($empresa_nombre) && $crear_empresas_auto) {
                $stmtEmp = $pdo->prepare("SELECT id FROM empresas WHERE nombre = ? OR (cif IS NOT NULL AND cif = ?) LIMIT 1");
                $stmtEmp->execute([$empresa_nombre, $cif]);
                $empresa_id = $stmtEmp->fetchColumn();

                if (!$empresa_id) {
                    $stmtInsEmp = $pdo->prepare("INSERT INTO empresas (nombre, cif, localidad, provincia) VALUES (?, ?, ?, ?)");
                    $stmtInsEmp->execute([$empresa_nombre, $cif ?: null, $localidad ?: null, $provincia ?: null]);
                    $empresa_id = $pdo->lastInsertId();
                    $total_empresas_creadas++;
                }
            }

            // Gestionar Alumno (Buscar por DNI o Email)
            $alumno_id = null;
            if (!empty($dni)) {
                $stmtAl = $pdo->prepare("SELECT id, email, moodle_user_id FROM alumnos WHERE dni = ? LIMIT 1");
                $stmtAl->execute([$dni]);
                $alRow = $stmtAl->fetch(PDO::FETCH_ASSOC);
                if ($alRow) {
                    $alumno_id = $alRow['id'];
                    $email = $email ?: $alRow['email'];
                    $moodle_user_id = $alRow['moodle_user_id'];
                }
            }

            if (!$email) {
                $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dni ?: uniqid('al_'))) . '@alumnos.grupoefp.es';
            }

            if (!$alumno_id) {
                $stmtInsAl = $pdo->prepare("INSERT INTO alumnos (dni, nombre, primer_apellido, segundo_apellido, fecha_nacimiento, localidad, provincia, telefono, email, ultima_empresa_id) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtInsAl->execute([
                    $dni ?: null,
                    $nombre,
                    $primer_apellido,
                    $segundo_apellido,
                    $fecha_nac ?: null,
                    $localidad ?: null,
                    $provincia ?: null,
                    $telefono ?: null,
                    $email,
                    $empresa_id
                ]);
                $alumno_id = $pdo->lastInsertId();
                $total_alumnos_creados++;
                $al_action = "Creado";
            } else {
                $stmtUpdAl = $pdo->prepare("UPDATE alumnos SET 
                                                fecha_nacimiento = COALESCE(?, fecha_nacimiento),
                                                localidad = COALESCE(?, localidad),
                                                provincia = COALESCE(?, provincia),
                                                telefono = COALESCE(?, telefono),
                                                ultima_empresa_id = COALESCE(?, ultima_empresa_id)
                                            WHERE id = ?");
                $stmtUpdAl->execute([$fecha_nac ?: null, $localidad ?: null, $provincia ?: null, $telefono ?: null, $empresa_id ?: null, $alumno_id]);
                $total_alumnos_actualizados++;
                $al_action = "Actualizado";
            }

            // Gestionar Matrícula en el Grupo
            $stmtMat = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND grupo_id = ? LIMIT 1");
            $stmtMat->execute([$alumno_id, $grupo_id]);
            $mat_id = $stmtMat->fetchColumn();

            if (!$mat_id) {
                $stmtInsMat = $pdo->prepare("INSERT INTO matriculas (alumno_id, grupo_id, estado, fecha_matricula) VALUES (?, ?, ?, CURDATE())");
                $stmtInsMat->execute([$alumno_id, $grupo_id, $estado_matricula]);
                $mat_id = $pdo->lastInsertId();
                $total_matriculados++;
            } else {
                $stmtUpdMat = $pdo->prepare("UPDATE matriculas SET estado = ? WHERE id = ?");
                $stmtUpdMat->execute([$estado_matricula, $mat_id]);
            }

            // Sincronizar con Moodle si está activo y configurado
            $moodle_status = "No requerido";
            if ($sincronizar_moodle && $moodle->isConfigured() && $moodle_course_id) {
                try {
                    $u_username = strtolower(preg_replace('/[^a-z0-9_.-]/', '', $dni ?: explode('@', $email)[0]));
                    $moodleUser = $moodle->getUsersByField('email', [$email]);
                    $mUserId = null;
                    
                    if (!empty($moodleUser) && isset($moodleUser['users'][0]['id'])) {
                        $mUserId = $moodleUser['users'][0]['id'];
                    } else {
                        // Crear usuario en Moodle
                        $newMUser = $moodle->createUser(
                            $u_username,
                            'Alumno2026*',
                            $nombre,
                            trim("$primer_apellido $segundo_apellido") ?: 'Alumno',
                            $email
                        );
                        if (isset($newMUser[0]['id'])) {
                            $mUserId = $newMUser[0]['id'];
                        }
                    }

                    if ($mUserId) {
                        $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$mUserId, $alumno_id]);
                        // Matricular como Estudiante (Role 5)
                        $moodle->enrolUser($mUserId, $moodle_course_id, 5);
                        $total_moodle_synced++;
                        $moodle_status = "Matriculado en Moodle (#$mUserId)";
                    } else {
                        $moodle_status = "Error al crear en Moodle";
                    }
                } catch (Exception $eM) {
                    $moodle_status = "Error Moodle: " . $eM->getMessage();
                }
            }

            $resultados[] = [
                'status' => 'success',
                'nombre' => "$nombre $primer_apellido $segundo_apellido",
                'dni' => $dni ?: '---',
                'empresa' => $empresa_nombre ?: '---',
                'accion' => "$al_action (ID: $alumno_id)",
                'matricula' => "Matrícula #$mat_id ($estado_matricula)",
                'moodle' => $moodle_status
            ];
        }

        $success = "¡Proceso de importación finalizado con éxito! Se han procesado $total_procesados registros.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación y Matriculación Rápida - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="img/logo_efp.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/fp_global.css">
    <style>
        .import-box {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        .import-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #1e3a8a;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group-custom label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
        }
        .form-control-custom {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.88rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }
        .form-control-custom:focus {
            border-color: #006ce4;
            box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.15);
        }
        .paste-textarea {
            width: 100%;
            height: 180px;
            font-family: monospace;
            font-size: 0.82rem;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            background: #f8fafc;
        }
        .btn-fast {
            background: #006ce4;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(0, 108, 228, 0.3);
        }
        .btn-fast:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 108, 228, 0.4);
        }
        .btn-example {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-example:hover {
            background: #e2e8f0;
        }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .badge-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .badge-moodle { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        @media (max-width: 900px) {
            .form-grid-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto; padding: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;">⚡ Importación y Matriculación Rápida de Alumnos</h1>
                <p style="color: #64748b; font-size: 0.88rem; margin: 4px 0 0 0;">Copia y pega listas de alumnos desde Excel/Word/PDF o sube un CSV para crearlos y matricularlos en un solo clic.</p>
            </div>
            <a href="inscripciones.php" class="btn-example" style="text-decoration: none; padding: 8px 16px;">« Volver a Matrículas</a>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 14px 18px; border-radius: 6px; color: #991b1b; font-weight: 700; margin-bottom: 20px;">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: #dcfce7; border-left: 4px solid #16a34a; padding: 16px 20px; border-radius: 6px; color: #15803d; font-weight: 700; margin-bottom: 20px;">
                <div style="font-size: 1rem; margin-bottom: 8px;">✓ <?= htmlspecialchars($success) ?></div>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.85rem; font-weight: 600;">
                    <span>Alumnos Creados: <strong><?= $total_alumnos_creados ?></strong></span>
                    <span>Alumnos Actualizados: <strong><?= $total_alumnos_actualizados ?></strong></span>
                    <span>Empresas Creadas: <strong><?= $total_empresas_creadas ?></strong></span>
                    <span>Matrículas Generadas: <strong><?= $total_matriculados ?></strong></span>
                    <span>Matriculados en Moodle: <strong><?= $total_moodle_synced ?></strong></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="import-box">
            <div class="import-title">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                1. Seleccionar Destino del Curso
            </div>

            <div class="form-grid-3">
                <div class="form-group-custom">
                    <label>Acción Formativa / Curso:</label>
                    <select name="accion_id" id="accion_id_select" class="form-control-custom" required onchange="updateGruposDropdown()">
                        <option value="">-- Seleccionar Acción Formativa --</option>
                        <?php foreach ($acciones_formativas as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= (isset($_POST['accion_id']) && $_POST['accion_id'] == $a['id']) || (isset($_GET['af_id']) && $_GET['af_id'] == $a['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($a['abreviatura'] ?: $a['curso_codigo']) . ' - ' . $a['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-custom">
                    <label>Grupo de Impartición:</label>
                    <select name="grupo_id" id="grupo_id_select" class="form-control-custom">
                        <option value="">Grupo 1 (Automático)</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>" data-accion="<?= $g['accion_id'] ?>" <?= (isset($_POST['grupo_id']) && $_POST['grupo_id'] == $g['id']) || (isset($_GET['grupo_id']) && $_GET['grupo_id'] == $g['id']) ? 'selected' : '' ?>>
                                Grupo <?= $g['numero_grupo'] ?> (<?= htmlspecialchars($g['abreviatura']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-custom">
                    <label>Estado de Matrícula Inicial:</label>
                    <select name="estado_matricula" class="form-control-custom">
                        <option value="Admitido" selected>Admitido</option>
                        <option value="Preinscrito">Preinscrito</option>
                        <option value="Pendiente validacion">Pendiente validación</option>
                        <option value="Reserva">Reserva</option>
                        <option value="Inscrito">Inscrito</option>
                    </select>
                </div>
            </div>

            <div class="import-title" style="margin-top: 25px;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                2. Opciones de Automatización
            </div>

            <div style="display: flex; gap: 30px; flex-wrap: wrap; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem; font-weight: 600; color: #1e3a8a;">
                    <input type="checkbox" name="crear_empresas_auto" value="1" checked style="width: 17px; height: 17px; accent-color: #006ce4;">
                    Crear Empresas automáticamente si no existen
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem; font-weight: 600; color: #c2410c;">
                    <input type="checkbox" name="sincronizar_moodle" value="1" checked style="width: 17px; height: 17px; accent-color: #ea580c;">
                    Crear usuario en Moodle y Matricular en el curso automáticamente
                </label>
            </div>

            <div class="import-title" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    3. Pegar Lista de Alumnos
                </div>
                <button type="button" class="btn-example" onclick="loadTikTokExample()">📋 Cargar Ejemplo 16 Alumnos TikTok</button>
            </div>

            <p style="font-size: 0.82rem; color: #64748b; margin: -5px 0 10px 0;">
                Puedes pegar directamente filas de Excel o texto tabulado. Detecta automáticamente <strong>Nombre, DNI, Fecha Nacimiento, Empresa, CIF, Localidad, Provincia y Email</strong>.
            </p>

            <textarea name="datos_texto" id="datos_texto" class="paste-textarea" placeholder="Ejemplo:
ANGEL ALBERTO AGUILERA JIMENEZ	15474740H	10/02/1989	AGUILERA JIMENEZ ANGEL ALBERTO [15474740H]	ALMUÑECAR	GRANADA
JUAN CRISTOBAL ALVAREZ CEBRIAN	52746478A	11/07/1968	LUMER CREATIVE STUDIO S.L. [B23864903]	PICASSENT	VALENCIA"><?= htmlspecialchars($_POST['datos_texto'] ?? '') ?></textarea>

            <div style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">O subir archivo CSV / Excel:</label>
                    <input type="file" name="archivo_csv" accept=".csv,.txt" style="font-size: 0.82rem;">
                </div>
                <button type="submit" name="ejecutar_importacion" class="btn-fast">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    🚀 Procesar y Matricular Alumnos Ahora
                </button>
            </div>
        </form>

        <?php if (!empty($resultados)): ?>
            <div class="import-box">
                <div class="import-title">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Detalle de Alumnos Procesados (<?= count($resultados) ?>)
                </div>
                <div style="overflow-x: auto;">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 10px; text-align: left;">ALUMNO</th>
                                <th style="padding: 10px; text-align: left;">DNI / NIF</th>
                                <th style="padding: 10px; text-align: left;">EMPRESA</th>
                                <th style="padding: 10px; text-align: center;">ALUMNO</th>
                                <th style="padding: 10px; text-align: center;">MATRÍCULA</th>
                                <th style="padding: 10px; text-align: center;">MOODLE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $r): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                    <td style="padding: 10px; font-family: monospace; font-weight: 600; color: #006ce4;"><?= htmlspecialchars($r['dni'] ?? '') ?></td>
                                    <td style="padding: 10px; color: #475569;"><?= htmlspecialchars($r['empresa'] ?? '') ?></td>
                                    <td style="padding: 10px; text-align: center;"><span class="badge-info"><?= htmlspecialchars($r['accion'] ?? '') ?></span></td>
                                    <td style="padding: 10px; text-align: center;"><span class="badge-success"><?= htmlspecialchars($r['matricula'] ?? '') ?></span></td>
                                    <td style="padding: 10px; text-align: center;"><span class="badge-moodle"><?= htmlspecialchars($r['moodle'] ?? '') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function loadTikTokExample() {
    const data = `ANGEL ALBERTO AGUILERA JIMENEZ\t15474740H\t10/02/1989\tAGUILERA JIMENEZ ANGEL ALBERTO [15474740H]\tALMUÑECAR\tGRANADA
JUAN CRISTOBAL ALVAREZ CEBRIAN\t52746478A\t11/07/1968\tLUMER CREATIVE STUDIO S.L. [B23864903]\tPICASSENT\tVALENCIA
ADELA CANTALLOPS JERONIMO\t43126411P\t23/12/1975\tADELA CANTALLOPS JERONIMO [43126411P]\tINCA\tILLES BALEARS
DANIEL FERNANDEZ JUTZ\t33566259J\t01/04/1978\tDANIEL FERNANDEZ JUTZ [33566259J]\tDENIA\tALICANTE
IRENE FUENTES GONZALEZ\t53051042R\t17/02/1975\tCIUDAD DE LAS ARTES Y DE LAS CIENCIAS [A46483095]\tVALENCIA\tVALENCIA
ALICIA GALVEZ GARCIA\t48684041X\t29/08/1998\tAVATEL TELECOM SA [A93135218]\tORIHUELA\tALICANTE
LAURA HEVIA BRAGA\t71776224D\t21/12/1989\tLAURA HEVIA BRAGA [71776224D]\tPOLA DE LENA\tASTURIAS
ENRIQUE ALFREDO MARTIN PARODI\tZ0044851Y\t12/05/1969\tDALE U MADRID S.L. [B70691803]\tLAS ROZAS\tMADRID
INSAF MOHAMED AZOUAGH\t45315783X\t24/02/1999\tINSAF MOHAMED AZOUAGH [45315783X]\tMELILLA\tMELILLA
ANA JOSE OLCOZ SESMA\t18203637B\t23/02/1966\tANA JOSE OLCOZ SESMA [18203637B]\tTAFALLA\tNAVARRA
MIGUEL ANGEL ORTELLS MORENO\t22695340K\t03/06/1966\tMIGUEL ANGEL ORTELLS [22695340K]\tVALENCIA\tVALENCIA
GONZALO PEREZ ZUNZUNEGUI\t30683105R\t30/11/1974\tEDICIONES IZORIA 2004 S.L [B1373489]\tMIRANDA DE EBRO\tBURGOS
JAVIER PRADOS HIPOLITO\t05926565V\t28/03/1982\tB3MEDIA SERVICIOS AUDIOVISUALES SL [B21735691]\tSAN SEBASTIAN DE LOS REYES\tMADRID
MARIA LUISA RUBIO MARTINEZ\t44375137H\t04/06/1971\tMARIA LUISA RUBIO MARTINEZ [44375137H]\tALBACETE\tALBACETE
ESTEFANIA SANCHEZ LOPEZ\t32736760D\t07/04/1999\tMULTICOPIAS GALICIA, SL [B15506561]\tVALDOVIÑO\tA CORUÑA
ANDREW THOMPSON\tX4550143F\t16/10/1977\tANDREW THOMPSON [X4550143F]\tSANT BOI DE LLOBREGAT\tBARCELONA`;

    document.getElementById('datos_texto').value = data;

    // Buscar si existe opción con TikTok en el selector
    const sel = document.getElementById('accion_id_select');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].text.toLowerCase().includes('tik tok') || sel.options[i].text.toLowerCase().includes('ctrd0016')) {
            sel.selectedIndex = i;
            break;
        }
    }
}

function updateGruposDropdown() {
    const accionId = document.getElementById('accion_id_select').value;
    const grupoSel = document.getElementById('grupo_id_select');
    let hasGroup = false;

    for (let i = 0; i < grupoSel.options.length; i++) {
        const opt = grupoSel.options[i];
        const optAccion = opt.getAttribute('data-accion');
        if (!optAccion) continue;

        if (optAccion === accionId) {
            opt.style.display = 'block';
            if (!hasGroup) {
                opt.selected = true;
                hasGroup = true;
            }
        } else {
            opt.style.display = 'none';
        }
    }
}
</script>
</body>
</html>
