<?php
// pdf_encuesta.php
ini_set('display_errors', 0); // Hide errors to avoid breaking PDF output on warnings
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

$encuesta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$encuesta_id) die("ID de encuesta no especificado.");

try {
    $stmt = $pdo->prepare("
        SELECT er.*, m.id as matricula_id, af.id as accion_id, af.titulo as curso_nombre, af.abreviatura, af.duracion,
               af.modalidad, af.num_accion, g.numero_grupo, g.fecha_inicio, g.fecha_fin,
               co.codigo_expediente,
               a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.sexo as alumno_sexo
        FROM encuestas_resultados er
        JOIN matriculas m ON er.matricula_id = m.id
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN planes pl ON af.plan_id = pl.id
        LEFT JOIN convocatorias co ON pl.convocatoria_id = co.id
        WHERE er.id = ? LIMIT 1
    ");
    $stmt->execute([$encuesta_id]);
    $survey = $stmt->fetch();
} catch (Exception $e) {
    die("Error al consultar la encuesta: " . $e->getMessage());
}
if (!$survey) die("Cuestionario no encontrado.");

function pdf_utf8_to_iso($str) {
    if ($str === null || $str === '') return '';
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        if ($converted !== false) return $converted;
    }
    return @\utf8_decode($str);
}
function safe_date($format, $dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') return '';
    $ts = strtotime($dateStr);
    return $ts ? date($format, $ts) : '';
}

