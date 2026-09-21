<?php
// pdf_hoja_bienvenida.php - Native Vector Clean PDF Generator
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

global $moodle_bypass_auth;
if (empty($moodle_bypass_auth) && !has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL, ROLE_PRACTICAS])) {
    header("Location: dashboard.php");
    exit();
}

$accion_id = isset($_GET['accion_id']) ? intval($_GET['accion_id']) : 0;
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if (!$accion_id) {
    die("Se requiere el ID de la acción formativa.");
}

$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email,
        a.plat_usuario, a.plat_clave,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin, g.fecha_25, 
        g.horario_desde, g.horario_hasta, g.horario_info, g.horas_tutorias_programadas,
        af.num_accion, af.titulo as curso_titulo, af.objetivos, af.contenidos, af.modalidad,
        COALESCE(NULLIF(g.expediente, ''), conv.codigo_expediente) as codigo_expediente,
        conv.texto_resolucion,
        u_tutor.email as tutor_email,
        c_sede.nombre as sede_nombre, c_sede.direccion as sede_direccion, c_sede.provincia as sede_provincia, c_sede.cp as sede_cp
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    JOIN grupos g ON m.grupo_id = g.id
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
    LEFT JOIN usuarios u_tutor ON g.tutor_id = u_tutor.id
    LEFT JOIN centros c_sede ON g.sede_id = c_sede.id
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
    $alumnos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("SQL ERROR: " . $e->getMessage());
}

if (empty($alumnos) && $alumno_id > 0) {
    // FALLBACK Preview
    $fallback_query = "
        SELECT 
            a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email,
            a.plat_usuario, a.plat_clave,
            g.numero_grupo, g.fecha_inicio, g.fecha_fin, g.fecha_25, 
            g.horario_desde, g.horario_hasta, g.horario_info, g.horas_tutorias_programadas,
            af.num_accion, af.titulo as curso_titulo, af.objetivos, af.contenidos, af.modalidad,
            COALESCE(NULLIF(g.expediente, ''), conv.codigo_expediente) as codigo_expediente,
            conv.texto_resolucion,
            u_tutor.email as tutor_email,
            c_sede.nombre as sede_nombre, c_sede.direccion as sede_direccion, c_sede.provincia as sede_provincia, c_sede.cp as sede_cp
        FROM acciones_formativas af
        LEFT JOIN grupos g ON g.accion_id = af.id
        LEFT JOIN planes p ON af.plan_id = p.id
        LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
        LEFT JOIN usuarios u_tutor ON g.tutor_id = u_tutor.id
        LEFT JOIN centros c_sede ON g.sede_id = c_sede.id
        CROSS JOIN alumnos a
        WHERE af.id = ? AND a.id = ?
        ORDER BY g.id ASC LIMIT 1
    ";
    $stmtFallback = $pdo->prepare($fallback_query);
    $stmtFallback->execute([$accion_id, $alumno_id]);
    $alumnos = $stmtFallback->fetchAll();
}

if (empty($alumnos)) {
    die("No se encontraron alumnos matriculados ni se pudo generar la vista previa.");
}

function pdf_iso($string) {
    return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
}

class PDF_Hoja_Bienvenida extends FPDF {
    function Header() {
        if (file_exists('img/plantilla_fondo_efp.png')) {
            $this->Image('img/plantilla_fondo_efp.png', 0, 0, 210, 297);
        } else {
            if (file_exists('img/logo_efp.png')) {
                $this->Image('img/logo_efp.png', 15, 10, 45);
            }
            if (file_exists('img/logo_fundae.png')) {
                $this->Image('img/logo_fundae.png', 115, 12, 30);
            }
            if (file_exists('img/logo_ministerio.png')) {
                $this->Image('img/logo_ministerio.png', 150, 10, 45);
            }
        }
        
        $this->SetXY(15, 27);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(180, 4, 'www.escueladeformacionprofesional.es', 0, 1, 'R');
        $this->Ln(3);
    }

    function Footer() {
        // Page number
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, pdf_iso('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SectionHeading($title) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, pdf_iso($title), 0, 1, 'L');
        $this->Ln(1);
    }
}

$pdf = new PDF_Hoja_Bienvenida();
$pdf->AliasNbPages();
$pdf->SetMargins(18, 15, 18);
$pdf->SetAutoPageBreak(true, 18);

foreach ($alumnos as $alumno) {
    $nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . ($alumno['segundo_apellido'] ?? ''));
    $curso = trim($alumno['curso_titulo']);
    $num_accion = trim($alumno['num_accion'] ?? '');
    $numero_grupo = trim($alumno['numero_grupo'] ?? '1');
    $codigo_expediente = trim($alumno['codigo_expediente'] ?? '');
    
