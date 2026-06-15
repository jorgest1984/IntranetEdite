<?php
// scratch/migrate_matriculas_moodle_fields.php
require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "--- INICIO DE MIGRACIÓN: MATRICULAS MOODLE FIELDS (M1-M3 / E1-E3) ---\n";

try {
    // Obtener las columnas existentes de la tabla 'matriculas'
    $stmt = $pdo->query("DESCRIBE matriculas");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $fieldsToAdd = [
        'moodle_first_access'    => "DATETIME NULL DEFAULT NULL",
        'moodle_last_access'     => "DATETIME NULL DEFAULT NULL",
        'moodle_connected_time'  => "INT DEFAULT 0 COMMENT 'Tiempo de conexion en segundos'",
        'moodle_progress'        => "INT DEFAULT 0 COMMENT 'Progreso en porcentaje'",
        'moodle_m1_completed'    => "TINYINT DEFAULT 0 COMMENT 'Visualización Módulo 1'",
        'moodle_m2_completed'    => "TINYINT DEFAULT 0 COMMENT 'Visualización Módulo 2'",
        'moodle_m3_completed'    => "TINYINT DEFAULT 0 COMMENT 'Visualización Módulo 3'",
        'moodle_e1_completed'    => "TINYINT DEFAULT 0 COMMENT 'Evaluacion Inicial Realizada'",
        'moodle_e2_completed'    => "TINYINT DEFAULT 0 COMMENT 'Evaluacion Intermedia Realizada'",
        'moodle_e3_completed'    => "TINYINT DEFAULT 0 COMMENT 'Evaluacion Final Realizada'",
        'moodle_e1_grade'        => "DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'Nota Evaluacion Inicial'",
        'moodle_e2_grade'        => "DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'Nota Evaluacion Intermedia'",
        'moodle_e3_grade'        => "DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'Nota Evaluacion Final'",
        'moodle_final_grade'     => "DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'Nota Media E2 y E3'",
        'moodle_aptitud'         => "VARCHAR(20) DEFAULT NULL COMMENT 'APTO, NO APTO o PENDIENTE'",
        'moodle_last_sync'       => "DATETIME NULL DEFAULT NULL"
    ];

    foreach ($fieldsToAdd as $fieldName => $definition) {
        if (!in_array($fieldName, $columns)) {
            $sql = "ALTER TABLE matriculas ADD COLUMN `$fieldName` $definition";
            $pdo->exec($sql);
            echo "✅ Columna '$fieldName' añadida correctamente.\n";
        } else {
            echo "ℹ️ La columna '$fieldName' ya existe.\n";
        }
    }

    echo "\n🎉 ¡MIGRACIÓN COMPLETADA CON ÉXITO!\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR EN MIGRACIÓN:\n" . $e->getMessage() . "\n";
}
echo "--- FIN DE MIGRACIÓN ---\n";
?>
