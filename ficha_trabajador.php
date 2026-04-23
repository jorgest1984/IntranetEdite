<?php
// ficha_trabajador.php - Perfil Profesional del Personal (Usuarios)
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: usuarios.php");
    exit();
}

// Cargar datos del usuario/trabajador
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$trabajador = $stmt->fetch();

if (!$trabajador) {
    die("Trabajador no encontrado.");
}

// Cargar detalles de profesorado (vínculo a usuario)
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE usuario_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

$active_tab = $_GET['tab'] ?? 'personales';
$error = null;

// Acciones CV: Agregar Registros (Formación, Experiencia, Idiomas, Informática)
$cv_actions = [
    'add_formacion' => ['table' => 'prof_formacion', 'fields' => ['denominacion', 'organismo', 'centro', 'desde', 'hasta', 'horas', 'tipo_formacion']],
    'add_experiencia' => ['table' => 'prof_experiencia', 'fields' => ['empresa', 'desde', 'hasta', 'cargo', 'tareas']],
    'add_idioma' => ['table' => 'prof_idiomas', 'fields' => ['idioma', 'nivel_hablado', 'nivel_oral', 'nivel_escrito', 'nivel_leido']],
    'add_informatica' => ['table' => 'prof_informatica', 'fields' => ['programa', 'dominio']],
    'add_asistencia' => ['table' => 'prof_asistencia', 'fields' => ['fecha_desde', 'fecha_hasta', 'tipo', 'duracion_dias', 'duracion_horas', 'observaciones']]
];