class FundaeSurveyPDF extends FPDF {
    function Header() {
        if (file_exists('img/cabecera_fundae.png')) {
            try {
                $imgInfo = @getimagesize('img/cabecera_fundae.png');
                $type = '';
                if ($imgInfo) {
                    if ($imgInfo[2] == IMAGETYPE_JPEG) $type = 'JPEG';
                    elseif ($imgInfo[2] == IMAGETYPE_PNG) $type = 'PNG';
                    elseif ($imgInfo[2] == IMAGETYPE_GIF) $type = 'GIF';
                }
                // Try to put it full width, but since it's only one image, we center it or let it take width
                $this->Image('img/cabecera_fundae.png', 10, 8, 190, 0, $type);
            } catch (Exception $e) {}
        }
        $this->SetY(25);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 10, pdf_utf8_to_iso("Modelo publicado en Resolución de 27 de abril de 2009, del Servicio Público de Empleo Estatal.          Página ") . $this->PageNo(), 0, 0, 'L');
    }

    function DrawBigCheckBox($x, $y, $checked) {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        $this->Rect($x, $y, 5, 5); // 5x5 mm square
        $this->SetLineWidth(0.2); // reset
        if ($checked) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(0, 0, 0);
            // Draw cross
            $this->Line($x, $y, $x+5, $y+5);
            $this->Line($x+5, $y, $x, $y+5);
        }
    }
    
    function BlueTitle($text, $x, $y) {
        $this->SetXY($x, $y);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(31, 78, 121); // Blue
        $this->Cell(100, 5, pdf_utf8_to_iso($text), 0, 1);
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new FundaeSurveyPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Blue Box Top
$pdf->SetDrawColor(31, 78, 121); // Blue
$pdf->SetLineWidth(0.4);
$pdf->Rect(10, 25, 190, 48);

$pdf->SetXY(10, 26);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(190, 5, pdf_utf8_to_iso("CUESTIONARIO PARA LA EVALUACIÓN DE LA CALIDAD DE LAS ACCIONES FORMATIVAS"), 0, 1, 'C');
$pdf->Cell(190, 5, pdf_utf8_to_iso("EN EL MARCO DEL SISTEMA DE FORMACIÓN PARA EL EMPLEO."), 0, 1, 'C');
$pdf->Cell(190, 5, pdf_utf8_to_iso("FORMACIÓN DE DEMANDA"), 0, 1, 'C');
$pdf->Cell(190, 5, pdf_utf8_to_iso("(Orden TAS 2307/2007, de 27 de julio)"), 0, 1, 'C');

$pdf->Line(10, 47, 200, 47);

$pdf->SetXY(10, 48);
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(190, 4, pdf_utf8_to_iso("Para evaluar la calidad de las acciones formativas es necesaria su opinión como alumno/a, acerca de los distintos"), 0, 1, 'C');
$pdf->Cell(190, 4, pdf_utf8_to_iso("aspectos del curso en el que ha participado."), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->Cell(190, 5, pdf_utf8_to_iso("LE ROGAMOS RESPONDA A TODAS Y CADA UNA DE LAS PREGUNTAS DE ESTE CUESTIONARIO."), 0, 1, 'C');
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(190, 5, pdf_utf8_to_iso("MUCHAS GRACIAS POR SU COLABORACIÓN"), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8.5);
$pdf->Cell(190, 4, pdf_utf8_to_iso("Los datos aportados en el presente cuestionario son confidenciales y serán utilizados, únicamente, para analizar la"), 0, 1, 'C');
$pdf->Cell(190, 4, pdf_utf8_to_iso("calidad de las acciones formativas."), 0, 1, 'C');

// SECCION I
$pdf->SetY(75);
$pdf->SetDrawColor(31, 78, 121);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetLineWidth(0.4);
$pdf->Rect(10, 75, 190, 24);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(190, 6, pdf_utf8_to_iso("I. DATOS IDENTIFICATIVOS DE LA ACCIÓN FORMATIVA (Preimpresos o a cumplimentar por la entidad beneficiaria)"), 'B', 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->SetLineWidth(0.2);
$pdf->SetDrawColor(0,0,0);

// Fila 1
$pdf->Cell(25, 4.5, pdf_utf8_to_iso("1. Nº expediente"), 0, 0, 'L');
$pdf->Cell(55, 4.5, pdf_utf8_to_iso($survey['codigo_expediente'] ?? ''), 1, 0, 'C');
$pdf->Cell(15, 4.5, pdf_utf8_to_iso("2. Perfil"), 0, 0, 'C');
$pdf->Cell(95, 4.5, "", 1, 1, 'C'); // Perfil vacio
// Fila 2
$pdf->Cell(25, 4.5, pdf_utf8_to_iso("3. CIF empresa"), 0, 0, 'L');
$pdf->Cell(55, 4.5, "", 1, 0, 'C'); // CIF vacio
$pdf->Cell(20, 4.5, pdf_utf8_to_iso("4. Nº Acción"), 0, 0, 'C');
$pdf->Cell(45, 4.5, pdf_utf8_to_iso($survey['num_accion'] ?? ''), 1, 0, 'C');
$pdf->Cell(20, 4.5, pdf_utf8_to_iso("5. Nº grupo"), 0, 0, 'C');
$pdf->Cell(25, 4.5, pdf_utf8_to_iso($survey['numero_grupo'] ?? ''), 1, 1, 'C');
// Fila 3
$pdf->Cell(35, 4.5, pdf_utf8_to_iso("6. Denominación acción"), 0, 0, 'L');
$pdf->Cell(155, 4.5, pdf_utf8_to_iso($survey['curso_nombre']), 1, 1, 'L');
// Fila 4
$pdf->Cell(35, 4.5, pdf_utf8_to_iso("7. Modalidad"), 0, 0, 'L');
$pdf->Cell(155, 4.5, pdf_utf8_to_iso($survey['modalidad'] ?? 'Teleformación'), 1, 1, 'L');

// SECCION II
$pdf->SetY(102);
$pdf->SetDrawColor(150, 150, 150); // Grey border outer
$pdf->SetLineWidth(0.4);
$pdf->Rect(10, 102, 190, 175);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(31, 78, 121); // Blue
$pdf->Cell(190, 8, pdf_utf8_to_iso("II. DATOS DE CLASIFICACIÓN DEL PARTICIPANTE (señale con una X la casilla correspondiente)"), 'B', 1, 'L');

// Column 1
$col1_x = 12;
$y = 113;

$pdf->BlueTitle("1. Edad", $col1_x, $y);
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.8); // Thick box
$pdf->Rect($col1_x, $y+6, 25, 5);
$pdf->SetXY($col1_x+1, $y+6);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(23, 5, pdf_utf8_to_iso($survey['edad'] ?? ''), 0, 1, 'C');

$y = 126;
$pdf->BlueTitle("3. Titulación actual", $col1_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);

$titulaciones = [
    '1' => '1. Sin titulación',
    '11' => '11. Certificado de Profesionalidad Nivel 1',
    '12' => '12. Formación Profesional Básica/Cualificación Profesional',
    '2' => '2. Título de graduado E.S.O./Graduado escolar',
    '21' => '21. Certificado de Profesionalidad Nivel 2',
    '3' => '3. Título de Bachiller',
    '4' => '4. Título de Técnico/ FP grado medio',
    '41' => '41. Título Profesional enseñanzas música-danza;artes',
    '42' => '42. Certificado de Profesionalidad Nivel 3',
    '5' => '5. Título de Técnico Superior/ FP grado superior',
    '6' => '6. E. universitarios 1º ciclo (Diplomatura-Grado)',
    '7' => '7. E. universitarios 2º ciclo (Licenciatura-Máster)',
    '8' => '8. E. universitarios 3º ciclo (Doctor)',
    '9' => '9. Título de Doctor'
];

$cy = $y + 5;
foreach ($titulaciones as $code => $label) {
    $pdf->SetXY($col1_x, $cy);
    $pdf->Cell(70, 4.5, pdf_utf8_to_iso($label), 0, 0, 'L');
    $pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['titulacion'] == $code);
    $cy += 4.5;
}
// Otra titulación
$pdf->SetXY($col1_x, $cy);
$pdf->Cell(70, 4.5, pdf_utf8_to_iso('10. Otra titulación'), 0, 0, 'L');
$cy += 4.5;
$pdf->SetXY($col1_x + 3, $cy);
$pdf->Cell(70, 4.5, pdf_utf8_to_iso('1. Carnet profesional'), 0, 0, 'L');
$pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['otra_titulacion'] == '1');
$cy += 4.5;
$pdf->SetXY($col1_x + 3, $cy);
$pdf->Cell(70, 4.5, pdf_utf8_to_iso('2. Enseñanzas de escuelas oficiales de idiomas'), 0, 0, 'L');
$pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['otra_titulacion'] == '2');
$cy += 4.5;
$pdf->SetXY($col1_x + 3, $cy);
$pdf->Cell(70, 4.5, pdf_utf8_to_iso('3. Otra titulación no formal (especificar) '.($survey['otra_titulacion_txt'] ?? '')), 0, 0, 'L');
$pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['otra_titulacion'] == '3');

