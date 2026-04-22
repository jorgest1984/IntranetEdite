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
        .info-section h3 { color: var(--label-blue); border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
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
                                <option value="MADRID">MADRID</option>
                                <option value="BARCELONA">BARCELONA</option>
                                <!-- ... resto de provincias ... -->
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
                            <input type="email" name="email_fake" value="<?= htmlspecialchars($trabajador['email']) ?>" readonly style="color: #64748b;">
                        </div>
                        <div class="form-group" style="grid-column: span 4;">
                            <label>E-mail personal:</label>
                            <input type="email" name="email2" value="<?= htmlspecialchars($prof['email2'] ?? '') ?>">
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
                            <div style="font-size: 0.85rem; display: flex; gap: 10px; align-items: center;">
                                <label style="font-weight: 500;"><input type="radio" name="nuestro" value="SI" <?= ($prof['nuestro'] ?? 0) ? 'checked' : '' ?>> Sí</label>
                                <label style="font-weight: 500;"><input type="radio" name="nuestro" value="NO" <?= !($prof['nuestro'] ?? 0) ? 'checked' : '' ?>> No</label>
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
                    <h3>Gestión de Profesorado</h3>
                    <p>Módulo en desarrollo...</p>
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
