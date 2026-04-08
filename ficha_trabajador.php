<?php
// ficha_trabajador.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: alumnos.php");
    exit();
}

// Cargar datos base (de la tabla alumnos)
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->execute([$id]);
$alumno = $stmt->fetch();

if (!$alumno) {
    die("Trabajador/Alumno no encontrado.");
}

// Cargar detalles extendidos (de profesorado_detalles)
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE alumno_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

// Cargar Cursos (Contratos-Programa)
$stmtCursos = $pdo->prepare("
    SELECT m.id, m.situacion, c.denominacion as curso_nombre, p.nombre as plan_nombre, 
           conv.nombre as convocatoria_nombre, e.nombre_comercial as empresa_nombre,
           m.fecha_inicio, m.fecha_fin, m.n_accion, m.n_grupo, m.modalidad, m.horas
    FROM matriculas m
    JOIN convocatorias conv ON m.convocatoria_id = conv.id
    JOIN planes p ON conv.plan_id = p.id
    JOIN cursos c ON m.curso_id = c.id
    LEFT JOIN empresas e ON m.empresa_id = e.id
    WHERE m.alumno_id = ?
    ORDER BY m.fecha_inicio DESC
");
$stmtCursos->execute([$id]);
$cursos = $stmtCursos->fetchAll();

$success = $_GET['success'] ?? null;
$error = null;

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_trabajador') {
    try {
        // 1. Actualizar datos base en alumnos
        $sqlAlumno = "UPDATE alumnos SET 
            nombre = ?, primer_apellido = ?, segundo_apellido = ?, dni = ?, 
            fecha_nacimiento = ?, email = ?, telefono = ?, movil = ?, sexo = ?
            WHERE id = ?";
        $pdo->prepare($sqlAlumno)->execute([
            $_POST['nombre'], $_POST['apellido1'], $_POST['apellido2'], $_POST['nif'],
            $_POST['fecha_nacimiento'], $_POST['email'], $_POST['telefono'], $_POST['movil'], $_POST['sexo'] ?? $alumno['sexo'],
            $id
        ]);

        // 2. Actualizar/Insertar en profesorado_detalles
        $fields = [
            'alias', 'num_ss', 'omitir_ss', 'profesion', 'discapacitado', 'estudios',
            'tipo_via', 'nombre_via', 'tipo_numeracion', 'num_domicilio', 'calificador_num',
            'bloque', 'portal', 'escalera', 'planta', 'puerta', 'complemento',
            'cp_trabajador', 'localidad_trabajador', 'provincia_trabajador',
            'mananas_desde', 'mananas_hasta', 'tardes_desde', 'tardes_hasta', 'solo_los',
            'email2', 'ultima_empresa', 'centro_trabajo', 'enviar_emails',
            'usuario_plataforma', 'clave_plataforma', 'id_plataforma_2015', 'id_plataforma_2016',
            'bloqueado', 'restringido', 'baja',
            'entrega_atencion_de', 'entrega_domicilio', 'entrega_cp', 'entrega_localidad', 'entrega_provincia',
            'modulacion', 'horarios_pref'
        ];

        // Mapping special names from form to DB if they differ
        $field_mapping = [
            'id_plat_2015' => 'id_plataforma_2015',
            'id_plat_2016' => 'id_plataforma_2016'
        ];

        if (empty($prof)) {
            $cols = ['alumno_id'];
            $vals = [$id];
            foreach($fields as $f) {
                $cols[] = $f;
                // Check if we need to map from a different post name
                $post_key = array_search($f, $field_mapping) ?: $f;
                $vals[] = $_POST[$post_key] ?? null;
            }
            $placeholders = array_fill(0, count($cols), '?');
            $sqlProf = "INSERT INTO profesorado_detalles (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $pdo->prepare($sqlProf)->execute($vals);
        } else {
            $sets = [];
            $vals = [];
            foreach($fields as $f) {
                $sets[] = "$f = ?";
                $post_key = array_search($f, $field_mapping) ?: $f;
                $vals[] = $_POST[$post_key] ?? null;
            }
            $vals[] = $id;
            $sqlProf = "UPDATE profesorado_detalles SET " . implode(',', $sets) . " WHERE alumno_id = ?";
            $pdo->prepare($sqlProf)->execute($vals);
        }

        header("Location: ficha_trabajador.php?id=$id&success=1");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FICHA DE TRABAJADOR - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --ft-header-bg: #f97316; /* Color naranja cabecera según imagen */
            --ft-border: #b1b1b1;
            --ft-label-clr: #000080; /* Azul oscuro para etiquetas */
        }
        .ft-container {
            background: #fff;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            border: 1px solid var(--ft-border);
        }
        .ft-main-header {
            background: var(--ft-header-bg);
            color: #fff;
            text-align: center;
            font-weight: bold;
            padding: 4px;
            font-size: 13px;
            text-transform: uppercase;
        }
        .ft-form-row {
            display: flex;
            border-bottom: 1px solid #ddd;
            align-items: center;
        }
        .ft-group {
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-right: 1px solid #eee;
        }
        .ft-label {
            color: var(--ft-label-clr);
            font-weight: bold;
            margin-right: 6px;
            white-space: nowrap;
        }
        .ft-input {
            border: 1px solid #ccc;
            padding: 2px 4px;
            font-size: 11px;
            background: #fdfdfd;
        }
        .ft-input:focus {
            background: #fff;
            border-color: var(--ft-header-bg);
            outline: none;
        }
        .ft-address-display {
            font-weight: bold;
            color: #333;
            margin-left: 10px;
            font-size: 10px;
        }
        .ft-checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 4px 15px;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 10px;
        }
        .ft-section-title {
            background: #eee;
            color: #c00;
            font-weight: bold;
            text-align: center;
            padding: 4px;
            font-size: 12px;
            text-transform: uppercase;
            border-top: 2px solid var(--ft-header-bg);
            border-bottom: 2px solid var(--ft-header-bg);
            margin-top: 10px;
        }
        .ft-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .ft-table th {
            background: #e5e7eb;
            color: var(--ft-label-clr);
            padding: 6px;
            border: 1px solid var(--ft-border);
            text-align: left;
        }
        .ft-table td {
            padding: 6px;
            border: 1px solid var(--ft-border);
        }
        .bg-grey { background: #f3f4f6; }
        .text-center { text-align: center; }
        .btn-save {
            background: #eee;
            border: 1px solid #999;
            padding: 4px 10px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px auto;
            display: block;
        }
        .btn-save:hover { background: #ddd; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ft-container">
            <div class="ft-main-header">FICHA DE TRABAJADOR</div>
            
            <form method="POST">
                <input type="hidden" name="action" value="save_trabajador">
                
                <!-- ROW 1: ID, Comercial, Status -->
                <div class="ft-form-row">
                    <div class="ft-group" style="width: 80px;">
                        <input type="text" readonly class="ft-input" style="width: 100%;" value="<?= $id ?>">
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Comercial:</span>
                        <select name="comercial" class="ft-input" style="flex: 1;">
                            <option value="MARIA LUZ">MARIA LUZ</option>
                            <!-- Otros comerciales... -->
                        </select>
                    </div>
                    <div class="ft-checkbox-group">
                        <label><span style="color:red;">BLOQUEADO:</span> <input type="checkbox" name="bloqueado" value="1" <?= ($prof['bloqueado'] ?? 0) ? 'checked' : '' ?>></label>
                        <label><span style="color:red;">RESTRINGIDO:</span> <input type="checkbox" name="restringido" value="1" <?= ($prof['restringido'] ?? 0) ? 'checked' : '' ?>></label>
                        <label><span style="color:red;">BAJA:</span> <input type="checkbox" name="baja" value="1" <?= ($prof['baja'] ?? 0) ? 'checked' : '' ?>></label>
                    </div>
                </div>

                <!-- ROW 2: Nombre, Apellido 1, Apellido 2, Alias -->
                <div class="ft-form-row">
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Nombre:</span>
                        <input type="text" name="nombre" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($alumno['nombre'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Apellido 1:</span>
                        <input type="text" name="apellido1" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($alumno['primer_apellido'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Apellido 2:</span>
                        <input type="text" name="apellido2" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($alumno['segundo_apellido'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Alias:</span>
                        <input type="text" name="alias" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['alias'] ?? '') ?>">
                    </div>
                </div>

                <!-- ROW 3: NIF, Nacimiento, SS, Profesion -->
                <div class="ft-form-row">
                    <div class="ft-group" style="width: 15%;">
                        <span class="ft-label">NIF:</span>
                        <input type="text" name="nif" class="ft-input" style="width: 70px;" value="<?= htmlspecialchars($alumno['dni'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Fecha nacimiento:</span>
                        <input type="text" name="fecha_nacimiento" class="ft-input" style="width: 80px;" value="<?= htmlspecialchars($alumno['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Nº S.S.:</span>
                        <input type="text" name="num_ss" class="ft-input" style="width: 100px;" value="<?= htmlspecialchars($prof['num_ss'] ?? '') ?>">
                        <label style="margin-left: 10px; font-weight: normal;"><input type="checkbox" name="omitir_ss" value="1" <?= ($prof['omitir_ss'] ?? 0) ? 'checked' : '' ?>> / Omitir Nº S.S.:</label>
                    </div>
                    <div class="ft-group" style="width: 25%;">
                        <span class="ft-label">Profesión:</span>
                        <input type="text" name="profesion" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['profesion'] ?? '') ?>">
                    </div>
                </div>

                <!-- ROW 4: Discapacitado, Sexo, Estudios -->
                <div class="ft-form-row">
                    <div class="ft-group">
                        <span class="ft-label">Discapacitado:</span>
                        <input type="checkbox" name="discapacitado" value="1" <?= ($prof['discapacitado'] ?? 0) ? 'checked' : '' ?>>
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Sexo:</span>
                        <select name="sexo" class="ft-input">
                            <option value="Mujer" <?= ($alumno['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                            <option value="Hombre" <?= ($alumno['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                        </select>
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Estudios:</span>
                        <input type="text" name="estudios" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['estudios'] ?? '') ?>">
                    </div>
                </div>

                <!-- ADDRESS SECTION -->
                <div class="ft-form-row bg-grey">
                    <div class="ft-group" style="width: 20%;">
                        <span class="ft-label">Tipo de vía:</span>
                        <select name="tipo_via" class="ft-input" style="flex: 1;">
                            <option value="Camino" <?= ($prof['tipo_via'] ?? '') == 'Camino' ? 'selected' : '' ?>>Camino</option>
                            <option value="Calle" <?= ($prof['tipo_via'] ?? '') == 'Calle' ? 'selected' : '' ?>>Calle</option>
                            <option value="Avenida" <?= ($prof['tipo_via'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                        </select>
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Nombre de vía:</span>
                        <input type="text" name="nombre_via" id="in_nombre_via" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['nombre_via'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Tipo de numeración:</span>
                        <select name="tipo_numeracion" class="ft-input">
                            <option value="Nº" <?= ($prof['tipo_numeracion'] ?? '') == 'Nº' ? 'selected' : '' ?>>Nº</option>
                            <option value="KM" <?= ($prof['tipo_numeracion'] ?? '') == 'KM' ? 'selected' : '' ?>>KM</option>
                        </select>
                        <span class="ft-label" style="margin-left: 10px;">Nº domicilio:</span>
                        <input type="text" name="num_domicilio" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['num_domicilio'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Calificador nº:</span>
                        <select name="calificador_num" class="ft-input">
                            <option value="" <?= ($prof['calificador_num'] ?? '') == '' ? 'selected' : '' ?>>--</option>
                            <option value="Bis" <?= ($prof['calificador_num'] ?? '') == 'Bis' ? 'selected' : '' ?>>Bis</option>
                        </select>
                    </div>
                </div>

                <div class="ft-form-row bg-grey">
                    <div class="ft-group">
                        <span class="ft-label">Bloque:</span>
                        <input type="text" name="bloque" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['bloque'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Portal:</span>
                        <input type="text" name="portal" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['portal'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Escalera:</span>
                        <input type="text" name="escalera" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['escalera'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Planta:</span>
                        <input type="text" name="planta" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['planta'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Puerta:</span>
                        <input type="text" name="puerta" class="ft-input" style="width: 40px;" value="<?= htmlspecialchars($prof['puerta'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Complemento:</span>
                        <input type="text" name="complemento" id="in_complemento" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['complemento'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="ft-form-row bg-grey" style="padding: 2px 8px; border-top: none;">
                    <span class="ft-label">Domicilio:</span>
                    <span id="address_preview" class="ft-address-display">Cargando...</span>
                </div>

                <div class="ft-form-row">
                    <div class="ft-group">
                        <span class="ft-label">CP:</span>
                        <input type="text" name="cp_trabajador" class="ft-input" style="width: 50px;" value="<?= htmlspecialchars($prof['cp_trabajador'] ?? $alumno['cp']) ?>">
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Localidad:</span>
                        <input type="text" name="localidad_trabajador" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['localidad_trabajador'] ?? $alumno['localidad']) ?>">
                    </div>
                    <div class="ft-group" style="width: 30%;">
                        <span class="ft-label">Provincia:</span>
                        <select name="provincia_trabajador" class="ft-input" style="flex: 1;">
                            <?php 
                            $provincias = ["ÁLAVA","ALBACETE","ALICANTE","ALMERÍA","ASTURIAS","ÁVILA","BADAJOZ","BARCELONA","BURGOS","CÁCERES","CÁDIZ","CANTABRIA","CASTELLÓN","CIUDAD REAL","CÓRDOBA","A CORUÑA","CUENCA","GERONA","GRANADA","GUADALAJARA","GUIPÚZCOA","HUELVA","HUESCA","BALEARES","JAÉN","LEÓN","LÉRIDA","LUGO","MADRID","MÁLAGA","MURCIA","NAVARRA","OURENSE","PALENCIA","LAS PALMAS","PONTEVEDRA","LA RIOJA","SALAMANCA","SEGOVIA","SEVILLA","SORIA","TARRAGONA","SANTA CRUZ DE TENERIFE","TERUEL","TOLEDO","VALENCIA","VALLADOLID","VIZCAYA","ZAMORA","ZARAGOZA","CEUTA","MELILLA"];
                            foreach($provincias as $prov): ?>
                                <option value="<?= $prov ?>" <?= (strtoupper($prof['provincia_trabajador'] ?? $alumno['provincia'] ?? '') == $prov) ? 'selected' : '' ?>><?= $prov ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- CONTACT & TIMINGS -->
                <div class="ft-form-row">
                    <div class="ft-group">
                        <span class="ft-label">Teléfono:</span>
                        <input type="text" name="telefono" class="ft-input" style="width: 100px;" value="<?= htmlspecialchars($alumno['telefono'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Móvil:</span>
                        <input type="text" name="movil" class="ft-input" style="width: 100px;" value="<?= htmlspecialchars($alumno['movil'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Mañanas desde:</span>
                        <input type="time" name="mananas_desde" class="ft-input" value="<?= $prof['mananas_desde'] ?? '' ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Mañanas hasta:</span>
                        <input type="time" name="mananas_hasta" class="ft-input" value="<?= $prof['mananas_hasta'] ?? '' ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Tardes desde:</span>
                        <input type="time" name="tardes_desde" class="ft-input" value="<?= $prof['tardes_desde'] ?? '' ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Tardes hasta:</span>
                        <input type="time" name="tardes_hasta" class="ft-input" value="<?= $prof['tardes_hasta'] ?? '' ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Sólo los:</span>
                        <input type="text" name="solo_los" class="ft-input" style="width: 100px;" value="<?= htmlspecialchars($prof['solo_los'] ?? '') ?>">
                    </div>
                </div>

                <!-- EMAIL & COMPANY -->
                <div class="ft-form-row">
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">E-mail:</span>
                        <input type="email" name="email" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($alumno['email'] ?? '') ?>">
                    </div>
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">E-mail 2:</span>
                        <input type="email" name="email2" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['email2'] ?? '') ?>">
                    </div>
                </div>

                <div class="ft-form-row bg-grey">
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Última Empresa:</span>
                        <input type="text" name="ultima_empresa" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['ultima_empresa'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Centro de trabajo:</span>
                        <input type="text" name="centro_trabajo" class="ft-input" style="width: 60px;" value="<?= htmlspecialchars($prof['centro_trabajo'] ?? '1') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Enviar e-mails:</span>
                        <input type="checkbox" name="enviar_emails" value="1" <?= ($prof['enviar_emails'] ?? 1) ? 'checked' : '' ?>>
                    </div>
                </div>

                <!-- PLATFORM ACCESS -->
                <div class="ft-form-row">
                    <div class="ft-group">
                        <span class="ft-label">Acceso Plataforma: Usuario:</span>
                        <input type="text" name="usuario_plataforma" class="ft-input" value="<?= htmlspecialchars($prof['usuario_plataforma'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Clave:</span>
                        <input type="text" name="clave_plataforma" class="ft-input" value="<?= htmlspecialchars($prof['clave_plataforma'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Id Plat 2015:</span>
                        <input type="text" name="id_plat_2015" class="ft-input" value="<?= htmlspecialchars($prof['id_plataforma_2015'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Id Plat 2016:</span>
                        <input type="text" name="id_plat_2016" class="ft-input" value="<?= htmlspecialchars($prof['id_plataforma_2016'] ?? '') ?>">
                    </div>
                </div>

                <!-- PREFERENCES -->
                <div class="ft-form-row">
                    <div class="ft-group">
                        <span class="ft-label">Preferencias impartición presencial:</span>
                        <input type="text" name="preferencias_presencial" class="ft-input" style="width: 150px;" value="<?= htmlspecialchars($prof['preferencias_presencial'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Modulación:</span>
                        <select name="modulacion" class="ft-input">
                            <option value="">--</option>
                        </select>
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Horarios:</span>
                        <select name="horarios_pref" class="ft-input">
                            <option value="">--</option>
                        </select>
                    </div>
                </div>

                <!-- OBSERVATIONS -->
                <div class="ft-form-row" style="flex-direction: column; align-items: stretch; padding: 10px;">
                    <span class="ft-label" style="margin-bottom: 5px;">Observaciones:</span>
                    <textarea name="observaciones" class="ft-input" style="height: 60px;"><?= htmlspecialchars($prof['horario_general'] ?? '') ?></textarea>
                </div>

                <!-- SHIPPING ADDRESS -->
                <div class="ft-section-title" style="margin-top: 0; border:none; background:#f8fafc; color:#333; font-size:10px; border-bottom:1px solid #ddd;">DOMICILIO DIFERENTE PARA ENTREGAS DE MATERIAL :</div>
                <div class="ft-form-row bg-grey">
                    <div class="ft-group" style="width: 100%;">
                        <span class="ft-label">A la atención de:</span>
                        <input type="text" name="entrega_atencion_de" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['entrega_atencion_de'] ?? '') ?>">
                    </div>
                </div>
                <div class="ft-form-row bg-grey">
                    <div class="ft-group" style="flex: 1;">
                        <span class="ft-label">Domicilio:</span>
                        <input type="text" name="entrega_domicilio" class="ft-input" style="flex: 1;" value="<?= htmlspecialchars($prof['entrega_domicilio'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">CP:</span>
                        <input type="text" name="entrega_cp" class="ft-input" style="width: 50px;" value="<?= htmlspecialchars($prof['entrega_cp'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Localidad:</span>
                        <input type="text" name="entrega_localidad" class="ft-input" value="<?= htmlspecialchars($prof['entrega_localidad'] ?? '') ?>">
                    </div>
                    <div class="ft-group">
                        <span class="ft-label">Provincia:</span>
                        <select name="entrega_provincia" class="ft-input">
                            <option value="">--</option>
                            <?php foreach($provincias as $prov): ?>
                                <option value="<?= $prov ?>" <?= (strtoupper($prof['entrega_provincia'] ?? '') == $prov) ? 'selected' : '' ?>><?= $prov ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-save">Guardar registro</button>
            </form>

            <div class="ft-section-title">CURSOS CONTRATOS-PROGRAMA</div>
            <div style="padding: 10px;">
                <p class="text-center" style="font-weight: bold; color: var(--ft-label-clr); margin-bottom: 10px;">Se muestran cursos de todas las convocatorias. <a href="#" style="color: blue; text-decoration: underline;">VER SÓLO CURSOS DE CONVOCATORIA ACTUAL</a></p>
                <table class="ft-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Plan</th>
                            <th>Nº Acción</th>
                            <th>Nº Grupo</th>
                            <th>Modalidad</th>
                            <th>Horas</th>
                            <th>Curso</th>
                            <th>Tutor</th>
                            <th>Situación</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cursos)): ?>
                            <tr><td colspan="12" class="text-center">No hay cursos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cursos as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['empresa_nombre'] ?? 'DESEMPLEADO') ?></td>
                                <td><?= htmlspecialchars($c['plan_nombre']) ?></td>
                                <td><?= htmlspecialchars($c['n_accion']) ?></td>
                                <td><?= htmlspecialchars($c['n_grupo']) ?></td>
                                <td><?= htmlspecialchars($c['modalidad']) ?></td>
                                <td><?= htmlspecialchars($c['horas']) ?></td>
                                <td><?= htmlspecialchars($c['curso_nombre']) ?></td>
                                <td>Patricia Ferreiro Matias</td> <!-- Mocked based on screenshot -->
                                <td><?= htmlspecialchars($c['situacion']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['fecha_fin'])) ?></td>
                                <td class="text-center">📝 ❌</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
    function updateAddressPreview() {
        const via = document.querySelector('select[name="tipo_via"]').value;
        const nombre = document.getElementById('in_nombre_via').value;
        const num = document.querySelector('input[name="num_domicilio"]').value;
        const comp = document.getElementById('in_complemento').value;
        
        let preview = `${via} ${nombre}`;
        if(num) preview += ` nº ${num}`;
        if(comp) preview += ` ${comp}`;
        
        document.getElementById('address_preview').textContent = preview;
    }

    document.querySelectorAll('.ft-input').forEach(input => {
        input.addEventListener('input', updateAddressPreview);
    });
    window.addEventListener('load', updateAddressPreview);
</script>

</body>
</html>
