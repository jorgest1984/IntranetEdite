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

// Acciones CV: Agregar Registros (Formación, Experiencia, Idiomas, Informática)
$cv_actions = [
    'add_formacion' => ['table' => 'prof_formacion', 'fields' => ['denominacion', 'organismo', 'centro', 'desde', 'hasta', 'horas', 'tipo_formacion']],
    'add_experiencia' => ['table' => 'prof_experiencia', 'fields' => ['empresa', 'desde', 'hasta', 'cargo', 'tareas']],
    'add_idioma' => ['table' => 'prof_idiomas', 'fields' => ['idioma', 'nivel_hablado', 'nivel_oral', 'nivel_escrito', 'nivel_leido']],
    'add_informatica' => ['table' => 'prof_informatica', 'fields' => ['programa', 'dominio']],
    'add_asistencia' => ['table' => 'prof_asistencia', 'fields' => ['fecha_desde', 'fecha_hasta', 'tipo', 'duracion_dias', 'duracion_horas', 'observaciones']]
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($cv_actions[$_POST['action']])) {
    try {
        $act = $cv_actions[$_POST['action']];
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
    } catch (Exception $e) { $error = "Error al añadir registro: " . $e->getMessage(); }
}

// Obtener registros para el CV
$cv_formacion = $pdo->prepare("SELECT * FROM prof_formacion WHERE usuario_id = ? ORDER BY desde DESC");
$cv_formacion->execute([$id]);
$cv_formacion = $cv_formacion->fetchAll();

$cv_experiencia = $pdo->prepare("SELECT * FROM prof_experiencia WHERE usuario_id = ? ORDER BY desde DESC");
$cv_experiencia->execute([$id]);
$cv_experiencia = $cv_experiencia->fetchAll();

// Cargar departamentos y perfiles del usuario
$dept_stmt = $pdo->prepare("SELECT departamento FROM usuario_departamentos WHERE usuario_id = ?");
$dept_stmt->execute([$id]);
$selected_depts = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

$perfil_stmt = $pdo->prepare("SELECT perfil FROM usuario_perfiles WHERE usuario_id = ?");
$perfil_stmt->execute([$id]);
$selected_perfiles = $perfil_stmt->fetchAll(PDO::FETCH_COLUMN);

