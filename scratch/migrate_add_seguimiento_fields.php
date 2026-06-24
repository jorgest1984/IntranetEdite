<?php
// scratch/migrate_add_seguimiento_fields.php
require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "--- INICIO DE MIGRACIÓN: MATRICULAS SEGUIMIENTO FIELDS ---\n";

try {
    // Obtener las columnas existentes de la tabla 'matriculas'
    $stmt = $pdo->query("DESCRIBE matriculas");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $fieldsToAdd = [
        'envio_claves'            => "TINYINT(1) DEFAULT 0 COMMENT 'Indica si se enviaron las claves'",
        'fecha_claves'            => "DATE NULL DEFAULT NULL COMMENT 'Fecha de envio de claves'",
        'email_admision_enviado'  => "TINYINT(1) DEFAULT 0 COMMENT 'Indica si se envio el email de admision'",
        'encuesta'                 => "TINYINT(1) DEFAULT 0 COMMENT 'Indica si se realizo la encuesta'",
        'conectado'               => "TINYINT(1) DEFAULT 0 COMMENT 'Indica si el alumno se ha conectado'",
        'fecha_conectado'         => "DATE NULL DEFAULT NULL COMMENT 'Fecha de conexion'",
        'email_1_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 1 enviado'",
        'email_1_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 1'",
        'email_2_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 2 enviado'",
        'email_2_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 2'",
        'email_3_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 3 enviado'",
        'email_3_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 3'",
        'email_4_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 4 enviado'",
        'email_4_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 4'",
        'email_5_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 5 enviado'",
        'email_5_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 5'",
        'email_6_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 6 enviado'",
        'email_6_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 6'",
        'email_7_check'           => "TINYINT(1) DEFAULT 0 COMMENT 'Email 7 enviado'",
        'email_7_fecha'           => "DATE NULL DEFAULT NULL COMMENT 'Fecha Email 7'",
        'eval_inicial'            => "VARCHAR(20) NULL DEFAULT NULL COMMENT 'Nota Evaluacion Inicial'",
        'fecha_eval_inicial'      => "DATE NULL DEFAULT NULL COMMENT 'Fecha Evaluacion Inicial'",
        'eval_final'              => "VARCHAR(20) NULL DEFAULT NULL COMMENT 'Nota Evaluacion Final'",
        'fecha_eval_final'        => "DATE NULL DEFAULT NULL COMMENT 'Fecha Evaluacion Final'",
        'nota_media'              => "VARCHAR(20) NULL DEFAULT NULL COMMENT 'Nota Media de Evaluaciones'",
        'observaciones_solicitante'=> "TEXT NULL DEFAULT NULL COMMENT 'Observaciones para el solicitante'",
        'llamada_inicio'          => "TINYINT(1) DEFAULT 0 COMMENT 'Llamada inicio curso check'",
        'llamada_mitad'           => "TINYINT(1) DEFAULT 0 COMMENT 'Llamada mitad curso check'",
        'llamada_7dias'           => "TINYINT(1) DEFAULT 0 COMMENT 'Llamada 7 dias fin check'",
        'llamada_cierre'          => "TINYINT(1) DEFAULT 0 COMMENT 'Llamada cierre check'",
        'llamada_4_fecha'         => "DATE NULL DEFAULT NULL COMMENT 'Fecha Llamada 4'",
        'llamada_5_fecha'         => "DATE NULL DEFAULT NULL COMMENT 'Fecha Llamada 5'",
        'llamada_6_fecha'         => "DATE NULL DEFAULT NULL COMMENT 'Fecha Llamada 6'",
        'llamada_8_fecha'         => "DATE NULL DEFAULT NULL COMMENT 'Fecha Llamada 8'",
        'no_pedir_nomina'         => "TINYINT(1) DEFAULT 0 COMMENT 'Llamada no pedir nomina check'"
    ];

    foreach ($fieldsToAdd as $fieldName => $definition) {
        if (!in_array($fieldName, $columns)) {
            $sql = "ALTER TABLE matriculas ADD COLUMN `$fieldName` $definition";
            $pdo->exec($sql);
            echo "✅ Columna '$fieldName' añadida correctamente.\n";
        } else {
            echo "ℹ️ La columna '$fieldName' ya existe.\n";
        }
    }

    echo "\n🎉 ¡MIGRACIÓN COMPLETADA CON ÉXITO!\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR EN MIGRACIÓN:\n" . $e->getMessage() . "\n";
}
echo "--- FIN DE MIGRACIÓN ---\n";
?>
