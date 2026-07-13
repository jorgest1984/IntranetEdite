<?php
// pdf_changelog.php — PDF de Changelog para auditoría ISO 27001
ini_set('display_errors', 0);
if (ob_get_level()) ob_end_clean();

require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/fpdf/fpdf.php';

// ── Filtros (mismos que changelog.php) ──
$f_entorno = $_GET['entorno'] ?? '';
$f_tipo    = $_GET['tipo']    ?? '';
$f_estado  = $_GET['estado']  ?? '';
$f_desde   = $_GET['desde']   ?? '';
$f_hasta   = $_GET['hasta']   ?? '';

$where  = ['1=1'];
$params = [];
if ($f_entorno) { $where[] = 'entorno = ?'; $params[] = $f_entorno; }
if ($f_tipo)    { $where[] = 'tipo = ?';    $params[] = $f_tipo; }
if ($f_estado)  { $where[] = 'estado = ?';  $params[] = $f_estado; }
if ($f_desde)   { $where[] = 'DATE(fecha_registro) >= ?'; $params[] = $f_desde; }
if ($f_hasta)   { $where[] = 'DATE(fecha_registro) <= ?'; $params[] = $f_hasta; }

$sql = "SELECT c.*, u.nombre, u.apellidos, u.username
        FROM changelog c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha_registro DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Detección del entorno actual
$host = $_SERVER['HTTP_HOST'] ?? '';
$entorno_actual = 'Producción';
if (in_array($host, ['localhost','127.0.0.1','localhost:8000'])) $entorno_actual = 'Local';
elseif ($host === 'pre-gestion.grupoefp.es') $entorno_actual = 'Pre-producción';

// Helper: UTF-8 → ISO-8859-1
function cl_str($s) {
    if ($s === null) return '';
    return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');
}

// Etiquetas de tipo (sin emojis para PDF)
$tipo_labels = [
    'feature'  => 'Nueva Funcion',
    'fix'      => 'Correccion',
    'security' => 'Seguridad',
    'database' => 'Base de Datos',
    'hotfix'   => 'Hotfix',
    'refactor' => 'Refactorizacion',
    'docs'     => 'Documentacion',
];
$estado_labels = [
    'pendiente'  => 'Pendiente',
    'desplegado' => 'Desplegado',
    'revertido'  => 'Revertido',
];

// ── Clase PDF ──
class ChangelogPDF extends FPDF {
    public $entorno, $desde, $hasta, $total;

