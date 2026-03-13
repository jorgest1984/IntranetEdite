-- SCRIPT DE REPARACIÓN TOTAL - INTRANET EDITE
-- Importa este archivo en phpMyAdmin (base: intranet_formacion)
-- gestion.grupoefp.es

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Asegurar tabla Alumnos y sus columnas extendidas
CREATE TABLE IF NOT EXISTS `alumnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `alumnos` 
  DROP COLUMN IF EXISTS `apellidos`,
  ADD COLUMN IF NOT EXISTS `primer_apellido` varchar(150) AFTER `nombre`,
  ADD COLUMN IF NOT EXISTS `segundo_apellido` varchar(150) AFTER `primer_apellido`,
  ADD COLUMN IF NOT EXISTS `fecha_nacimiento` date DEFAULT NULL AFTER `dni`,
  ADD COLUMN IF NOT EXISTS `seguridad_social` varchar(50) DEFAULT NULL AFTER `fecha_nacimiento`,
  ADD COLUMN IF NOT EXISTS `cuenta_bancaria` varchar(50) DEFAULT NULL AFTER `seguridad_social`,
  ADD COLUMN IF NOT EXISTS `domicilio` varchar(255) DEFAULT NULL AFTER `cuenta_bancaria`,
  ADD COLUMN IF NOT EXISTS `cp` varchar(10) DEFAULT NULL AFTER `domicilio`,
  ADD COLUMN IF NOT EXISTS `localidad` varchar(100) DEFAULT NULL AFTER `cp`,
  ADD COLUMN IF NOT EXISTS `provincia` varchar(100) DEFAULT NULL AFTER `localidad`,
  ADD COLUMN IF NOT EXISTS `telefono` varchar(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `telefono_empresa` varchar(20) DEFAULT NULL AFTER `telefono`,
  ADD COLUMN IF NOT EXISTS `email_personal` varchar(150) DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `teams` varchar(100) DEFAULT NULL AFTER `email_personal`,
  ADD COLUMN IF NOT EXISTS `nacionalidad` varchar(100) DEFAULT NULL AFTER `teams`,
  ADD COLUMN IF NOT EXISTS `sexo` enum('Hombre','Mujer','Otro') DEFAULT NULL AFTER `nacionalidad`,
  ADD COLUMN IF NOT EXISTS `activo_hasta` varchar(100) DEFAULT NULL AFTER `sexo`,
  ADD COLUMN IF NOT EXISTS `es_nuestro` tinyint(1) DEFAULT 0 AFTER `activo_hasta`,
  ADD COLUMN IF NOT EXISTS `moodle_user_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `observaciones` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `otros_datos_interes` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cv_updated_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `creado_en` datetime DEFAULT CURRENT_TIMESTAMP;

-- 2. Estructura de Cursos y Convocatorias
CREATE TABLE IF NOT EXISTS `convocatorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_expediente` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('SEPE_DESEMPLEADOS','FUNDAE_OCUPADOS','PRIVADA') NOT NULL,
  `organismo` varchar(100) DEFAULT NULL,
  `fecha_inicio_prevista` date DEFAULT NULL,
  `fecha_fin_prevista` date DEFAULT NULL,
  `presupuesto` decimal(10,2) DEFAULT NULL,
  `estado` enum('Borrador','Aprobada','En Ejecución','Finalizada','Justificada') NOT NULL DEFAULT 'Borrador',
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_expediente` (`codigo_expediente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- 3. Asistencia e Incidencias
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

-- 4. Perfil Docente Extendido
CREATE TABLE IF NOT EXISTS `profesorado_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `titulacion` varchar(255) DEFAULT NULL,
  `es_tutor` tinyint(1) DEFAULT 0, `es_teleformador` tinyint(1) DEFAULT 0, `es_presencial` tinyint(1) DEFAULT 0,
  `hace_seguimiento` tinyint(1) DEFAULT 0, `tope_alumnos_turno` int(11) DEFAULT 0,
  `centro` varchar(255) DEFAULT NULL, `id_plataforma` varchar(100) DEFAULT NULL,
  `tramo1_de` time DEFAULT NULL, `tramo1_a` time DEFAULT NULL,
  `tramo2_de` time DEFAULT NULL, `tramo2_a` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alumno_prof` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_formacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `profesor_id` int(11) NOT NULL,
  `denominacion` varchar(255) NOT NULL, `organismo` varchar(255) DEFAULT NULL,
  `desde` date DEFAULT NULL, `hasta` date DEFAULT NULL, `horas` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_experiencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT, `profesor_id` int(11) NOT NULL,
  `empresa` varchar(255) NOT NULL, `cargo` varchar(255) DEFAULT NULL,
  `desde` date DEFAULT NULL, `hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Gestión Documental (Faltante en turnos previos)
CREATE TABLE IF NOT EXISTS `documentos_alumno` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_documento` varchar(100) DEFAULT 'General',
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_alumno` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
