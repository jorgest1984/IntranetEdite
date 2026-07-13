<?php
// pdf_encuesta.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

// Validar acceso (permite al alumno descargar su propio PDF, o al personal ver cualquiera)
$encuesta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$encuesta_id) {
    die("ID de encuesta no especificado.");
}

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
        WHERE er.id = ?
        LIMIT 1
    ");
    $stmt->execute([$encuesta_id]);
    $survey = $stmt->fetch();
} catch (Exception $e) {
    die("Error al consultar la encuesta: " . $e->getMessage());
}

if (!$survey) {
    die("Cuestionario no encontrado.");
}


// Función compatible con PHP 8.2+ para evitar deprecación de utf8_decode
function pdf_utf8_to_iso($str) {
    if ($str === null || $str === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        if ($converted !== false) {
            return $converted;
        }
    }
    return @\utf8_decode($str);
}

class FundaeSurveyPDF extends FPDF {
    function Header() {
        // Logo o Encabezado Oficial
        if (file_exists('img/cabecera_fundae.png')) {
            try {
                $imgInfo = @getimagesize('img/cabecera_fundae.png');
                $type = '';
                if ($imgInfo) {
                    if ($imgInfo[2] == IMAGETYPE_JPEG) $type = 'JPEG';
                    elseif ($imgInfo[2] == IMAGETYPE_PNG) $type = 'PNG';
                    elseif ($imgInfo[2] == IMAGETYPE_GIF) $type = 'GIF';
                }
                $this->Image('img/cabecera_fundae.png', 10, 8, 190, 0, $type);
                $this->SetY(24);
            } catch (Exception $e) {
                $this->SetFillColor(185, 28, 28); // Rojo Fundae
                $this->Rect(10, 10, 190, 3, 'F');
                $this->SetY(15);
            }
        } else {
            $this->SetFillColor(185, 28, 28); // Rojo Fundae
            $this->Rect(10, 10, 190, 3, 'F');
            $this->SetY(15);
        }
        
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(153, 27, 27); // Rojo oscuro
        $this->Cell(0, 4, pdf_utf8_to_iso("CUESTIONARIO DE LA EVALUACIÓN PARA LA CALIDAD DE LAS ACCIONES"), 0, 1, 'C');
        $this->Cell(0, 4, pdf_utf8_to_iso("FORMATIVAS EN EL MARCO DEL SISTEMA DE FORMACIÓN PARA EL EMPLEO"), 0, 1, 'C');
        $this->Cell(0, 4, pdf_utf8_to_iso("FORMACIÓN DE OFERTA"), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 4, pdf_utf8_to_iso("(Orden TAS/718/2008, de 7 de Marzo)"), 0, 1, 'C');
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 10, pdf_utf8_to_iso("Página ") . $this->PageNo() . " de {nb} - Documento oficial Fundae generado por Intranet Edite", 0, 0, 'C');
    }

    // Dibuja una casilla [ ] o [X]
    function CheckBox($x, $y, $checked = false, $size = 3.5) {
        $this->Rect($x, $y, $size, $size);
        if ($checked) {
            $this->Line($x, $y, $x + $size, $y + $size);
            $this->Line($x + $size, $y, $x, $y + $size);
        }
    }
}

$pdf = new FundaeSurveyPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 15, 10);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Sección I: DATOS IDENTIFICATIVOS
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(190, 5, pdf_utf8_to_iso("I. DATOS IDENTIFICATIVOS DE LA ACCIÓN FORMATIVA"), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 7.5);
// Fila 1
$pdf->Cell(63.3, 5, pdf_utf8_to_iso("1. Nº Expediente: ") . ($survey['codigo_expediente'] ?? '---'), 1, 0);
$pdf->Cell(63.3, 5, pdf_utf8_to_iso("2. Nº Acción: ") . ($survey['num_accion'] ?? '---'), 1, 0);
$pdf->Cell(63.3, 5, pdf_utf8_to_iso("3. Nº Grupo: ") . ($survey['numero_grupo'] ?? '---'), 1, 1);

// Fila 2
$pdf->Cell(190, 5, pdf_utf8_to_iso("4. Denominación acción: ") . ($survey['curso_nombre']), 1, 1);

