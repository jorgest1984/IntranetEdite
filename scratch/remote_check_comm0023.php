<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== BUSCANDO ACCION FORMATIVA COMM0023 / ACCION 5 / EXPEDIENTE F241978AA ===\n\n";

$stmt = $pdo->query("
    SELECT af.*, c.nombre_corto as curso_codigo, c.nombre_largo as curso_titulo, c.moodle_id as curso_moodle_id
    FROM acciones_formativas af
    LEFT JOIN cursos c ON af.curso_id = c.id
    WHERE af.num_accion = '5' OR af.titulo LIKE '%INSTAGRAM%' OR af.abreviatura LIKE '%COMM0023%' OR af.titulo LIKE '%COMM0023%'
");
$acciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Acciones formativas encontradas: " . count($acciones) . "\n";
print_r($acciones);

foreach ($acciones as $af) {
    echo "\n----------------------------------------------------\n";
    echo "ACCION ID: {$af['id']} | Abreviatura: {$af['abreviatura']} | Titulo: {$af['titulo']} | id_plataforma: {$af['id_plataforma']} | num_accion: {$af['num_accion']}\n";
    
    // Grupos
    $stmtG = $pdo->prepare("SELECT * FROM grupos WHERE accion_id = ?");
    $stmtG->execute([$af['id']]);
    $grupos = $stmtG->fetchAll(PDO::FETCH_ASSOC);
    echo "Grupos (" . count($grupos) . "):\n";
    print_r($grupos);
    
    foreach ($grupos as $g) {
        $stmtM = $pdo->prepare("
            SELECT m.id as matricula_id, m.alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.moodle_user_id,
                   m.moodle_connected_time, m.moodle_progress, m.moodle_last_sync, m.moodle_e1_grade, m.moodle_final_grade
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            WHERE m.grupo_id = ?
        ");
        $stmtM->execute([$g['id']]);
        $matriculas = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        echo "  Grupo ID {$g['id']} (Nº Grupo: {$g['numero_grupo']}, Expediente: {$g['expediente']}) -> Matriculas: " . count($matriculas) . "\n";
        print_r($matriculas);
    }
}

// Ahora consultar en Moodle DB por el curso
echo "\n=== BUSCANDO EN MOODLE DB POR CURSOS QUE CONTENGAN 'INSTAGRAM' O 'COMM0023' ===\n";
$moodleDb = new MoodleDB();
if ($moodleDb->isConnected()) {
    $mpdo = $moodleDb->getPDO();
    $prefix = $moodleDb->getTablePrefix();
    
    $stmtMC = $mpdo->query("SELECT id, fullname, shortname, idnumber FROM {$prefix}course WHERE fullname LIKE '%INSTAGRAM%' OR shortname LIKE '%COMM0023%' OR fullname LIKE '%COMM0023%'");
    $moodleCourses = $stmtMC->fetchAll(PDO::FETCH_ASSOC);
    echo "Cursos Moodle encontrados:\n";
    print_r($moodleCourses);
} else {
    echo "No se pudo conectar a Moodle DB: " . $moodleDb->getError() . "\n";
}
