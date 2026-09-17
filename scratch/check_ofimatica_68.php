<?php
// scratch/check_ofimatica_68.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO MATRÍCULAS DE OFIMÁTICA EN LA INTRANET ===\n\n";

$stmt = $pdo->prepare("SELECT af.id as af_id, af.num_accion, af.titulo, af.id_plataforma, g.id as grupo_id, g.numero_grupo
                       FROM acciones_formativas af
                       JOIN grupos g ON g.accion_id = af.id
                       WHERE af.titulo LIKE '%Ofim% ' OR af.titulo LIKE '%ADGG057PO%' OR af.id_plataforma = 68");
$stmt->execute();
$afRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($afRows as $af) {
    echo "AF ID {$af['af_id']} | {$af['num_accion']} - {$af['titulo']} | id_plataforma: {$af['id_plataforma']} | Grupo Intranet ID {$af['grupo_id']} (G{$af['numero_grupo']})\n";
    
    $stmtM = $pdo->prepare("SELECT m.id, m.alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.moodle_user_id,
                                   m.moodle_first_access, m.moodle_last_access, m.moodle_connected_time, m.moodle_progress,
                                   m.moodle_m1_completed, m.moodle_m2_completed, m.moodle_m3_completed,
                                   m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, m.moodle_final_grade, m.moodle_aptitud, m.moodle_last_sync
                            FROM matriculas m
                            JOIN alumnos a ON m.alumno_id = a.id
                            WHERE m.grupo_id = ?");
    $stmtM->execute([$af['grupo_id']]);
    $mats = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Total matriculados en este grupo: " . count($mats) . "\n";
    foreach ($mats as $m) {
        echo "  - Alumno ID {$m['alumno_id']}: {$m['nombre']} {$m['primer_apellido']} | DNI: {$m['dni']} | Moodle UID: {$m['moodle_user_id']}\n";
        echo "    Prim: " . ($m['moodle_first_access'] ?: 'null') . " | Últ: " . ($m['moodle_last_access'] ?: 'null') . " | Connected sec: {$m['moodle_connected_time']} | Progress: {$m['moodle_progress']}%\n";
        echo "    E1: {$m['moodle_e1_grade']} | E2: {$m['moodle_e2_grade']} | E3: {$m['moodle_e3_grade']} | Final: {$m['moodle_final_grade']} | Aptitud: {$m['moodle_aptitud']} | Last sync: {$m['moodle_last_sync']}\n";
    }
}
