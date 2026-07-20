<?php
// includes/PdfGenerator.php
require_once 'includes/fpdf/fpdf.php';
require_once 'includes/fpdf/fpdf_curve.php';

// Helpers FPDF
if (!function_exists('pdf_utf8_to_iso')) {
    function pdf_utf8_to_iso($string) {
        return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
    }
}

class PDF_Diploma extends PDF_Curve {
    function drawBackground() {
        // Cyan shape
        $this->SetFillColor(5, 149, 197); // Cyan-ish #0595c5
        $pts_cyan = array(
            array('type'=>'m', 'x'=>135, 'y'=>210),
            array('type'=>'c', 'x1'=>150, 'y1'=>140, 'x2'=>200, 'y2'=>80, 'x'=>150, 'y'=>0),
            array('type'=>'l', 'x'=>297, 'y'=>0),
            array('type'=>'l', 'x'=>297, 'y'=>210),
            array('type'=>'l', 'x'=>135, 'y'=>210),
        );
        $this->DrawShape($pts_cyan, 'F');
        
        // Dark blue shape top right
        $this->SetFillColor(24, 60, 125); // Dark Blue #183c7d
        $pts_blue = array(
            array('type'=>'m', 'x'=>150, 'y'=>0),
            array('type'=>'c', 'x1'=>180, 'y1'=>60, 'x2'=>250, 'y2'=>80, 'x'=>297, 'y'=>60),
            array('type'=>'l', 'x'=>297, 'y'=>0),
            array('type'=>'l', 'x'=>150, 'y'=>0),
        );
        $this->DrawShape($pts_blue, 'F');
    }
}