$y = 224;
$pdf->BlueTitle("4. Lugar de trabajo (indicar PROVINCIA)", $col1_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($col1_x, $y+6);
$pdf->Cell(45, 5, pdf_utf8_to_iso("1. Lugar del centro de trabajo"));
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.8);
$pdf->Rect($col1_x+48, $y+6, 38, 5);
$pdf->SetXY($col1_x+49, $y+6);
$pdf->Cell(36, 5, pdf_utf8_to_iso($survey['trabajo_provincia'] ?? ''), 0, 1, 'C');

$y = 240;
$pdf->BlueTitle("5. Categoría profesional", $col1_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$catOps = [
    '1' => '1. Directivo/a',
    '2' => '2. Mando Intermedio',
    '3' => '3. Técnico/a',
    '4' => '4. Trabajador/a cualificado/a',
    '5' => '5. Trabajador/a de baja cualificación'
];
$cy = $y + 5;
foreach ($catOps as $code => $label) {
    $pdf->SetXY($col1_x, $cy);
    $pdf->Cell(70, 4.5, pdf_utf8_to_iso($label), 0, 0, 'L');
    $pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['categoria_profesional'] == $code);
    $cy += 4.5;
}
$pdf->SetXY($col1_x, $cy);
$pdf->Cell(70, 4.5, pdf_utf8_to_iso('6. Otra categoría (especificar) '.($survey['categoria_profesional_txt'] ?? '')), 0, 0, 'L');
$pdf->DrawBigCheckBox($col1_x + 83, $cy, $survey['categoria_profesional'] == '6');


