<?php
// migrate_trabajadores.php
require_once 'includes/config.php';

$queries = [
    "ALTER TABLE profesorado_detalles ADD COLUMN alias VARCHAR(100) DEFAULT NULL AFTER alumno_id",
    "ALTER TABLE profesorado_detalles ADD COLUMN num_ss VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN omitir_ss TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN profesion VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN discapacitado TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN estudios VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN tipo_via VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN nombre_via VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN tipo_numeracion VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN num_domicilio VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN calificador_num VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN bloque VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN portal VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN escalera VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN planta VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN puerta VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN complemento TEXT DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN cp_trabajador VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN localidad_trabajador VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN provincia_trabajador VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN mananas_desde TIME DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN mananas_hasta TIME DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN tardes_desde TIME DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN tardes_hasta TIME DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN solo_los VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN email2 VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN ultima_empresa VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN centro_trabajo VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN enviar_emails TINYINT(1) DEFAULT 1",
    "ALTER TABLE profesorado_detalles ADD COLUMN usuario_plataforma VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN clave_plataforma VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN bloqueado TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN restringido TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN baja TINYINT(1) DEFAULT 0",
    "ALTER TABLE profesorado_detalles ADD COLUMN entrega_atencion_de VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN entrega_domicilio TEXT DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN entrega_cp VARCHAR(10) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN entrega_localidad VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN entrega_provincia VARCHAR(150) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN modulacion VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE profesorado_detalles ADD COLUMN horarios_pref VARCHAR(255) DEFAULT NULL"
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
echo "\nMigration complete.\n";
?>
