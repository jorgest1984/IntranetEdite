<?php
// Bypass auth redirect
define('NO_AUTH_CHECK', true);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=pre_intranet_formacion;charset=utf8mb4", "pre_gestion", "Oy0v?ggswFBr6d0~");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, m.id as matricula_id,
               a.*,
               c.nombre as convocatoria_nombre, c.codigo_expediente,
               p.nombre as plan_nombre, 
               e.nombre as empresa_nombre,
               g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_fin as grupo_fin,
               af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, 
               cu.nombre_corto as curso_nombre, cu.titulo as curso_titulo
        FROM matriculas m
        LEFT JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
        LEFT JOIN planes p ON c.id = p.convocatoria_id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos cu ON af.curso_id = cu.id
        LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
        WHERE m.id = ?
    ");
    $stmt->execute([47]);
    echo "SUCCESS\n";
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "SQL ERROR: " . $e->getMessage();
}
