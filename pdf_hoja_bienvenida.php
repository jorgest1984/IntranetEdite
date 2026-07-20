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

// Fetch students
$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email,
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
    $alumnos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("SQL ERROR: " . $e->getMessage());
}

if (empty($alumnos)) {
    die("No se encontraron alumnos matriculados.");
}

// Helper to convert UTF-8 to ISO-8859-1 for FPDF
function pdf_utf8_to_iso($string) {
    return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
}

class PDF_Bienvenida extends FPDF {
    // Page header
    function Header() {
        // Logos
        if (file_exists('img/logo_ministerio.png')) {
            $this->Image('img/logo_ministerio.png', 10, 10, 50);
        }
        if (file_exists('img/logo_fundae.png')) {
            $this->Image('img/logo_fundae.png', 70, 10, 40);
        }
        
        $this->SetY(35);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(30, 58, 138); // Dark Blue
        $this->Cell(0, 10, pdf_utf8_to_iso('HOJA DE BIENVENIDA Y CLAVES DE ACCESO'), 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, pdf_utf8_to_iso('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Custom Text Function
    function WriteText($text, $font = 'Arial', $style = '', $size = 11, $color = [0,0,0]) {
        $this->SetFont($font, $style, $size);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->MultiCell(0, 7, pdf_utf8_to_iso($text), 0, 'L');
        $this->Ln(2);
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
    $codigo_expediente = trim($alumno['codigo_expediente'] ?? '');
    
    $fecha_inicio = !empty($alumno['fecha_inicio']) ? date('d/m/Y', strtotime($alumno['fecha_inicio'])) : '---';
    $fecha_fin = !empty($alumno['fecha_fin']) ? date('d/m/Y', strtotime($alumno['fecha_fin'])) : '---';
    
    // Generate Password the same way as api_sync_moodle
    $clean_dni = str_replace(['-', '.', ' '], '', $dni);
    $password = 'Edite' . $clean_dni . '!';
    
    $pdf->Ln(10);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, pdf_utf8_to_iso('Estimado/a alumno/a:'), 0, 1, 'L');
    
    $pdf->WriteText("Bienvenido/a al curso de formación en el que se ha matriculado. A continuación, le detallamos los datos de la acción formativa y las instrucciones para acceder a nuestra plataforma de teleformación.");
    $pdf->Ln(5);
    
    // Box for Course Data
    $pdf->SetFillColor(241, 245, 249); // slate-100
    $pdf->SetDrawColor(203, 213, 225); // slate-300
    $pdf->SetFont('Arial', 'B', 11);
    
    $pdf->Cell(0, 10, pdf_utf8_to_iso(' DATOS DEL ALUMNO Y CURSO'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Alumno/a:'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso($nombre_completo), 'R', 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' NIF/NIE:'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso($dni), 'R', 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Acción Formativa:'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->MultiCell(0, 8, pdf_utf8_to_iso($curso), 'R', 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Expediente:'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso($codigo_expediente), 'R', 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Fechas de Impartición:'), 'L,B', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso("Del $fecha_inicio al $fecha_fin"), 'R,B', 1, 'L');
    
    $pdf->Ln(10);
    
    // Box for Access Keys
    $pdf->SetFillColor(241, 245, 249);
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, pdf_utf8_to_iso(' CLAVES DE ACCESO A LA PLATAFORMA DE TELEFORMACIÓN'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Dirección Web (URL):'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'BU', 10);
    $pdf->SetTextColor(37, 99, 235); // Blue link
    $pdf->Cell(0, 8, pdf_utf8_to_iso('https://gestion.grupoefp.es/moodle/'), 'R', 1, 'L');
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Usuario:'), 'L', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso($dni), 'R', 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(45, 8, pdf_utf8_to_iso(' Contraseña:'), 'L,B', 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, pdf_utf8_to_iso($password), 'R,B', 1, 'L');
    
    $pdf->Ln(10);
    
    $pdf->WriteText("Por favor, guarde este documento en un lugar seguro. Podrá acceder a la plataforma desde cualquier dispositivo con conexión a internet utilizando estos datos.");
    
    $pdf->WriteText("Le recordamos que su participación activa y la superación de las pruebas de evaluación son requisitos indispensables para la obtención del diploma acreditativo a la finalización de la acción formativa.");
    
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, pdf_utf8_to_iso('¡Le deseamos mucho éxito en su aprendizaje!'), 0, 1, 'C');
}

$pdf->Output('I', "Hoja_Bienvenida_" . ($alumno_id > 0 ? $alumno_id : "Grupo_$accion_id") . ".pdf");
