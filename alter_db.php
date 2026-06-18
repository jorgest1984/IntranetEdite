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

    echo json_encode(['success' => true, 'added_columns' => $added]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
