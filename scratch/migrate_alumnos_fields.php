<?php
// migrate_alumnos_fields.php
require_once 'includes/config.php';

$sql = "ALTER TABLE alumnos 
    ADD COLUMN comercial_id INT DEFAULT NULL,
    ADD COLUMN bloqueado TINYINT(1) DEFAULT 0,
    ADD COLUMN restringido TINYINT(1) DEFAULT 0,
    ADD COLUMN baja TINYINT(1) DEFAULT 0,
    ADD COLUMN alias VARCHAR(100) DEFAULT NULL,
    ADD COLUMN profesion VARCHAR(150) DEFAULT NULL,
    ADD COLUMN estudios VARCHAR(100) DEFAULT NULL,
    ADD COLUMN tipo_via VARCHAR(50) DEFAULT NULL,
    ADD COLUMN nombre_via VARCHAR(150) DEFAULT NULL,
    ADD COLUMN tipo_num VARCHAR(50) DEFAULT NULL,
    ADD COLUMN num_domicilio VARCHAR(20) DEFAULT NULL,
    ADD COLUMN calificador VARCHAR(50) DEFAULT NULL,
    ADD COLUMN bloque VARCHAR(20) DEFAULT NULL,
    ADD COLUMN portal VARCHAR(20) DEFAULT NULL,
    ADD COLUMN escalera VARCHAR(20) DEFAULT NULL,
    ADD COLUMN planta VARCHAR(20) DEFAULT NULL,
    ADD COLUMN puerta VARCHAR(20) DEFAULT NULL,
    ADD COLUMN complemento VARCHAR(150) DEFAULT NULL,
    ADD COLUMN mananas_desde VARCHAR(20) DEFAULT NULL,
    ADD COLUMN mananas_hasta VARCHAR(20) DEFAULT NULL,
    ADD COLUMN tardes_desde VARCHAR(20) DEFAULT NULL,
    ADD COLUMN tardes_hasta VARCHAR(20) DEFAULT NULL,
    ADD COLUMN solo_los VARCHAR(100) DEFAULT NULL,
    ADD COLUMN email_2 VARCHAR(150) DEFAULT NULL,
    ADD COLUMN ultima_empresa_id INT DEFAULT NULL,
    ADD COLUMN centro_trabajo VARCHAR(150) DEFAULT NULL,
    ADD COLUMN enviar_emails TINYINT(1) DEFAULT 1,
    ADD COLUMN plat_usuario VARCHAR(100) DEFAULT NULL,
    ADD COLUMN plat_clave VARCHAR(100) DEFAULT NULL,
    ADD COLUMN id_plat_2015 VARCHAR(50) DEFAULT NULL,
    ADD COLUMN id_plat_2016 VARCHAR(50) DEFAULT NULL,
    ADD COLUMN pref_presencial VARCHAR(100) DEFAULT NULL,
    ADD COLUMN modulacion VARCHAR(100) DEFAULT NULL,
    ADD COLUMN horarios VARCHAR(100) DEFAULT NULL,
    ADD COLUMN entrega_atencion VARCHAR(150) DEFAULT NULL,
    ADD COLUMN entrega_domicilio VARCHAR(255) DEFAULT NULL,
    ADD COLUMN entrega_cp VARCHAR(10) DEFAULT NULL,
    ADD COLUMN entrega_localidad VARCHAR(100) DEFAULT NULL,
    ADD COLUMN entrega_provincia VARCHAR(100) DEFAULT NULL;";

try {
    $pdo->prepare($sql)->execute();
    echo "Migration successful: New columns added to 'alumnos' table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration already applied or columns partially exist.\n";
    } else {
        echo "Error during migration: " . $e->getMessage() . "\n";
    }
}
