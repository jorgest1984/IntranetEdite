<?php
// exportar_facturas.php - Exportar facturas a Excel
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: home.php");
    exit();
}

// Aplicar los mismos filtros que en facturas.php
$sql = "SELECT f.* FROM facturas f WHERE 1=1";
$params = [];

if (!empty($_GET['cif'])) {
    $sql .= " AND f.cif LIKE ?";
    $params[] = "%" . $_GET['cif'] . "%";
}
if (!empty($_GET['razon'])) {
    $sql .= " AND f.razon_social LIKE ?";
    $params[] = "%" . $_GET['razon'] . "%";
}
if (!empty($_GET['referencia'])) {
    $sql .= " AND f.referencia LIKE ?";
    $params[] = "%" . $_GET['referencia'] . "%";
}
if (!empty($_GET['num_factura'])) {
    $sql .= " AND f.numero_factura LIKE ?";
    $params[] = "%" . $_GET['num_factura'] . "%";
}
if (!empty($_GET['fecha_desde'])) {
    $sql .= " AND f.fecha_emision >= ?";
    $params[] = $_GET['fecha_desde'];
}
if (!empty($_GET['fecha_hasta'])) {
    $sql .= " AND f.fecha_emision <= ?";
    $params[] = $_GET['fecha_hasta'];
}
if (!empty($_GET['plan_id'])) {
    $sql .= " AND f.plan_id = ?";
    $params[] = $_GET['plan_id'];
}
if (!empty($_GET['convocatoria_id'])) {
    $sql .= " AND f.convocatoria_id = ?";
    $params[] = $_GET['convocatoria_id'];
}

$sql .= " ORDER BY f.fecha_emision DESC, f.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error al consultar facturas: " . $e->getMessage());
}

// Columnas según la estructura del screenshot
$columnas = [
    'CIF',
    'Nº FACTURA',
    'IMPORTE',
    'IMPUTABLE',
    'FECHA',
    'FECHA INGRESO',
    'REFERENCIA',
    'EXPEDIENTE',
    'Nº ACCIÓN',
    'Nº GRUPO',
    'CONCEPTO',
    'Nº LICENCIAS FACTURADAS'
];

// Función para formatear fecha DD/MM/YYYY
function formatFechaExport($fecha) {
    if (empty($fecha)) return '';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : $fecha;
}

// Generar Excel XML Spreadsheet 2003
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
$xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
$xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
$xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// Estilos
$xml .= '<Styles>' . "\n";

// Estilo por defecto
$xml .= ' <Style ss:ID="Default" ss:Name="Normal">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= ' </Style>' . "\n";

// Encabezado (fondo verde oscuro, texto blanco, negrita)
$xml .= ' <Style ss:ID="Header">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
$xml .= '  <Interior ss:Color="#548235" ss:Pattern="Solid"/>' . "\n";
$xml .= '  <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#375623"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#375623"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#375623"/>' . "\n";
$xml .= '   <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#375623"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";

// Datos normales
$xml .= ' <Style ss:ID="Data">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";

// Datos numéricos (alineados a la derecha)
$xml .= ' <Style ss:ID="DataNum">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= '  <Alignment ss:Horizontal="Right"/>' . "\n";
$xml .= '  <NumberFormat ss:Format="#,##0.00"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";

// Datos enteros centrados
$xml .= ' <Style ss:ID="DataInt">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= '  <Alignment ss:Horizontal="Center"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E2F3"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";

$xml .= '</Styles>' . "\n";

// Hoja de cálculo
$xml .= '<Worksheet ss:Name="Facturas">' . "\n";
$xml .= ' <Table>' . "\n";

// Anchos de columna (A-L)
$anchos = [100, 130, 90, 90, 100, 110, 100, 110, 85, 80, 90, 180];
foreach ($anchos as $ancho) {
    $xml .= '  <Column ss:AutoFitWidth="0" ss:Width="' . $ancho . '"/>' . "\n";
}

// Fila de encabezados
$xml .= '  <Row ss:Height="30">' . "\n";
foreach ($columnas as $col) {
    $xml .= '   <Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($col) . '</Data></Cell>' . "\n";
}
$xml .= '  </Row>' . "\n";

// Filas de datos
foreach ($facturas as $f) {
    $importe = floatval($f['total'] ?? 0);
    // importe_imputable puede estar en otro campo o ser igual al total
    $imputable = floatval($f['importe_imputable'] ?? $f['total'] ?? 0);
    
    $xml .= '  <Row>' . "\n";
    
    // A: CIF
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($f['cif'] ?? '') . '</Data></Cell>' . "\n";
    
    // B: Nº FACTURA
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($f['numero_factura'] ?? '') . '</Data></Cell>' . "\n";
    
    // C: IMPORTE
    $xml .= '   <Cell ss:StyleID="DataNum"><Data ss:Type="Number">' . $importe . '</Data></Cell>' . "\n";
    
    // D: IMPUTABLE
    $xml .= '   <Cell ss:StyleID="DataNum"><Data ss:Type="Number">' . $imputable . '</Data></Cell>' . "\n";
    
    // E: FECHA (emisión)
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . formatFechaExport($f['fecha_emision'] ?? '') . '</Data></Cell>' . "\n";
    
    // F: FECHA INGRESO (pago)
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . formatFechaExport($f['fecha_pago'] ?? '') . '</Data></Cell>' . "\n";
    
    // G: REFERENCIA
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($f['referencia'] ?? '') . '</Data></Cell>' . "\n";
    
    // H: EXPEDIENTE
    $xml .= '   <Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($f['expediente'] ?? '') . '</Data></Cell>' . "\n";
    
    // I: Nº ACCIÓN
    $xml .= '   <Cell ss:StyleID="DataInt"><Data ss:Type="String">' . htmlspecialchars($f['num_accion'] ?? '') . '</Data></Cell>' . "\n";
    
    // J: Nº GRUPO
    $xml .= '   <Cell ss:StyleID="DataInt"><Data ss:Type="String">' . htmlspecialchars($f['num_grupo'] ?? '') . '</Data></Cell>' . "\n";
    
    // K: CONCEPTO
    $xml .= '   <Cell ss:StyleID="DataInt"><Data ss:Type="String">' . htmlspecialchars($f['concepto'] ?? '') . '</Data></Cell>' . "\n";
    
    // L: Nº LICENCIAS FACTURADAS
    $xml .= '   <Cell ss:StyleID="DataInt"><Data ss:Type="String">' . htmlspecialchars($f['unidades'] ?? '') . '</Data></Cell>' . "\n";
    
    $xml .= '  </Row>' . "\n";
}

$xml .= ' </Table>' . "\n";

// Configuración de impresión
$xml .= ' <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
$xml .= '  <FreezePanes/>' . "\n";
$xml .= '  <FrozenNoSplit/>' . "\n";
$xml .= '  <SplitHorizontal>1</SplitHorizontal>' . "\n";
$xml .= '  <TopRowBottomPane>1</TopRowBottomPane>' . "\n";
$xml .= ' </WorksheetOptions>' . "\n";

$xml .= '</Worksheet>' . "\n";
$xml .= '</Workbook>' . "\n";

// Nombre del archivo con fecha
$filename = 'facturas_export_' . date('Ymd_His') . '.xls';

// Headers para descarga
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xml));
header('Cache-Control: max-age=0');
header('Pragma: public');

echo $xml;
exit();
?>
