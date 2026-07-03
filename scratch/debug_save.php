<?php
require 'includes/config.php';
$rows = $pdo->query('
    SELECT m.id, m.alumno_id, a.nombre, a.primer_apellido, m.grupo_id, g.accion_id, af.curso_id, af.num_accion, af.abreviatura 
    FROM matriculas m 
    LEFT JOIN alumnos a ON m.alumno_id = a.id 
    LEFT JOIN grupos g ON m.grupo_id = g.id 
    LEFT JOIN acciones_formativas af ON g.accion_id = af.id 
    ORDER BY m.id DESC LIMIT 5
')->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
