<?php
require_once 'includes/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `acciones_formativas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) DEFAULT NULL,
  `nivel` varchar(50) DEFAULT NULL,
  `prioridad` varchar(50) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `destacar_web` tinyint(1) DEFAULT 0,
  `ultimas_plazas` tinyint(1) DEFAULT 0,
  `id_plataforma` varchar(100) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `abreviatura` varchar(100) DEFAULT NULL,
  `num_accion` int(11) DEFAULT 0,
  `duracion` int(11) DEFAULT 0,
  `p` int(11) DEFAULT 0,
  `d` int(11) DEFAULT 0,
  `t` int(11) DEFAULT 0,
  `modalidad` varchar(100) DEFAULT NULL,
  `area_tematica` varchar(255) DEFAULT NULL,
  `familia_profesional` varchar(255) DEFAULT NULL,
  `horas_teoricas` int(11) DEFAULT 0,
  `horas_practicas` int(11) DEFAULT 0,
  `dias_extra` int(11) DEFAULT 0,
  `asignacion` varchar(255) DEFAULT NULL,
  `modulo_sensib` tinyint(1) DEFAULT 0,
  `modulo_alfab` tinyint(1) DEFAULT 0,
  `encuesta_post` tinyint(1) DEFAULT 0,
  `dur_int_empresas` varchar(50) DEFAULT NULL,
  `dur_emprendimiento` varchar(50) DEFAULT NULL,
  `objetivos` text DEFAULT NULL,
  `objetivos_especificos` text DEFAULT NULL,
  `contenidos` text DEFAULT NULL,
  `contenidos_breves` text DEFAULT NULL,
  `que_aprenden` text DEFAULT NULL,
  `contenidos_fes` text DEFAULT NULL,
  `recursos_accion` text DEFAULT NULL,
  `demanda_mercado` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->query($sql);
    echo "Tabla 'acciones_formativas' creada con éxito.\n";
} catch (Throwable $e) {
    echo "Error al crear la tabla: " . $e->getMessage() . "\n";
}
