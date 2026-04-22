<?php
require 'includes/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `tutorias_seguimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa` varchar(255) DEFAULT NULL,
  `alumno_id` int(11) DEFAULT NULL,
  `curso_id` int(11) DEFAULT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `forma` varchar(100) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seg_alumno` (`alumno_id`),
  KEY `idx_seg_usuario` (`usuario_id`),
  KEY `idx_seg_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->query($sql);
    echo "Tabla 'tutorias_seguimiento' creada o ya existente.\n";
} catch (Exception $e) {
    echo "Error creando tabla: " . $e->getMessage() . "\n";
}
