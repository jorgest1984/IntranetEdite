<?php
require_once 'includes/config.php';

try {
    $columns_alumnos = [
        "discapacidad" => "tinyint(1) DEFAULT 0",
        "grupo_cotizacion" => "varchar(50) DEFAULT NULL",
        "categoria_profesional" => "varchar(100) DEFAULT NULL",
        "area_funcional" => "varchar(100) DEFAULT NULL",
        "ocupacion_cno" => "varchar(10) DEFAULT NULL",
        "situacion_laboral" => "varchar(100) DEFAULT NULL",
        "situacion_laboral_codigo" => "varchar(10) DEFAULT NULL"
    ];

    foreach ($columns_alumnos as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE alumnos ADD COLUMN $col $def");
            echo "<span style='color:green'>Columna alumnos.$col añadida correctamente.</span><br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<span style='color:orange'>La columna alumnos.$col ya existe.</span><br>";
            } else {
                echo "<span style='color:red'>ERROR añadiendo alumnos.$col: " . $e->getMessage() . "</span><br>";
            }
        }
    }
    echo "<b>Tabla alumnos procesada.</b><br><br>";

    $columns_empresas = [
        "domicilio" => "varchar(255) DEFAULT NULL",
        "cp" => "varchar(20) DEFAULT NULL",
        "tamano_empresa" => "varchar(50) DEFAULT NULL",
        "sector_actividad" => "varchar(200) DEFAULT NULL",
        "convenio_aplicacion" => "varchar(255) DEFAULT NULL"
    ];

    foreach ($columns_empresas as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE empresas ADD COLUMN $col $def");
            echo "<span style='color:green'>Columna empresas.$col añadida correctamente.</span><br>";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<span style='color:orange'>La columna empresas.$col ya existe.</span><br>";
            } else {
                echo "<span style='color:red'>ERROR añadiendo empresas.$col: " . $e->getMessage() . "</span><br>";
            }
        }
    }
    echo "<b>Tabla empresas procesada.</b><br><br>";

    // Update existing 'estudios' to match the new strict values
    $estudios_map = [
        'Sin estudios' => '0 - Sin titulación',
        'Primaria' => '1 - Educación Primaria',
        'ESO/EGB' => '22 - Título de Graduado E.S.O. / E.G.B.',
        'Bachillerato' => '32 - Título de Bachillerato',
        'FP Grado Medio' => '33 - Título de Técnico o equivalente (FP Grado Medio)',
        'FP Grado Superior' => '51 - Título de Técnico Superior o equivalente (FP Grado Superior)',
        'Universidad' => '61 - Título Universitario de Grado o equivalente'
    ];

    foreach ($estudios_map as $old => $new) {
        $stmt = $pdo->prepare("UPDATE alumnos SET estudios = ? WHERE estudios = ?");
        $stmt->execute([$new, $old]);
    }
    echo "Valores de estudios mapeados correctamente al nuevo formato.<br>";
    
    echo "<br><b>¡MIGRACIÓN COMPLETADA CON ÉXITO!</b> Ya puedes borrar este archivo.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
