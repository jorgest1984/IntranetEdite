<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../includes/config.php';

try {
    // 1. Tabla de Contactos (Empresas)
    $sql1 = "CREATE TABLE IF NOT EXISTS comerciales_contactos_empresa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercial_id INT NOT NULL,
        empresa VARCHAR(255) NOT NULL,
        actividad VARCHAR(255),
        sector VARCHAR(255),
        baja TINYINT(1) DEFAULT 0,
        no_valido TINYINT(1) DEFAULT 0,
        motivo_no_valido VARCHAR(255),
        interesado_nuevas TINYINT(1) DEFAULT 0,
        domicilio VARCHAR(255),
        cp VARCHAR(20),
        localidad VARCHAR(255),
        provincia VARCHAR(255),
        nif VARCHAR(50),
        telefono VARCHAR(50),
        movil_1 VARCHAR(50),
        movil_2 VARCHAR(50),
        movil_3 VARCHAR(50),
        web VARCHAR(255),
        email_1 VARCHAR(255),
        email_2 VARCHAR(255),
        email_3 VARCHAR(255),
        email_4 VARCHAR(255),
        toma_contacto_1 TEXT,
        toma_contacto_2 TEXT,
        toma_contacto_3 TEXT,
        observacion_1 TEXT,
        observacion_2 TEXT,
        observacion_3 TEXT,
        tema_interes VARCHAR(255),
        es_promax TINYINT(1) DEFAULT 0,
        persona_contacto VARCHAR(255),
        tiene_certificados TINYINT(1) DEFAULT 0,
        num_certificados INT DEFAULT 0,
        notas TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (comercial_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql1);
    echo "Tabla comerciales_contactos_empresa creada correctamente.\n";

    // 2. Tabla de Gestorias relacionadas con la empresa
    $sql2 = "CREATE TABLE IF NOT EXISTS comerciales_contactos_gestoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contacto_empresa_id INT NOT NULL,
        razon_social VARCHAR(255),
        fecha_desde DATE,
        fecha_hasta DATE,
        FOREIGN KEY (contacto_empresa_id) REFERENCES comerciales_contactos_empresa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql2);
    echo "Tabla comerciales_contactos_gestoria creada correctamente.\n";

    // 3. Tabla de Certificados relacionadas con la empresa
    $sql3 = "CREATE TABLE IF NOT EXISTS comerciales_contactos_certificado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contacto_empresa_id INT NOT NULL,
        familia_profesional VARCHAR(255),
        area_profesional VARCHAR(255),
        denominacion VARCHAR(255),
        FOREIGN KEY (contacto_empresa_id) REFERENCES comerciales_contactos_empresa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql3);
    echo "Tabla comerciales_contactos_certificado creada correctamente.\n";

    // 4. Tabla de Contactos (Trabajadores)
    $sql4 = "CREATE TABLE IF NOT EXISTS comerciales_contactos_trabajador (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercial_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(150),
        nif VARCHAR(50),
        empresa_id INT DEFAULT NULL,
        empresa_nombre VARCHAR(255),
        puesto VARCHAR(255),
        telefono VARCHAR(50),
        movil VARCHAR(50),
        email VARCHAR(255),
        domicilio VARCHAR(255),
        cp VARCHAR(20),
        localidad VARCHAR(255),
        provincia VARCHAR(255),
        notas TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (comercial_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (empresa_id) REFERENCES comerciales_contactos_empresa(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql4);
    echo "Tabla comerciales_contactos_trabajador creada correctamente.\n";
    
} catch (PDOException $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
