<?php
// pdf_hoja_bienvenida.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

global $moodle_bypass_auth;
if (empty($moodle_bypass_auth) && !has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
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
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin, g.fecha_25, 
        g.horario_desde, g.horario_hasta, g.horario_info, g.horas_tutorias_programadas,
        af.num_accion, af.titulo as curso_titulo, af.objetivos, af.contenidos,
        COALESCE(NULLIF(g.expediente, ''), conv.codigo_expediente) as codigo_expediente,
        conv.texto_resolucion
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
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
    $alumnos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("SQL ERROR: " . $e->getMessage());
}

if (empty($alumnos)) {
    die("No se encontraron alumnos matriculados.");
}

function pdf_utf8_to_iso($string) {
    return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
}

class PDF_Bienvenida extends FPDF {
    function Header() {
        if (file_exists('img/logo_ministerio.png')) {
            $this->Image('img/logo_ministerio.png', 10, 10, 50);
        }
        if (file_exists('img/logo_fundae.png')) {
            $this->Image('img/logo_fundae.png', 110, 10, 30);
        }
        if (file_exists('img/logo_sepe.png')) {
            $this->Image('img/logo_sepe.png', 150, 10, 50);
        }
        
        $this->SetY(30);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, pdf_utf8_to_iso('www.escueladeformacionprofesional.es'), 0, 1, 'R');
        $this->Ln(10);
    }
    
    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, pdf_utf8_to_iso($title), 0, 1, 'L');
    }

    function WriteText($text, $font = 'Arial', $style = '', $size = 9, $color = [0,0,0]) {
        $this->SetFont($font, $style, $size);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->MultiCell(0, 5, pdf_utf8_to_iso($text), 0, 'J');
        $this->Ln(4);
    }
}

$pdf = new PDF_Bienvenida();
$pdf->AliasNbPages();
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 20);

