<?php
require_once 'includes/config.php';

try {
    $pdo->exec("
    ALTER TABLE alumnos
    ADD COLUMN IF NOT EXISTS discapacidad tinyint(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS grupo_cotizacion varchar(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS categoria_profesional varchar(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS area_funcional varchar(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ocupacion_cno varchar(10) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS situacion_laboral varchar(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS situacion_laboral_codigo varchar(10) DEFAULT NULL;
    ");
    echo "Tabla alumnos actualizada correctamente.<br>";

    $pdo->exec("
    ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS domicilio varchar(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cp varchar(20) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tamano_empresa varchar(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS sector_actividad varchar(200) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS convenio_aplicacion varchar(255) DEFAULT NULL;
    ");
    echo "Tabla empresas actualizada correctamente.<br>";

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