// Procesar actualización de perfiles y departamentos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_perfiles_dept') {
        try {
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
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }

    if ($_POST['action'] == 'update_personales') {
        try {
            // Actualizar tabla usuarios (Nombre y Apellidos básicos - por consistencia)
            $stU = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellidos = ? WHERE id = ?");
            $apellidos_full = trim(($_POST['apellido1'] ?? '') . ' ' . ($_POST['apellido2'] ?? ''));
            $stU->execute([$_POST['nombre'], $apellidos_full, $id]);

            // Actualizar tabla profesorado_detalles
            $fields = [
                'dni', 'fecha_nacimiento', 'apellido1', 'apellido2',
                'num_ss', 'cuenta_bancaria',
                'nombre_via', 'cp_trabajador', 'localidad_trabajador', 'provincia_trabajador',
                'telefono', 'telefono_empresa',
                'email2', 'skype', 'sexo',
                'activo_hasta', 'nuestro', 'observaciones_personales'
            ];
            
            $set_part = implode(' = ?, ', $fields) . ' = ?';
            $sql = "UPDATE profesorado_detalles SET $set_part WHERE usuario_id = ?";
            
            $params = [];
            foreach ($fields as $f) {
                $val = $_POST[$f] ?? null;
                if ($f == 'nuestro') $val = ($val == 'SI' ? 1 : 0);
                if ($f == 'activo_hasta' && !empty($val) && strlen($val) == 4) $val = $val . "-12-31"; // Convertir año a fecha
                if ($val === '') $val = null;
                $params[] = $val;
            }
            $params[] = $id;

            // Asegurar que el registro existe en profesorado_detalles
            $check = $pdo->prepare("SELECT 1 FROM profesorado_detalles WHERE usuario_id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO profesorado_detalles (usuario_id) VALUES (?)")->execute([$id]);
            }

            $stP = $pdo->prepare($sql);
            $stP->execute($params);

            header("Location: ficha_trabajador.php?id=$id&tab=personales&success=1");
            exit();
        } catch (Exception $e) {
            $error = "Error al actualizar datos personales: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Personal: <?= htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellidos']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .trabajador-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px 8px 0 0;
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
        }
        .tabs-header::-webkit-scrollbar { display: none; } /* Chrome/Safari */

        .tab-btn {
            padding: 0.75rem 1.25rem;
            border: none;
            background: none;
            font-size: 0.8rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            white-space: nowrap;
            border-right: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .tab-btn:hover { background: #f1f5f9; }
        .tab-btn.active { 
            background: white; 
            color: #1e40af; 
            font-weight: 700;
            box-shadow: inset 0 2px 0 #1e40af;
        }
        .tab-panel {
            background: white;
            padding: 2rem;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 12px 12px;
            min-height: 600px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        /* Formulario Premium Estilo Imagen */
        .form-premium-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem 2rem;
            align-items: center;
        }
        .form-premium-grid .form-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-premium-grid label {
            font-weight: 700;
            font-size: 0.85rem;
            color: #0f172a;
            white-space: nowrap;
            min-width: fit-content;
        }
        .form-premium-grid input[type="text"],
        .form-premium-grid input[type="date"],
        .form-premium-grid input[type="email"],
        .form-premium-grid select {
            border: none;
            border-bottom: 1px solid #cbd5e1;
            padding: 4px 0;
            font-size: 0.85rem;
            color: #1e40af;
            font-weight: 500;
            width: 100%;
            background: transparent;
        }
        .form-premium-grid input:focus {
            outline: none;
            border-bottom-color: #1e40af;
        }
        
        .observations-box {
            grid-column: span 12;
            margin-top: 1rem;
        }
        .observations-box textarea {
            width: 100%;
            height: 80px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px;
            font-size: 0.85rem;
            background: #f8fafc;
            resize: none;
        }

        .btn-actualizar {
            background: white;
            border: 1px solid #cbd5e1;
            padding: 4px 15px;
            font-size: 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.2s;
        }
        .btn-actualizar:hover { background: #f1f5f9; }

        .info-section { margin-bottom: 2rem; }
        .info-section h3 { color: #1e40af; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1.5rem; font-size: 1.1rem; }

        /* Iconos amarillos emails */
        .email-wrapper {
            display: flex;
            align-items: center;
            gap: 5px;
            width: 100%;
        }
        .btn-yellow-icon {
            background: #fde047;
            border: none;
            border-radius: 4px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.2s;
        }
        .btn-yellow-icon:hover { background: #facc15; }
        .btn-yellow-icon svg { width: 14px; height: 14px; fill: #854d0e; }

        /* Radio Buttons Polish */
        .radio-group-premium {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: #0f172a;
        }
        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            font-weight: 500;
        }
        .radio-item input[type="radio"] {
            accent-color: #1e40af;
            cursor: pointer;
        }

        /* Tutorias Table */
        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
        .table-premium th {
            text-align: left;
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e2e8f0;
            color: #475569;
            font-weight: 600;
        }
        .table-premium td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        .table-premium tr:hover { background: #f8fafc; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="trabajador-header">
            <div>
                <a href="usuarios.php" class="btn-back">← Volver a Usuarios</a>
                <h1 style="margin-top: 0.5rem; color: var(--primary-color);"><?= htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellidos']) ?></h1>
                <p style="color: var(--text-muted); margin: 0;"><?= htmlspecialchars($trabajador['email']) ?> | Perfil de Trabajador</p>
            </div>
            <div>
                <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700;">TRABAJADOR</span>
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
            <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Cambios guardados con éxito en el perfil profesional.</div><?php endif; ?>

            <!-- TAB: Personales (Estética idéntica a imagen) -->
            <div id="tab-personales" style="<?= $active_tab == 'personales' ? '' : 'display:none;' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_personales">
                    <div class="form-premium-grid">
                        
                        <!-- FILA 1 -->
                        <div class="form-group" style="grid-column: span 4;">
                            <label>DNI:</label>
                            <input type="text" name="dni" value="<?= htmlspecialchars($prof['dni'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 8;">
                            <label>Fecha de nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($prof['fecha_nacimiento'] ?? '') ?>" style="max-width: 150px;">
                        </div>

                        <!-- FILA 2 -->
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($trabajador['nombre']) ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 4;">
                            <label>Primer apellido:</label>
                            <input type="text" name="apellido1" value="<?= htmlspecialchars($prof['apellido1'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 5;">
                            <label>Segundo apellido:</label>
                            <input type="text" name="apellido2" value="<?= htmlspecialchars($prof['apellido2'] ?? '') ?>">
                        </div>

                        <!-- FILA 3 -->
                        <div class="form-group" style="grid-column: span 4;">
                            <label>Seguridad Social:</label>
                            <input type="text" name="num_ss" value="<?= htmlspecialchars($prof['num_ss'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 8;">
                            <label>Cuenta Bancaria:</label>
                            <input type="text" name="cuenta_bancaria" value="<?= htmlspecialchars($prof['cuenta_bancaria'] ?? '') ?>">
                        </div>

                        <!-- FILA 4 -->
                        <div class="form-group" style="grid-column: span 12;">
                            <label>Domicilio:</label>
                            <input type="text" name="nombre_via" value="<?= htmlspecialchars($prof['nombre_via'] ?? '') ?>">
                        </div>

                        <!-- FILA 5 -->
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
                                <option value="Álava">Álava</option>
                                <option value="Albacete">Albacete</option>
                                <option value="Alicante">Alicante</option>
                                <option value="Almería">Almería</option>
                                <option value="Asturias">Asturias</option>
                                <option value="Ávila">Ávila</option>
                                <option value="Badajoz">Badajoz</option>
                                <option value="Baleares">Baleares</option>
                                <option value="Barcelona">Barcelona</option>
                                <option value="Burgos">Burgos</option>
                                <option value="Cáceres">Cáceres</option>
                                <option value="Cádiz">Cádiz</option>
                                <option value="Cantabria">Cantabria</option>
                                <option value="Castellón">Castellón</option>
                                <option value="Ciudad Real">Ciudad Real</option>
                                <option value="Córdoba">Córdoba</option>
                                <option value="Cuenca">Cuenca</option>
                                <option value="Gerona">Gerona</option>
                                <option value="Granada">Granada</option>
                                <option value="Guadalajara">Guadalajara</option>
                                <option value="Guipúzcoa">Guipúzcoa</option>
                                <option value="Huelva">Huelva</option>
                                <option value="Huesca">Huesca</option>
                                <option value="Jaén">Jaén</option>
                                <option value="La Coruña">La Coruña</option>
                                <option value="La Rioja">La Rioja</option>
                                <option value="Las Palmas">Las Palmas</option>
                                <option value="León">León</option>
                                <option value="Lérida">Lérida</option>
                                <option value="Lugo">Lugo</option>
                                <option value="Madrid">Madrid</option>
                                <option value="Málaga">Málaga</option>
                                <option value="Murcia">Murcia</option>
                                <option value="Navarra">Navarra</option>
                                <option value="Orense">Orense</option>
                                <option value="Palencia">Palencia</option>
                                <option value="Pontevedra">Pontevedra</option>
                                <option value="Salamanca">Salamanca</option>
                                <option value="Santa Cruz de Tenerife">Santa Cruz de Tenerife</option>
                                <option value="Segovia">Segovia</option>
                                <option value="Sevilla">Sevilla</option>
                                <option value="Soria">Soria</option>
                                <option value="Tarragona">Tarragona</option>
                                <option value="Teruel">Teruel</option>
                                <option value="Toledo">Toledo</option>
                                <option value="Valencia">Valencia</option>
                                <option value="Valladolid">Valladolid</option>
                                <option value="Vizcaya">Vizcaya</option>
                                <option value="Zamora">Zamora</option>
                                <option value="Zaragoza">Zaragoza</option>
                                <option value="Ceuta">Ceuta</option>
                                <option value="Melilla">Melilla</option>
                            </select>
                        </div>

                        <!-- FILA 6 -->
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Teléfono:</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($prof['telefono'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 9;">
                            <label>Teléfono empresa:</label>
                            <input type="text" name="telefono_empresa" value="<?= htmlspecialchars($prof['telefono_empresa'] ?? '') ?>" style="max-width: 200px;">
                        </div>

                        <!-- FILA 7 -->
                        <div class="form-group" style="grid-column: span 5;">
                            <label>E-mail:</label>
                            <div class="email-wrapper">
                                <input type="email" name="email_fake" value="<?= htmlspecialchars($trabajador['email']) ?>" readonly style="color: #64748b;">
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

                        <!-- FILA 8 -->
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Sexo:</label>
                            <select name="sexo">
                                <option value="Hombre" <?= ($prof['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                <option value="Mujer" <?= ($prof['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                <option value="Otro" <?= ($prof['sexo'] ?? '') == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>

                        <!-- FILA 9 -->
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

                        <!-- OBSERVACIONES -->
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

            <!-- TAB: CV -->
            <div id="tab-cv" style="<?= $active_tab == 'cv' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Experiencia y Formación</h3>
                    <p>Gestiona el CV del trabajador para acreditaciones y perfil de tutor.</p>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Denominación / Empresa</th>
                                <th>Periodo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cv_formacion as $f): ?>
                            <tr>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1;">Formación</span></td>
                                <td><?= htmlspecialchars($f['denominacion']) ?></td>
                                <td><?= $f['desde'] ?> / <?= $f['hasta'] ?></td>
                                <td>Verificado</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cv_formacion)): ?>
                            <tr><td colspan="4" style="text-align:center;">No hay registros de formación todavía.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Perfil/Depto -->
            <div id="tab-perfil" style="<?= $active_tab == 'perfil' ? '' : 'display:none;' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_perfiles_dept">
                    <div class="info-section">
                        <h3>Departamentos</h3>
                        <div class="checkbox-grid">
                            <?php $depts = ['ADMINISTRACIÓN', 'COMERCIAL', 'DIRECCIÓN', 'FORMACIÓN', 'GRUPOS', 'INFORMÁTICA', 'MARKETING', 'RECURSOS HUMANOS']; 
                            foreach($depts as $d): ?>
                                <label><input type="checkbox" name="depts[]" value="<?= $d ?>" <?= in_array($d, $selected_depts) ? 'checked' : '' ?>> <?= $d ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="info-section">
                        <h3>Perfil de Usuario</h3>
                        <div class="checkbox-grid">
                            <?php $perfiles = ['ADMINISTRADOR', 'COORDINADOR', 'COMERCIAL', 'TUTOR', 'ADMINISTRATIVO', 'SOPORTE']; 
                            foreach($perfiles as $p): ?>
                                <label><input type="checkbox" name="perfiles[]" value="<?= $p ?>" <?= in_array($p, $selected_perfiles) ? 'checked' : '' ?>> <?= $p ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar Atribuciones</button>
                </form>
            </div>

            <!-- PLACEHOLDERS PARA OTRAS PESTAÑAS (En desarrollo) -->
            <div id="tab-profesorado" style="<?= $active_tab == 'profesorado' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Gestión Académica y Tutorías</h3>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <p style="color: #64748b; font-size: 0.9rem;">Historial de tutorías y cargos académicos del trabajador.</p>
                        <button type="button" class="btn btn-primary" style="font-size: 0.75rem; padding: 5px 12px;">+ Nueva Tutoría</button>
                    </div>

                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Año</th>
                                <th>Curso / Denominación</th>
                                <th>Modalidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmtTut = $pdo->prepare("SELECT * FROM prof_tutorias WHERE usuario_id = ? ORDER BY anio DESC");
                            $stmtTut->execute([$id]);
                            $tutorias = $stmtTut->fetchAll();
                            
                            foreach ($tutorias as $tut): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($tut['anio']) ?></td>
                                <td><?= htmlspecialchars($tut['curso']) ?></td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #475569;"><?= htmlspecialchars($tut['modalidad']) ?></span>
                                </td>
                                <td>
                                    <button class="btn-icon" title="Editar"><svg viewBox="0 0 24 24" width="14"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($tutorias)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 2rem;">No hay tutorías registradas para este trabajador.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="info-section" style="margin-top: 3rem;">
                    <h3>Acreditaciones Académicas</h3>
                    <p style="color: #64748b; font-size: 0.9rem;">Documentación y títulos habilitantes para la docencia.</p>
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; color: #94a3b8;">
                        Espacio para gestión de títulos (CAP, Máster, etc.) - Próximamente
                    </div>
                </div>
            </div>

            <div id="tab-asistencia" style="<?= $active_tab == 'asistencia' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Control de Asistencia Laboral</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>

            <div id="tab-docs" style="<?= $active_tab == 'docs' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Documentación Asociada</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>

            <div id="tab-cuenta" style="<?= $active_tab == 'cuenta' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Configuración de Cuenta</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>

            <div id="tab-formacion" style="<?= $active_tab == 'formacion' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Historial de Formación</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>

            <div id="tab-comerciales" style="<?= $active_tab == 'comerciales' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Gestión Comercial</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>

            <div id="tab-tareas" style="<?= $active_tab == 'tareas' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Listado de Tareas</h3>
                    <p>Módulo en desarrollo...</p>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
