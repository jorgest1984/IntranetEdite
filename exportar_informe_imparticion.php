<?php
// exportar_informe_imparticion.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    die("No tiene permisos para exportar este informe.");
}

$convocatoria_id = intval($_GET['convocatoria_id'] ?? 0);
$plan_id = intval($_GET['plan_id'] ?? 0);

if (!$convocatoria_id) {
    die("Falta el ID de la convocatoria.");
}

// Fetch group data with action info and instructor details
$query = "
    SELECT 
        c.codigo_expediente as expediente,
        af.num_accion as accion,
        g.numero_grupo as grupo,
        CONCAT(prof.nombre, ' ', prof.primer_apellido) as tutor,
        g.fecha_inicio,
        g.fecha_fin,
        af.duracion as horas,
        (SELECT COUNT(*) FROM matriculas m WHERE m.convocatoria_id = c.id AND m.estado = 'Activo') as num_participantes,
        pt.mes_1, pt.mes_2, pt.mes_3, pt.mes_4, pt.mes_5, pt.mes_6, 
        pt.mes_7, pt.mes_8, pt.mes_9, pt.mes_10, pt.mes_11, pt.mes_12
    FROM grupos g
    INNER JOIN acciones_formativas af ON g.accion_id = af.id
    INNER JOIN convocatorias c ON af.plan_id IN (SELECT id FROM planes WHERE convocatoria_id = ?)
    LEFT JOIN alumnos prof ON g.tutor_id = prof.id
    LEFT JOIN prof_tareas pt ON pt.expediente_id = c.id AND pt.num_accion = af.num_accion AND pt.num_grupo = g.numero_grupo
    WHERE c.id = ?
";
$params = [$convocatoria_id, $convocatoria_id];

if ($plan_id) {
    $query .= " AND af.plan_id = ?";
    $params[] = $plan_id;
}

$query .= " ORDER BY af.num_accion ASC, g.numero_grupo ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al consultar datos: " . $e->getMessage());
}

$month_names = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="informe_imparticion_' . date('Ymd') . '.xls"');

echo '<?xml version="1.0"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Header">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Cell">
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="Date">
   <NumberFormat ss:Format="Short Date"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  <Style ss:ID="Total">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
   <Exterior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
 </Styles>
 <Worksheet ss:Name="Informe Imparticion">
  <Table>
   <Column ss:Width="80"/> <!-- Expediente -->
   <Column ss:Width="50"/> <!-- Accion -->
   <Column ss:Width="50"/> <!-- Grupo -->
   <Column ss:Width="150"/> <!-- Tutor -->
   <Column ss:Width="80"/> <!-- F. Inicio -->
   <Column ss:Width="80"/> <!-- F. Fin -->
   <Column ss:Width="60"/> <!-- N Horas -->
   <Column ss:Width="80"/> <!-- N Part -->
   <Column ss:Width="80"/> <!-- Mes 1 Label -->
   <Column ss:Width="60"/> <!-- Mes 1 Value -->
   <Column ss:Width="80"/> <!-- Mes 2 Label -->
   <Column ss:Width="60"/> <!-- Mes 2 Value -->
   <Column ss:Width="80"/> <!-- Mes 3 Label -->
   <Column ss:Width="60"/> <!-- Mes 3 Value -->
   <Column ss:Width="80"/> <!-- Mes 4 Label -->
   <Column ss:Width="60"/> <!-- Mes 4 Value -->
   <Column ss:Width="60"/> <!-- Total -->
   
   <Row ss:AutoFitHeight="0" ss:Height="25">
    <Cell ss:StyleID="Header"><Data ss:Type="String">Expediente</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Accion</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Grupo</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tutor/a</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Fecha Inicio</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Fecha Fin</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nº Horas</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nº Participantes</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Mes 1</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Horas mes 1</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Mes 2</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Horas mes 2</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Mes 3</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Horas mes 3</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Mes 4</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Horas mes 4</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Total</Data></Cell>
   </Row>

   <?php foreach ($data as $row): 
       $start_date = new DateTime($row['fecha_inicio'] ?? 'now');
       $start_month = (int)$start_date->format('n');
       
       $months_data = [];
       $total_period = 0;
       
       for ($i = 0; $i < 4; $i++) {
           $current_m = (($start_month + $i - 1) % 12) + 1;
           $col_name = "mes_" . $current_m;
           $hours = floatval($row[$col_name] ?? 0);
           $months_data[] = [
               'label' => $month_names[$current_m],
               'value' => $hours
           ];
           $total_period += $hours;
       }
   ?>
   <Row>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= htmlspecialchars($row['expediente']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number"><?= intval($row['accion']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number"><?= intval($row['grupo']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= htmlspecialchars($row['tutor'] ?: '') ?></Data></Cell>
    <Cell ss:StyleID="Date"><Data ss:Type="DateTime"><?= date('Y-m-dT00:00:00.000', strtotime($row['fecha_inicio'])) ?></Data></Cell>
    <Cell ss:StyleID="Date"><Data ss:Type="DateTime"><?= date('Y-m-dT00:00:00.000', strtotime($row['fecha_fin'])) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number"><?= floatval($row['horas']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="Number"><?= intval($row['num_participantes']) ?></Data></Cell>
    
    <?php foreach ($months_data as $m): ?>
        <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= $m['label'] ?></Data></Cell>
        <Cell ss:StyleID="Cell"><Data ss:Type="Number"><?= $m['value'] ?></Data></Cell>
    <?php endforeach; ?>
    
    <Cell ss:StyleID="Total"><Data ss:Type="Number"><?= $total_period ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
 </Worksheet>
</Workbook>