foreach ($alumnos as $alumno) {
    $pdf->AddPage();
    
    $nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']);
    $dni = trim($alumno['dni']);
    $curso = trim($alumno['curso_titulo']);
    $num_accion = trim($alumno['num_accion'] ?? '');
    $grupo = trim($alumno['numero_grupo'] ?? '');
    $codigo_expediente = trim($alumno['codigo_expediente'] ?? '');
    $texto_resolucion = trim($alumno['texto_resolucion'] ?? '');
    $horas_tutorias = trim($alumno['horas_tutorias_programadas'] ?? '20');
    
    $horario_str = (!empty($alumno['horario_desde']) && !empty($alumno['horario_hasta'])) ? 
                    ($alumno['horario_desde'] . ' a ' . $alumno['horario_hasta'] . ' h') : 
                    ($alumno['horario_info'] ?: '9 a 11 h');
                    
    $fecha_inicio = !empty($alumno['fecha_inicio']) ? date('d/m/Y', strtotime($alumno['fecha_inicio'])) : '---';
    $fecha_fin = !empty($alumno['fecha_fin']) ? date('d/m/Y', strtotime($alumno['fecha_fin'])) : '---';
    
    $fecha_25 = !empty($alumno['fecha_25']) ? date('d \d\e F', strtotime($alumno['fecha_25'])) : '___ de ___';
    // Spanish month names
    $meses = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
    $fecha_25 = strtr($fecha_25, $meses);
    
    $clean_dni = str_replace(['-', '.', ' '], '', $dni);
    $password = 'Edite' . $clean_dni . '!';
    
    $pdf->SectionTitle('BIENVENIDA AL CURSO');
    $pdf->WriteText("Estimado/a " . $nombre_completo . ":");
    
    $pdf->WriteText("En primer lugar, queremos darle la bienvenida al curso $num_accion - $curso.\nAcción $num_accion, Grupo $grupo.");
    
    $texto_res_final = $texto_resolucion ?: "perteneciente a la aprobación de subvenciones públicas para la ejecución de programas de formación de ámbito estatal, dirigido prioritariamente a personas trabajadoras ocupadas, al amparo de la convocatoria correspondiente.";
    
    $pdf->WriteText("Esta Acción Formativa se encuentra incluida en el plan de Formación Estatal, dirigido prioritariamente a trabajadores ocupados con nº de expediente $codigo_expediente, $texto_res_final");
    
    $pdf->SectionTitle('FINANCIACIÓN DE LA ACCIÓN FORMATIVA');
    $pdf->WriteText("La acción formativa en la que está usted participando corresponde a la convocatoria para planes de formación de ámbito estatal, citada anteriormente. Los recursos para financiar el subsistema de formación para el empleo proceden de la cuota de formación profesional que recauda la Seguridad Social a la que se suman las aportaciones del Servicio Público de Empleo Estatal (SEPE). Por lo que, al ser un curso subvencionado, no tiene coste ni para los trabajadores ni para las empresas donde trabajan.");
    
    $pdf->SectionTitle('FECHAS');
    $pdf->WriteText("Las fechas previstas para la realización del curso son las siguientes:");
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(10, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Inicio: $fecha_inicio"), 0, 1);
    $pdf->Cell(10, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Finalización: $fecha_fin"), 0, 1);
    $pdf->Ln(4);
    
    $pdf->SectionTitle('ACCESO AL CURSO');
    $pdf->WriteText("A continuación le indicamos los datos para acceder al aula virtual. La dirección es la siguiente:");
    $pdf->SetFont('Arial', 'U', 9);
    $pdf->SetTextColor(0, 0, 255);
    $pdf->Cell(0, 5, pdf_utf8_to_iso("https://aulavirtual.grupoefp.es/login/index.php"), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, pdf_utf8_to_iso("y debe acceder a \"Aula Virtual\". Recuerde que no podrá acceder al aula hasta la fecha de inicio indicada. Sus datos de usuario y contraseña son los siguientes:"), 0, 'J');
    $pdf->Ln(2);
    $pdf->Cell(10, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Usuario: $dni"), 0, 1);
    $pdf->Cell(10, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Contraseña: $password"), 0, 1);
    $pdf->Ln(4);
    
    $pdf->SectionTitle('OBJETIVOS DEL CURSO');
    $pdf->WriteText(strip_tags($alumno['objetivos'] ?? 'Sin definir'));
    
    $pdf->SectionTitle('CONTENIDOS');
    $pdf->WriteText(strip_tags($alumno['contenidos'] ?? 'Sin definir'));
    
    $pdf->SectionTitle('MATERIAL DIDÁCTICO');
    $pdf->WriteText("Todo lo que usted necesita para realizar el curso se encuentra en la plataforma de teleformación, donde una vez finalizado el estudio de los contenidos, debe realizar tanto los ejercicios propuestos como las evaluaciones, así como el cuestionario de evaluación de la calidad debidamente cumplimentado.");
    $pdf->WriteText("En los recursos del aula virtual encontrará, aparte de este documento, la Guía Didáctica del alumno, la guía de usuario del aula virtual, que contiene información sobre el funcionamiento de la plataforma y los procesos a seguir. Le recomendamos que lea estos documentos con atención para poder aprovechar al máximo su formación.");
    
    $pdf->SectionTitle('TUTORÍAS');
    $pdf->WriteText("Esta Acción Formativa dispone de un total de $horas_tutorias horas tutorizadas, durante las cuales tendrá a su disposición un tutor personal que le guiará y apoyará en el estudio.");
    $pdf->WriteText("El horario para tutorías es de $horario_str. En dicho horario podrá contactar con su tutor para la resolución de cuantas dudas le surjan.");
    $pdf->WriteText("Puede contactar con él a través del teléfono: 958 089 725, o bien por correo electrónico a la dirección:"); // TODO add email? screenshot doesn't show email
    
    $pdf->SectionTitle('METODOLOGÍA Y EVALUACIÓN');
    $pdf->WriteText("Durante este tiempo de formación, deberá realizar el estudio personalizado de los contenidos del curso a través de la plataforma, así como los ejercicios de autoevaluación para reforzar sus conocimientos sobre la temática, en el tiempo establecido que le indicamos, teniendo la posibilidad de contactar con su tutor/a para consultar cualquier duda.");
    $pdf->WriteText("Una vez que acceda al entorno de formación con sus claves de acceso, encontrará todos los recursos didácticos para la realización de la acción formativa.");
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso("Conexión al curso"), 0, 1, 'L');
    $pdf->WriteText("Para considerar a un alumno iniciado en la acción formativa, debe haberse conectado y tener actividad antes de alcanzar el primer 25%, $fecha_25. Teniendo en cuenta los siguientes aspectos:");
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(5, 5, '', 0, 0); $pdf->MultiCell(0, 5, pdf_utf8_to_iso("- El participante deberá visualizar todos los contenidos del curso y consultar al tutor/formador las dudas, en su caso, a través de la propia plataforma."), 0, 'J');
    $pdf->Cell(5, 5, '', 0, 0); $pdf->MultiCell(0, 5, pdf_utf8_to_iso("- Aquellos alumnos que hayan realizado el 75 por ciento de los controles periódicos de seguimiento de su aprendizaje, se podrán considerar alumnos finalizados."), 0, 'J');
    $pdf->Ln(4);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso("Herramientas de evaluación"), 0, 1, 'L');
    $pdf->WriteText("Se establecen las siguientes evaluaciones, que le permitirán comprobar su grado de aprovechamiento realizado en el curso, siendo requisito imprescindible para la finalización del mismo:");
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(5, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Evaluación Inicial."), 0, 1);
    $pdf->Cell(5, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Evaluación Intermedia. Fecha máxima aconsejable de realización:"), 0, 1);
    $pdf->Cell(5, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Evaluación Final. Fecha máxima aconsejable de realización:"), 0, 1);
    $pdf->Cell(5, 5, '', 0, 0); $pdf->Cell(0, 5, pdf_utf8_to_iso("- Evaluación de Calidad."), 0, 1);
    $pdf->Ln(4);
    
    $pdf->SectionTitle('CERTIFICACIÓN');
    $pdf->WriteText("A la finalización y/o superación del curso se entregará al alumno un DIPLOMA o CERTIFICADO de asistencia de los conocimientos adquiridos, en base a las instrucciones del Servicio Público de Empleo Estatal.");
    $pdf->WriteText("Se facilitará por e-mail un enlace web para su descarga en PDF.");
    
    $pdf->SectionTitle('SOPORTE TÉCNICO');
    $pdf->WriteText("Le recordamos que para cualquier duda, problema de carácter técnico o consulta sobre el desarrollo de los cursos, también puede contactar con nosotros en el teléfono 958 089 725.");
    $pdf->WriteText("Esperamos que este curso sea de su agrado.");
    $pdf->Ln(8);
    $pdf->WriteText("Un saludo,\nEl equipo de Edite Formación");
}

$pdf->Output('I', "Hoja_Bienvenida_" . ($alumno_id > 0 ? $alumno_id : "Grupo_$accion_id") . ".pdf");
