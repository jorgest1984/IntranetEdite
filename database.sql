-- Estructura de Base de Datos para Intranet - Gestión de Empresa de Formación (ISO 27001)

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema y configuración'),
(2, 'Coordinador', 'Gestión de convocatorias, alumnos y cursos'),
(3, 'Formador', 'Acceso a sus grupos, calificaciones y asistencia'),
(4, 'Solo Lectura', 'Auditoría externa o consulta sin permisos de edición');

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `moodle_user_id` int(11) DEFAULT NULL COMMENT 'ID de usuario en Moodle (sincronizado)',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_rol` (`rol_id`),
  CONSTRAINT `fk_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario admin por defecto (contraseña: Admin123!)
INSERT INTO `usuarios` (`username`, `password_hash`, `nombre`, `apellidos`, `email`, `rol_id`) VALUES
('admin', '$2y$10$w09ZJ35p5J.Z961/7R7vpe.Q8/E8S9YyU./.n/v.e/c..n...u.C.', 'Admin', 'Sistema', 'admin@empresa.com', 1);

-- Tabla para ISO 27001: Registro de Auditoría (Audit Log inmutable)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(100) NOT NULL COMMENT 'Tabla o módulo afectado',
  `entidad_id` int(11) DEFAULT NULL,
  `datos_antiguos` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` text NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('moodle_url', 'https://moodle.ejemplo.com', 'URL base del servidor Moodle'),
('moodle_token', '', 'Token de Web Service Rest de Moodle'),
('empresa_nombre', 'Mi Empresa de Formación', 'Nombre de la empresa para documentos');

-- Convocatorias SEPE/FUNDAE
CREATE TABLE IF NOT EXISTS `convocatorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_expediente` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('SEPE_DESEMPLEADOS', 'FUNDAE_OCUPADOS', 'PRIVADA') NOT NULL,
  `organismo` varchar(100) DEFAULT NULL,
  `fecha_inicio_prevista` date DEFAULT NULL,
  `fecha_fin_prevista` date DEFAULT NULL,
  `presupuesto` decimal(10,2) DEFAULT NULL,
  `estado` enum('Borrador', 'Aprobada', 'En Ejecución', 'Finalizada', 'Justificada') NOT NULL DEFAULT 'Borrador',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_expediente` (`codigo_expediente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
