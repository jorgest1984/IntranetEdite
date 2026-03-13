-- Tablas faltantes para Intranet Edite Formación
-- Importa este archivo en tu panel Plesk (phpMyAdmin)

-- 1. Tabla de Alumnos
CREATE TABLE IF NOT EXISTS `alumnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `moodle_user_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla de Cursos
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moodle_id` int(11) NOT NULL,
  `nombre_corto` varchar(100) NOT NULL,
  `nombre_largo` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `visible` tinyint(4) DEFAULT 1,
  `fecha_sync` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moodle_id` (`moodle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de Asistencia
CREATE TABLE IF NOT EXISTS `asistencia` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `convocatoria_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('Presente','Falta','Falta Justificada','Retraso') NOT NULL DEFAULT 'Presente',
  `horas` int(11) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_asistencia_convocatoria` (`convocatoria_id`),
  KEY `fk_asistencia_alumno` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Matriculas
CREATE TABLE IF NOT EXISTS `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convocatoria_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `estado` enum('Inscrito','Activo','Finalizada','Baja','Cancelada') NOT NULL DEFAULT 'Inscrito',
  `fecha_matricula` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_matricula_convocatoria` (`convocatoria_id`),
  KEY `fk_matricula_alumno` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla de Incidencias de Seguridad
CREATE TABLE IF NOT EXISTS `incidencias_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `gravedad` enum('Baja','Media','Alta') NOT NULL DEFAULT 'Baja',
  `estado` enum('Abierta','En Proceso','Resuelta') NOT NULL DEFAULT 'Abierta',
  `resolucion` text DEFAULT NULL,
  `fecha_reporte` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_incidencia_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