// PROCESAR POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if (isset($cv_actions[$action])) {
            $act = $cv_actions[$action];
            $fields = $act['fields'];
            $sql = "INSERT INTO " . $act['table'] . " (usuario_id, " . implode(', ', $fields) . ") VALUES (?, " . implode(', ', array_fill(0, count($fields), '?')) . ")";
            $params = [$id];
            foreach($fields as $f) {
                $val = isset($_POST[$f]) ? trim($_POST[$f]) : null;
                $params[] = ($val === '') ? null : $val;
            }
            $st = $pdo->prepare($sql);
            $st->execute($params);
            
            header("Location: ficha_trabajador.php?id=$id&tab=cv&success=1");
            exit();
        }

        if ($action == 'update_perfiles_dept') {
            $pdo->beginTransaction();
            
            $pdo->prepare("DELETE FROM usuario_departamentos WHERE usuario_id = ?")->execute([$id]);
            if (isset($_POST['depts']) && is_array($_POST['depts'])) {
                $stD = $pdo->prepare("INSERT INTO usuario_departamentos (usuario_id, departamento) VALUES (?, ?)");
                foreach($_POST['depts'] as $d) $stD->execute([$id, $d]);
            }
            
            $pdo->prepare("DELETE FROM usuario_perfiles WHERE usuario_id = ?")->execute([$id]);
            if (isset($_POST['perfiles']) && is_array($_POST['perfiles'])) {
                $stP = $pdo->prepare("INSERT INTO usuario_perfiles (usuario_id, perfil) VALUES (?, ?)");
                foreach($_POST['perfiles'] as $p) $stP->execute([$id, $p]);
            }
            
            $pdo->commit();
            header("Location: ficha_trabajador.php?id=$id&tab=perfil&success=1");
            exit();
        }

        if ($action == 'update_personales') {
            // 1. Actualizar tabla usuarios
            $stU = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellidos = ? WHERE id = ?");
            $apellidos_full = trim(($_POST['apellido1'] ?? '') . ' ' . ($_POST['apellido2'] ?? ''));
            $stU->execute([$_POST['nombre'] ?? '', $apellidos_full, $id]);

            // 2. Definir campos para profesorado_detalles
            $fields = [
                'dni', 'fecha_nacimiento', 'apellido1', 'apellido2',
                'num_ss', 'cuenta_bancaria',
                'nombre_via', 'cp_trabajador', 'localidad_trabajador', 'provincia_trabajador',
                'telefono', 'telefono_empresa',
                'email2', 'skype', 'sexo',
                'activo_hasta', 'nuestro', 'observaciones_personales'
            ];
            
            $fields_escaped = array_map(function($f) { return "`$f`"; }, $fields);
            $set_part = implode(' = ?, ', $fields_escaped) . ' = ?';
            $sql = "UPDATE profesorado_detalles SET $set_part WHERE usuario_id = ?";
            
            $params = [];
            foreach ($fields as $f) {
                $val = $_POST[$f] ?? null;
                if ($f == 'nuestro') {
                    $val = (isset($_POST['nuestro']) && $_POST['nuestro'] == 'SI') ? 1 : 0;
                }
                if ($f == 'activo_hasta' && !empty($val) && strlen($val) == 4) {
                    $val = $val . "-12-31"; 
                }
                if ($val === '') $val = null;
                $params[] = $val;
            }
            $params[] = $id;

            // Asegurar que el registro existe
            $check = $pdo->prepare("SELECT 1 FROM profesorado_detalles WHERE usuario_id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO profesorado_detalles (usuario_id) VALUES (?)")->execute([$id]);
            }

            $stP = $pdo->prepare($sql);
            $stP->execute($params);

            header("Location: ficha_trabajador.php?id=$id&tab=personales&success=1");
            exit();
        }
        if ($action == 'update_profesorado') {
            $fields = [
                'titulacion', 'es_tutor', 'es_teleformador', 'es_presencial', 'hace_seguimiento',
                'tope_alumnos_turno', 'aplicar_viernes',
                'tramo1_de', 'tramo1_a', 'tramo1_v2_de', 'tramo1_v2_a',
                'tramo2_de', 'tramo2_a', 'tramo2_v2_de', 'tramo2_v2_a'
            ];
            
            $fields_escaped = array_map(function($f) { return "`$f`"; }, $fields);
            $set_part = implode(' = ?, ', $fields_escaped) . ' = ?';
            $sql = "UPDATE profesorado_detalles SET $set_part WHERE usuario_id = ?";
            
            $params = [];
            foreach ($fields as $f) {
                $val = $_POST[$f] ?? null;
                // Convertir SI/NO de radios a 1/0
                if (in_array($f, ['es_tutor', 'es_teleformador', 'es_presencial', 'hace_seguimiento', 'aplicar_viernes'])) {
                    $val = ($val == 'SI') ? 1 : 0;
                }
                if ($val === '') $val = null;
                $params[] = $val;
            }
            $params[] = $id;

            $stP = $pdo->prepare($sql);
            $stP->execute($params);

            header("Location: ficha_trabajador.php?id=$id&tab=profesorado&success=1");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Cargar datos para la vista
$tutorias = [];
$stmtTut = $pdo->prepare("SELECT * FROM prof_tutorias WHERE usuario_id = ? ORDER BY anio DESC");
$stmtTut->execute([$id]);
$tutorias = $stmtTut->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Personal: <?= htmlspecialchars(($trabajador['nombre'] ?? '') . ' ' . ($trabajador['apellidos'] ?? '')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .trabajador-header {
            background: white; padding: 1.5rem 2rem; border-radius: 12px; border: 1px solid var(--border-color);
            margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .tabs-header {
            display: flex; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px 8px 0 0;
            overflow-x: auto; scrollbar-width: none;
        }
        .tab-btn {
            padding: 0.75rem 1.25rem; border: none; background: none; font-size: 0.8rem; font-weight: 500;
            color: #64748b; cursor: pointer; white-space: nowrap; border-right: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .tab-btn.active { background: white; color: #1e40af; font-weight: 700; box-shadow: inset 0 2px 0 #1e40af; }
        .tab-panel { background: white; padding: 2rem; border: 1px solid var(--border-color); border-top: none; border-radius: 0 0 8px 8px; }
        .form-premium-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-group label { font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;
            color: #1e293b; background-color: #fff; transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .email-wrapper { display: flex; gap: 0.5rem; align-items: center; }
        .btn-yellow-icon {
            background: #fef08a; border: 1px solid #facc15; padding: 0.5rem; border-radius: 6px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .btn-yellow-icon:hover { background: #fde047; }
        .btn-yellow-icon svg { width: 16px; height: 16px; fill: #854d0e; }
        .radio-group-premium { display: flex; gap: 1.5rem; font-size: 0.85rem; color: #0f172a; padding: 0.5rem 0; }
        .radio-item { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-weight: 500; }
        .radio-item input[type="radio"] { accent-color: #1e40af; cursor: pointer; }
        .observations-box { grid-column: span 12; margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .observations-box textarea { min-height: 80px; resize: vertical; }
        .btn-actualizar {
            background: #1e40af; color: white; border: none; padding: 0.75rem 2.5rem; border-radius: 6px;
            font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 1.5rem;
        }
        .btn-actualizar:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .table-premium { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 1rem; }
        .table-premium th { text-align: left; background: #f8fafc; padding: 0.75rem 1rem; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; }
        .table-premium td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        /* MODAL STYLES (Copiados de usuarios.php para consistencia) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal-container {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            padding: 20px 25px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .modal-close {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .modal-close:hover { background: #f1f5f9; color: #b91c1c; }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
        }
        
        .btn-create-full {
            background: #1e3a8a;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            width: 100%;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }
        .btn-create-full:hover { background: #1e40af; transform: translateY(-1px); }

    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="trabajador-header">
            <div>
                <a href="usuarios.php" class="btn-back" style="text-decoration:none; color:#64748b; font-size:0.85rem;">← Volver a Usuarios</a>
                <h1 style="margin-top: 0.5rem; color: #1e3a8a;"><?= htmlspecialchars(($trabajador['nombre'] ?? '') . ' ' . ($trabajador['apellidos'] ?? '')) ?></h1>
                <p style="color: #64748b; margin: 0; font-size:0.9rem;"><?= htmlspecialchars($trabajador['email'] ?? '') ?> | Perfil de Trabajador</p>
            </div>
            <div>
                <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700; font-size:0.75rem;">TRABAJADOR</span>
            </div>
        </div>

        <nav class="tabs-header">
            <button class="tab-btn <?= $active_tab == 'personales' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=personales'">Datos Personales</button>
            <button class="tab-btn <?= $active_tab == 'profesorado' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=profesorado'">Profesorado</button>
            <button class="tab-btn <?= $active_tab == 'cv' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=cv'">Currículum</button>
            <button class="tab-btn <?= $active_tab == 'asistencia' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=asistencia'">Control de Asistencia</button>
            <button class="tab-btn <?= $active_tab == 'docs' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=docs'">Docs Asociados</button>
            <button class="tab-btn <?= $active_tab == 'cuenta' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=cuenta'">Cuenta</button>
            <button class="tab-btn <?= $active_tab == 'formacion' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=formacion'">Formación</button>
            <button class="tab-btn <?= $active_tab == 'perfil' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=perfil'">Departamentos/Perfiles</button>
            <button class="tab-btn <?= $active_tab == 'comerciales' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=comerciales'">Comerciales</button>
            <button class="tab-btn <?= $active_tab == 'tareas' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=tareas'">Tareas</button>
        </nav>

        <div class="tab-panel">
            <?php if (isset($_GET['success'])) { ?>
                <div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #10b981; font-weight: 600;">
                    <?php 
                        if($_GET['success'] == 'upload') echo "Documento subido y registrado correctamente.";
                        elseif($_GET['success'] == 'deleted') echo "Documento eliminado correctamente.";
                        else echo "Cambios guardados con éxito.";
                    ?>
                </div>
            <?php } ?>
            
            <?php if ($error) { ?>
                <div class="alert alert-error" style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #ef4444; font-weight: 600;"><?= htmlspecialchars($error) ?></div>
            <?php } ?>

            <!-- TAB: Personales -->
            <div id="tab-personales" style="<?= $active_tab == 'personales' ? '' : 'display:none;' ?>">
                <form method="POST" action="ficha_trabajador.php?id=<?= $id ?>&tab=personales">
                    <input type="hidden" name="action" value="update_personales">
                    <div class="form-premium-grid">
                        
                        <div class="form-group" style="grid-column: span 4;">
                            <label>DNI:</label>
                            <input type="text" name="dni" value="<?= htmlspecialchars($prof['dni'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 8;">
                            <label>Fecha de nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($prof['fecha_nacimiento'] ?? '') ?>" style="max-width: 150px;">
                        </div>

                        <div class="form-group" style="grid-column: span 3;">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($trabajador['nombre'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 4;">
                            <label>Primer apellido:</label>
                            <input type="text" name="apellido1" value="<?= htmlspecialchars($prof['apellido1'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 5;">
                            <label>Segundo apellido:</label>
                            <input type="text" name="apellido2" value="<?= htmlspecialchars($prof['apellido2'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Seguridad Social:</label>
                            <input type="text" name="num_ss" value="<?= htmlspecialchars($prof['num_ss'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 8;">
                            <label>Cuenta Bancaria:</label>
                            <input type="text" name="cuenta_bancaria" value="<?= htmlspecialchars($prof['cuenta_bancaria'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 12;">
                            <label>Domicilio:</label>
                            <input type="text" name="nombre_via" value="<?= htmlspecialchars($prof['nombre_via'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>CP:</label>
                            <input type="text" name="cp_trabajador" value="<?= htmlspecialchars($prof['cp_trabajador'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 4;">
                            <label>Localidad:</label>
                            <input type="text" name="localidad_trabajador" value="<?= htmlspecialchars($prof['localidad_trabajador'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 6;">
                            <label>Provincia:</label>
                            <select name="provincia_trabajador">
                                <option value="<?= htmlspecialchars($prof['provincia_trabajador'] ?? '') ?>"><?= htmlspecialchars($prof['provincia_trabajador'] ?? 'Seleccionar...') ?></option>
                                <?php 
                                $provincias = ["Álava","Albacete","Alicante","Almería","Asturias","Ávila","Badajoz","Baleares","Barcelona","Burgos","Cáceres","Cádiz","Cantabria","Castellón","Ciudad Real","Córdoba","Cuenca","Gerona","Granada","Guadalajara","Guipúzcoa","Huelva","Huesca","Jaén","La Coruña","La Rioja","Las Palmas","León","Lérida","Lugo","Madrid","Málaga","Murcia","Navarra","Orense","Palencia","Pontevedra","Salamanca","Santa Cruz de Tenerife","Segovia","Sevilla","Soria","Tarragona","Teruel","Toledo","Valencia","Valladolid","Vizcaya","Zamora","Zaragoza","Ceuta","Melilla"];
                                foreach($provincias as $p) {
                                    echo "<option value=\"$p\">$p</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: span 3;">
                            <label>Teléfono:</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($prof['telefono'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 9;">
                            <label>Teléfono empresa:</label>
                            <input type="text" name="telefono_empresa" value="<?= htmlspecialchars($prof['telefono_empresa'] ?? '') ?>" style="max-width: 200px;">
                        </div>

                        <div class="form-group" style="grid-column: span 5;">
                            <label>E-mail:</label>
                            <div class="email-wrapper">
                                <input type="email" value="<?= htmlspecialchars($trabajador['email'] ?? '') ?>" readonly style="color: #64748b; background:#f1f5f9;">
                                <button type="button" class="btn-yellow-icon" title="Enviar email">
                                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 4;">
                            <label>E-mail personal:</label>
                            <div class="email-wrapper">
                                <input type="email" name="email2" value="<?= htmlspecialchars($prof['email2'] ?? '') ?>">
                                <button type="button" class="btn-yellow-icon" title="Enviar email personal">
                                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Skype:</label>
                            <input type="text" name="skype" value="<?= htmlspecialchars($prof['skype'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 3;">
                            <label>Sexo:</label>
                            <select name="sexo">
                                <option value="Hombre" <?= ($prof['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                <option value="Mujer" <?= ($prof['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                <option value="Otro" <?= ($prof['sexo'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Activo hasta:</label>
                            <input type="text" name="activo_hasta" value="<?= htmlspecialchars($prof['activo_hasta'] ?? '') ?>" placeholder="AAAA">
                        </div>
                        <div class="form-group" style="grid-column: span 8;">
                            <label>Nuestro:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="nuestro" value="SI" <?= ($prof['nuestro'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="nuestro" value="NO" <?= !($prof['nuestro'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>

                        <div class="observations-box">
                            <label>Observaciones:</label>
                            <textarea name="observaciones_personales"><?= htmlspecialchars($prof['observaciones_personales'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div style="text-align: center;">
                        <button type="submit" class="btn-actualizar">Actualizar</button>
                    </div>
                </form>
            </div>

            <!-- TAB: Profesorado -->
            <div id="tab-profesorado" style="<?= $active_tab == 'profesorado' ? '' : 'display:none;' ?>">
                
                <!-- Botones de Acción Superiores -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <button class="btn-yellow-icon" onclick="window.open('generar_certificado.php?id=<?= $id ?>', '_blank')" style="padding: 0.6rem 1.2rem; font-size: 0.8rem; font-weight: 600; color: #854d0e;">Certificado</button>
                    <button class="btn-yellow-icon" style="padding: 0.6rem 1.2rem; font-size: 0.8rem; font-weight: 600; color: #854d0e;">Crear/actualizar profesor en Aula Virtual</button>
                    <button class="btn-yellow-icon" onclick="openModalDocumentos()" style="padding: 0.6rem 1.2rem; font-size: 0.8rem; font-weight: 600; color: #854d0e;">Subir documento</button>
                </div>

                <!-- Listado de Documentos Subidos -->
                <?php
                $stmt_docs = $pdo->prepare("SELECT id, nombre_archivo, tipo_documento, fecha_subida FROM profesorado_documentos WHERE usuario_id = ? ORDER BY fecha_subida DESC");
                $stmt_docs->execute([$id]);
                $docs_subidos = $stmt_docs->fetchAll();
                
                if (count($docs_subidos) > 0):
                ?>
                <div style="margin-top: 1.5rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    <div style="background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.8rem; color: #1e3a8a; text-transform: uppercase;">Documentación Registrada</div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                        <thead>
                            <tr style="background: #f1f5f9; text-align: left;">
                                <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0;">Tipo</th>
                                <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0;">Archivo</th>
                                <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0;">Fecha</th>
                                <th style="padding: 10px 15px; border-bottom: 1px solid #e2e8f0; text-align: right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($docs_subidos as $d): ?>
                            <tr>
                                <td style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9; font-weight: 600;"><?= htmlspecialchars($d['tipo_documento']) ?></td>
                                <td style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9;"><?= htmlspecialchars($d['nombre_archivo']) ?></td>
                                <td style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9; color: #64748b;"><?= date('d/m/Y H:i', strtotime($d['fecha_subida'])) ?></td>
                                <td style="padding: 10px 15px; border-bottom: 1px solid #f1f5f9; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                                    <a href="api/ver_documento_profesor.php?id=<?= $d['id'] ?>" target="_blank" style="color: #1e3a8a; text-decoration: none; font-weight: 700;">Ver</a>
                                    <span style="color: #cbd5e1;">|</span>
                                    <a href="javascript:void(0)" onclick="eliminarDocumento(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nombre_archivo']) ?>')" style="color: #b91c1c; text-decoration: none; font-weight: 700;">Eliminar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <form method="POST" action="ficha_trabajador.php?id=<?= $id ?>&tab=profesorado">
                    <input type="hidden" name="action" value="update_profesorado">
                    
                    <div class="form-premium-grid">
                        <!-- Titulación -->
                        <div class="form-group" style="grid-column: span 12;">
                            <label>Titulación:</label>
                            <input type="text" name="titulacion" value="<?= htmlspecialchars($prof['titulacion'] ?? '') ?>" style="width: 100%;">
                        </div>

                        <!-- Checks Radios -->
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Tutor:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="es_tutor" value="SI" <?= ($prof['es_tutor'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="es_tutor" value="NO" <?= !($prof['es_tutor'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Teleformador:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="es_teleformador" value="SI" <?= ($prof['es_teleformador'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="es_teleformador" value="NO" <?= !($prof['es_teleformador'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Presencial:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="es_presencial" value="SI" <?= ($prof['es_presencial'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="es_presencial" value="NO" <?= !($prof['es_presencial'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Seguimiento:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="hace_seguimiento" value="SI" <?= ($prof['hace_seguimiento'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="hace_seguimiento" value="NO" <?= !($prof['hace_seguimiento'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>

                        <!-- Tope Alumnos -->
                        <div class="form-group" style="grid-column: span 12; margin-top: 1rem;">
                            <label>Tope de alumnos por turno:</label>
                            <input type="number" name="tope_alumnos_turno" value="<?= htmlspecialchars($prof['tope_alumnos_turno'] ?? 0) ?>" style="max-width: 100px;">
                        </div>

                        <!-- HORARIOS (TRAMOS) -->
                        <div style="grid-column: span 12; margin-top: 2rem;">
                            <h4 style="color: #1e3a8a; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; font-size: 0.9rem;">Tramos de tutorías (horarios)</h4>
                            
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <!-- Tramo 1 -->
                                <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; color: #475569;">
                                    <span style="font-weight: 600; min-width: 80px;">Tramo 1, de</span>
                                    <input type="time" name="tramo1_de" value="<?= $prof['tramo1_de'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>a</span>
                                    <input type="time" name="tramo1_a" value="<?= $prof['tramo1_a'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>y de</span>
                                    <input type="time" name="tramo1_v2_de" value="<?= $prof['tramo1_v2_de'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>a</span>
                                    <input type="time" name="tramo1_v2_a" value="<?= $prof['tramo1_v2_a'] ?? '' ?>" style="padding: 0.3rem;">
                                </div>

                                <!-- Tramo 2 -->
                                <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; color: #475569;">
                                    <span style="font-weight: 600; min-width: 80px;">Tramo 2, de</span>
                                    <input type="time" name="tramo2_de" value="<?= $prof['tramo2_de'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>a</span>
                                    <input type="time" name="tramo2_a" value="<?= $prof['tramo2_a'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>y de</span>
                                    <input type="time" name="tramo2_v2_de" value="<?= $prof['tramo2_v2_de'] ?? '' ?>" style="padding: 0.3rem;">
                                    <span>a</span>
                                    <input type="time" name="tramo2_v2_a" value="<?= $prof['tramo2_v2_a'] ?? '' ?>" style="padding: 0.3rem;">
                                </div>
                            </div>
                        </div>

                        <!-- Aplicar Viernes -->
                        <div class="form-group" style="grid-column: span 12; margin-top: 1.5rem;">
                            <label>Aplicar horario de tutorías también los viernes:</label>
                            <div class="radio-group-premium">
                                <label class="radio-item"><input type="radio" name="aplicar_viernes" value="SI" <?= ($prof['aplicar_viernes'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label class="radio-item"><input type="radio" name="aplicar_viernes" value="NO" <?= !($prof['aplicar_viernes'] ?? 0) ? 'checked' : '' ?>> No</label>
                            </div>
                        </div>

                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn-actualizar">Actualizar Profesorado</button>
                    </div>
                </form>

                <!-- Tabla de Tutorías Existente (se mantiene abajo como historial) -->
                <div class="info-section" style="margin-top: 4rem; border-top: 2px dashed #e2e8f0; padding-top: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1rem;">Historial de Tutorías y Cargos</h3>
                        <button class="btn-actualizar" style="margin:0; padding: 0.5rem 1rem; font-size:0.75rem; background: #64748b;">+ Nueva Asignación</button>
                    </div>
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Año</th>
                                <th>Curso / Grupo</th>
                                <th>Modalidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tutorias as $tut) { ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($tut['anio']) ?></td>
                                <td><?= htmlspecialchars($tut['curso']) ?></td>
                                <td><span class="badge" style="background: #f1f5f9; color: #475569; padding:0.2rem 0.5rem; border-radius:4px; font-size:0.75rem;"><?= htmlspecialchars($tut['modalidad']) ?></span></td>
                                <td>
                                    <button class="btn-icon" style="background:none; border:none; cursor:pointer;"><svg viewBox="0 0 24 24" width="14" fill="#64748b"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
                                </td>
                            </tr>
                            <?php } ?>
                            <?php if (empty($tutorias)) { ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 2rem;">No hay tutorías registradas en el historial.</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- RESTO DE TABS (Placeholder) -->
            <div id="tab-cv" style="<?= $active_tab == 'cv' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Currículum Vitae</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-asistencia" style="<?= $active_tab == 'asistencia' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Control de Asistencia</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-docs" style="<?= $active_tab == 'docs' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Documentos</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-cuenta" style="<?= $active_tab == 'cuenta' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Cuenta</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-formacion" style="<?= $active_tab == 'formacion' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Formación</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-perfil" style="<?= $active_tab == 'perfil' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Perfiles</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-comerciales" style="<?= $active_tab == 'comerciales' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Comercial</h3><p>Módulo en desarrollo...</p></div></div>
            <div id="tab-tareas" style="<?= $active_tab == 'tareas' ? '' : 'display:none;' ?>"><div class="info-section"><h3>Tareas</h3><p>Módulo en desarrollo...</p></div></div>

        </div>
    </main>
</div>

    <!-- MODAL: Subir Documentos Profesorado -->
    <div class="modal-overlay" id="modalDocumentos">
        <div class="modal-container" style="max-width: 700px;">
            <div class="modal-header">
                <h2 style="color: var(--label-blue);">Subir Documentación del Profesor</h2>
                <button class="modal-close" onclick="closeModalDocumentos()">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <!-- Info Profesor -->
                <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                    <div style="color: #b91c1c; font-weight: 800; font-size: 1.1rem;"><?= htmlspecialchars(($trabajador['nombre'] ?? '') . ' ' . ($trabajador['apellidos'] ?? '')) ?></div>
                    <div style="color: #475569; font-weight: 600; font-size: 0.9rem;">NIF: <?= htmlspecialchars($prof['dni'] ?? 'No definido') ?></div>
                </div>

                <form action="api/subir_documento_profesor.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="usuario_id" value="<?= $id ?>">
                    
                    <label style="display: block; font-weight: 700; color: #1e3a8a; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 15px;">Seleccione el tipo de archivo a subir:</label>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 25px;">
                        <?php 
                        $doc_types = [
                            'ACP' => 'Acreditación profesor',
                            'CNP' => 'Contrato profesor',
                            'CVP' => 'CV profesor',
                            'FVP' => 'FV profesor',
                            'MERP' => 'Méritos',
                            'NIFP' => 'NIF profesor',
                            'OTRO' => 'Otros documentos'
                        ];
                        foreach ($doc_types as $code => $label): ?>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <input type="radio" name="tipo_doc" value="<?= $code ?>" required style="width: 18px; height: 18px; accent-color: #1e3a8a;">
                            <span style="font-size: 0.85rem; font-weight: 600; color: #334155;"><?= $label ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="premium-field">
                        <label>Seleccionar Archivo</label>
                        <input type="file" name="documento" required style="width:100%; padding: 20px; border: 2px dashed #cbd5e1; background: #f8fafc; cursor: pointer; box-sizing: border-box;">
                    </div>

                    <button type="submit" class="btn-create-full" style="background: #1e3a8a; margin-top: 1rem;">Aceptar y Subir Archivo</button>
                </form>

                <!-- Guía de Nomenclatura -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <div style="font-weight: 700; color: #1e3a8a; font-size: 0.85rem; margin-bottom: 12px;">Forma de nombrar los archivos (clic para copiar):</div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php 
                        $nif = $prof['dni'] ?? 'NIF';
                        foreach ($doc_types as $code => $label): 
                            $filename = $nif . "-" . $code;
                        ?>
                        <div onclick="copyToClipboard('<?= $filename ?>', this)" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.2s;" title="Clic para copiar nombre">
                            <span style="font-size: 0.8rem; color: #64748b; font-weight: 500;"><?= $label ?>:</span>
                            <span style="font-family: monospace; font-weight: 700; color: #1e3a8a;"><?= $filename ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModalDocumentos() {
            document.getElementById('modalDocumentos').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeModalDocumentos() {
            document.getElementById('modalDocumentos').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(() => {
                const originalBg = element.style.background;
                element.style.background = '#dcfce7';
                element.style.borderColor = '#22c55e';
                setTimeout(() => {
                    element.style.background = originalBg;
                    element.style.borderColor = '#e2e8f0';
                }, 1000);
            });
        }

        let docIdToDelete = null;

        function eliminarDocumento(id, nombre) {
            docIdToDelete = id;
            document.getElementById('deleteDocName').innerText = nombre;
            document.getElementById('modalConfirmDelete').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmDelete() {
            document.getElementById('modalConfirmDelete').style.display = 'none';
            document.body.style.overflow = 'auto';
            docIdToDelete = null;
        }

        function ejecutarBorrado() {
            if (docIdToDelete) {
                location.href = 'api/eliminar_documento_profesor.php?id=' + docIdToDelete + '&usuario_id=<?= $id ?>';
            }
        }
    </script>
    <!-- MODAL: Confirmar Borrado -->
    <div class="modal-overlay" id="modalConfirmDelete" style="z-index: 3000;">
        <div class="modal-container" style="max-width: 400px; text-align: center;">
            <div class="modal-body" style="padding: 40px 30px;">
                <div style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <h3 style="margin: 0 0 10px; color: #1e293b; font-weight: 800; font-size: 1.2rem; text-transform: uppercase;">¿Eliminar documento?</h3>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin-bottom: 30px;">
                    Estás a punto de eliminar <strong id="deleteDocName" style="color: #1e293b;"></strong>.<br>
                    Esta acción es <strong>permanente</strong> y no se puede deshacer.
                </p>
                <div style="display: flex; gap: 10px;">
                    <button onclick="closeConfirmDelete()" style="flex: 1; padding: 12px; border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 0.75rem;">Cancelar</button>
                    <button onclick="ejecutarBorrado()" style="flex: 1; padding: 12px; border: none; background: #ef4444; color: #fff; border-radius: 8px; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 0.75rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">Sí, eliminar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
