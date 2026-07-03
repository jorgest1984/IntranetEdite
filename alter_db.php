<?php
require_once 'includes/config.php';

try {
    $cols = [
        'moodle_first_access' => 'DATETIME NULL',
        'moodle_last_access' => 'DATETIME NULL',
        'moodle_connected_time' => 'INT DEFAULT 0',
        'moodle_progress' => 'INT DEFAULT 0',
        'moodle_last_sync' => 'DATETIME NULL',
        'moodle_m1_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_m2_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_m3_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_e1_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_e2_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_e3_completed' => 'TINYINT(1) DEFAULT 0',
        'moodle_e1_grade' => 'DECIMAL(5,2) NULL',
        'moodle_e2_grade' => 'DECIMAL(5,2) NULL',
        'moodle_e3_grade' => 'DECIMAL(5,2) NULL',
        'moodle_final_grade' => 'DECIMAL(5,2) NULL',
        'moodle_aptitud' => 'VARCHAR(20) DEFAULT "PENDIENTE"'
    ];

    $stmt = $pdo->query("DESCRIBE matriculas");
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $added = [];
    foreach ($cols as $col => $def) {
        if (!in_array($col, $existing)) {
            $pdo->exec("ALTER TABLE matriculas ADD COLUMN `$col` $def");
            $added[] = $col;
        }
    }
    
    // Alumnos table missing moodle_user_id?
    $stmt2 = $pdo->query("DESCRIBE alumnos");
    $existing2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('moodle_user_id', $existing2)) {
        $pdo->exec("ALTER TABLE alumnos ADD COLUMN `moodle_user_id` INT NULL");
        $added[] = 'alumnos.moodle_user_id';
    }

    // Encuestas table check and creation/update
    $stmt3 = $pdo->query("SHOW TABLES LIKE 'encuestas_resultados'");
    $tableExists = $stmt3->rowCount() > 0;
    $needRecreate = false;
    
    if ($tableExists) {
        $stmtCol = $pdo->query("DESCRIBE encuestas_resultados");
        $cols = $stmtCol->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('residencia_provincia', $cols)) {
            $needRecreate = true;
        }
    } else {
        $needRecreate = true;
    }

    if ($needRecreate) {
        $pdo->exec("DROP TABLE IF EXISTS encuestas_resultados");
        $pdo->exec("CREATE TABLE encuestas_resultados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matricula_id INT NOT NULL,
            edad INT NULL,
            sexo VARCHAR(10) NULL,
            titulacion VARCHAR(10) NULL,
            otra_titulacion VARCHAR(10) NULL,
            otra_titulacion_txt VARCHAR(255) NULL,
            situacion_laboral VARCHAR(10) NULL,
            residencia_provincia VARCHAR(100) NULL,
            trabajo_provincia VARCHAR(100) NULL,
            como_conocio VARCHAR(10) NULL,
            como_conocio_txt VARCHAR(255) NULL,
            categoria_profesional VARCHAR(10) NULL,
            categoria_profesional_txt VARCHAR(255) NULL,
            horario_curso VARCHAR(10) NULL,
            jornada_porcentaje VARCHAR(10) NULL,
            tamano_empresa VARCHAR(10) NULL,
            p1_1 INT NULL,
            p1_2 INT NULL,
            p2_1 INT NULL,
            p2_2 INT NULL,
            p3_1 INT NULL,
            p3_2 INT NULL,
            p4_1_f INT NULL,
            p4_2_f INT NULL,
            p4_1_t INT NULL,
            p4_2_t INT NULL,
            p5_1 INT NULL,
            p5_2 INT NULL,
            p6_1 INT NULL,
            p6_2 INT NULL,
            p7_1 INT NULL,
            p7_2 INT NULL,
            p8_1 VARCHAR(10) NULL,
            p8_2 VARCHAR(10) NULL,
            p9_1 INT NULL,
            p9_2 INT NULL,
            p9_3 INT NULL,
            p9_4 INT NULL,
            p9_5 INT NULL,
            p10_1 INT NULL,
            comentarios TEXT NULL,
            p12_1 INT NULL,
            p12_2 VARCHAR(10) NULL,
            p12_3 INT NULL,
            p12_4 INT NULL,
            p12_5 TEXT NULL,
            fecha_realizacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $added[] = 'encuestas_resultados (recreated with all Fundae columns)';
    }

    echo json_encode(['success' => true, 'added_columns' => $added]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
