<?php
require_once 'includes/config.php';

try {
    $columns = [
        'fecha_inicio_oficial' => "ALTER TABLE planes ADD COLUMN fecha_inicio_oficial DATE AFTER nombre",
        'anio_convocatoria' => "ALTER TABLE planes ADD COLUMN anio_convocatoria INT AFTER fecha_inicio_oficial",
        'tope_horas_alumno' => "ALTER TABLE planes ADD COLUMN tope_horas_alumno INT AFTER anio_convocatoria",
        'fecha_fin_convocatoria' => "ALTER TABLE planes ADD COLUMN fecha_fin_convocatoria DATE AFTER tope_horas_alumno",
        'programacion_automatica' => "ALTER TABLE planes ADD COLUMN programacion_automatica TINYINT(1) DEFAULT 0 AFTER fecha_fin_convocatoria",
        'expediente' => "ALTER TABLE planes ADD COLUMN expediente VARCHAR(100) AFTER programacion_automatica",
        'ambito' => "ALTER TABLE planes ADD COLUMN ambito VARCHAR(100) AFTER expediente",
        'cod_acceso' => "ALTER TABLE planes ADD COLUMN cod_acceso VARCHAR(50) AFTER ambito",
        'mostrar' => "ALTER TABLE planes ADD COLUMN mostrar TINYINT(1) DEFAULT 1 AFTER cod_acceso",
        'nuestro' => "ALTER TABLE planes ADD COLUMN nuestro TINYINT(1) DEFAULT 0 AFTER mostrar",
        'entidad' => "ALTER TABLE planes ADD COLUMN entidad VARCHAR(255) AFTER nuestro",
        'solicitante' => "ALTER TABLE planes ADD COLUMN solicitante VARCHAR(255) AFTER entidad",
        'sector' => "ALTER TABLE planes ADD COLUMN sector VARCHAR(255) AFTER solicitante",
        'grupo_sector' => "ALTER TABLE planes ADD COLUMN grupo_sector VARCHAR(255) AFTER sector",
        'coordinador' => "ALTER TABLE planes ADD COLUMN coordinador VARCHAR(255) AFTER grupo_sector",
        'porc_frar' => "ALTER TABLE planes ADD COLUMN porc_frar DECIMAL(5,2) DEFAULT 0 AFTER coordinador",
        'facturar_por_grupos' => "ALTER TABLE planes ADD COLUMN facturar_por_grupos TINYINT(1) DEFAULT 0 AFTER porc_frar",
        'porc_calidad' => "ALTER TABLE planes ADD COLUMN porc_calidad DECIMAL(5,2) DEFAULT 0 AFTER facturar_por_grupos",
        'porc_costes_indirectos' => "ALTER TABLE planes ADD COLUMN porc_costes_indirectos DECIMAL(5,2) DEFAULT 0 AFTER porc_calidad",
        'subvencion' => "ALTER TABLE planes ADD COLUMN subvencion DECIMAL(15,2) DEFAULT 0 AFTER porc_costes_indirectos",
        'cofin_fse' => "ALTER TABLE planes ADD COLUMN cofin_fse DECIMAL(15,2) DEFAULT 0 AFTER subvencion",
        'grupo_zona_1' => "ALTER TABLE planes ADD COLUMN grupo_zona_1 VARCHAR(100) AFTER cofin_fse",
        'grupo_zona_2' => "ALTER TABLE planes ADD COLUMN grupo_zona_2 VARCHAR(100) AFTER grupo_zona_1",
        'ejecutar_edite' => "ALTER TABLE planes ADD COLUMN ejecutar_edite DECIMAL(15,2) DEFAULT 0 AFTER grupo_zona_2",
        'cofinanciado_edite' => "ALTER TABLE planes ADD COLUMN cofinanciado_edite DECIMAL(15,2) DEFAULT 0 AFTER ejecutar_edite",
        'prioridad_sectorial' => "ALTER TABLE planes ADD COLUMN prioridad_sectorial DECIMAL(5,2) DEFAULT 0 AFTER cofinanciado_edite",
        'prioridad_sectorial_colchon' => "ALTER TABLE planes ADD COLUMN prioridad_sectorial_colchon DECIMAL(5,2) DEFAULT 0 AFTER prioridad_sectorial",
        'transversal' => "ALTER TABLE planes ADD COLUMN transversal DECIMAL(5,2) DEFAULT 0 AFTER prioridad_sectorial_colchon",
        'transversal_colchon' => "ALTER TABLE planes ADD COLUMN transversal_colchon DECIMAL(5,2) DEFAULT 0 AFTER transversal",
        'minima' => "ALTER TABLE planes ADD COLUMN minima DECIMAL(5,2) DEFAULT 0 AFTER transversal_colchon",
        'minima_colchon' => "ALTER TABLE planes ADD COLUMN minima_colchon DECIMAL(5,2) DEFAULT 0 AFTER minima",
        'reconfiguracion' => "ALTER TABLE planes ADD COLUMN reconfiguracion DECIMAL(5,2) DEFAULT 0 AFTER minima_colchon",
        'porc_au' => "ALTER TABLE planes ADD COLUMN porc_au DECIMAL(5,2) DEFAULT 0 AFTER reconfiguracion",
        'porc_mujeres' => "ALTER TABLE planes ADD COLUMN porc_mujeres DECIMAL(5,2) DEFAULT 0 AFTER porc_au",
        'porc_colectivos_prioritarios' => "ALTER TABLE planes ADD COLUMN porc_colectivos_prioritarios DECIMAL(5,2) DEFAULT 0 AFTER porc_mujeres",
        'porc_max_desempleados' => "ALTER TABLE planes ADD COLUMN porc_max_desempleados DECIMAL(5,2) DEFAULT 0 AFTER porc_colectivos_prioritarios",
        'cant_ref_cofinanciada' => "ALTER TABLE planes ADD COLUMN cant_ref_cofinanciada DECIMAL(15,2) DEFAULT 0 AFTER porc_max_desempleados",
        'cant_ref_no_cofinanciada' => "ALTER TABLE planes ADD COLUMN cant_ref_no_cofinanciada DECIMAL(15,2) DEFAULT 0 AFTER cant_ref_cofinanciada",
        'fecha_convenio' => "ALTER TABLE planes ADD COLUMN fecha_convenio DATE AFTER cant_ref_no_cofinanciada",
        'observaciones' => "ALTER TABLE planes ADD COLUMN observaciones TEXT AFTER fecha_convenio"
    ];

    foreach ($columns as $col => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM planes LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->query($sql);
            echo "Columna '$col' añadida.<br>";
        } else {
            echo "Columna '$col' ya existe.<br>";
        }
    }

    echo "Migración de la tabla 'planes' completada con éxito.";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage();
}
