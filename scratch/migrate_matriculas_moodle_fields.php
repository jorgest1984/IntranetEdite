<?php
// scratch/migrate_matriculas_moodle_fields.php
require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "--- INICIO DE MIGRACIÓN: MATRICULAS MOODLE FIELDS ---\n";

try {
    // Obtener las columnas existentes de la tabla 'matriculas'
    $stmt = $pdo->query("DESCRIBE matriculas");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $fieldsToAdd = [
        'moodle_first_access'  => "DATETIME NULL DEFAULT NULL",
        'moodle_last_access'   => "DATETIME NULL DEFAULT NULL",
        'moodle_connected_time' => "INT DEFAULT 0 COMMENT 'Tiempo de conexion en segundos'",
        'moodle_progress'       => "INT DEFAULT 0 COMMENT 'Progreso en porcentaje'",
        'moodle_last_sync'      => "DATETIME NULL DEFAULT NULL"
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