// Fila 3
$pdf->Cell(95, 5, pdf_utf8_to_iso("5. Modalidad: ") . ($survey['modalidad'] ?? 'Teleformación'), 1, 0);
// Helper function to safely format dates and avoid TypeError in PHP 8
function safe_date($format, $dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') return '---';
    $ts = strtotime($dateStr);
    return $ts ? date($format, $ts) : '---';
}

$pdf->Cell(95, 5, pdf_utf8_to_iso("6. Fechas: ") . safe_date('d/m/Y', $survey['fecha_inicio']) . " al " . safe_date('d/m/Y', $survey['fecha_fin']), 1, 1);

$pdf->Ln(4);

// Sección II: DATOS DEL PARTICIPANTE
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(190, 5, pdf_utf8_to_iso("II. DATOS A CUMPLIMENTAR POR EL PARTICIPANTE"), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 7.5);

// Edad y Sexo
$yStart = $pdf->GetY();
$pdf->Rect(10, $yStart, 95, 12);
$pdf->SetXY(11, $yStart + 2);
$pdf->Cell(40, 4, pdf_utf8_to_iso("1. Edad: ") . ($survey['edad'] ?? '---') . " años");

$pdf->SetXY(60, $yStart + 2);
$pdf->Cell(20, 4, pdf_utf8_to_iso("2. Sexo:"));
$pdf->CheckBox(75, $yStart + 2.5, $survey['sexo'] == 'Mujer');
$pdf->Text(80, $yStart + 5.5, pdf_utf8_to_iso("Mujer"));
$pdf->CheckBox(75, $yStart + 7, $survey['sexo'] == 'Varon');
$pdf->Text(80, $yStart + 10, pdf_utf8_to_iso("Varón"));

// Lugar de Residencia
$pdf->SetXY(105, $yStart);
$pdf->Cell(95, 12, pdf_utf8_to_iso("5. Residencia (Provincia): ") . ($survey['residencia_provincia'] ?? '---'), 1, 1);

$pdf->Ln(2);

// Titulación Actual y Otra
$yStart = $pdf->GetY();
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Text(12, $yStart + 3, pdf_utf8_to_iso("3. Titulación Actual (Marcar una opción)"));
$pdf->SetFont('Arial', '', 7);

$titulaciones = [
    '1' => '1. Sin titulación',
    '11' => '11. Certificado de Profesionalidad Nivel 1',
    '12' => '12. Formación Profesional Básica / CPI',
    '2' => '2. Graduado ESO / Graduado Escolar',
    '21' => '21. Certificado de Profesionalidad Nivel 2',
    '3' => '3. Bachiller',
    '4' => '4. Técnico FP grado medio / FPI',
    '41' => '41. Título profesional música y danza...',
    '42' => '42. Certificado de Profesionalidad Nivel 3',
    '5' => '5. Técnico FP grado superior / FPII',
    '6' => '6. Univ. 1º ciclo (Diplomatura-Grado)',
    '7' => '7. Univ. 2º ciclo (Licenciatura-Máster)',
    '8' => '8. Univ. 3º ciclo (Doctorado)',
    '9' => '9. Doctor'
];

$i = 0;
foreach ($titulaciones as $code => $label) {
    $col = $i % 2;
    $row = floor($i / 2);
    $xPos = 12 + ($col * 95);
    $yPos = $yStart + 5 + ($row * 4.5);
    $pdf->CheckBox($xPos, $yPos, $survey['titulacion'] == $code);
    $pdf->Text($xPos + 5, $yPos + 3, pdf_utf8_to_iso($label));
    $i++;
}

// Dibujar recuadro de Titulación
$pdf->SetY($yStart);
$pdf->Cell(190, 42, "", 1, 1);

// Otra titulación
$pdf->Cell(190, 6, pdf_utf8_to_iso("3.1. Otra titulación: ") . 
    ($survey['otra_titulacion'] == '1' ? '[X] Carnet profesional' : '[ ] Carnet profesional') . "   " . 
    ($survey['otra_titulacion'] == '2' ? '[X] EOI' : '[ ] EOI') . "   " . 
    ($survey['otra_titulacion'] == '3' ? '[X] No formal (' . $survey['otra_titulacion_txt'] . ')' : '[ ] No formal'), 1, 1);

