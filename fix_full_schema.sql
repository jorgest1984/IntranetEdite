-- CORRECCIÓN INTEGRAL DE ESQUEMA - INTRANET EDITE
-- Importa este archivo en phpMyAdmin de tu servidor Plesk (gestion.grupoefp.es)

-- 1. Actualizar tabla Alumnos con todas las columnas necesarias
-- Usamos ALTER para no perder el usuario 'admin' (aunque admin suele estar en 'usuarios')
-- Si ya existen las tablas, esto solo añadirá lo que falte.

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
  ADD COLUMN IF NOT EXISTS `telefono_empresa` varchar(20) DEFAULT NULL AFTER `telefono`,
  ADD COLUMN IF NOT EXISTS `email_personal` varchar(150) DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `teams` varchar(100) DEFAULT NULL AFTER `email_personal`,
  ADD COLUMN IF NOT EXISTS `nacionalidad` varchar(100) DEFAULT NULL AFTER `teams`,
  ADD COLUMN IF NOT EXISTS `sexo` enum('Hombre','Mujer','Otro') DEFAULT NULL AFTER `nacionalidad`,
  ADD COLUMN IF NOT EXISTS `activo_hasta` varchar(100) DEFAULT NULL AFTER `sexo`,
  ADD COLUMN IF NOT EXISTS `es_nuestro` tinyint(1) DEFAULT 0 AFTER `activo_hasta`,
  ADD COLUMN IF NOT EXISTS `otros_datos_interes` text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `cv_updated_at` datetime DEFAULT NULL;

-- 2. Tabla de Detalles de Profesorado (Requerida por ficha_alumno.php)
CREATE TABLE IF NOT EXISTS `profesorado_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `titulacion` varchar(255) DEFAULT NULL,
  `es_tutor` tinyint(1) DEFAULT 0,
  `es_teleformador` tinyint(1) DEFAULT 0,
  `es_presencial` tinyint(1) DEFAULT 0,
  `hace_seguimiento` tinyint(1) DEFAULT 0,
  `tope_alumnos_turno` int(11) DEFAULT 0,
  `centro` varchar(255) DEFAULT NULL,
  `id_plataforma` varchar(100) DEFAULT NULL,
  `id_plataforma_2010` varchar(100) DEFAULT NULL,
  `id_plataforma_2011` varchar(100) DEFAULT NULL,
  `id_plataforma_2013` varchar(100) DEFAULT NULL,
  `id_plataforma_2015` varchar(100) DEFAULT NULL,
  `id_plataforma_2016` varchar(100) DEFAULT NULL,
  `tramo1_de` time DEFAULT NULL,
  `tramo1_a` time DEFAULT NULL,
  `tramo1_v2_de` time DEFAULT NULL,
  `tramo1_v2_a` time DEFAULT NULL,
  `tramo2_de` time DEFAULT NULL,
  `tramo2_a` time DEFAULT NULL,
  `tramo2_v2_de` time DEFAULT NULL,
  `tramo2_v2_a` time DEFAULT NULL,
  `aplicar_viernes` tinyint(1) DEFAULT 0,
  `com_fijo` decimal(10,2) DEFAULT 0,
  `com_tramo1` decimal(10,2) DEFAULT 0,
  `com_alumnos_fijo` int(11) DEFAULT 0,
  `com_fecha_fijo` date DEFAULT NULL,
  `com_tramo2` decimal(10,2) DEFAULT 0,
  `com_tope2` decimal(10,2) DEFAULT 0,
  `com_presenciales` decimal(10,2) DEFAULT 0,
  `com_tramo3` decimal(10,2) DEFAULT 0,
  `com_tope3` decimal(10,2) DEFAULT 0,
  `horario_general` text DEFAULT NULL,
  `obs_asistencia` text DEFAULT NULL,
  `vac_dias_pendientes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alumno_prof` (`alumno_id`),
  CONSTRAINT `fk_prof_alumno` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tablas auxiliares para CV (Profesorado)
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

-- 4. Otros detalles y vinculaciones
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

CREATE TABLE IF NOT EXISTS `prof_asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profesor_id` int(11) NOT NULL,
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `duracion_dias` int(11) DEFAULT 0,
  `duracion_horas` int(11) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_a_prof` (`profesor_id`)
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
  `mes_1` decimal(10,2) DEFAULT 0, `mes_2` decimal(10,2) DEFAULT 0, 
  `mes_3` decimal(10,2) DEFAULT 0, `mes_4` decimal(10,2) DEFAULT 0,
  `mes_5` decimal(10,2) DEFAULT 0, `mes_6` decimal(10,2) DEFAULT 0,
  `mes_7` decimal(10,2) DEFAULT 0, `mes_8` decimal(10,2) DEFAULT 0,
  `mes_9` decimal(10,2) DEFAULT 0, `mes_10` decimal(10,2) DEFAULT 0,
  `mes_11` decimal(10,2) DEFAULT 0, `mes_12` decimal(10,2) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