    $cleanDni = !empty($alumno['dni']) ? strtolower(trim(str_replace([' ', '-', '.'], '', $alumno['dni']))) : '';
    $to_email = trim($alumno['email'] ?? '');
    $username = trim($alumno['plat_usuario'] ?? '');
    $password = trim($alumno['plat_clave'] ?? '');

    if (empty($username)) {
        $username = !empty($cleanDni) ? $cleanDni : strtolower(explode('@', $to_email)[0]);
    }
    if (empty($password)) {
        $password = !empty($cleanDni) ? ('Edite' . str_replace(['-', '.', ' '], '', $alumno['dni']) . '!') : 'Efp2026!';
    }
    
    $fecha_inicio = !empty($alumno['fecha_inicio']) ? date('d/m/Y', strtotime($alumno['fecha_inicio'])) : '---';
    $fecha_fin = !empty($alumno['fecha_fin']) ? date('d/m/Y', strtotime($alumno['fecha_fin'])) : '---';
    
    $fecha_25 = !empty($alumno['fecha_25']) ? date('d \d\e F', strtotime($alumno['fecha_25'])) : '___ de ___';
    $meses = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
    $fecha_25 = strtr($fecha_25, $meses);
    
    $horario_str = (!empty($alumno['horario_desde']) && !empty($alumno['horario_hasta'])) ? 
                    ($alumno['horario_desde'] . ' a ' . $alumno['horario_hasta'] . ' h') : 
                    ($alumno['horario_info'] ?: '9 a 11h');
                    
    $horas_tutorias = trim($alumno['horas_tutorias_programadas'] ?? '20');
    $tutor_email = trim($alumno['tutor_email'] ?? 'tutor@grupoefp.es');
    