// Column 2
$col2_x = 115;
$y = 113;
$pdf->BlueTitle("2. Sexo", $col2_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($col2_x, $y+5);
$pdf->Cell(30, 4.5, pdf_utf8_to_iso("1. Mujer"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $y+5, $survey['sexo'] == 'Mujer');
$pdf->SetXY($col2_x, $y+9.5);
$pdf->Cell(30, 4.5, pdf_utf8_to_iso("2. Varón"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $y+9.5, $survey['sexo'] == 'Varon' || $survey['sexo'] == 'Hombre');

$y = 126;
$pdf->BlueTitle("6. Horario del curso", $col2_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($col2_x, $y+5);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("1. Dentro de la jornada laboral (ir a 6.1)"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $y+5, $survey['horario_curso'] == '1');
$pdf->SetXY($col2_x, $y+9.5);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("2. Fuera de la jornada laboral"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $y+9.5, $survey['horario_curso'] == '2');
$pdf->SetXY($col2_x, $y+14);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("3. Ambas (ir a 6.1)"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $y+14, $survey['horario_curso'] == '3');

$y = 149;
$pdf->SetXY($col2_x, $y);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(31, 78, 121); // Blue
$pdf->Cell(75, 4.5, pdf_utf8_to_iso("6.1 Porcentaje de la jornada laboral que abarca"), 0, 1);
$pdf->SetXY($col2_x, $y+4.5);
$pdf->Cell(75, 4.5, pdf_utf8_to_iso("el curso"), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);

$cy = $y + 11;
$pdf->SetXY($col2_x, $cy);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("1. Menos del 25%"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $cy, $survey['jornada_porcentaje'] == '1');
$cy += 4.5;
$pdf->SetXY($col2_x, $cy);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("2. Entre el 25% al 50%"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $cy, $survey['jornada_porcentaje'] == '2');
$cy += 4.5;
$pdf->SetXY($col2_x, $cy);
$pdf->Cell(60, 4.5, pdf_utf8_to_iso("3. Más del 50%"), 0, 0, 'L');
$pdf->DrawBigCheckBox($col2_x + 65, $cy, $survey['jornada_porcentaje'] == '3');

$y = 177;
$pdf->BlueTitle("7. Tamaño de la empresa del participante", $col2_x, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$tamOps = [
    '1' => '1. De 1 a 9 empleos',
    '2' => '2. De 10 a 49 empleos',
    '3' => '3. De 50 a 99 empleos',
    '4' => '4. De 100 a 250 empleos',
    '5' => '5. De más de 250 empleos'
];
$cy = $y + 6;
foreach ($tamOps as $code => $label) {
    $pdf->SetXY($col2_x, $cy);
    $pdf->Cell(60, 4.5, pdf_utf8_to_iso($label), 0, 0, 'L');
    $pdf->DrawBigCheckBox($col2_x + 65, $cy, $survey['tamano_empresa'] == $code);
    $cy += 4.5;
}

// ---------------- PAGE 2 ---------------- //
$pdf->AddPage();
$pdf->SetDrawColor(150, 150, 150);
$pdf->SetLineWidth(0.4);

// SECCION III
$pdf->Rect(10, 25, 190, 230); // Main outer box for page 2
$pdf->SetXY(10, 25);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(190, 8, pdf_utf8_to_iso("III. VALORACIÓN DE LAS ACCIONES FORMATIVAS"), 'B', 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(190, 4.5, pdf_utf8_to_iso("Valore los siguientes aspectos del curso utilizando una escala de puntuación del 1 al 4. Marque con una X la puntuación"), 0, 1, 'L');
$pdf->Cell(190, 4.5, pdf_utf8_to_iso("correspondiente:"), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(190, 5, pdf_utf8_to_iso("1 Completamente en desacuerdo, 2 En desacuerdo, 3 De acuerdo, 4 Completamente de acuerdo"), 'B', 1, 'L');

$pdf->SetLineWidth(0.2);

function DrawValGrid($pdf, $title, $items, $scores, $ypos) {
    $pdf->SetXY(10, $ypos);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(31, 78, 121); // Blue
    $pdf->SetFillColor(240, 240, 240); // Grey header bg
    $pdf->Cell(158, 6, pdf_utf8_to_iso($title), 'B,R', 0, 'L', true);
    
    // Scale 1 2 3 4
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(210, 210, 210); // Darker grey for numbers
    $pdf->Cell(8, 6, "1", 'B,R', 0, 'C', true);
    $pdf->Cell(8, 6, "2", 'B,R', 0, 'C', true);
    $pdf->Cell(8, 6, "3", 'B,R', 0, 'C', true);
    $pdf->Cell(8, 6, "4", 'B', 1, 'C', true);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $h = 5;
    foreach ($items as $idx => $label) {
        $pdf->SetX(10);
        $val = $scores[$idx] ?? '';
        $pdf->Cell(158, $h, pdf_utf8_to_iso($label), 'B,R', 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(8, $h, $val == '1' ? 'X' : '', 'B,R', 0, 'C');
        $pdf->Cell(8, $h, $val == '2' ? 'X' : '', 'B,R', 0, 'C');
        $pdf->Cell(8, $h, $val == '3' ? 'X' : '', 'B,R', 0, 'C');
        $pdf->Cell(8, $h, $val == '4' ? 'X' : '', 'B', 1, 'C');
        $pdf->SetFont('Arial', '', 8);
    }
    return $pdf->GetY();
}

$y = $pdf->GetY();

$y = DrawValGrid($pdf, "1. Organización del curso", [
    "1.1 El curso ha estado bien organizado (información, cumplimiento fechas y de horarios, entrega material)",
    "1.2 El número de alumnos del grupo ha sido adecuado para el desarrollo del curso"
], [$survey['p1_1'], $survey['p1_2']], $y);

$y = DrawValGrid($pdf, "2. Contenidos y metodología de impartición", [
    "2.1 Los contenidos del curso han respondido a mis necesidades formativas",
    "2.2 Ha habido una combinación adecuada de teoría y aplicación práctica"
], [$survey['p2_1'], $survey['p2_2']], $y);

$y = DrawValGrid($pdf, "3. Duración y horario", [
    "3.1 La duración del curso ha sido suficiente según los objetivos y contenidos del mismo",
    "3.2 El horario ha favorecido la asistencia al curso"
], [$survey['p3_1'], $survey['p3_2']], $y);

// Custom header for Formadores
$pdf->SetXY(10, $y);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(31, 78, 121);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(126, 8, pdf_utf8_to_iso("4. Formadores / Tutores"), 'B,R', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(32, 4, pdf_utf8_to_iso("Formadores"), 'B,R', 0, 'C', true);
$pdf->Cell(32, 4, pdf_utf8_to_iso("Tutores"), 'B', 1, 'C', true);

$pdf->SetX(136);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(210, 210, 210);
$pdf->Cell(8, 4, "1", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "2", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "3", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "4", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "1", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "2", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "3", 'B,R', 0, 'C', true);
$pdf->Cell(8, 4, "4", 'B', 1, 'C', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
// 4.1
$pdf->SetX(10);
$pdf->Cell(126, 5, pdf_utf8_to_iso("4.1 La forma de impartir o tutorizar el curso ha facilitado el aprendizaje"), 'B,R', 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(8, 5, $survey['p4_1_f'] == '1' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_f'] == '2' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_f'] == '3' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_f'] == '4' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_t'] == '1' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_t'] == '2' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_t'] == '3' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_1_t'] == '4' ? 'X' : '', 'B', 1, 'C');
// 4.2
$pdf->SetFont('Arial', '', 8);
$pdf->SetX(10);
$pdf->Cell(126, 5, pdf_utf8_to_iso("4.2 Conocen los temas impartidos en profundidad"), 'B,R', 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(8, 5, $survey['p4_2_f'] == '1' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_f'] == '2' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_f'] == '3' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_f'] == '4' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_t'] == '1' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_t'] == '2' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_t'] == '3' ? 'X' : '', 'B,R', 0, 'C');
$pdf->Cell(8, 5, $survey['p4_2_t'] == '4' ? 'X' : '', 'B', 1, 'C');
$y = $pdf->GetY();

$y = DrawValGrid($pdf, "5. Medios didácticos (guías, manuales, fichas...)", [
    "5.1 La documentación y materiales entregados son comprensibles y adecuados",
    "5.2 Los medios didácticos están actualizados"
], [$survey['p5_1'], $survey['p5_2']], $y);

$y = DrawValGrid($pdf, "6. Instalaciones y medios técnicos (pizarra, pantalla, proyector, TV, vídeo, ordenador...)", [
    "6.1 El aula, el taller o las instalaciones han sido apropiadas para el desarrollo del curso",
    "6.2 Los medios técnicos han sido adecuados para desarrollar el contenido del curso"
], [$survey['p6_1'], $survey['p6_2']], $y);

$y = DrawValGrid($pdf, "7. Sólo cuando el curso se ha realizado en la modalidad a distancia, teleformación o mixta", [
    "7.1 Las guías tutoriales y los materiales didácticos han permitido realizar fácilmente el curso",
    "7.2 Se ha contado con medios de apoyo suficientes (tutorías, foro, correo, biblioteca virtual...)"
], [$survey['p7_1'], $survey['p7_2']], $y);

// Mecanismos para evaluacion (8.1, 8.2 son Si/No)
$pdf->SetXY(10, $y);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(31, 78, 121);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(190, 6, pdf_utf8_to_iso("8. Mecanismos para la evaluación del aprendizaje"), 'B', 1, 'L', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->SetX(10);
$pdf->Cell(158, 6, pdf_utf8_to_iso("8.1. Se ha dispuesto de pruebas de evaluación y autoevaluación que me permiten conocer el nivel"), 'B,R', 0, 'L');
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(6, 6, "1. Sí", 0, 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(10, 6, $survey['p8_1'] == 'Si' ? 'X' : '', 'R', 0, 'C');
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(6, 6, "2. No", 0, 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(10, 6, $survey['p8_1'] == 'No' ? 'X' : '', 0, 1, 'C');

$pdf->SetX(10);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(158, 6, pdf_utf8_to_iso("8.2 El curso me permite obtener una acreditación donde se reconoce mi cualificación"), 'B,R', 0, 'L');
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(6, 6, "1. Sí", 'B', 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(10, 6, $survey['p8_2'] == 'Si' ? 'X' : '', 'B,R', 0, 'C');
$pdf->SetTextColor(31, 78, 121);
$pdf->Cell(6, 6, "2. No", 'B', 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(10, 6, $survey['p8_2'] == 'No' ? 'X' : '', 'B', 1, 'C');
$y = $pdf->GetY();

$y = DrawValGrid($pdf, "9. Valoración general del curso", [
    "9.1 Puede contribuir a mi incorporación al mercado de trabajo",
    "9.2 Me ha permitido adquirir nuevas habilidades/capacidades que puedo aplicar al puesto de trabajo",
    "9.3 Ha mejorado mis posibilidades para cambiar de puesto de trabajo en la empresa o fuera de ella",
    "9.4 He ampliado conocimientos para progresar en mi carrera profesional",
    "9.5 Ha favorecido mi desarrollo personal"
], [$survey['p9_1'], $survey['p9_2'], $survey['p9_3'], $survey['p9_4'], $survey['p9_5']], $y);

$y = DrawValGrid($pdf, "10. Grado de satisfacción general con el curso", [
    "Valoración general del curso"
], [$survey['p10_1']], $y);

// Comentarios
$pdf->SetXY(10, $y);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(31, 78, 121); // Blue
$pdf->Cell(190, 6, pdf_utf8_to_iso("11. Si desea realizar cualquier sugerencia u observación, por favor, utilice el espacio reservado a continuación"), 'B', 1, 'L');
$pdf->SetTextColor(0, 0, 0);
// Draw multiCell for comments
$pdf->SetXY(10, $pdf->GetY());
$pdf->MultiCell(190, 5, pdf_utf8_to_iso($survey['comentarios'] ? $survey['comentarios'] : ''), 0, 'L');

// Move to bottom for date
$pdf->SetXY(10, 245);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(31, 78, 121); // Blue
$pdf->Cell(70, 10, pdf_utf8_to_iso("Fecha de cumplimentación del cuestionario"), 'T,R', 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(120, 10, safe_date('d/m/Y', $survey['fecha_realizacion']), 'T', 1, 'C');

$pdf->Output('I', 'Cuestionario_Fundae_' . $survey['id'] . '.pdf');