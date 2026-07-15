<?php
// excel_informe_tutorias.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR])) {
    die("Acceso denegado.");
}

$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : 0;
$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;

if (!$accion_id || !$grupo_id) {
    die("Faltan parámetros requeridos (Acción Formativa y Grupo).");
}

// 1. Obtener datos de la cabecera (Convocatoria, Acción, Grupo, Especialidad)
$stmtHeader = $pdo->prepare("
    SELECT 
        c.codigo_expediente as expediente,
        af.num_accion as accion,
        g.numero_grupo as grupo,
        af.titulo as especialidad
    FROM grupos g
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
    WHERE g.id = ? AND af.id = ?
");
$stmtHeader->execute([$grupo_id, $accion_id]);
$headerInfo = $stmtHeader->fetch(PDO::FETCH_ASSOC);

if (!$headerInfo) {
    die("No se encontró información del grupo seleccionado.");
}

// 2. Obtener el registro de tutorías ordenado alfabéticamente por alumno y cronológicamente
$stmtTutorias = $pdo->prepare("
    SELECT 
        a.primer_apellido, 
        a.segundo_apellido, 
        a.nombre, 
        a.dni, 
        ts.forma, 
        ts.fecha, 
        ts.hora, 
        ts.asunto, 
        ts.notas,
        u.nombre as nombre_tutor,
        u.apellidos as apellidos_tutor
    FROM tutorias_seguimiento ts
    JOIN alumnos a ON ts.alumno_id = a.id
    JOIN matriculas m ON m.alumno_id = a.id
    LEFT JOIN usuarios u ON ts.usuario_id = u.id
    WHERE ts.curso_id = ? AND m.grupo_id = ?
    ORDER BY a.primer_apellido ASC, a.segundo_apellido ASC, a.nombre ASC, ts.fecha ASC, ts.hora ASC
");
$stmtTutorias->execute([$accion_id, $grupo_id]);
$tutorias = $stmtTutorias->fetchAll(PDO::FETCH_ASSOC);

// Determinar el tutor principal (el que más llamadas ha hecho) o dejarlo en blanco si no hay
$tutor_principal = "";
if (count($tutorias) > 0) {
    $tutores_count = [];
    foreach ($tutorias as $t) {
        if (!empty($t['nombre_tutor'])) {
            $nombreCompleto = trim($t['nombre_tutor'] . ' ' . $t['apellidos_tutor']);
            if (!isset($tutores_count[$nombreCompleto])) $tutores_count[$nombreCompleto] = 0;
            $tutores_count[$nombreCompleto]++;
        }
    }
    if (!empty($tutores_count)) {
        arsort($tutores_count);
        $tutor_principal = array_key_first($tutores_count);
    }
}

// Generar descarga
$filename = "Informe_Tutorias_Accion_" . $headerInfo['accion'] . "_Grupo_" . $headerInfo['grupo'] . "_" . date('Ymd_Hi') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// HTML Output for Excel
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body { font-family: Calibri, sans-serif; }
        .title { font-size: 18pt; font-weight: bold; }
        .header-cell { font-weight: bold; }
        .table-header { background-color: #444444; color: white; font-weight: bold; text-align: center; border: 1px solid #000; }
        .table-cell { border: 1px solid #000; vertical-align: top; }
        .table-cell-center { border: 1px solid #000; text-align: center; vertical-align: top; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="8" class="title">Informe de tutorías - Formación a distancia</td>
        </tr>
        <tr><td colspan="8"></td></tr>
        <tr>
            <td class="header-cell">Expediente:</td>
            <td colspan="7"><?= htmlspecialchars($headerInfo['expediente'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="header-cell">Acción:</td>
            <td colspan="7"><?= htmlspecialchars($headerInfo['accion'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="header-cell">Grupo:</td>
            <td colspan="7"><?= htmlspecialchars($headerInfo['grupo'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="header-cell">Especialidad:</td>
            <td colspan="7"><?= htmlspecialchars($headerInfo['especialidad'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="header-cell">Tutor:</td>
            <td colspan="7"><?= htmlspecialchars($tutor_principal) ?></td>
        </tr>
        <tr><td colspan="8"></td></tr>
        
        <tr>
            <td colspan="4" class="table-header">Datos de los alumnos</td>
            <td colspan="4" class="table-header">Datos de tutorías</td>
        </tr>
        <tr>
            <td class="table-header">Nº</td>
            <td class="table-header">Apellidos</td>
            <td class="table-header">Nombre</td>
            <td class="table-header">NIF</td>
            <td class="table-header">Forma</td>
            <td class="table-header">Fecha</td>
            <td class="table-header">Asunto</td>
            <td class="table-header">Observaciones</td>
        </tr>
        
        <?php
        $current_alumno_dni = null;
        $counter = 0;
        
        foreach ($tutorias as $t) {
            $is_new_alumno = ($current_alumno_dni !== $t['dni']);
            
            if ($is_new_alumno) {
                $counter++;
                $current_alumno_dni = $t['dni'];
                $apellidos = mb_strtoupper($t['primer_apellido'] . ' ' . $t['segundo_apellido'], 'UTF-8');
                $nombre = mb_strtoupper($t['nombre'], 'UTF-8');
                $dni = mb_strtoupper($t['dni'], 'UTF-8');
                $num_str = $counter;
            } else {
                $apellidos = "";
                $nombre = "";
                $dni = "";
                $num_str = "";
            }
            
            $fecha_hora = date('d/m/Y', strtotime($t['fecha'])) . ' ' . date('H:i:s', strtotime($t['hora']));
            
            echo "<tr>";
            echo "<td class='table-cell-center'>" . $num_str . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars(trim($apellidos)) . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars(trim($nombre)) . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars(trim($dni)) . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars($t['forma'] ?? '') . "</td>";
            echo "<td class='table-cell-center'>" . htmlspecialchars($fecha_hora) . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars($t['asunto'] ?? '') . "</td>";
            echo "<td class='table-cell'>" . htmlspecialchars($t['notas'] ?? '') . "</td>";
            echo "</tr>";
        }
        
        if (count($tutorias) === 0) {
            echo "<tr><td colspan='8' class='table-cell-center'>No hay tutorías registradas para este grupo.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