    $objetivos_raw = html_entity_decode(strip_tags(str_replace('&nbsp;', ' ', $alumno['objetivos'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $contenidos_raw = html_entity_decode(strip_tags(str_replace('&nbsp;', ' ', $alumno['contenidos'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // =========================================================================
    // PAGE 1
    // =========================================================================
    $pdf->AddPage();
    $pdf->SetY(38);

    // Document Main Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, pdf_iso("BIENVENIDA AL CURSO"), 0, 1, 'L');
    $pdf->Ln(3);

    // Salutation
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(5, pdf_iso("Estimado/a "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Write(5, pdf_iso($nombre_completo));
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Write(5, pdf_iso(" :\n\n"));

    // Intro paragraph
    $pdf->Write(5, pdf_iso("En primer lugar, queremos darle la bienvenida al curso "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(5, pdf_iso((!empty($num_accion) ? ($num_accion . ' - ') : '') . $curso));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(5, pdf_iso(",\nAcción "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(5, pdf_iso($num_accion ?: '---'));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(5, pdf_iso(" , Grupo "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(5, pdf_iso($numero_grupo));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(5, pdf_iso(" .\n\n"));

    // Legal / Expediente text
    $pdf->Write(5, pdf_iso("Esta Acción Formativa se encuentra incluida en el plan de Formación Estatal, dirigido prioritariamente a trabajadores ocupados con nº de expediente "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Write(5, pdf_iso($codigo_expediente ?: 'F241037AA'));
    $pdf->SetFont('Arial', '', 10);
    $pdf->Write(5, pdf_iso(", perteneciente a la aprobación de subvenciones públicas para la ejecución de programas de formación de ámbito estatal, dirigido prioritariamente a personas trabajadoras ocupadas, al amparo de la convocatoria aprobada mediante Resolución del Servicio Público de Empleo Estatal de 6 de agosto de 2024, solicitada por Marsdigital S.L.\n\n"));

    // Financiación
    $pdf->SectionHeading("FINANCIACIÓN DE LA ACCIÓN FORMATIVA");
    $pdf->SetFont('Arial', '', 9.5);
    $financiacion_txt = "La acción formativa en la que está usted participando corresponde a la convocatoria para planes de formación de ámbito estatal, citada anteriormente, regulada por la resolución de 6 de Agosto de 2024, del Servicio Público de Empleo Estatal. Los recursos para financiar el subsistema de formación para el empleo proceden de la cuota de formación profesional que recauda la Seguridad Social a la que se suman las aportaciones del Servicio Público de Empleo Estatal (SEPE). Por lo que, al ser un curso subvencionado, no tiene coste ni para los trabajadores ni para las empresas donde trabajan.";
    $pdf->MultiCell(0, 4.8, pdf_iso($financiacion_txt), 0, 'J');
    $pdf->Ln(4);

    // Fechas
    $pdf->SectionHeading("FECHAS");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(0, 5, pdf_iso("Las fechas previstas para la realización del curso son las siguientes:"), 0, 1, 'L');
    $pdf->Ln(1);

    $pdf->SetX(24);
    $pdf->Write(5, pdf_iso("• Inicio: "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(5, pdf_iso($fecha_inicio . "\n"));

    $pdf->SetX(24);
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(5, pdf_iso("• Finalización: "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(5, pdf_iso($fecha_fin . "\n\n"));

    // Acceso al curso
    $pdf->SectionHeading("ACCESO AL CURSO");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso("A continuación le indicamos los datos para acceder al aula virtual. La dirección es la siguiente: "));
    $pdf->SetFont('Arial', 'U', 9.5);
    $pdf->SetTextColor(0, 102, 204);
    $pdf->Write(4.8, "www.editeformacion.com");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Write(4.8, pdf_iso(" y debe acceder a \"Aula Virtual\". Recuerde que no podrá acceder al aula hasta la fecha de inicio indicada. Sus datos de usuario y contraseña son los siguientes:\n\n"));

    $pdf->SetX(24);
    $pdf->Write(5, pdf_iso("• Usuario: "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Write(5, pdf_iso($username . "\n"));

    $pdf->SetX(24);
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Write(5, pdf_iso("• Contraseña: "));
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Write(5, pdf_iso($password . "\n\n"));
    $pdf->SetTextColor(0, 0, 0);

    // Objetivos
    $pdf->SectionHeading("OBJETIVOS DEL CURSO");
    $pdf->SetFont('Arial', '', 9.5);
    if (!empty($objetivos_raw)) {
        $pdf->MultiCell(0, 4.8, pdf_iso($objetivos_raw), 0, 'J');
    } else {
        $pdf->Cell(0, 5, pdf_iso("Adquirir y desarrollar las competencias asociadas a la acción formativa."), 0, 1, 'L');
    }
    $pdf->Ln(4);

    // Contenidos Header
    $pdf->SectionHeading("CONTENIDOS DEL CURSO");
    $pdf->SetFont('Arial', '', 9.5);
    if (!empty($contenidos_raw)) {
        $pdf->MultiCell(0, 4.8, pdf_iso($contenidos_raw), 0, 'J');
    } else {
        $pdf->Cell(0, 5, pdf_iso("Módulos y unidades didácticas especificadas en la guía del alumno."), 0, 1, 'L');
    }

    // =========================================================================
    // PAGE 2
    // =========================================================================
    $pdf->AddPage();
    $pdf->SetY(38);

    // Material Didáctico
    $pdf->SectionHeading("MATERIAL DIDÁCTICO");
    $pdf->SetFont('Arial', '', 9.5);
    $mat_1 = "Todo lo que usted necesita para realizar el curso se encuentra en la plataforma de teleformación, donde una vez finalizado el estudio de los contenidos, debe realizar tanto los ejercicios propuestos como las evaluaciones, así como el cuestionario de evaluación de la calidad debidamente cumplimentado.";
    $pdf->MultiCell(0, 4.8, pdf_iso($mat_1), 0, 'J');
    $pdf->Ln(3);
    $mat_2 = "En los recursos del aula virtual encontrará, aparte de este documento, la Guía Didáctica del alumno, la guía de usuario del aula virtual, que contiene información sobre el funcionamiento de la plataforma y los procesos a seguir. Le recomendamos que lea estos documentos con atención para poder aprovechar al máximo su formación.";
    $pdf->MultiCell(0, 4.8, pdf_iso($mat_2), 0, 'J');
    $pdf->Ln(5);

    // Tutorías
    $pdf->SectionHeading("TUTORÍAS");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso("Esta Acción Formativa dispone de un total de "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(4.8, pdf_iso($horas_tutorias));
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso(" horas tutorizadas, durante las cuales tendrá a su disposición un tutor personal que le guiará y apoyará en el estudio.\n\n"));

    $pdf->Write(4.8, pdf_iso("El horario para tutorías es de "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(4.8, pdf_iso($horario_str));
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso(". En dicho horario podrá contactar con su tutor para la resolución de cuantas dudas le surjan.\n\n"));

    $pdf->Write(4.8, pdf_iso("Puede contactar con él a través del teléfono: "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(4.8, "958 089 725");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso(", o bien por correo electrónico a la dirección: "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Write(4.8, pdf_iso($tutor_email));
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Write(4.8, "\n\n");

    // Metodología y Evaluación
    $pdf->SectionHeading("METODOLOGÍA Y EVALUACIÓN");
    $pdf->SetFont('Arial', '', 9.5);
    $met_1 = "Durante este tiempo de formación, deberá realizar el estudio personalizado de los contenidos del curso a través de la plataforma, así como los ejercicios de autoevaluación para reforzar sus conocimientos sobre la temática, en el tiempo establecido que le indicamos, teniendo la posibilidad de contactar con su tutor/a para consultar cualquier duda.";
    $pdf->MultiCell(0, 4.8, pdf_iso($met_1), 0, 'J');
    $pdf->Ln(3);
    $met_2 = "Una vez que acceda al entorno de formación con sus claves de acceso, encontrará todos los recursos didácticos para la realización de la acción formativa.";
    $pdf->MultiCell(0, 4.8, pdf_iso($met_2), 0, 'J');
    $pdf->Ln(5);

    // Conexión al curso
    $pdf->SectionHeading("Conexión al curso");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso("Para considerar a un alumno iniciado en la acción formativa, debe haberse conectado y tener actividad antes de alcanzar el primer 25% , "));
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->SetTextColor(180, 0, 0);
    $pdf->Write(4.8, pdf_iso($fecha_25));
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(4.8, pdf_iso(". Teniendo en cuenta los siguientes aspectos:\n\n"));

    $pdf->SetX(24);
    $pdf->Write(4.8, pdf_iso("• El participante deberá visualizar todos los contenidos del curso y consultar al tutor/formador las dudas, en su caso, a través de la propia plataforma.\n"));
    $pdf->SetX(24);
    $pdf->Write(4.8, pdf_iso("• Aquellos alumnos que hayan realizado el 75 por ciento de los controles periódicos de seguimiento de su aprendizaje, se podrán considerar alumnos finalizados.\n"));

    // =========================================================================
    // PAGE 3
    // =========================================================================
    $pdf->AddPage();
    $pdf->SetY(38);

    // Herramientas de Evaluación
    $pdf->SectionHeading("Herramientas de evaluación");
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(0, 5, pdf_iso("Se establecen las siguientes evaluaciones, que le permitirán comprobar su grado de aprovechamiento realizado en el"), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_iso("curso, siendo requisito imprescindible para la finalización del mismo:"), 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetX(24);
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(5, pdf_iso("• Evaluación Inicial.\n"));
    $pdf->SetX(24);
    $pdf->Write(5, pdf_iso("• Evaluación Intermedia. "));
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(5, pdf_iso("Fecha máxima aconsejable de realización:\n"));
    $pdf->SetX(24);
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(5, pdf_iso("• Evaluación Final. "));
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Write(5, pdf_iso("Fecha máxima aconsejable de realización:\n"));
    $pdf->SetX(24);
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->Write(5, pdf_iso("• Evaluación de Calidad.\n\n"));

    // Certificación
    $pdf->SectionHeading("CERTIFICACIÓN");
    $pdf->SetFont('Arial', '', 9.5);
    $cert_txt = "A la finalización y/o superación del curso se entregará al alumno un DIPLOMA o CERTIFICADO de asistencia de los conocimientos adquiridos, en base a las instrucciones del Servicio Público de Empleo Estatal.\n\nSe facilitará por e-mail un enlace web para su descarga en PDF.";
    $pdf->MultiCell(0, 4.8, pdf_iso($cert_txt), 0, 'L');
    $pdf->Ln(5);

    // Soporte Técnico
    $pdf->SectionHeading("SOPORTE TÉCNICO");
    $pdf->SetFont('Arial', '', 9.5);
    $sop_txt = "Le recordamos que para cualquier duda, problema de carácter técnico o consulta sobre el desarrollo de los cursos, también puede contactar con nosotros en el teléfono 958 089 725.\n\nEsperamos que este curso sea de su agrado.";
    $pdf->MultiCell(0, 4.8, pdf_iso($sop_txt), 0, 'L');
    $pdf->Ln(10);

    // Despedida
    $pdf->Cell(0, 5, pdf_iso("Un saludo,"), 0, 1, 'L');
    $pdf->Cell(0, 5, pdf_iso("El equipo de Edite Formación"), 0, 1, 'L');
}

$pdf->Output('I', "Hoja_Bienvenida_" . ($alumno_id > 0 ? $alumno_id : "Grupo_$accion_id") . ".pdf");
