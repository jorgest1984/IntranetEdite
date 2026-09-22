<?php
// scratch/fix_comm0023_and_sync.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

echo "=== FIXING COMM0023 PLATFORM IDs AND SYNCING ===\n\n";

try {
    // 1. Update acciones_formativas for COMM0023
    $stmt1 = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = 48 WHERE id = 22 OR abreviatura = 'COMM0023'");
    $stmt1->execute();
    echo "Acciones formativas actualizadas: " . $stmt1->rowCount() . " filas.\n";

    // 2. Query cursos for moodle_id = 48
    $stmt2 = $pdo->prepare("SELECT * FROM cursos WHERE moodle_id = 48 OR id = 58");
    $stmt2->execute();
    echo "Cursos encontrados:\n";
    print_r($stmt2->fetchAll());

    // 3. Test encuesta lookup for Gisela with id_curso=48 and id_alumno=1142
    $id_alumno = 1142;
    $id_curso = 48;

    $stmtTest = $pdo->prepare("
        SELECT m.id as matricula_id, af.id as accion_id, af.titulo as curso_nombre, af.abreviatura, af.duracion,
               af.modalidad, af.num_accion, g.numero_grupo, g.fecha_inicio, g.fecha_fin,
               co.codigo_expediente,
               a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.sexo, a.email
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c ON af.curso_id = c.id
        LEFT JOIN planes pl ON af.plan_id = pl.id
        LEFT JOIN convocatorias co ON pl.convocatoria_id = co.id
        WHERE (a.moodle_user_id = :id_alumno OR a.id = :id_alumno)
          AND (
              af.id_plataforma = :id_curso
              OR af.id = :id_curso
              OR (c.id IS NOT NULL AND c.moodle_id = :id_curso)
              OR (c.id IS NOT NULL AND c.id = :id_curso)
              OR g.id_plataforma = :id_curso
              OR g.id = :id_curso
          )
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $stmtTest->execute([':id_alumno' => $id_alumno, ':id_curso' => $id_curso]);
    $mat = $stmtTest->fetch();

    echo "\n--- PRUEBA DE MATRÍCULA PARA GISELA (id_curso=48, id_alumno=1142) ---\n";
    if ($mat) {
        echo "¡ÉXITO! Matrícula encontrada:\n";
        print_r($mat);
    } else {
        echo "ERROR: No se encontró la matrícula.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
