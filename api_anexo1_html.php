<?php
// api_anexo1_html.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    die("Acceso denegado");
}

$accion_id = isset($_GET['accion_id']) ? intval($_GET['accion_id']) : 0;
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if (!$accion_id) {
    die("Se requiere el ID de la acción formativa.");
}

$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.telefono, a.email,
        a.fecha_nacimiento, a.sexo, a.domicilio, a.cp, a.localidad, a.provincia, a.estudios,
        a.tipo_via, a.nombre_via, a.num_domicilio, a.planta, a.puerta,
        a.discapacidad, a.grupo_cotizacion, a.categoria_profesional, a.area_funcional,
        a.ocupacion_cno, a.situacion_laboral, a.situacion_laboral_codigo,
        emp.nombre as empresa_nombre, emp.domicilio as empresa_domicilio, emp.cp as empresa_cp, 
        emp.tamano_empresa as empresa_tamano, emp.sector_actividad as empresa_sector, 
        emp.convenio_aplicacion as empresa_convenio, emp.cif as empresa_cif,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin,
        af.num_accion, af.modalidad, af.abreviatura as curso_codigo, af.titulo as curso_titulo,
        conv.codigo_expediente
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN empresas emp ON a.ultima_empresa_id = emp.id
    JOIN grupos g ON m.grupo_id = g.id
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
    WHERE g.accion_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
";
$params = [$accion_id];

if ($alumno_id > 0) {
    $query .= " AND a.id = ?";
    $params[] = $alumno_id;
}

$query .= " ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("<h1>SQL ERROR: " . $e->getMessage() . "</h1>");
}

