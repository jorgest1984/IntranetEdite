<?php
// pdf_recibi_material.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    header("Location: dashboard.php");
    exit();
}

$accion_id = isset($_GET['accion_id']) ? intval($_GET['accion_id']) : 0;
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if (!$accion_id) {
    die("Se requiere el ID de la acción formativa.");
}

// Fetch all students for this accion_id (or just one)
$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin,
        af.num_accion, af.modalidad, af.abreviatura as curso_codigo, af.titulo as curso_titulo,
        COALESCE(NULLIF(g.expediente, ''), conv.codigo_expediente) as codigo_expediente
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
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}

if (empty($alumnos)) {
    die("No se encontraron alumnos para generar el documento.");
}

class PDF extends FPDF {
    // Para imprimir texto con negritas y normal (básico)
    function WriteText($text, $lineHeight=6) {
        $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        $this->Write($lineHeight, $text);
    }
}

$pdf = new PDF();
$pdf->SetAutoPageBreak(true, 20);

foreach ($alumnos as $data) {
    $pdf->AddPage();
    
    // Logos
    // (X, Y, Width)
    if (file_exists('img/logo_efp.png')) {
        $pdf->Image('img/logo_efp.png', 15, 10, 60);
    }
    if (file_exists('img/logo_fundae.png')) {
        $pdf->Image('img/logo_fundae.png', 120, 10, 35);
    }
    if (file_exists('img/logo_ministerio.png')) {
        $pdf->Image('img/logo_ministerio.png', 160, 10, 35);
    }
    
    $pdf->Ln(25);
    
    // Title
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, mb_convert_encoding("RECIBÍ MATERIAL", 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Ln(5);
    
    $cursoCodigoTitulo = strtoupper($data['curso_codigo'] ?? '') . ' - ' . mb_strtoupper($data['curso_titulo'] ?? '', 'UTF-8');
    $expediente = strtoupper($data['codigo_expediente'] ?? '');
    $accion = $data['num_accion'] ?? '';
    $grupo = $data['numero_grupo'] ?? '';
    $modalidad = $data['modalidad'] ?? 'Teleformación';

    $fechaInicioObj = $data['fecha_inicio'] ? new DateTime($data['fecha_inicio']) : null;
    $fechaFinObj = $data['fecha_fin'] ? new DateTime($data['fecha_fin']) : null;
    $fechaInicio = $fechaInicioObj ? $fechaInicioObj->format('d/m/Y') : '';
    $fechaFin = $fechaFinObj ? $fechaFinObj->format('d/m/Y') : '';
    
    $nombreAlumno = trim($data['nombre'] . ' ' . $data['primer_apellido'] . ' ' . $data['segundo_apellido']);
    $dniAlumno = $data['dni'] ?? '';
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->WriteText("Denominación Acción Formativa: ");
    $pdf->SetFont('Arial', '', 10);
    $pdf->WriteText($cursoCodigoTitulo . "\n\n");
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 6, mb_convert_encoding("Nº Expediente:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 6, mb_convert_encoding($expediente, 'ISO-8859-1', 'UTF-8'), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(25, 6, mb_convert_encoding("Nº AAFF:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 6, mb_convert_encoding($accion, 'ISO-8859-1', 'UTF-8'), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(15, 6, mb_convert_encoding("Grupo:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(25, 6, mb_convert_encoding($grupo, 'ISO-8859-1', 'UTF-8'), 0, 1);
    
    $pdf->Ln(2);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 6, mb_convert_encoding("Modalidad:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 6, mb_convert_encoding($modalidad, 'ISO-8859-1', 'UTF-8'), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(25, 6, mb_convert_encoding("Fecha inicio:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 6, mb_convert_encoding($fechaInicio, 'ISO-8859-1', 'UTF-8'), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(20, 6, mb_convert_encoding("Fecha fin:", 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(20, 6, mb_convert_encoding($fechaFin, 'ISO-8859-1', 'UTF-8'), 0, 1);
    
    $pdf->Ln(6);
    
    $pdf->WriteText("D. ");
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->WriteText($nombreAlumno);
    $pdf->SetFont('Arial', '', 10);
    $pdf->WriteText(" con DNI nº ");
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->WriteText($dniAlumno);
    $pdf->SetFont('Arial', '', 10);
    $pdf->WriteText(" como participante de la acción formativa arriba indicada,\n\n");
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->WriteText("CERTIFICO:\n\n");
    $pdf->SetFont('Arial', '', 10);
    
    $pdf->WriteText("Que he RECIBIDO el siguiente material didáctico correspondiente a la acción formativa indicada:\n");
    
    $pdf->Ln(2);
    $pdf->SetX(20);
    $pdf->WriteText("• Hoja de bienvenida al curso en formato electrónico, con la dirección Web de la plataforma de teleformación y claves para el acceso al curso.\n");
    $pdf->SetX(20);
    $pdf->WriteText("• La siguiente documentación en formato electrónico disponible en la plataforma de teleformación:\n");
    
    $pdf->SetX(30);
    $pdf->WriteText("- Guía didáctica del alumno\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Guía de usuario del aula virtual\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Hoja informativa del SEPE sobre la situación de la demanda de empleo (sólo desempleados)\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Calendario\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Contenido del curso\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Evaluación de contenidos\n");
    $pdf->SetX(30);
    $pdf->WriteText("- Cuestionario de evaluación de calidad\n");
    
    $pdf->Ln(4);
    
    $p1 = "Que he recibido la información correspondiente a la Financiación de la Acción Formativa y soy consciente de que la acción formativa en la que participo corresponde a la convocatoria para la concesión de subvenciones públicas para la ejecución de programas de formación en el ámbito estatal, dirigidos prioritariamente a las personas ocupadas, regulada por resolución del director general del Servicio Público de Empleo Estatal de 6 de agosto de 2024, no tiene coste ni para los trabajadores ni para las empresas donde trabajan.";
    $pdf->MultiCell(0, 5, mb_convert_encoding($p1, 'ISO-8859-1', 'UTF-8'));
    
    $pdf->Ln(4);
    $p2 = "Que he sido informado/a de que según el art. 6.5 de la citada convocatoria, no podré realizar más de 180 horas de formación, con este o cualquier otro centro de formación, en el marco de esta convocatoria salvo que participe en una acción formativa cuya duración sea superior. En este caso realizaré una única acción. De igual forma, podrá superarse dicho límite cuando se participe en diferentes módulos formativos correspondientes a un mismo certificado de profesionalidad.";
    $pdf->MultiCell(0, 5, mb_convert_encoding($p2, 'ISO-8859-1', 'UTF-8'));
    
    $pdf->Ln(4);
    $p3 = "Que estoy interesado/a en esta acción formativa comprometiéndome a realizarla y a cumplimentar, firmar personalmente todos los documentos que se me soliciten y a remitir/entregar los originales a la entidad beneficiaria, necesario para cumplir los requisitos de la subvención y justificar el derecho a realizar esta formación.";
    $pdf->MultiCell(0, 5, mb_convert_encoding($p3, 'ISO-8859-1', 'UTF-8'));
    
    $pdf->Ln(6);
    $fechaActual = date('d/m/Y');
    $pdf->WriteText("En Granada, a " . $fechaActual . "\n");
    
    $pdf->Ln(25);
    $pdf->SetX(140);
    $pdf->WriteText("Fdo.:\n");
    $pdf->SetX(140);
    $pdf->WriteText("DNI nº: " . $dniAlumno . "\n");
    
    $pdf->Ln(15);
    $datetime = date('d/m/Y H:i:s');
    $pdf->SetFont('Arial', '', 8);
    $p4 = "Documento aceptado y leído por con DNI el día $datetime mediante aceptación expresa del contenido del presente documento. El firmante se ha autenticado en la plataforma de teleformación con su usuario y contraseña personal.";
    $pdf->MultiCell(0, 4, mb_convert_encoding($p4, 'ISO-8859-1', 'UTF-8'));
}

$filename = $alumno_id > 0 ? "Recibi_Material_" . $alumnos[0]['dni'] . ".pdf" : "Recibos_Material.pdf";
$pdf->Output('D', $filename);