// Situación Laboral y Cómo conoció
$yStart = $pdf->GetY();
$pdf->Rect(10, $yStart, 95, 18);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Text(12, $yStart + 3.5, pdf_utf8_to_iso("4. Situación Laboral:"));
$pdf->SetFont('Arial', '', 7);
$pdf->CheckBox(12, $yStart + 5.5, $survey['situacion_laboral'] == '1');
$pdf->Text(17, $yStart + 8.5, pdf_utf8_to_iso("Desempleado/a"));
$pdf->CheckBox(12, $yStart + 9.5, $survey['situacion_laboral'] == '2');
$pdf->Text(17, $yStart + 12.5, pdf_utf8_to_iso("Autónomo / Cuenta propia"));
$pdf->CheckBox(12, $yStart + 13.5, $survey['situacion_laboral'] == '3');
$pdf->Text(17, $yStart + 16.5, pdf_utf8_to_iso("Trabajador cuenta ajena"));

$pdf->SetXY(105, $yStart);
$pdf->Rect(105, $yStart, 95, 18);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Text(107, $yStart + 3.5, pdf_utf8_to_iso("6. ¿Cómo conoció el curso?:"));
$pdf->SetFont('Arial', '', 7);
$comoConocioLabel = 'Ninguno';
if ($survey['como_conocio'] == '1') $comoConocioLabel = 'Servicio Público de Empleo';
elseif ($survey['como_conocio'] == '2') $comoConocioLabel = 'Itinerario formativo';
elseif ($survey['como_conocio'] == '3') $comoConocioLabel = 'A través de mi empresa';
elseif ($survey['como_conocio'] == '4') $comoConocioLabel = 'Org. empresarial o sindical';
elseif ($survey['como_conocio'] == '5') $comoConocioLabel = 'Medios de comunicación';
elseif ($survey['como_conocio'] == '6') $comoConocioLabel = 'Otros (' . $survey['como_conocio_txt'] . ')';
$pdf->Text(107, $yStart + 8.5, pdf_utf8_to_iso($comoConocioLabel));

$pdf->SetY($yStart + 18);
$pdf->Ln(2);

// Conditional fields for Ocupados (Only if situacion_laboral is 2 or 3)
if (in_array($survey['situacion_laboral'], ['2', '3'])) {
    $yStart = $pdf->GetY();
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(190, 5, pdf_utf8_to_iso("Datos de ocupados (Categoría, Horario y Tamaño empresa)"), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 7);
    
    $catLabel = '---';
    if ($survey['categoria_profesional'] == '1') $catLabel = 'Directivo/a';
    elseif ($survey['categoria_profesional'] == '2') $catLabel = 'Mando intermedio';
    elseif ($survey['categoria_profesional'] == '3') $catLabel = 'Técnico/a';
    elseif ($survey['categoria_profesional'] == '4') $catLabel = 'Trabajador/a cualificado/a';
    elseif ($survey['categoria_profesional'] == '5') $catLabel = 'Trabajador/a baja cualificación';
    elseif ($survey['categoria_profesional'] == '6') $catLabel = 'Otra (' . $survey['categoria_profesional_txt'] . ')';

    $horarioLabel = '---';
    if ($survey['horario_curso'] == '1') $horarioLabel = 'Dentro de jornada';
    elseif ($survey['horario_curso'] == '2') $horarioLabel = 'Fuera de jornada';
    elseif ($survey['horario_curso'] == '3') $horarioLabel = 'Ambas';

    $tamanoLabel = '---';
    if ($survey['tamano_empresa'] == '1') $tamanoLabel = '1-9 emp.';
    elseif ($survey['tamano_empresa'] == '2') $tamanoLabel = '10-49 emp.';
    elseif ($survey['tamano_empresa'] == '3') $tamanoLabel = '50-99 emp.';
    elseif ($survey['tamano_empresa'] == '4') $tamanoLabel = '100-250 emp.';
    elseif ($survey['tamano_empresa'] == '5') $tamanoLabel = '>250 emp.';

    $pdf->Cell(63.3, 5, pdf_utf8_to_iso("Centro de Trabajo: ") . ($survey['trabajo_provincia'] ?? '---'), 1, 0);
    $pdf->Cell(63.3, 5, pdf_utf8_to_iso("Categoría: ") . $catLabel, 1, 0);
    $pdf->Cell(63.3, 5, pdf_utf8_to_iso("Horario: ") . $horarioLabel, 1, 1);
    
    $pdf->Cell(95, 5, pdf_utf8_to_iso("Jornada ocupada por curso: ") . ($survey['jornada_porcentaje'] ? $survey['jornada_porcentaje'] . ' (1=<25%, 2=25-50%, 3=>50%)' : '---'), 1, 0);
    $pdf->Cell(95, 5, pdf_utf8_to_iso("Tamaño empresa: ") . $tamanoLabel, 1, 1);
    $pdf->Ln(2);
}