if (empty($alumnos)) {
    die("<p>No se encontraron alumnos matriculados para generar el Anexo I.</p>");
}
?>
<style>
    @page { margin: 0; size: A4 portrait; }
    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        font-size: 10px;
        background: #fff;
        color: #000;
    }
    .page, .page * {
        box-sizing: border-box !important;
    }
    .page {
        width: 100% !important;
        max-width: 800px !important;
        height: 1040px;
        position: relative;
        page-break-after: always;
        overflow: hidden;
        background: #fff;
        margin: 0 !important;
        padding: 40px !important;
    }
    .page:last-child {
        page-break-after: auto; /* Prevent trailing blank page */
    }
    .form-table {
        width: 100%;
        border-collapse: collapse;
    }
    .form-table td, .form-table th {
        border: 1px solid #000;
        padding: 4px;
        vertical-align: top;
        font-size: 8.5px;
        word-wrap: break-word;
        line-height: 1.1;
    }
    .checkbox-box {
        width: 10px;
        height: 10px;
        border: 1px solid #000;
        display: inline-block;
        text-align: center;
        line-height: 10px;
        font-size: 9px;
        margin-right: 4px;
    }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-justify { text-align: justify; }
    .bold { font-weight: bold; }
    
    .header-logos {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .header-logos img {
        max-height: 45px;
    }

    h1.main-title {
        text-align: center;
        font-size: 13px;
        font-weight: bold;
        margin: 0 0 10px 0;
        line-height: 1.2;
    }
    h2.sub-title {
        text-align: center;
        font-size: 12px;
        font-weight: bold;
        margin: 15px 0;
    }
    h3.section-title {
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        margin: 0 0 15px 0;
    }

    /* Form Fields */
    .field-row {
        margin-bottom: 5px;
        font-size: 10px;
        display: flex;
        align-items: flex-end;
    }
    .field-row > div {
        display: flex;
        align-items: flex-end;
        min-width: 0;
    }
    .field-inline {
        display: inline-block;
    }
    .dotted-line {
        display: inline-block;
        border-bottom: 1px solid #000;
        min-height: 12px;
        flex: 1;
        min-width: 0;
        margin-left: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fixed-line {
        display: inline-block;
        border-bottom: 1px solid #000;
        min-height: 12px;
        margin-left: 4px;
    }
    
    /* Boxed Sections */
    .box-section {
        border: 2px solid #000;
        padding: 5px;
        margin-bottom: 10px;
    }
    .box-header {
        font-weight: bold;
        margin-bottom: 5px;
        font-size: 10px;
    }

    /* Tables */
    table.border-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    table.border-table td, table.border-table th {
        border: 2px solid #000;
        padding: 5px;
        vertical-align: top;
        font-size: 10px;
    }
    
    /* Checkboxes */
    .chk-box {
        display: inline-block;
        width: 10px;
        height: 10px;
        border: 1px solid #000;
        text-align: center;
        line-height: 10px;
        font-size: 9px;
        margin-right: 4px;
        vertical-align: middle;
    }

    .list-item {
        margin-bottom: 2px;
    }
    
    .cno-box {
        display: inline-block;
        width: 14px;
        height: 18px;
        border: 1px solid #000;
        margin-right: 2px;
        vertical-align: middle;
    }

    /* Layout Helpers */
    .flex-row {
        display: flex;
        justify-content: space-between;
    }
    .flex-col {
        flex: 1;
    }

</style>
<?php foreach($alumnos as $alumno): 
    $cursoTitulo = mb_strtoupper($alumno['curso_titulo'] ?? '', 'UTF-8');
    $cursoCodigo = htmlspecialchars($alumno['num_accion'] ?? '');
    
    $nombre = mb_strtoupper($alumno['nombre'] ?? '', 'UTF-8');
    $apellidos = mb_strtoupper(($alumno['primer_apellido'] ?? '') . ' ' . ($alumno['segundo_apellido'] ?? ''), 'UTF-8');
    $apellido1 = mb_strtoupper($alumno['primer_apellido'] ?? '', 'UTF-8');
    $apellido2 = mb_strtoupper($alumno['segundo_apellido'] ?? '', 'UTF-8');
    $dni = trim($alumno['dni'] ?? '');
    
    $sexo = strtolower(trim($alumno['sexo'] ?? ''));
    $genero_text = ($sexo === 'mujer' || $sexo === 'f') ? 'MUJER' : (($sexo === 'hombre' || $sexo === 'm') ? 'HOMBRE' : '');
    
    $fechaNac = $alumno['fecha_nacimiento'] ?? '';
    $diaNac = '&nbsp;&nbsp;'; $mesNac = '&nbsp;&nbsp;'; $anioNac = '&nbsp;&nbsp;&nbsp;&nbsp;';
    if ($fechaNac && $fechaNac !== '0000-00-00') {
        try {
            // Reemplazar barras por guiones para que strtotime no confunda mes/dia
            $fechaLimpiada = str_replace('/', '-', $fechaNac);
            $ts = strtotime($fechaLimpiada);
            if ($ts) {
                $diaNac = date('d', $ts);
                $mesNac = date('m', $ts);
                $anioNac = date('Y', $ts);
            }
        } catch (Exception $e) {
            // Ignore date format errors
        }
    }
    
    $domicilio = mb_strtoupper(trim($alumno['domicilio'] ?? ''), 'UTF-8');
    $nombreVia = mb_strtoupper(trim($alumno['tipo_via'] . ' ' . $alumno['nombre_via']), 'UTF-8');
    $numDomicilio = trim($alumno['num_domicilio'] ?? '');
    if (empty(trim($nombreVia))) {
        if (preg_match('/^(.*?),\s*(\d+.*)$/', $domicilio, $matches)) {
            $nombreVia = $matches[1];
            $numDomicilio = $matches[2];
        } else {
            $nombreVia = $domicilio;
        }
    }
    $direccionCompleta = trim($nombreVia . ' ' . $numDomicilio);
    
    $cp = trim($alumno['cp'] ?? '');
    $localidad = mb_strtoupper(trim($alumno['localidad'] ?? ''), 'UTF-8');
    $telefono = trim($alumno['telefono'] ?? '');
    $email = trim($alumno['email'] ?? '');
    
    $expediente = trim($alumno['codigo_expediente'] ?? '');
    
    // Discapacidad
    $discapacidad = (int)($alumno['discapacidad'] ?? 0);
    $chk_disc_si = $discapacidad === 1 ? 'X' : '&nbsp;';
    $chk_disc_no = $discapacidad === 0 ? 'X' : '&nbsp;';

    // Mapeo de Estudios (exacto al nuevo formato)
    $estudios = trim($alumno['estudios'] ?? '');
    $e_0 = $e_1 = $e_22 = $e_23 = $e_24 = $e_32 = $e_33 = $e_34 = $e_38 = $e_41 = $e_51 = $e_61 = $e_62 = $e_71 = $e_72 = $e_73 = $e_74 = $e_81 = '&nbsp;';
    if (strpos($estudios, '0 -') === 0) $e_0 = 'X';
    elseif (strpos($estudios, '1 -') === 0) $e_1 = 'X';
    elseif (strpos($estudios, '22 -') === 0) $e_22 = 'X';
    elseif (strpos($estudios, '23 -') === 0) $e_23 = 'X';
    elseif (strpos($estudios, '24 -') === 0) $e_24 = 'X';
    elseif (strpos($estudios, '25 -') === 0) $e_25 = 'X'; // Wait, there's no e_25 in the old code, I should add it
    elseif (strpos($estudios, '32 -') === 0) $e_32 = 'X';
    elseif (strpos($estudios, '33 -') === 0) $e_33 = 'X';
    elseif (strpos($estudios, '51 -') === 0) $e_51 = 'X';
    elseif (strpos($estudios, '61 -') === 0) $e_61 = 'X';
    elseif (strpos($estudios, '71 -') === 0) $e_71 = 'X';
    elseif (strpos($estudios, '81 -') === 0) $e_81 = 'X';

    // Grupo cotizacion
    $grupo_cot = trim($alumno['grupo_cotizacion'] ?? '');
    $g_01 = $g_02 = $g_03 = $g_04 = $g_05 = $g_06 = $g_07 = $g_08 = $g_09 = $g_10 = $g_11 = '&nbsp;';
    if ($grupo_cot === '01') $g_01 = 'X';
    elseif ($grupo_cot === '02') $g_02 = 'X';
    elseif ($grupo_cot === '03') $g_03 = 'X';
    elseif ($grupo_cot === '04') $g_04 = 'X';
    elseif ($grupo_cot === '05') $g_05 = 'X';
    elseif ($grupo_cot === '06') $g_06 = 'X';
    elseif ($grupo_cot === '07') $g_07 = 'X';
    elseif ($grupo_cot === '08') $g_08 = 'X';
    elseif ($grupo_cot === '09') $g_09 = 'X';
    elseif ($grupo_cot === '10') $g_10 = 'X';
    elseif ($grupo_cot === '11') $g_11 = 'X';

    // Categoría profesional
    $cat_prof = trim($alumno['categoria_profesional'] ?? '');
    $cp_dir = $cp_mando = $cp_tec = $cp_cual = $cp_baja = '&nbsp;';
    if ($cat_prof === 'Directivo') $cp_dir = 'X';
    elseif ($cat_prof === 'Mando Intermedio') $cp_mando = 'X';
    elseif ($cat_prof === 'Técnico') $cp_tec = 'X';
    elseif ($cat_prof === 'Trabajador cualificado') $cp_cual = 'X';
    elseif ($cat_prof === 'Trabajador de baja cualificación') $cp_baja = 'X';

    // Área funcional
    $area_func = trim($alumno['area_funcional'] ?? '');
    $af_dir = $af_adm = $af_com = $af_man = $af_pro = '&nbsp;';
    if ($area_func === 'Dirección') $af_dir = 'X';
    elseif ($area_func === 'Administración') $af_adm = 'X';
    elseif ($area_func === 'Comercial') $af_com = 'X';
    elseif ($area_func === 'Mantenimiento') $af_man = 'X';
    elseif ($area_func === 'Producción') $af_pro = 'X';

    // Situacion Laboral
    $sit_lab = trim($alumno['situacion_laboral'] ?? '');
    $sl_ocu = $sl_dsp = $sl_dspld = $sl_cpn = '&nbsp;';
    if ($sit_lab === 'Ocupado') $sl_ocu = 'X';
    elseif ($sit_lab === 'Desempleado DSP') $sl_dsp = 'X';
    elseif ($sit_lab === 'Desempleado DSPLD') $sl_dspld = 'X';
    elseif ($sit_lab === 'Cuidador CPN') $sl_cpn = 'X';

    $cno = trim($alumno['ocupacion_cno'] ?? '');
    $cno_chars = str_split(str_pad($cno, 4, ' ', STR_PAD_RIGHT));

    $sit_cod = trim($alumno['situacion_laboral_codigo'] ?? '');

    // Empresa
    $emp_tam = trim($alumno['empresa_tamano'] ?? '');
    $et_10 = $et_49 = $et_99 = $et_249 = $et_250 = '&nbsp;';
    if ($emp_tam === 'Inferior a 10') $et_10 = 'X';
    elseif ($emp_tam === 'De 10 a 49') $et_49 = 'X';
    elseif ($emp_tam === 'De 50 a 99') $et_99 = 'X';
    elseif ($emp_tam === 'De 100 a 249') $et_249 = 'X';
    elseif ($emp_tam === '250 y más') $et_250 = 'X';
    
?>
<div class="student-wrapper">
    <!-- PÁGINA 1 -->
    <div class="page">
        <!-- CABECERA LOGOS -->
        <div class="header-logos">
            <img src="img/logo_fundae.png" alt="Fundae" style="max-height: 45px;">
            <img src="img/logo_ministerio.png" alt="Ministerio y SEPE" style="max-height: 45px;">
        </div>

        <h1 class="main-title">CONVOCATORIA PARA LA CONCESIÓN DE SUBVENCIONES PÚBLICAS PARA LA EJECUCIÓN DE PROGRAMAS DE FORMACIÓN DE ÁMBITO ESTATAL, DIRIGIDOS PRIORITARIAMENTE A LAS PERSONAS OCUPADAS</h1>
        
        <h2 class="sub-title">ANEXO I</h2>
        
        <h3 class="section-title">Solicitud de Participación</h3>

        <div class="field-row">
            <div style="flex: 0 0 auto;">N.º de Expediente</div>
            <span class="fixed-line" style="width: 150px;"><?= htmlspecialchars($expediente) ?></span>
            <div style="flex: 0 0 auto; margin-left: 15px;">Sector al que se dirige el programa de formación:</div>
            <span class="dotted-line"></span>
        </div>
        <div class="field-row">
            <div style="flex: 0 0 auto;">Entidad solicitante del Programa de formación:</div>
            <span class="dotted-line"></span>
        </div>
        <div class="field-row">
            <div style="flex: 0 0 auto;">Acción Formativa (denominación y número):</div>
            <span class="dotted-line"><?= $cursoTitulo ?> (<?= $cursoCodigo ?>)</span>
        </div>

        <!-- DATOS DEL PARTICIPANTE -->
        <div class="box-section" style="margin-top: 15px;">
            <div class="box-header">DATOS DEL PARTICIPANTE:</div>
            
            <div class="field-row">
                <div style="flex: 0 0 auto;">1er. Apellido:</div> <span class="dotted-line"><?= htmlspecialchars($apellido1) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">2º. Apellido:</div> <span class="dotted-line"><?= htmlspecialchars($apellido2) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">Nombre:</div> <span class="dotted-line"><?= htmlspecialchars($nombre) ?></span>
            </div>
            
            <div class="field-row" style="margin-top: 8px;">
                <div style="flex: 0 0 auto;">Dirección</div> <span class="dotted-line"><?= htmlspecialchars($direccionCompleta) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">Localidad</div> <span class="fixed-line" style="width: 150px;"><?= htmlspecialchars($localidad) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">C.P.</div> <span class="fixed-line" style="width: 60px;"><?= htmlspecialchars($cp) ?></span>
            </div>
            
            <div class="field-row" style="margin-top: 8px;">
                <div style="flex: 0 0 auto;">Tfno.:</div> <span class="fixed-line" style="width: 100px;"><?= htmlspecialchars($telefono) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">Email:</div> <span class="dotted-line"><?= htmlspecialchars($email) ?></span>
                <div style="flex: 0 0 auto; margin-left: 10px;">N.I.F.:</div> <span class="fixed-line" style="width: 100px;"><?= htmlspecialchars($dni) ?></span>
            </div>
            
            <div class="field-row" style="margin-top: 8px;">
                <div style="flex: 0 0 auto;">Nº. de afiliación a la Seguridad Social:</div> <span class="fixed-line" style="width: 60px;"></span> / <span class="dotted-line"></span>
            </div>
            
            <div class="field-row" style="margin-top: 8px;">
                <div style="flex: 0 0 auto;">Fecha de nacimiento:</div> 
                <span class="fixed-line" style="width: 25px; text-align: center;"><?= $diaNac ?></span> / 
                <span class="fixed-line" style="width: 25px; text-align: center;"><?= $mesNac ?></span> / 
                <span class="fixed-line" style="width: 40px; text-align: center;"><?= $anioNac ?></span>
                
                <div style="flex: 0 0 auto; margin-left: 15px;">Género:</div> 
                <span class="fixed-line" style="width: 80px;"><?= htmlspecialchars($genero_text) ?></span>
                
                <div style="flex: 1;"></div>
                
                <div style="flex: 0 0 auto;">
                    Discapacidad: 
                    <span class="chk-box" style="margin-left: 5px;"><?= $chk_disc_si ?></span> SI &nbsp;&nbsp;&nbsp; 
                    <span class="chk-box"><?= $chk_disc_no ?></span> NO
                </div>
            </div>
        </div>

        <!-- TABLA ESTUDIOS Y COTIZACION -->
        <table class="border-table">
            <tr>
                <td style="width: 50%;">
                    <div class="box-header">ESTUDIOS <span style="font-weight: normal; color: #222;">(Indicar nivel máximo alcanzado)</span></div>
                    
                    <div class="list-item"><span class="chk-box"><?= $e_0 ?></span> 0 - Sin titulación.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_1 ?></span> 1 - Educación Primaria.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_22 ?></span> 22 - Título de Graduado E.S.O./ E.G.B.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_23 ?></span> 23 - Certificados de Profesionalidad Nivel 1.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_24 ?? '&nbsp;' ?></span> 24 - Certificados de Profesionalidad Nivel 2.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_25 ?? '&nbsp;' ?></span> 25 - Certificados de Profesionalidad Nivel 3.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_32 ?></span> 32 - Bachillerato.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_33 ?></span> 33 - Enseñanzas de Formación Profesional de Grado Medio.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_34 ?></span> 34 - Enseñanzas Profesionales de Música-danza.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_38 ?></span> 38 - Formación Profesional Básica.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_41 ?></span> 41 - Certificados de Profesionalidad Nivel 3.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_51 ?></span> 51 - Enseñanzas de Formación Profesional de Grado Superior.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_61 ?></span> 61 - Grados Universitarios de hasta 240 créditos.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_62 ?></span> 62 - Diplomados Universitarios.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_71 ?></span> 71 - Grados Universitarios de más 240 créditos.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_72 ?></span> 72 - Licenciados o equivalentes.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_73 ?></span> 73 - Másteres oficiales Universitarios.</div>
                    <div class="list-item"><span class="chk-box"><?= $e_74 ?></span> 74 - Especialidades en CC. Salud (residentes).</div>
                    <div class="list-item"><span class="chk-box"><?= $e_81 ?></span> 81 - Doctorado Universitario.</div>
                    
                    <div class="box-header" style="margin-top: 15px;">OTRA TITULACIÓN</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> PR - Carnet profesional /Profesiones Reguladas.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> A1 - Nivel de idioma A1 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> A2 - Nivel de idioma A2 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> B1 - Nivel de idioma B1 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> B2 - Nivel de idioma B2 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> C1 - Nivel de idioma C1 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> C2 - Nivel de idioma C2 del MCER.</div>
                    <div class="list-item"><span class="chk-box">&nbsp;</span> ZZ - Otra: (Especificar) <span class="dotted-line" style="width: 150px;"></span></div>
                </td>
                <td style="width: 50%;">
                    <div class="box-header">GRUPO DE COTIZACIÓN</div>
                    <div class="list-item"><span class="chk-box"><?= $g_01 ?></span> 01 - Ingenieros y Licenciados. Personal de alta dirección no incluido en el artículo 1.3.c) del Estatuto de los Trabajadores.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_02 ?></span> 02 - Ingenieros Técnicos, Peritos y Ayudantes titulados.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_03 ?></span> 03 - Jefes administrativos y de Taller.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_04 ?></span> 04 - Ayudantes no Titulados.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_05 ?></span> 05 - Oficiales Administrativos.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_06 ?></span> 06 - Subalternos.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_07 ?></span> 07 - Auxiliares Administrativos.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_08 ?></span> 08 - Oficiales de primera y segunda.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_09 ?></span> 09 - Oficiales de tercera y Especialistas.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_10 ?></span> 10 - Peones.</div>
                    <div class="list-item"><span class="chk-box"><?= $g_11 ?></span> 11 - Trabajadores menores de dieciocho años cualquiera que sea su categoría profesional.</div>
                </td>
            </tr>
        </table>
        
        <!-- Pie de página 1: Firmante/CSV simulado o espacio -->
        <div style="position: absolute; bottom: 10px; left: 30px; font-size: 8px; border-top: 1px solid #000; padding-top: 5px; width: calc(100% - 60px);">
            <!-- Espacio reservado para CSV de firma digital (como en la captura) -->
        </div>
    </div> <!-- FIN PAGINA 1 -->

    <!-- PÁGINA 2 -->
    <div class="page">
        <!-- CABECERA LOGOS -->
        <div class="header-logos">
            <img src="img/logo_fundae.png" alt="Fundae" style="max-height: 45px;">
            <img src="img/logo_ministerio.png" alt="Ministerio y SEPE" style="max-height: 45px;">
        </div>

        <table class="border-table">
            <tr>
                <td style="width: 50%;">
                    <div class="box-header">CATEGORÍA PROFESIONAL</div>
                    <div class="list-item"><span class="chk-box"><?= $cp_dir ?></span> Directivo</div>
                    <div class="list-item"><span class="chk-box"><?= $cp_mando ?></span> Mando Intermedio</div>
                    <div class="list-item"><span class="chk-box"><?= $cp_tec ?></span> Técnico</div>
                    <div class="list-item"><span class="chk-box"><?= $cp_cual ?></span> Trabajador cualificado</div>
                    <div class="list-item"><span class="chk-box"><?= $cp_baja ?></span> Trabajador de baja cualificación (*)</div>
                    
                    <div style="font-size: 8px; margin-top: 15px; line-height: 1.2;">
                        (*) Grupos de cotización 06, 07, 09 o 10 de la última ocupación. En el caso de tratarse personas desempleadas aquellas que no estén en posesión de un carnet profesional, certificado de profesionalidad de nivel 2 o 3, título de formación profesional o de una titulación universitaria.
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="box-header">ÁREA FUNCIONAL (solo ocupados)</div>
                    <div class="list-item"><span class="chk-box"><?= $af_dir ?></span> Dirección</div>
                    <div class="list-item"><span class="chk-box"><?= $af_adm ?></span> Administración</div>
                    <div class="list-item"><span class="chk-box"><?= $af_com ?></span> Comercial</div>
                    <div class="list-item"><span class="chk-box"><?= $af_man ?></span> Mantenimiento</div>
                    <div class="list-item"><span class="chk-box"><?= $af_pro ?></span> Producción</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="box-header" style="display:inline-block; margin-right: 10px;">OCUPACIÓN (Clasificación Nacional de Ocupaciones 2011 (CNO-11):</div>
                    <span class="cno-box"><?= $cno_chars[0] ?? '&nbsp;' ?></span><span class="cno-box"><?= $cno_chars[1] ?? '&nbsp;' ?></span><span class="cno-box"><?= $cno_chars[2] ?? '&nbsp;' ?></span><span class="cno-box"><?= $cno_chars[3] ?? '&nbsp;' ?></span>
                    <span style="font-size: 9px; margin-left: 5px;">(Si está desempleado, indicar la última ocupación)</span>
                    <div style="font-size: 8px; margin-top: 5px;">(Si fuera necesario, requerir la ayuda de la entidad solicitante del Programa de Formación para cumplimentar este epígrafe)</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="box-header">PARTICIPANTE:</div>
                    <div class="list-item"><span class="chk-box"><?= $sl_ocu ?></span> Ocupado. Consignar Código (1): <span class="dotted-line" style="width: 150px;"><?= htmlspecialchars($sit_cod) ?></span></div>
                    <div class="list-item"><span class="chk-box"><?= $sl_dsp ?></span> Desempleado (DSP)</div>
                    <div class="list-item"><span class="chk-box"><?= $sl_dspld ?></span> Desempleado de larga duración (*)(DSPLD)</div>
                    <div class="list-item"><span class="chk-box"><?= $sl_cpn ?></span> Cuidador no profesional (CPN)</div>
                    <div style="font-size: 8px; margin-top: 5px;">(*) Personas inscritas como demandantes en la oficina de empleo al menos 12 meses en los 18 meses anteriores a la selección.</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="box-header" style="display:inline-block; margin-bottom: 10px;">ENTIDAD DONDE TRABAJA ACTUALMENTE:</div> <span class="dotted-line" style="width: 450px;"><?= htmlspecialchars($alumno['empresa_nombre'] ?? '') ?></span>
                    
                    <div class="field-row" style="margin-top: 10px; margin-bottom: 10px;">
                        TAMAÑO DE EMPRESA: 
                        &nbsp;&nbsp;<span class="chk-box"><?= $et_10 ?></span> Inferior a 10
                        &nbsp;&nbsp;<span class="chk-box"><?= $et_49 ?></span> De 10 a 49
                        &nbsp;&nbsp;<span class="chk-box"><?= $et_99 ?></span> De 50 a 99
                        &nbsp;&nbsp;<span class="chk-box"><?= $et_249 ?></span> De 100 a 249
                        &nbsp;&nbsp;<span class="chk-box"><?= $et_250 ?></span> 250 y más
                    </div>
                    
                    <div class="field-row">
                        SECTOR DE ACTIVIDAD: <span class="dotted-line" style="width: 580px;"><?= htmlspecialchars($alumno['empresa_sector'] ?? '') ?></span>
                    </div>
                    <div class="field-row">
                        CONVENIO DE APLICACIÓN: <span class="dotted-line" style="width: 560px;"><?= htmlspecialchars($alumno['empresa_convenio'] ?? '') ?></span>
                    </div>
                    
                    <div class="field-row" style="margin-top: 15px;">
                        Razón Social: <span class="dotted-line" style="width: 630px;"><?= htmlspecialchars($alumno['empresa_nombre'] ?? '') ?></span>
                    </div>
                    <div class="field-row">
                        C. I. F. <span class="dotted-line" style="width: 180px;"><?= htmlspecialchars($alumno['empresa_cif'] ?? '') ?></span>
                        Domicilio del Centro de Trabajo: <span class="dotted-line" style="width: 380px;"><?= htmlspecialchars($alumno['empresa_domicilio'] ?? '') ?></span>
                    </div>
                    <div class="field-row">
                        Localidad <span class="dotted-line" style="width: 350px;"></span>
                        C.P. <span class="dotted-line" style="width: 100px;"><?= htmlspecialchars($alumno['empresa_cp'] ?? '') ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <div style="font-size: 9px; line-height: 1.3; text-align: justify; margin-top: 15px;">
            <b>(1) Relación de Códigos:</b> <b>RG</b> Régimen general, <b>FD</b> Fijos discontinuos en periodos de no ocupación <b>RE</b> Regulación de empleo en períodos de no ocupación, <b>ERTE</b> Personas trabajadoras afectadas por expedientes de regulación temporal de empleo, <b>RERED</b> Trabajadores en ERTE afectados por Mecanismo RED, <b>AGP</b> Régimen especial agrario por cuenta propia, <b>AGA</b> Régimen especial agrario por cuenta ajena, <b>AU</b> Régimen especial autónomos, <b>AP</b> Administración Pública, <b>EH</b> Empleado hogar, <b>DF</b> Trabajadores que accedan al desempleo durante el periodo formativo, <b>RLE</b> trabajadores con relaciones laborales de carácter especial que se recogen en el art. 2 del Estatuto de los Trabajadores, <b>CESS</b> Trabajadores con convenio especial con la Seguridad Social, <b>FDI</b> Trabajadores a tiempo parcial de carácter indefinido(con trabajos discontinuos) en sus periodos de no ocupación, <b>TM</b> Régimen especial del mar, <b>CP</b> Mutualistas de Colegios Profesionales no incluidos como autónomos, <b>OCTP</b> Trabajadores ocupados con contrato a tiempo parcial, <b>OCT</b> Trabajadores ocupados con contrato temporal.
        </div>

        <div style="font-size: 10px; line-height: 1.3; text-align: justify; margin-top: 30px;">
            El abajo firmante declara que los datos declarados se corresponden con la realidad, y en la presente convocatoria, no participa en otra acción formativa de igual contenido a la solicitada.
        </div>
        
    </div> <!-- FIN PAGINA 2 -->

    <!-- PÁGINA 3 -->
    <div class="page">
        <!-- CABECERA LOGOS -->
        <div class="header-logos">
            <img src="img/logo_fundae.png" alt="Fundae" style="max-height: 45px;">
            <img src="img/logo_ministerio.png" alt="Ministerio y SEPE" style="max-height: 45px;">
        </div>

        <h3 style="font-size: 11px; font-weight: bold; margin-bottom: 10px;">Información básica sobre protección de datos:</h3>
        
        <div style="font-size: 10px; line-height: 1.4; text-align: justify;">
            Responsable: SERVICIO PÚBLICO DE EMPLEO ESTATAL.; Finalidad: gestionar la solicitud, evaluación, gestión y concesión, en su caso, control y seguimiento de la beca o ayuda solicitada en las iniciativas de formación profesional para el empleo, directamente por el Servicio Público de Empleo Estatal o a través de la Fundación para la Formación en el Empleo. Así mismo, autorizo al Servicio Público de Empleo Estatal para que compruebe mis datos mediante el Sistema de Verificación de Datos de Identidad, según establece el Real Decreto 522/2006, de 28 de abril; Legitimación: cumplimiento de una obligación legal; ejercicio de poderes públicos; Destinatarios: están previstas cesiones de datos a: Administración pública con competencia en la materia; Derechos: tiene derecho a acceder, rectificar y suprimir los datos, así como otros derechos, indicados en la información adicional, que puede ejercer dirigiéndose al correo electrónico <a href="mailto:datos@fundae.es" style="color: #000;">datos@fundae.es</a> ; Información adicional: Consultando el Aviso legal/Política de Privacidad- Protección de datos de carácter personal de la página <a href="http://www.sepe.es/HomeSepe/mas-informacion/aviso-legal.html" style="color: #000;">www.sepe.es/HomeSepe/mas-informacion/aviso-legal.html</a>
        </div>

        <div style="margin-top: 60px; text-align: center;">
            Fecha: en <span class="dotted-line" style="width: 150px;"></span>, a <span class="dotted-line" style="width: 30px;"></span> de <span class="dotted-line" style="width: 120px;"></span> 202<span class="dotted-line" style="width: 15px;"></span>
        </div>

        <div style="margin-top: 80px; text-align: center;">
            Firma del/la trabajador /a
        </div>
        
    </div> <!-- FIN PAGINA 3 -->
    
</div> <!-- Fin student-wrapper -->
<?php endforeach; ?>

