<?php
// scratch/migrate_add_empresas_fields.php
require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "--- INICIO DE MIGRACIÓN: EMPRESAS FIELDS ---\n";

try {
    // Obtener las columnas existentes de la tabla 'empresas'
    $stmt = $pdo->query("DESCRIBE empresas");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $fieldsToAdd = [
        'cp'              => "VARCHAR(10) NULL DEFAULT NULL COMMENT 'Código Postal'",
        'actividad'       => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Actividad'",
        'es_adhesion'     => "TINYINT(1) DEFAULT 0 COMMENT 'Es adhesión'",
        'es_gestora'      => "TINYINT(1) DEFAULT 0 COMMENT 'Es gestora'",
        'es_mercadolid'   => "TINYINT(1) DEFAULT 0 COMMENT 'Es Mercadolid'",
        'comercial_id'    => "INT(11) NULL DEFAULT NULL COMMENT 'ID de Comercial'",
        'sector'          => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Sector'",
        'rlt'             => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'RLT'"
    ];

    foreach ($fieldsToAdd as $fieldName => $definition) {
        if (!in_array($fieldName, $columns)) {
            $sql = "ALTER TABLE empresas ADD COLUMN `$fieldName` $definition";
            $pdo->exec($sql);
            echo "✅ Columna '$fieldName' añadida correctamente.\n";
        } else {
            echo "ℹ️ La columna '$fieldName' ya existe.\n";
        }
    }

    echo "\n🎉 ¡MIGRACIÓN DE EMPRESAS COMPLETADA CON ÉXITO!\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR EN MIGRACIÓN:\n" . $e->getMessage() . "\n";
}
echo "--- FIN DE MIGRACIÓN ---\n";
?>
