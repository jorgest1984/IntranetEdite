-- SCRIPT DE REPARACIÓN TOTAL V3.0 - INTRANET EDITE
-- Importa este archivo en phpMyAdmin de Plesk (base: intranet_formacion)
-- Este script es acumulativo y no borra datos existentes.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla Alumnos y Columnas Extendidas
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

-- 2. Estructura de Cursos y Gestión Académica
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

CREATE TABLE IF NOT EXISTS `convocatorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_expediente` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `organismo` varchar(100) DEFAULT NULL,
  `estado` varchar(50) DEFAULT 'Borrador',
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_expediente` (`codigo_expediente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `convocatoria_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `estado` varchar(50) DEFAULT 'Inscrito',
  `fecha_matricula` date DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `asistencia` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `convocatoria_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(50) DEFAULT 'Presente',
  `horas` int(11) DEFAULT 0,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Perfil Docente y CV (¡ELIMINANDO ERRORES "TABLE NOT FOUND"!)
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `denominacion` varchar(255) NOT NULL,
  `organismo` varchar(255) DEFAULT NULL,
  `centro` varchar(255) DEFAULT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `horas` int(11) DEFAULT NULL,
  `tipo_formacion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_f_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_experiencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `empresa` varchar(255) NOT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `cargo` varchar(255) DEFAULT NULL,
  `tareas` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_e_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_idiomas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `idioma` varchar(100) NOT NULL,
  `nivel_hablado` varchar(50) DEFAULT NULL,
  `nivel_oral` varchar(50) DEFAULT NULL,
  `nivel_escrito` varchar(50) DEFAULT NULL,
  `nivel_leido` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_i_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_informatica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `programa` varchar(150) NOT NULL,
  `dominio` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inf_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_tutorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `anio` int(11) DEFAULT NULL,
  `curso` varchar(255) DEFAULT NULL,
  `modalidad` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_t_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_formacion_interna` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `accion_formativa` varchar(255) NOT NULL,
  `fecha_desde` date DEFAULT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `calificacion` varchar(50) DEFAULT NULL,
  `valoracion_usuario` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fi_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `duracion_dias` int(11) DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pa_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prof_tareas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `expediente_id` int(11) DEFAULT NULL,
  `tipo_tarea` varchar(100) DEFAULT NULL,
  `num_accion` varchar(50) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `horas_imparticion` decimal(10,2) DEFAULT 0,
  `horas_tutorizacion` decimal(10,2) DEFAULT 0,
  `mes_1` decimal(10,2) DEFAULT 0, `mes_2` decimal(10,2) DEFAULT 0, `mes_3` decimal(10,2) DEFAULT 0,
  `mes_4` decimal(10,2) DEFAULT 0, `mes_5` decimal(10,2) DEFAULT 0, `mes_6` decimal(10,2) DEFAULT 0,
  `mes_7` decimal(10,2) DEFAULT 0, `mes_8` decimal(10,2) DEFAULT 0, `mes_9` decimal(10,2) DEFAULT 0,
  `mes_10` decimal(10,2) DEFAULT 0, `mes_11` decimal(10,2) DEFAULT 0, `mes_12` decimal(10,2) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pt_prof` (`profesor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Otros Sistemas (Documentos, Seguridad, Auditoría)
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

CREATE TABLE IF NOT EXISTS `incidencias_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `gravedad` varchar(50) DEFAULT 'Baja',
  `estado` varchar(50) DEFAULT 'Abierta',
  `fecha_reporte` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `empleado_departamentos` (
  `alumno_id` int(11) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  PRIMARY KEY (`alumno_id`, `departamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `empleado_perfiles` (
  `alumno_id` int(11) NOT NULL,
  `perfil` varchar(100) NOT NULL,
  PRIMARY KEY (`alumno_id`, `perfil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Módulo de Empresas / Centros de Impartición
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `cif` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `localidad` varchar(100) DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `contacto_nombre` varchar(150) DEFAULT NULL,
  `contacto_telefono` varchar(20) DEFAULT NULL,
  `es_vigilante` tinyint(1) DEFAULT 0,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emp_nombre` (`nombre`),
  KEY `idx_emp_provincia` (`provincia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
