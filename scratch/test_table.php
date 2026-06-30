<?php
// scratch/test_table.php
require_once '../includes/config.php';

$where = ["1=1"];
$params = [];

$sql = "SELECT m.*, a.nombre as alumno_nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.provincia, a.pref_presencial,
               c.nombre as convocatoria_nombre,
               (SELECT pl.nombre FROM planes pl WHERE pl.convocatoria_id = c.id ORDER BY pl.id ASC LIMIT 1) as plan_nombre,
               e.nombre as empresa_nombre, e.sector as empresa_sector,
               g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_mitad as grupo_mitad, g.fecha_fin as grupo_fin,
               af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, cu.nombre_corto as curso_nombre,
               u_com.nombre as comercial_nombre, u_com.apellidos as comercial_apellidos,
               COALESCE(af.modalidad, g.modalidad) as modalidad_real
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
        LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos cu ON af.curso_id = cu.id
        LEFT JOIN usuarios u_com ON m.comercial_id = u_com.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY m.id DESC
        LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total Results: " . count($resultados) . "<br><br>";
    echo "<table border='1'>";
    echo "<thead><tr><th>Index</th><th>ID</th><th>Plan</th><th>Alumno</th><th>DNI</th><th>Empresa</th></tr></thead>";
    echo "<tbody>";
    foreach ($resultados as $idx => $res) {
        echo "<tr>";
        echo "<td>" . $idx . "</td>";
        echo "<td>" . htmlspecialchars($res['id']) . "</td>";
        echo "<td>" . htmlspecialchars($res['plan_nombre'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($res['primer_apellido'] . ' ' . $res['segundo_apellido'] . ', ' . $res['alumno_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($res['dni'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($res['empresa_nombre'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