    function Header() {
        // Banda de color superior
        $this->SetFillColor(0, 108, 228);
        $this->Rect(0, 0, 210, 14, 'F');

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(3);
        $this->Cell(0, 8, cl_str('CHANGELOG — REGISTRO DE CAMBIOS ISO 27001:2022 · A.8.32'), 0, 0, 'C');

        // Subheader gris
        $this->SetFillColor(245, 247, 250);
        $this->Rect(0, 14, 210, 10, 'F');
        $this->SetY(15);
        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(80, 90, 110);
        $this->Cell(70, 8, cl_str('Organización: Grupo EFP'), 0, 0, 'L');
        $this->Cell(70, 8, cl_str('Entorno: ' . $this->entorno), 0, 0, 'C');
        $this->Cell(70, 8, cl_str('Generado: ' . date('d/m/Y H:i')), 0, 0, 'R');

        $this->SetY(26);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(95, 5, cl_str('Documento confidencial — Uso interno — ISO 27001'), 0, 0, 'L');
        $this->Cell(95, 5, cl_str('Pag. ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

$pdf = new ChangelogPDF();
$pdf->entorno = $entorno_actual;
$pdf->total   = count($entries);
$pdf->desde   = $f_desde ?: '—';
$pdf->hasta   = $f_hasta ?: '—';
$pdf->AliasNbPages();
$pdf->SetMargins(12, 30, 12);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// ── Resumen ──
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 60, 140);
$pdf->Cell(0, 6, cl_str('Resumen del informe'), 0, 1, 'L');
$pdf->SetDrawColor(0, 108, 228);
$pdf->SetLineWidth(0.4);
$pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(50, 60, 80);
$total     = count($entries);
$desplegados = count(array_filter($entries, fn($e) => $e['estado'] === 'desplegado'));
$pendientes  = count(array_filter($entries, fn($e) => $e['estado'] === 'pendiente'));
$revertidos  = count(array_filter($entries, fn($e) => $e['estado'] === 'revertido'));

$pdf->Cell(45, 6, cl_str("Total entradas: $total"),       0, 0);
$pdf->Cell(45, 6, cl_str("Desplegados: $desplegados"),    0, 0);
$pdf->Cell(45, 6, cl_str("Pendientes: $pendientes"),      0, 0);
$pdf->Cell(45, 6, cl_str("Revertidos: $revertidos"),      0, 1);
if ($f_desde || $f_hasta) {
    $pdf->Cell(0, 5, cl_str("Rango filtrado: " . ($f_desde ?: '—') . " al " . ($f_hasta ?: '—')), 0, 1);
}
$pdf->Ln(4);

// ── Cabecera tabla ──
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(220, 232, 250);
$pdf->SetTextColor(0, 60, 140);
$pdf->SetDrawColor(180, 200, 230);

$pdf->Cell(18, 7, cl_str('Versión'),  1, 0, 'C', true);
$pdf->Cell(22, 7, cl_str('Fecha'),    1, 0, 'C', true);
$pdf->Cell(22, 7, cl_str('Tipo'),     1, 0, 'C', true);
$pdf->Cell(22, 7, cl_str('Entorno'),  1, 0, 'C', true);
$pdf->Cell(22, 7, cl_str('Estado'),   1, 0, 'C', true);
$pdf->Cell(60, 7, cl_str('Título'),   1, 0, 'L', true);
$pdf->Cell(20, 7, cl_str('Autor'),    1, 1, 'C', true);

// ── Filas ──
$pdf->SetFont('Arial', '', 7);
$fill = false;

foreach ($entries as $entry) {
    $autor = trim(($entry['nombre'] ?? '') . ' ' . ($entry['apellidos'] ?? '')) ?: ($entry['username'] ?? 'N/A');
    $tipo_lbl   = $tipo_labels[$entry['tipo']]   ?? $entry['tipo'];
    $estado_lbl = $estado_labels[$entry['estado']] ?? $entry['estado'];
    $entorno_lbl = ucfirst($entry['entorno']);
    $fecha      = date('d/m/Y', strtotime($entry['fecha_registro']));
    $titulo_trunc = mb_substr($entry['titulo'], 0, 55);

    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, 255);
    $pdf->SetTextColor(30, 40, 60);

    $rowH = 6;
    $pdf->Cell(18, $rowH, cl_str($entry['version']),    1, 0, 'C', $fill);
    $pdf->Cell(22, $rowH, cl_str($fecha),               1, 0, 'C', $fill);
    $pdf->Cell(22, $rowH, cl_str($tipo_lbl),            1, 0, 'C', $fill);
    $pdf->Cell(22, $rowH, cl_str($entorno_lbl),         1, 0, 'C', $fill);
    $pdf->Cell(22, $rowH, cl_str($estado_lbl),          1, 0, 'C', $fill);
    $pdf->Cell(60, $rowH, cl_str($titulo_trunc),        1, 0, 'L', $fill);
    $pdf->Cell(20, $rowH, cl_str(mb_substr($autor,0,16)), 1, 1, 'L', $fill);

    // Descripcion (si tiene)
    if (!empty($entry['descripcion'])) {
        $pdf->SetFont('Arial', 'I', 6.5);
        $pdf->SetTextColor(100, 110, 130);
        $pdf->SetX(30);
        $desc_short = mb_substr(str_replace(["\r","\n"], ' ', $entry['descripcion']), 0, 200);
        $pdf->MultiCell(156, 4.5, cl_str($desc_short), 0, 'L');
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(30, 40, 60);
    }

    // Referencias/commit
    if (!empty($entry['referencia']) || !empty($entry['git_commit'])) {
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->SetTextColor(120, 130, 150);
        $ref_line = '';
        if ($entry['referencia']) $ref_line .= 'Ref: ' . $entry['referencia'];
        if ($entry['git_commit']) $ref_line .= ($ref_line ? ' | ' : '') . 'Git: ' . substr($entry['git_commit'], 0, 50);
        $pdf->SetX(30);
        $pdf->Cell(156, 4, cl_str($ref_line), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(30, 40, 60);
    }

    $pdf->SetDrawColor(210, 218, 228);
    $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
    $pdf->SetDrawColor(180, 200, 230);
    $fill = !$fill;
}

// ── Pie de firma ──
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(0, 60, 140);
$pdf->Cell(0, 5, cl_str('Firma del responsable de cambios:'), 0, 1, 'L');
$pdf->Ln(12);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.4);
$pdf->Line(12, $pdf->GetY(), 90, $pdf->GetY());
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetX(12);
$pdf->Cell(78, 4, cl_str('Nombre y firma'), 0, 0, 'C');
$pdf->SetX(100);
$pdf->Line(100, $pdf->GetY() - 4, 198, $pdf->GetY() - 4);
$pdf->Cell(98, 4, cl_str('Fecha de aprobación'), 0, 1, 'C');

$filename = 'Changelog_ISO27001_' . date('Ymd_Hi') . '.pdf';
$pdf->Output('D', $filename);
exit;
