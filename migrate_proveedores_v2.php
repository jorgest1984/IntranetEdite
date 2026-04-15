<?php
// migrate_proveedores_v2.php - Añadir campos extra a la tabla proveedores para la ficha de proveedor
require_once 'includes/config.php';

$sql = "
ALTER TABLE `proveedores`
ADD COLUMN IF NOT EXISTS `tipo_material` VARCHAR(100) DEFAULT NULL AFTER `sector`,
ADD COLUMN IF NOT EXISTS `aprobado` TINYINT(1) DEFAULT 0 AFTER `tipo_material`,
ADD COLUMN IF NOT EXISTS `es_proveedor` TINYINT(1) DEFAULT 1 AFTER `aprobado`,
ADD COLUMN IF NOT EXISTS `es_editor` TINYINT(1) DEFAULT 0 AFTER `es_proveedor`,
ADD COLUMN IF NOT EXISTS `contacto` VARCHAR(200) DEFAULT NULL AFTER `movil`,
ADD COLUMN IF NOT EXISTS `fax` VARCHAR(20) DEFAULT NULL AFTER `contacto`,
ADD COLUMN IF NOT EXISTS `web_usuario` VARCHAR(100) DEFAULT NULL AFTER `web`,
ADD COLUMN IF NOT EXISTS `web_password` VARCHAR(100) DEFAULT NULL AFTER `web_usuario`,
ADD COLUMN IF NOT EXISTS `materiales` TEXT DEFAULT NULL AFTER `web_password`,
ADD COLUMN IF NOT EXISTS `responsable` INT(11) DEFAULT NULL AFTER `materiales`,
ADD COLUMN IF NOT EXISTS `forma_pago` VARCHAR(200) DEFAULT NULL AFTER `responsable`,
ADD COLUMN IF NOT EXISTS `observaciones` TEXT DEFAULT NULL AFTER `forma_pago`;
";

try {
    $pdo->prepare($sql)->execute();
    echo "Tabla 'proveedores' ampliada correctamente con campos de ficha de proveedor.\n";
} catch (Exception $e) {
    echo "Error al ampliar la tabla: " . $e->getMessage() . "\n";
}
?>
