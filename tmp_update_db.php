<?php
require_once 'includes/config.php';

$sql = "ALTER TABLE acciones_formativas 
        ADD COLUMN IF NOT EXISTS hay_material TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS num_entregas INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS codigo_entregas VARCHAR(50),
        ADD COLUMN IF NOT EXISTS num_modulos INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS detalle_entregas TEXT,
        ADD COLUMN IF NOT EXISTS manual_curso TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS manual_sensibilizacion TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS carpeta_clasificadora TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS cuaderno_a4 TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS boligrafo TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS maletin TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS otros_materiales TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS otros_materiales_txt TEXT";

try {
    $db = DBBridge::get_db();
    $db->execute($sql);
    echo "Tabla acciones_formativas actualizada con éxito.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
