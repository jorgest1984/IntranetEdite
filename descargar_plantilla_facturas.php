<?php
// descargar_plantilla_facturas.php - Genera y descarga la plantilla Excel para importación de facturas
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

// Columnas de la plantilla
$columnas = [
    'CIF',
    'num_factura',
    'importe',
    'importe_imputable',
    'fecha_emision',
    'fecha_pago',
    'referencia',
    'expediente',
    'num_accion',
    'num_grupo',
    'concepto',
    'unidades',
    'tipo_imputacion'
];

// Datos de ejemplo
$ejemplo = [
    'B04629366',
    '036/2019',
    '196.20',
    '196.20',
    '17/07/2019',
    '19/07/2019',
    'FD010',
    'F1603',
    '1',
    '1',
    'Material formativo',
    '1',
    'Costes directos'
];

// Generar archivo Excel (formato XML Spreadsheet 2003, compatible con Excel)
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
$xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
$xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
$xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// Estilos
$xml .= '<Styles>' . "\n";
$xml .= ' <Style ss:ID="Default" ss:Name="Normal">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= ' </Style>' . "\n";
$xml .= ' <Style ss:ID="Header">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
$xml .= '  <Interior ss:Color="#1E293B" ss:Pattern="Solid"/>' . "\n";
$xml .= '  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
$xml .= '   <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";
$xml .= ' <Style ss:ID="Example">' . "\n";
$xml .= '  <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#64748B" ss:Italic="1"/>' . "\n";
$xml .= '  <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>' . "\n";
$xml .= '  <Borders>' . "\n";
$xml .= '   <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>' . "\n";
$xml .= '   <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>' . "\n";
$xml .= '   <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>' . "\n";
$xml .= '  </Borders>' . "\n";
$xml .= ' </Style>' . "\n";
$xml .= '</Styles>' . "\n";

// Hoja
$xml .= '<Worksheet ss:Name="Plantilla Facturas">' . "\n";
$xml .= ' <Table ss:DefaultColumnWidth="100">' . "\n";

// Anchos de columna
$anchos = [100, 120, 90, 110, 100, 100, 100, 100, 90, 90, 200, 80, 120];
foreach ($anchos as $ancho) {
    $xml .= '  <Column ss:AutoFitWidth="0" ss:Width="' . $ancho . '"/>' . "\n";
}

// Fila de encabezados
$xml .= '  <Row>' . "\n";
foreach ($columnas as $col) {
    $xml .= '   <Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($col) . '</Data></Cell>' . "\n";
}
$xml .= '  </Row>' . "\n";

// Fila de ejemplo
$xml .= '  <Row>' . "\n";
foreach ($ejemplo as $val) {
    $xml .= '   <Cell ss:StyleID="Example"><Data ss:Type="String">' . htmlspecialchars($val) . '</Data></Cell>' . "\n";
}
$xml .= '  </Row>' . "\n";

$xml .= ' </Table>' . "\n";
$xml .= '</Worksheet>' . "\n";
$xml .= '</Workbook>' . "\n";

// Enviar como descarga
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="plantilla_importacion_facturas.xls"');
header('Content-Length: ' . strlen($xml));
header('Cache-Control: max-age=0');

echo $xml;
exit();
?>
