<?php
// scratch/check_gisela_remote.php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

echo "=== DIAGNÓSTICO ALUMNA GISELA JUBANY ANIORTE ===\n\n";

// 1. Buscar alumno
try {
    $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE nombre LIKE '%Gisela%' OR primer_apellido LIKE '%Jubany%' OR segundo_apellido LIKE '%Jubany%'");
    $stmt->execute();
    $alumnos = $stmt->fetchAll();
    echo "--- ALUMNOS ENCONTRADOS (" . count($alumnos) . ") ---\n";
    print_r($alumnos);

    // 2. Buscar matrículas de Gisela
    foreach ($alumnos as $al) {
        echo "\n--- MATRICULAS ALUMNO ID: {$al['id']} (moodle_user_id: " . ($al['moodle_user_id'] ?? 'NULL') . ") ---\n";
        $stmtM = $pdo->prepare("
            SELECT m.*, g.numero_grupo, g.id_plataforma as grupo_plataforma, af.id as accion_id, af.titulo, af.num_accion, af.abreviatura, af.id_plataforma as af_plataforma
            FROM matriculas m
            JOIN grupos g ON m.grupo_id = g.id
            JOIN acciones_formativas af ON g.accion_id = af.id
            WHERE m.alumno_id = ?
        ");
        $stmtM->execute([$al['id']]);
        $mats = $stmtM->fetchAll();
        print_r($mats);
    }
} catch (Exception $e) {
    echo "Error Alumnos/Matriculas: " . $e->getMessage() . "\n";
}

// 3. Buscar acciones formativas / cursos relacionados con COMM0023 o Instagram
echo "\n--- ACCIONES FORMATIVAS (COMM0023 / Instagram) ---\n";
try {
    $stmtC = $pdo->prepare("
        SELECT af.*, g.id as grupo_id, g.numero_grupo, g.id_plataforma as grupo_plataforma
        FROM acciones_formativas af
        LEFT JOIN grupos g ON g.accion_id = af.id
        WHERE af.num_accion LIKE '%COMM0023%' OR af.abreviatura LIKE '%COMM0023%' OR af.titulo LIKE '%Instagram%'
    ");
    $stmtC->execute();
    $cursos = $stmtC->fetchAll();
    print_r($cursos);
} catch (Exception $e) {
    echo "Error Acciones: " . $e->getMessage() . "\n";
}

// 4. Buscar en cursos table
echo "\n--- TABLA CURSOS (COMM0023 / Instagram) ---\n";
try {
    $stmtC2 = $pdo->prepare("
        SELECT * FROM cursos WHERE nombre_curso LIKE '%Instagram%' OR codigo_curso LIKE '%COMM0023%'
    ");
    $stmtC2->execute();
    print_r($stmtC2->fetchAll());
} catch (Exception $e) {
    echo "Error Cursos: " . $e->getMessage() . "\n";
}

// 5. Moodle DB
if (defined('MOODLE_DB_HOST')) {
    echo "\n--- MOODLE DB SEARCH ---\n";
    try {
        $moodle_pdo = new PDO("mysql:host=".MOODLE_DB_HOST.";dbname=".MOODLE_DB_NAME.";charset=utf8mb4", MOODLE_DB_USER, MOODLE_DB_PASS);
        $moodle_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $moodle_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $prefix = MOODLE_DB_PREFIX;
        $stmtUsr = $moodle_pdo->prepare("SELECT id, username, firstname, lastname, email FROM {$prefix}user WHERE firstname LIKE '%Gisela%' OR lastname LIKE '%Jubany%'");
        $stmtUsr->execute();
        $moodle_users = $stmtUsr->fetchAll();
        echo "Moodle users:\n";
        print_r($moodle_users);

        $stmtCrs = $moodle_pdo->prepare("SELECT id, shortname, fullname FROM {$prefix}course WHERE shortname LIKE '%COMM0023%' OR fullname LIKE '%Instagram%'");
        $stmtCrs->execute();
        $moodle_courses = $stmtCrs->fetchAll();
        echo "Moodle courses:\n";
        print_r($moodle_courses);

    } catch (Exception $e) {
        echo "Error Moodle DB: " . $e->getMessage() . "\n";
    }
}

