<?php
require_once 'includes/config.php';

$sql = "
ALTER TABLE `facturas`
ADD COLUMN IF NOT EXISTS `tipo_emisor` ENUM('Proveedor', 'Usuario / Profesor') DEFAULT 'Proveedor' AFTER `numero_factura`,
ADD COLUMN IF NOT EXISTS `emisor_id` INT(11) DEFAULT NULL AFTER `tipo_emisor`,
MODIFY COLUMN `creado_en` datetime DEFAULT CURRENT_TIMESTAMP;
";

try {
    $pdo->prepare($sql)->execute();
    echo "Tabla 'facturas' ampliada correctamente.\n";
} catch (Exception $e) {
    echo "Error al ampliar la tabla: " . $e->getMessage() . "\n";
}
?>