// Sección III: VALORACIÓN
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(190, 5, pdf_utf8_to_iso("III. VALORACIÓN DE LAS ACCIONES FORMATIVAS (Puntuación 1 a 4)"), 1, 1, 'L', true);

$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(150, 5, pdf_utf8_to_iso("Aspecto a valorar"), 1, 0, 'L');
$pdf->Cell(10, 5, "1", 1, 0, 'C');
$pdf->Cell(10, 5, "2", 1, 0, 'C');
$pdf->Cell(10, 5, "3", 1, 0, 'C');
$pdf->Cell(10, 5, "4", 1, 1, 'C');

$pdf->SetFont('Arial', '', 7);

function drawRatingRow($pdf, $text, $val) {
    $pdf->Cell(150, 4.5, pdf_utf8_to_iso($text), 1, 0, 'L');
    $pdf->Cell(10, 4.5, $val == 1 ? 'X' : '', 1, 0, 'C');
    $pdf->Cell(10, 4.5, $val == 2 ? 'X' : '', 1, 0, 'C');
    $pdf->Cell(10, 4.5, $val == 3 ? 'X' : '', 1, 0, 'C');
    $pdf->Cell(10, 4.5, $val == 4 ? 'X' : '', 1, 1, 'C');
}

// 1. Organizacion
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("1. Organización del curso"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  1.1 Organización general, información, fechas y entrega de material", $survey['p1_1']);
drawRatingRow($pdf, "  1.2 Número de alumnos adecuado para el desarrollo", $survey['p1_2']);

// 2. Contenidos
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("2. Contenidos del curso"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  2.1 Contenidos adaptados a las necesidades formativas", $survey['p2_1']);
drawRatingRow($pdf, "  2.2 Adecuada combinación de teoría y práctica", $survey['p2_2']);

// 3. Duración
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("3. Duración y horario"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  3.1 Duración suficiente según objetivos y contenidos", $survey['p3_1']);
drawRatingRow($pdf, "  3.2 Horario adecuado para la asistencia", $survey['p3_2']);

// 4. Formadores/Tutores
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("4. Formadores y Tutores"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  4.1.F Formador: Facilidad para transmitir y explicar", $survey['p4_1_f']);
drawRatingRow($pdf, "  4.2.F Formador: Dominio y profundidad de los temas", $survey['p4_2_f']);
drawRatingRow($pdf, "  4.1.T Tutor: Facilidad para guiar el aprendizaje", $survey['p4_1_t']);
drawRatingRow($pdf, "  4.2.T Tutor: Dominio y profundidad en las tutorías", $survey['p4_2_t']);

// 5. Medios didácticos
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("5. Medios didácticos"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  5.1 Documentación y manuales comprensibles y de calidad", $survey['p5_1']);
drawRatingRow($pdf, "  5.2 Medios didácticos actualizados", $survey['p5_2']);

// 6. Instalaciones
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("6. Instalaciones y plataformas online"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  6.1 Aula / Entorno online adecuados", $survey['p6_1']);
drawRatingRow($pdf, "  6.2 Equipamiento y herramientas apropiadas", $survey['p6_2']);

// 7. Teleformacion
if ($survey['modalidad'] == 'Teleformacion' || $survey['modalidad'] == 'Mixta') {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(190, 4, pdf_utf8_to_iso("7. Cursos de Teleformación o Mixtos"), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 7);
    drawRatingRow($pdf, "  7.1 Guías de aprendizaje claras y fáciles de seguir", $survey['p7_1']);
    drawRatingRow($pdf, "  7.2 Medios de apoyo suficientes (foros, chats, biblioteca...)", $survey['p7_2']);
}

// 8. Evaluacion
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("8. Mecanismos para la evaluación del aprendizaje"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
$pdf->Cell(170, 4.5, pdf_utf8_to_iso("  8.1 Se han dispuesto pruebas de evaluación adecuadas"), 1, 0, 'L');
$pdf->Cell(20, 4.5, $survey['p8_1'] == 'Si' ? 'SI' : 'NO', 1, 1, 'C');
$pdf->Cell(170, 4.5, pdf_utf8_to_iso("  8.2 Permite obtener acreditación con validez laboral"), 1, 0, 'L');
$pdf->Cell(20, 4.5, $survey['p8_2'] == 'Si' ? 'SI' : 'NO', 1, 1, 'C');

// 9. Valoración General
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("9. Valoración general del curso"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  9.1 Contribución a la inserción laboral", $survey['p9_1']);
drawRatingRow($pdf, "  9.2 Habilidades aplicables en el puesto de trabajo", $survey['p9_2']);
drawRatingRow($pdf, "  9.3 Oportunidad de cambio o mejora de empleo", $survey['p9_3']);
drawRatingRow($pdf, "  9.4 Progreso y promoción en carrera profesional", $survey['p9_4']);
drawRatingRow($pdf, "  9.5 Utilidad para el desarrollo personal", $survey['p9_5']);

// 10. Satisfacción Global
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(190, 4, pdf_utf8_to_iso("10. Grado de satisfacción general con el curso"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 7);
drawRatingRow($pdf, "  Valoración global del curso", $survey['p10_1']);

$pdf->Ln(2);

// Comentarios
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->Cell(190, 4.5, pdf_utf8_to_iso("11. Observaciones, propuestas de mejora y sugerencias:"), 0, 1);
$pdf->SetFont('Arial', '', 7);
$pdf->MultiCell(190, 4, pdf_utf8_to_iso($survey['comentarios'] ? $survey['comentarios'] : 'Ninguna.'), 1, 'L');

// Prácticas No Laborales
if ($survey['p12_1'] !== null) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(190, 5, pdf_utf8_to_iso("SOLO PARA PERSONAS QUE HAN REALIZADO PRÁCTICAS NO LABORALES EN EMPRESAS"), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(150, 5, pdf_utf8_to_iso("Aspecto de las prácticas"), 1, 0, 'L');
    $pdf->Cell(10, 5, "1", 1, 0, 'C');
    $pdf->Cell(10, 5, "2", 1, 0, 'C');
    $pdf->Cell(10, 5, "3", 1, 0, 'C');
    $pdf->Cell(10, 5, "4", 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 7);
    drawRatingRow($pdf, "12.1 Prácticas relacionadas con los contenidos teóricos", $survey['p12_1']);
    
    $pdf->Cell(170, 4.5, pdf_utf8_to_iso("12.2 ¿Han sido suficientes las horas dedicadas a las prácticas?"), 1, 0, 'L');
    $pdf->Cell(20, 4.5, $survey['p12_2'] == 'Si' ? 'SI' : 'NO', 1, 1, 'C');
    
    drawRatingRow($pdf, "12.3 Prácticas útiles para adquirir destrezas profesionales", $survey['p12_3']);
    drawRatingRow($pdf, "12.4 Calidad del tutor o tutores de la empresa", $survey['p12_4']);
    
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(190, 4.5, pdf_utf8_to_iso("12.5 Descripción breve del contenido de las prácticas:"), 0, 1);
    $pdf->SetFont('Arial', '', 7);
    $pdf->MultiCell(190, 4, pdf_utf8_to_iso($survey['p12_5'] ? $survey['p12_5'] : 'Sin comentarios.'), 1, 'L');
}

$pdf->Ln(10);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, pdf_utf8_to_iso("Fecha de cumplimentación: ") . safe_date('d/m/Y H:i', $survey['fecha_realizacion']), 0, 1, 'R');

$pdf->Output('I', 'Cuestionario_Fundae_' . $survey['id'] . '.pdf');
