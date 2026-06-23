<?php
require_once 'includes/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID de matrícula no especificado.");
}

// 1. Obtener datos masivos de la matrícula
$stmtMatricula = $pdo->prepare("
    SELECT m.*, 
           a.nombre as alumno_nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.fecha_nacimiento, a.sexo, 
           a.tipo_via, a.nombre_via, a.num_domicilio, a.escalera, a.planta, a.puerta, a.codigo_postal, a.provincia, a.localidad, a.telefono, a.movil, a.email, a.ss, a.estudios, a.profesion, a.discapacitado,
           c.nombre as convocatoria_nombre, c.codigo_expediente,
           p.nombre as plan_nombre, 
           e.nombre as empresa_nombre,
           g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin,
           af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, 
           cu.nombre_corto as curso_nombre, cu.titulo as curso_titulo
    FROM matriculas m
    LEFT JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
    LEFT JOIN planes p ON c.id = p.convocatoria_id
    LEFT JOIN grupos g ON m.grupo_id = g.id
    LEFT JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN cursos cu ON af.curso_id = cu.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.id = ?
");
$stmtMatricula->execute([$id]);
$matricula = $stmtMatricula->fetch(PDO::FETCH_ASSOC);

if (!$matricula) {
    die("Matrícula no encontrada.");
}

// 2. Procesar formulario (Si el usuario guarda datos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_datos_personales') {
    // Aquí actualizaríamos la tabla alumnos. Como algunos campos de la imagen podrían no existir, 
    // hacemos un try-catch o actualizamos solo los seguros por ahora.
    $sql = "UPDATE alumnos SET 
            dni = ?, ss = ?, fecha_nacimiento = ?, 
            nombre = ?, primer_apellido = ?, segundo_apellido = ?, 
            profesion = ?, estudios = ?, discapacitado = ?, sexo = ?,
            tipo_via = ?, nombre_via = ?, num_domicilio = ?, escalera = ?, planta = ?, puerta = ?,
            codigo_postal = ?, provincia = ?, localidad = ?,
            telefono = ?, movil = ?, email = ?
            WHERE id = ?";
            
    try {
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            $_POST['dni'] ?? null, $_POST['ss'] ?? null, $_POST['fecha_nacimiento'] ?? null,
            $_POST['nombre'] ?? null, $_POST['primer_apellido'] ?? null, $_POST['segundo_apellido'] ?? null,
            $_POST['profesion'] ?? null, $_POST['estudios'] ?? null, $_POST['discapacitado'] ?? null, $_POST['sexo'] ?? null,
            $_POST['tipo_via'] ?? null, $_POST['nombre_via'] ?? null, $_POST['num_domicilio'] ?? null, $_POST['escalera'] ?? null, $_POST['planta'] ?? null, $_POST['puerta'] ?? null,
            $_POST['codigo_postal'] ?? null, $_POST['provincia'] ?? null, $_POST['localidad'] ?? null,
            $_POST['telefono'] ?? null, $_POST['movil'] ?? null, $_POST['email'] ?? null,
            $matricula['alumno_id']
        ]);
        header("Location: ficha_matricula.php?id=$id&success=1");
        exit();
    } catch (Exception $e) {
        $error = "Error al actualizar (es posible que algunos campos como SS o Profesion no estén en la base de datos aún): " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha Matrícula - <?= htmlspecialchars($matricula['alumno_nombre'] ?? '') ?></title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        
        .top-bar {
            background-color: #0073C4;
            color: white;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-group-top {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            justify-content: flex-end;
        }

        .btn-top {
            background: #fff;
            color: #0073C4;
            border: 1px solid #005a9c;
            padding: 2px 6px;
            font-size: 10px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-inscribir {
            margin: 5px 10px;
            background: #e0e0e0;
            border: 1px solid #999;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
            display: inline-block;
        }

        .info-header {
            border: 1px solid #ccc;
            margin: 5px 10px;
            padding: 5px;
        }

        .info-header-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 11px;
            color: #0000ff;
            font-weight: bold;
        }
        
        .info-header-row span {
            margin-right: 20px;
        }

        .info-header-row .label {
            color: #000;
        }

        .tabs {
            margin: 10px 10px 0 10px;
            border-bottom: 1px solid #ccc;
        }

        .tab {
            display: inline-block;
            padding: 4px 10px;
            border: 1px solid #ccc;
            border-bottom: none;
            background: #f0f0f0;
            color: #999;
            font-size: 11px;
            text-decoration: none;
            margin-right: 2px;
        }

        .tab.active {
            background: #fff;
            color: #0000ff;
            font-weight: bold;
        }

        .form-container {
            border: 1px solid #ccc;
            border-top: none;
            margin: 0 10px 10px 10px;
            padding: 10px;
        }

        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }

        .form-row label {
            font-weight: bold;
            color: #000;
            margin-right: 5px;
            font-size: 11px;
            width: 120px;
            text-align: right;
        }

        .form-row input, .form-row select {
            border: 1px solid #999;
            padding: 1px 3px;
            font-size: 11px;
            color: #0000ff;
            font-family: Arial;
        }

        .section-title {
            color: #ff0000;
            font-weight: bold;
            font-size: 11px;
            margin-top: 15px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .w-small { width: 50px; }
        .w-medium { width: 120px; }
        .w-large { width: 250px; }
        .w-xl { width: 400px; }
    </style>
</head>
<body>

<div style="text-align: center; font-size: 9px; color: #ff0000; margin-top: 2px;">
    Hola <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?>, bienvenid@
    <br>
    E: centro GRANADA :1
</div>

<div class="top-bar">
    <div style="font-size: 16px; font-weight: bold;">e<br><span style="font-size: 10px; font-weight: normal;">on</span></div>
    <div class="btn-group-top">
        <button class="btn-top">Notificar Baja/Aban</button>
        <button class="btn-top">Envio Claves</button>
        <button class="btn-top">Incidencia doc.</button>
        <a href="ficha_alumno.php?id=<?= $matricula['alumno_id'] ?>" class="btn-top">Ficha alumno</a>
        <button class="btn-top">Documentos</button>
        <button class="btn-top">Ficha inscr. Edite 2010 Privada</button>
        <button class="btn-top">Subir documento</button>
        <button class="btn-top">Actualizar desde Aula Virtual</button>
        <button class="btn-top">Actualizar datos en Aula Virtual</button>
        <button class="btn-top">Archivo doc</button>
        <button class="btn-top">Datos empresa</button>
        <button class="btn-top">Contacto vCard</button>
        <button class="btn-top">Contacto QR</button>
        <button class="btn-top">Página Inicio</button>
        <button class="btn-top">Ayuda</button>
        <button class="btn-top">Desconectar</button>
    </div>
</div>

<button class="btn-inscribir">Inscribir este alumno en otro curso</button>

<div class="info-header">
    <div class="info-header-row">
        <span><span class="label">Usuario:</span> 557 <span class="label">COD:</span> 09836</span>
        <span><span class="label">Alumno:</span> <?= mb_strtoupper($matricula['alumno_nombre'] . ' ' . $matricula['primer_apellido'] . ' ' . $matricula['segundo_apellido']) ?></span>
        <span><span class="label">ACCION:</span> <?= htmlspecialchars($matricula['af_abreviatura'] ?? '0386') ?> <span class="label">GRUPO:</span> <?= htmlspecialchars($matricula['numero_grupo'] ?? '1') ?></span>
    </div>
    <div class="info-header-row" style="margin-top: 8px;">
        <span><span class="label">PLAN:</span> <?= htmlspecialchars($matricula['plan_nombre'] ?? 'Ministerio de Educación, Formación Profesional y Deportes 2023') ?></span>
        <span><span class="label">CURSO:</span> <?= htmlspecialchars($matricula['curso_titulo'] ?? 'UF0131: Técnicas de comunicacion con personas dependientes en instituciones.') ?></span>
        <span><span class="label">INICIO:</span> <?= !empty($matricula['grupo_inicio']) ? date('d/m/Y', strtotime($matricula['grupo_inicio'])) : '26/05/2025' ?> <span class="label">FIN:</span> <?= !empty($matricula['grupo_fin']) ? date('d/m/Y', strtotime($matricula['grupo_fin'])) : '06/06/2025' ?></span>
    </div>
</div>

<?php if (isset($error)): ?><div style="color:red; margin-left: 10px; font-weight:bold;"><?= $error ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div style="color:green; margin-left: 10px; font-weight:bold;">Datos guardados correctamente.</div><?php endif; ?>

<div class="tabs">
    <a href="#" class="tab active">Datos Personales</a>
    <a href="#" class="tab">Datos Laborales</a>
    <a href="#" class="tab">Datos Curso</a>
    <a href="#" class="tab">Material y doc.</a>
</div>

<div class="form-container">
    <form method="POST">
        <input type="hidden" name="action" value="update_datos_personales">
        
        <div style="text-align: right; margin-bottom: 10px;">
            <button type="submit" style="background:#e0e0e0; border:1px solid #999; padding:2px 10px; font-size:11px; cursor:pointer;">Guardar Datos</button>
        </div>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <div class="form-row">
                    <label>NIF:</label>
                    <input type="text" name="dni" class="w-medium" value="<?= htmlspecialchars($matricula['dni'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Seguridad Social:</label>
                    <input type="text" name="ss" class="w-medium" value="<?= htmlspecialchars($matricula['ss'] ?? '00000000000') ?>">
                </div>
                <div class="form-row">
                    <label>Fecha de nacimiento:</label>
                    <input type="text" name="fecha_nacimiento" class="w-medium" placeholder="DD/MM/YYYY" value="<?= !empty($matricula['fecha_nacimiento']) ? date('d/m/Y', strtotime($matricula['fecha_nacimiento'])) : '' ?>">
                </div>
                <div class="form-row">
                    <label>Nombre:</label>
                    <input type="text" name="nombre" class="w-medium" value="<?= htmlspecialchars($matricula['alumno_nombre'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Apellido 1:</label>
                    <input type="text" name="primer_apellido" class="w-medium" value="<?= htmlspecialchars($matricula['primer_apellido'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Apellido 2:</label>
                    <input type="text" name="segundo_apellido" class="w-medium" value="<?= htmlspecialchars($matricula['segundo_apellido'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label style="color:#999; font-weight:normal;">Apellidos:</label>
                    <input type="text" readonly class="w-large" style="color:#ccc; border:1px solid #eee;" value="<?= htmlspecialchars(($matricula['primer_apellido'] ?? '') . ' ' . ($matricula['segundo_apellido'] ?? '')) ?>">
                </div>
                <div class="form-row" style="margin-top: 15px;">
                    <label>Profesion:</label>
                    <input type="text" name="profesion" class="w-large" value="<?= htmlspecialchars($matricula['profesion'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Estudios:</label>
                    <select name="estudios" class="w-large">
                        <option value=""></option>
                        <option value="Secundaria" <?= ($matricula['estudios'] ?? '') == 'Secundaria' ? 'selected' : '' ?>>Secundaria</option>
                        <option value="Bachillerato" <?= ($matricula['estudios'] ?? '') == 'Bachillerato' ? 'selected' : '' ?>>Bachillerato</option>
                        <option value="Grado" <?= ($matricula['estudios'] ?? '') == 'Grado' ? 'selected' : '' ?>>Grado</option>
                    </select>
                    <label style="width: auto; margin-left: 10px;">Otra titulación:</label>
                    <input type="text" name="otra_titulacion" class="w-medium">
                </div>
                <div class="form-row">
                    <label>Discapacitado:</label>
                    <select name="discapacitado" class="w-small">
                        <option value=""></option>
                        <option value="Sí" <?= ($matricula['discapacitado'] ?? '') == 'Sí' ? 'selected' : '' ?>>Sí</option>
                        <option value="No" <?= ($matricula['discapacitado'] ?? '') == 'No' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Tipo de discapacidad:</label>
                    <select name="tipo_discapacidad" class="w-medium"><option value=""></option></select>
                </div>
                <div class="form-row">
                    <label>Sexo:</label>
                    <select name="sexo" class="w-small">
                        <option value=""></option>
                        <option value="Hombre" <?= ($matricula['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                        <option value="Mujer" <?= ($matricula['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                    </select>
                    <label style="width: auto; margin-left: 10px;">Última revisión:</label>
                    <input type="text" class="w-medium">
                    <label style="width: auto; margin-left: 10px;">Revisiones anteriores:</label>
                    <select class="w-small"><option value=""></option></select>
                </div>
                
                <div style="border-top: 1px dotted #ccc; margin: 15px 0;"></div>

                <div class="form-row">
                    <label>Nombre de Vía:</label>
                    <input type="text" name="nombre_via" class="w-large" value="<?= htmlspecialchars($matricula['nombre_via'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Tipo de Vía:</label>
                    <select name="tipo_via" class="w-medium">
                        <option value=""></option>
                        <option value="Calle" <?= ($matricula['tipo_via'] ?? '') == 'Calle' ? 'selected' : '' ?>>Calle</option>
                        <option value="Avenida" <?= ($matricula['tipo_via'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                        <option value="Camino" <?= ($matricula['tipo_via'] ?? '') == 'Camino' ? 'selected' : '' ?>>Camino</option>
                    </select>
                    <label style="width: auto; margin-left: 10px;">Nº domicilio:</label>
                    <input type="text" name="num_domicilio" class="w-small" value="<?= htmlspecialchars($matricula['num_domicilio'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Calificador Nº:</label>
                    <select class="w-small"><option value=""></option></select>
                </div>
                <div class="form-row">
                    <label>Bloque:</label>
                    <input type="text" class="w-small">
                    <label style="width: auto; margin-left: 10px;">Portal:</label>
                    <input type="text" class="w-small">
                    <label style="width: auto; margin-left: 10px;">Escalera:</label>
                    <input type="text" name="escalera" class="w-small" value="<?= htmlspecialchars($matricula['escalera'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Planta:</label>
                    <input type="text" name="planta" class="w-small" value="<?= htmlspecialchars($matricula['planta'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Puerta:</label>
                    <input type="text" name="puerta" class="w-small" value="<?= htmlspecialchars($matricula['puerta'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Tipo de numeración:</label>
                    <select class="w-small"><option value=""></option></select>
                </div>
                <div class="form-row">
                    <label>Complemento:</label>
                    <input type="text" class="w-large">
                </div>
                <div class="form-row">
                    <label>Domicilio:</label>
                    <input type="text" class="w-xl" value="<?= htmlspecialchars(trim(($matricula['tipo_via'] ?? '') . ' ' . ($matricula['nombre_via'] ?? '') . ' ' . ($matricula['num_domicilio'] ?? ''))) ?>">
                </div>
                <div class="form-row">
                    <label>CP:</label>
                    <input type="text" name="codigo_postal" class="w-small" value="<?= htmlspecialchars($matricula['codigo_postal'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Localidad:</label>
                    <input type="text" name="localidad" class="w-medium" value="<?= htmlspecialchars($matricula['localidad'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Provincia:</label>
                    <select name="provincia" class="w-medium">
                        <option value=""></option>
                        <option value="VALLADOLID" <?= ($matricula['provincia'] ?? '') == 'VALLADOLID' ? 'selected' : '' ?>>VALLADOLID</option>
                        <option value="MADRID" <?= ($matricula['provincia'] ?? '') == 'MADRID' ? 'selected' : '' ?>>MADRID</option>
                    </select>
                </div>

                <div style="border-top: 1px dotted #ccc; margin: 15px 0;"></div>

                <div class="form-row">
                    <label>Telefono:</label>
                    <input type="text" name="telefono" class="w-medium" value="<?= htmlspecialchars($matricula['telefono'] ?? '') ?>">
                    <label style="width: auto; margin-left: 10px;">Movil:</label>
                    <input type="text" name="movil" class="w-medium" value="<?= htmlspecialchars($matricula['movil'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>E-mail:</label>
                    <input type="text" name="email" class="w-xl" value="<?= htmlspecialchars($matricula['email'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>E-mail 2:</label>
                    <input type="text" class="w-xl">
                </div>
                
                <div style="border-top: 1px dotted #ccc; margin: 15px 0;"></div>

                <div class="form-row">
                    <label>Mañanas Desde:</label>
                    <input type="text" class="w-medium">
                    <label style="width: auto; margin-left: 10px;">Mañanas Hasta:</label>
                    <input type="text" class="w-medium">
                </div>
                <div class="form-row">
                    <label>Tardes Desde:</label>
                    <input type="text" class="w-medium">
                    <label style="width: auto; margin-left: 10px;">Tardes Hasta:</label>
                    <input type="text" class="w-medium">
                </div>
                <div class="form-row">
                    <label>Sólo los:</label>
                    <input type="text" class="w-xl">
                </div>

                <div class="section-title">DOMICILIO DIFERENTE PARA ENTREGA DE MATERIAL</div>
                <div class="form-row">
                    <label>A la Atencion De:</label>
                    <input type="text" class="w-xl">
                </div>
                <div class="form-row">
                    <label>Domicilio:</label>
                    <input type="text" class="w-xl">
                </div>
                <div class="form-row">
                    <label>CP:</label>
                    <input type="text" class="w-small">
                </div>
                <div class="form-row">
                    <label>Localidad:</label>
                    <input type="text" class="w-large">
                </div>
                <div class="form-row">
                    <label>Provincia:</label>
                    <select class="w-medium"><option value=""></option></select>
                </div>
            </div>
        </div>
    </form>
</div>

</body>
</html>
