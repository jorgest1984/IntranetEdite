<?php
// pdf_contactos_fundae.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    http_response_code(403);
    die("Acceso denegado.");
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
if (!$grupo_id) {
    die("Se requiere grupo_id.");
}

// Información del grupo y acción formativa
$stmtGrupo = $pdo->prepare("
    SELECT g.*, af.num_accion, c.codigo_expediente, af.titulo as curso_nombre, af.modalidad
    FROM grupos g
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
    WHERE g.id = ?
    LIMIT 1
");
$stmtGrupo->execute([$grupo_id]);
$grupoData = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

if (!$grupoData) {
    die("Grupo no encontrado.");
}

// Alumnos
$stmtAlumnos = $pdo->prepare("
    SELECT m.*, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.telefono, a.email
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    WHERE m.estado IN ('Inscrito', 'Activo', 'Finalizada') AND m.grupo_id = ?
    ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre
");
$stmtAlumnos->execute([$grupo_id]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

if (empty($alumnos)) {
    die("No hay alumnos matriculados en este grupo.");
}

function pdf_utf8_to_iso($str) {
    if (empty($str)) return '';
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

class FundaeContactosPDF extends FPDF {
    function Header() {
        try {
            if (file_exists('img/logo_ministerio.png')) {
                $this->Image('img/logo_ministerio.png', 10, 8, 0, 13, 'PNG');
            }
            if (file_exists('img/logo_fundae.png')) {
                // Posicionar a la derecha (Página apaisada A4 = 297mm ancho, resto margen = 10)
                $this->Image('img/logo_fundae.png', 215, 8, 0, 12, 'PNG');
            }
        } catch (Exception $e) {}
        
        $this->SetY(30);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(31, 78, 121);
        $this->Cell(0, 10, pdf_utf8_to_iso("LISTADO DE CONTACTOS DE ALUMNOS (ALTA FUNDAE)"), 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, pdf_utf8_to_iso("Página ") . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// 'L' para apaisado
$pdf = new FundaeContactosPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Cabecera de información del grupo
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(35, 7, pdf_utf8_to_iso("Nº Expediente:"), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(80, 7, pdf_utf8_to_iso($grupoData['expediente'] ?? '---'), 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 7, pdf_utf8_to_iso("Acción:"), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 7, pdf_utf8_to_iso($grupoData['num_accion'] ?? '---'), 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, pdf_utf8_to_iso("Curso:"), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 7, pdf_utf8_to_iso($grupoData['curso_nombre']), 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, pdf_utf8_to_iso("Grupo:"), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(80, 7, pdf_utf8_to_iso($grupoData['numero_grupo']), 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(25, 7, pdf_utf8_to_iso("Modalidad:"), 0, 0, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 7, pdf_utf8_to_iso($grupoData['modalidad'] ?? '---'), 0, 1, 'L');

$pdf->Ln(5);

// Tabla de contactos
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(31, 78, 121); // Azul oscuro
$pdf->SetTextColor(255, 255, 255);

// Anchos de columnas (total = ~277)
$w = array(35, 45, 85, 30, 82); 
$pdf->Cell($w[0], 8, pdf_utf8_to_iso("DNI/NIE"), 1, 0, 'C', true);
$pdf->Cell($w[1], 8, pdf_utf8_to_iso("Nombre"), 1, 0, 'C', true);
$pdf->Cell($w[2], 8, pdf_utf8_to_iso("Apellidos"), 1, 0, 'C', true);
$pdf->Cell($w[3], 8, pdf_utf8_to_iso("Teléfono"), 1, 0, 'C', true);
$pdf->Cell($w[4], 8, pdf_utf8_to_iso("Correo Electrónico"), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);

$fill = false;
$pdf->SetFillColor(240, 245, 250);

foreach ($alumnos as $alumno) {
    $pdf->Cell($w[0], 7, pdf_utf8_to_iso($alumno['dni']), 'LRB', 0, 'C', $fill);
    $pdf->Cell($w[1], 7, pdf_utf8_to_iso($alumno['nombre']), 'LRB', 0, 'L', $fill);
    $apellidos = trim($alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']);
    $pdf->Cell($w[2], 7, pdf_utf8_to_iso($apellidos), 'LRB', 0, 'L', $fill);
    $pdf->Cell($w[3], 7, pdf_utf8_to_iso($alumno['telefono']), 'LRB', 0, 'C', $fill);
    
    // El email puede ser largo, usamos truncado o fuente más pequeña si es muy grande
    $email = $alumno['email'];
    if (strlen($email) > 40) {
        $pdf->SetFont('Arial', '', 7);
    }
    $pdf->Cell($w[4], 7, pdf_utf8_to_iso($email), 'LRB', 1, 'L', $fill);
    $pdf->SetFont('Arial', '', 9); // reset
    
    $fill = !$fill;
}

$pdf->Output('I', 'Contactos_FUNDAE_Grupo_' . $grupo_id . '.pdf');
