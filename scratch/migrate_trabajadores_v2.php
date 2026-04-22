<?php
// migrate_trabajadores_v2.php
require_once 'includes/config.php';

$queries = [
    "ALTER TABLE profesorado_detalles ADD COLUMN dni VARCHAR(20) DEFAULT NULL AFTER usuario_id",
    "ALTER TABLE profesorado_detalles ADD COLUMN fecha_nacimiento DATE DEFAULT NULL AFTER dni",
    "ALTER TABLE profesorado_detalles ADD COLUMN apellido1 VARCHAR(100) DEFAULT NULL AFTER fecha_nacimiento",
    "ALTER TABLE profesorado_detalles ADD COLUMN apellido2 VARCHAR(100) DEFAULT NULL AFTER apellido1",
    "ALTER TABLE profesorado_detalles ADD COLUMN cuenta_bancaria VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN telefono VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN telefono_empresa VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN skype VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN sexo ENUM('Hombre', 'Mujer', 'Otro') DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN activo_hasta DATE DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN nuestro TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN observaciones_personales TEXT DEFAULT NULL"
];

foreach ($queries as $sql) {
    try {
        echo "Executing: $sql ... ";
        $pdo->query($sql);
        echo "OK\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
echo "\nMigration v2 complete.\n";