class PdfGenerator {
    /**
     * Genera un Diploma o Certificado y lo devuelve en el formato especificado.
     * @param PDO $pdo Conexión a base de datos
     * @param int $alumno_id
     * @param int $grupo_id
     * @param int $accion_id
     * @param string $tipo 'certificado' o 'diploma'
     * @param string $outputMode 'I' para navegador, 'S' para string
     * @return string|void
     * @throws Exception
     */
    public static function generateDiplomaPdf($pdo, $alumno_id, $grupo_id, $accion_id, $tipo = 'certificado', $outputMode = 'I') {
        // 1. Obtener datos del alumno
        $stmt = $pdo->prepare("SELECT a.nombre, a.primer_apellido, a.segundo_apellido, a.dni 
                               FROM alumnos a WHERE a.id = ?");
        $stmt->execute([$alumno_id]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) {
            throw new Exception("Alumno no encontrado.");
        }

        $nombre_completo = mb_strtoupper($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']);
        $dni_alumno = strtoupper($alumno['dni']);

        // 2. Obtener datos de la acción, plan, convocatoria
        $stmtData = $pdo->prepare("SELECT g.fecha_inicio, g.fecha_fin, af.num_accion, af.titulo as curso_titulo, af.contenidos as af_contenidos,
                                          COALESCE(NULLIF(g.expediente, ''), c.codigo_expediente) as codigo_expediente, c.contenidos_diploma, u.nombre as tutor_nombre, u.apellidos as tutor_apellidos
                                   FROM grupos g
                                   JOIN acciones_formativas af ON g.accion_id = af.id
                                   LEFT JOIN planes p ON af.plan_id = p.id
                                   LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
                                   LEFT JOIN usuarios u ON g.tutor_id = u.id
                                   WHERE g.id = ? AND af.id = ?");
        $stmtData->execute([$grupo_id, $accion_id]);
        $data = $stmtData->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new Exception("Error al obtener datos del grupo.");
        }

        $curso_titulo = mb_strtoupper($data['curso_titulo'] ?? '', 'UTF-8');
        $num_accion = strtoupper($data['num_accion'] ?? '');
        $expediente = strtoupper($data['codigo_expediente'] ?? '');
        
        $contenidos_raw = !empty($data['af_contenidos']) ? $data['af_contenidos'] : 
                          (!empty($data['contenidos_diploma']) ? $data['contenidos_diploma'] : 
                           "Módulo 1: Introducción\nMódulo 2: Desarrollo\nMódulo 3: Conclusiones");
        
        // Convertir HTML a texto plano para FPDF preservando saltos de línea
        $contenidos_raw = preg_replace('/<br\s*\/?>/i', "\n", $contenidos_raw);
        $contenidos_raw = preg_replace('/<\/p>/i', "\n", $contenidos_raw);
        $contenidos = strip_tags($contenidos_raw);
        $contenidos = html_entity_decode($contenidos, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $contenidos = preg_replace("/[\r\n]+/", "\n", $contenidos); // Limpiar saltos múltiples

        $meses = array('Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');

        if (!empty($data['fecha_fin']) && $data['fecha_fin'] !== '0000-00-00') {
            $ts_fin = strtotime($data['fecha_fin']);
            $fecha_expedicion = date('d', $ts_fin) . ' de ' . $meses[date('n', $ts_fin)-1] . ' de ' . date('Y', $ts_fin);
        } else {
            $fecha_expedicion = date('d') . ' de ' . $meses[date('n')-1] . ' de ' . date('Y');
        }

        // Inicializar PDF Horizontal (Landscape, A4)
        $pdf = new PDF_Diploma('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false); // EVITA QUE CREE PÁGINAS EN BLANCO SI EL TEXTO ES LARGO
        $pdf->AddPage();
        $pdf->drawBackground();

        // Logos (Esquina superior derecha en la zona azul oscuro)
        if (file_exists('img/logo_diploma.png')) {
            // El nuevo logo es apaisado con certificados ISO, lo hacemos más grande y ajustamos la X
            $pdf->Image('img/logo_diploma.png', 190, 12, 95); 
        }

        // Título Principal
        $pdf->SetXY(15, 25);
        $pdf->SetTextColor(5, 149, 197); // Cyan

        if ($tipo === 'diploma') {
            $pdf->SetFont('Arial', 'B', 45);
            $pdf->Cell(150, 15, pdf_utf8_to_iso("DIPLOMA"), 0, 1, 'L');
            $pdf->Ln(15);
        } else {
            $pdf->SetFont('Arial', 'B', 38);
            $pdf->Cell(150, 14, pdf_utf8_to_iso("CERTIFICADO DE"), 0, 1, 'L');
            $pdf->SetX(15);
            $pdf->Cell(150, 14, pdf_utf8_to_iso("ASISTENCIA"), 0, 1, 'L');
            $pdf->Ln(5);
        }

        // Nombre del alumno
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(24, 60, 125); // Dark Blue
        $pdf->Cell(150, 10, pdf_utf8_to_iso($nombre_completo), 0, 1, 'L');

        // Línea separadora
        $pdf->SetX(15);
        $pdf->SetDrawColor(24, 60, 125);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(15, $pdf->GetY() + 5, 165, $pdf->GetY() + 5);
        $pdf->Ln(10);

        // Textos legales (Cuerpo)
        $pdf->SetX(15);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(40, 40, 40);

        $texto1 = "D./Dña. " . $nombre_completo . ", con NIF " . $dni_alumno;
        $pdf->Cell(150, 6, pdf_utf8_to_iso($texto1), 0, 1, 'L');
        $pdf->Ln(3);

        $texto2 = "Ha participado en la Acción Formativa:\n" . $num_accion . " - " . $curso_titulo;
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(25, 60, 126);
        $pdf->MultiCell(150, 6, pdf_utf8_to_iso($texto2), 0, 'L');
        $pdf->Ln(3);

        $pdf->SetX(15);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(40, 40, 40);
        $texto3 = "Esta Acción Formativa se encuentra incluida en el Plan de Formación Estatal, con nº de expediente $expediente, perteneciente a la aprobación de subvenciones públicas para la ejecución de programas de formación de ámbito estatal, dirigido prioritariamente a personas trabajadoras ocupadas, al amparo de la convocatoria aprobada mediante Resolución del Servicio Público de Empleo Estatal de 6 de agosto de 2024, solicitada por Marsdigital S.L.";
        $pdf->MultiCell(150, 5, pdf_utf8_to_iso($texto3), 0, 'L');
        $pdf->Ln(5);

        $pdf->SetX(15);
        $texto4 = "En Madrid, a " . $fecha_expedicion;
        $pdf->Cell(150, 6, pdf_utf8_to_iso($texto4), 0, 1, 'L');

        $pdf->Ln(15);

        // Firmas
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(25, 60, 126);
        $pdf->Cell(80, 6, pdf_utf8_to_iso("Fecha de expedición: " . $fecha_expedicion), 0, 1, 'L');
        $pdf->Ln(4);
        $pdf->SetX(15);
        $pdf->Cell(80, 6, pdf_utf8_to_iso("Firma y sello del centro de formación"), 0, 0, 'L');
        $pdf->Cell(70, 6, pdf_utf8_to_iso("Firma del trabajador"), 0, 1, 'L');

        // Colocar imagen de la firma debajo de la firma del centro
        if (file_exists('img/firma_mars.png')) {
            $pdf->Image('img/firma_mars.png', 15, $pdf->GetY() + 5, 40);
        }

        // ------ PANEL DERECHO (Contenidos) ------
        $pdf->SetXY(185, 70);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(255, 255, 255); // White text
        $pdf->Cell(100, 8, "CONTENIDOS:", 0, 1, 'L');
        $pdf->Ln(1);

        $lineas_contenidos = explode("\n", $contenidos);
        
        // Calcular tamaño de fuente óptimo para que no se desborde (Dynamic Font Resizing)
        $max_height = 125; // Espacio vertical disponible (aprox desde Y=80 hasta Y=205)
        $w = 105; // Ancho de la caja de contenidos
        $fontSize = 9.5; // Tamaño máximo inicial
        
        while ($fontSize >= 4) {
            $pdf->SetFont('Arial', '', $fontSize);
            $lh = $fontSize * 0.45; // Interlineado dinámico
            $totalHeight = 0;
            
            foreach ($lineas_contenidos as $linea) {
                $linea = trim($linea);
                if (empty($linea)) continue;
                
                $str = pdf_utf8_to_iso($linea);
                $strWidth = $pdf->GetStringWidth($str);
                
                // Aproximación de líneas que ocupará el texto
                if ($strWidth <= $w) {
                    $lines = 1;
                } else {
                    $lines = ceil($strWidth / $w) + 0.3; // Margen de seguridad para palabras largas
                }
                $totalHeight += ceil($lines) * $lh;
            }
            
            if ($totalHeight <= $max_height) {
                break; // Cabe en el espacio disponible
            }
            $fontSize -= 0.5; // Reducir fuente y volver a intentar
        }

        // Renderizar con el tamaño óptimo calculado
        $pdf->SetFont('Arial', '', $fontSize);
        $lh = $fontSize * 0.45;
        
        foreach ($lineas_contenidos as $linea) {
            $linea = trim($linea);
            if (!empty($linea)) {
                $pdf->SetX(185);
                $pdf->MultiCell($w, $lh, pdf_utf8_to_iso($linea), 0, 'L');
            }
        }

        $filename = strtoupper($tipo) . '_' . $dni_alumno . '.pdf';
        
        if ($outputMode === 'S') {
            return $pdf->Output('S');
        } else {
            $pdf->Output('I', $filename);
        }
    }
}
