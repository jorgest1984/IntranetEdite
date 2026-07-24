<?php
// alter_db.php - Script de actualización de esquema V4.0 para Intranet Edite
require_once 'includes/config.php';

echo "Iniciando actualización de base de datos...\n";

try {
    // 1. Campos adicionales en MATRICULAS
    $cols_matriculas = [
        'motivo_no_admision' => 'TEXT NULL',
        'no_volver_preinscrito' => 'TINYINT(1) DEFAULT 0',
        'no_desmatricular' => 'TINYINT(1) DEFAULT 0',
        'anular_sepe' => 'VARCHAR(50) DEFAULT "NO"',
        'evaluacion_tic' => 'VARCHAR(50) NULL',
        'baja_plataforma' => 'TINYINT(1) DEFAULT 0',
        'exento_practicas' => 'TINYINT(1) DEFAULT 0',
        'captado_ugt' => 'TINYINT(1) DEFAULT 0',
        'validar_plan' => 'TINYINT(1) DEFAULT 0',
        'enviar_mails_auto' => 'TINYINT(1) DEFAULT 1',
        'motivo_baja_sepe' => 'VARCHAR(100) NULL',
        'prefiere_fechas' => 'VARCHAR(255) NULL',
        'doc_checklist' => 'TEXT NULL',
        'prioridad' => 'INT DEFAULT 0'
    ];

    $stmt = $pdo->query("DESCRIBE matriculas");
    $existing_mat = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($cols_matriculas as $col => $def) {
        if (!in_array($col, $existing_mat)) {
            $pdo->exec("ALTER TABLE matriculas ADD COLUMN `$col` $def");
            echo "Añadida columna matriculas.$col\n";
        }
    }

    // 2. Campos adicionales en ALUMNOS
    $cols_alumnos = [
        'tipo_via' => 'VARCHAR(50) DEFAULT "Calle"',
        'nombre_via' => 'VARCHAR(150) NULL',
        'numero_via' => 'VARCHAR(20) NULL',
        'calificador_via' => 'VARCHAR(20) NULL',
        'bloque' => 'VARCHAR(20) NULL',
        'portal' => 'VARCHAR(20) NULL',
        'escalera' => 'VARCHAR(20) NULL',
        'planta' => 'VARCHAR(20) NULL',
        'puerta' => 'VARCHAR(20) NULL',
        'complemento_domicilio' => 'VARCHAR(255) NULL',
        'omitir_ss' => 'TINYINT(1) DEFAULT 0',
        'entrega_atencion' => 'VARCHAR(150) NULL',
        'entrega_domicilio' => 'VARCHAR(255) NULL',
        'entrega_cp' => 'VARCHAR(20) NULL',
        'entrega_localidad' => 'VARCHAR(100) NULL',
        'entrega_provincia' => 'VARCHAR(100) NULL',
        'clave_plataforma' => 'VARCHAR(100) NULL',
        'id_plat_2015' => 'VARCHAR(100) NULL',
        'id_plat_2016' => 'VARCHAR(100) NULL',
        'comercial_id' => 'INT NULL',
        'colectivo' => 'VARCHAR(255) NULL',
        'profesion' => 'VARCHAR(150) NULL',
        'estudios' => 'VARCHAR(150) NULL',
        'discapacidad' => 'TINYINT(1) DEFAULT 0'
    ];

    $stmt = $pdo->query("DESCRIBE alumnos");
    $existing_alum = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($cols_alumnos as $col => $def) {
        if (!in_array($col, $existing_alum)) {
            $pdo->exec("ALTER TABLE alumnos ADD COLUMN `$col` $def");
            echo "Añadida columna alumnos.$col\n";
        }
    }

    // 3. Campos adicionales en EMPRESAS
    $cols_empresas = [
        'no_llamar' => 'TINYINT(1) DEFAULT 0',
        'en_reserva' => 'TINYINT(1) DEFAULT 0',
        'es_gestoria' => 'TINYINT(1) DEFAULT 0',
        'es_mercaolid' => 'TINYINT(1) DEFAULT 0',
        'es_promax' => 'TINYINT(1) DEFAULT 0',
        'redes_total_participantes' => 'INT DEFAULT 0',
        'redes_colectivos_prioritarios' => 'INT DEFAULT 0',
        'redes_tiempo_parcial' => 'INT DEFAULT 0',
        'redes_temporal' => 'INT DEFAULT 0',
        'redes_mujeres' => 'INT DEFAULT 0',
        'redes_mayores_45' => 'INT DEFAULT 0',
        'rlt_sindicato' => 'VARCHAR(100) NULL',
        'rlt_contacto' => 'VARCHAR(150) NULL',
        'rlt_email' => 'VARCHAR(150) NULL',
        'rep_legal_nombre' => 'VARCHAR(100) NULL',
        'rep_legal_apellidos' => 'VARCHAR(150) NULL',
        'rep_legal_sexo' => 'VARCHAR(20) NULL',
        'rep_legal_nif' => 'VARCHAR(20) NULL',
        'rep_legal_cargo' => 'VARCHAR(100) NULL',
        'rep_legal_email' => 'VARCHAR(150) NULL',
        'comercial_id' => 'INT NULL',
        'bloqueado' => 'TINYINT(1) DEFAULT 0'
    ];

    $stmt = $pdo->query("DESCRIBE empresas");
    $existing_emp = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($cols_empresas as $col => $def) {
        if (!in_array($col, $existing_emp)) {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN `$col` $def");
            echo "Añadida columna empresas.$col\n";
        }
    }

    // 4. Crear tabla de CENTROS DE TRABAJO (Multi-sede)
    $pdo->exec("CREATE TABLE IF NOT EXISTS empresas_centros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nombre_centro VARCHAR(255) NULL,
        domicilio VARCHAR(255) NULL,
        cp VARCHAR(20) NULL,
        localidad VARCHAR(100) NULL,
        provincia VARCHAR(100) NULL,
        telefono VARCHAR(50) NULL,
        email VARCHAR(150) NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_empresa (empresa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabla empresas_centros verificada/creada.\n";

    // 5. Crear tabla de GESTORIAS VINCULADAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS contactos_gestorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contacto_id INT NOT NULL,
        razon_social VARCHAR(255) NOT NULL,
        fecha_desde DATE NULL,
        fecha_hasta DATE NULL,
        INDEX idx_contacto (contacto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabla contactos_gestorias verificada/creada.\n";

    // 6. Crear tabla de CERTIFICADOS DE PROFESIONALIDAD
    $pdo->exec("CREATE TABLE IF NOT EXISTS contactos_certificados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contacto_id INT NOT NULL,
        codigo VARCHAR(50) NOT NULL,
        familia_profesional VARCHAR(150) NULL,
        titulo VARCHAR(255) NOT NULL,
        INDEX idx_contacto_cert (contacto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabla contactos_certificados verificada/creada.\n";

    echo "Actualización de base de datos completada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error ejecutando alter_db: " . $e->getMessage() . "\n";
}
