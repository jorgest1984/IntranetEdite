<?php
// guardar_grupo.php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: grupos.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;
$accion_id = (int)$_POST['accion_id'];

// Self-healing database check
function check_and_add_columns($pdo) {
    $columns = [
        'modulacion' => "VARCHAR(150) NULL",
        'horario_desde' => "VARCHAR(5) NULL",
        'horario_hasta' => "VARCHAR(5) NULL",
        'horario_desde_2' => "VARCHAR(5) NULL",
        'horario_hasta_2' => "VARCHAR(5) NULL",
        'horario_presencial_desde' => "VARCHAR(5) NULL",
        'horario_presencial_hasta' => "VARCHAR(5) NULL",
        'horario_presencial_desde_2' => "VARCHAR(5) NULL",
        'horario_presencial_hasta_2' => "VARCHAR(5) NULL",
        'horario_distancia_desde' => "VARCHAR(5) NULL",
        'horario_distancia_hasta' => "VARCHAR(5) NULL",
        'horario_distancia_desde_2' => "VARCHAR(5) NULL",
        'horario_distancia_hasta_2' => "VARCHAR(5) NULL",
        'horario_telef_desde' => "VARCHAR(5) NULL",
        'horario_telef_hasta' => "VARCHAR(5) NULL",
        'horario_telef_desde_2' => "VARCHAR(5) NULL",
        'horario_telef_hasta_2' => "VARCHAR(5) NULL",
        'dias_lunes' => "TINYINT(1) DEFAULT 0",
        'dias_martes' => "TINYINT(1) DEFAULT 0",
        'dias_miercoles' => "TINYINT(1) DEFAULT 0",
        'dias_jueves' => "TINYINT(1) DEFAULT 0",
        'dias_viernes' => "TINYINT(1) DEFAULT 0",
        'dias_sabado' => "TINYINT(1) DEFAULT 0",
        'dias_domingo' => "TINYINT(1) DEFAULT 0",
        'horario_info' => "VARCHAR(100) NULL",
        'tutor_id_2' => "INT NULL",
        'mostrar_tutor' => "TINYINT(1) DEFAULT 1",
        'tutor_reserva_id' => "INT NULL",
        'teleformador_id' => "INT NULL",
        'tecnico_id' => "INT NULL",
        'fecha_modificado' => "DATE NULL",
        'coste_hora_aula' => "DECIMAL(10,2) DEFAULT 0.00",
        'coste_hora_profesor' => "DECIMAL(10,2) DEFAULT 0.00",
        'encuestas_finales' => "INT DEFAULT 0",
        'doc_ficha_aula' => "TINYINT(1) DEFAULT 0",
        'doc_cv_profesor' => "TINYINT(1) DEFAULT 0",
        'doc_contrato_profesor' => "TINYINT(1) DEFAULT 0",
        'doc_contrato_aula' => "TINYINT(1) DEFAULT 0",
        'doc_cert_ejecucion' => "TINYINT(1) DEFAULT 0",
        'observaciones' => "TEXT NULL",
        'modificacion_texto' => "TEXT NULL",
        'motivo_anulacion' => "TEXT NULL",
        'justificacion' => "TEXT NULL",
        'orientacion_ugt' => "TEXT NULL",
        'notas_internas' => "TEXT NULL",
        'material_facturado' => "VARCHAR(5) DEFAULT 'NO'",
        'inspeccionado' => "TINYINT(1) DEFAULT 0",
        'fecha_inspeccion' => "DATE NULL"
    ];

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM grupos");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing)) {
                $pdo->exec("ALTER TABLE grupos ADD COLUMN `$name` $definition");
            }
        }
    } catch (Exception $e) {}
}

check_and_add_columns($pdo);

$data = [
    'accion_id' => $accion_id,
    'numero_grupo' => $_POST['numero_grupo'] ?? '',
    'codigo_plataforma' => $_POST['codigo_plataforma'] ?? '',
    'id_plataforma' => $_POST['id_plataforma'] ?? '',
    'centro_id' => !empty($_POST['centro_id']) ? (int)$_POST['centro_id'] : null,
    'tutor_id' => !empty($_POST['tutor_id']) ? (int)$_POST['tutor_id'] : null,
    'fecha_inicio' => !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null,
    'fecha_mitad' => !empty($_POST['fecha_mitad']) ? $_POST['fecha_mitad'] : null,
    'fecha_7_dias' => !empty($_POST['fecha_7_dias']) ? $_POST['fecha_7_dias'] : null,
    'fecha_fin' => !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null,
    'modalidad' => $_POST['modalidad'] ?? '',
    'asignacion' => $_POST['asignacion'] ?? '',
    'situacion' => $_POST['situacion'] ?? '',
    'horas' => !empty($_POST['horas']) ? (int)$_POST['horas'] : 0,

    'modulacion' => $_POST['modulacion'] ?? '',
    'horario_desde' => $_POST['horario_desde'] ?? '',
    'horario_hasta' => $_POST['horario_hasta'] ?? '',
    'horario_desde_2' => $_POST['horario_desde_2'] ?? '',
    'horario_hasta_2' => $_POST['horario_hasta_2'] ?? '',

    'horario_presencial_desde' => $_POST['horario_presencial_desde'] ?? '',
    'horario_presencial_hasta' => $_POST['horario_presencial_hasta'] ?? '',
    'horario_presencial_desde_2' => $_POST['horario_presencial_desde_2'] ?? '',
    'horario_presencial_hasta_2' => $_POST['horario_presencial_hasta_2'] ?? '',

    'horario_distancia_desde' => $_POST['horario_distancia_desde'] ?? '',
    'horario_distancia_hasta' => $_POST['horario_distancia_hasta'] ?? '',
    'horario_distancia_desde_2' => $_POST['horario_distancia_desde_2'] ?? '',
    'horario_distancia_hasta_2' => $_POST['horario_distancia_hasta_2'] ?? '',

    'horario_telef_desde' => $_POST['horario_telef_desde'] ?? '',
    'horario_telef_hasta' => $_POST['horario_telef_hasta'] ?? '',
    'horario_telef_desde_2' => $_POST['horario_telef_desde_2'] ?? '',
    'horario_telef_hasta_2' => $_POST['horario_telef_hasta_2'] ?? '',

    'dias_lunes' => isset($_POST['dias_lunes']) ? 1 : 0,
    'dias_martes' => isset($_POST['dias_martes']) ? 1 : 0,
    'dias_miercoles' => isset($_POST['dias_miercoles']) ? 1 : 0,
    'dias_jueves' => isset($_POST['dias_jueves']) ? 1 : 0,
    'dias_viernes' => isset($_POST['dias_viernes']) ? 1 : 0,
    'dias_sabado' => isset($_POST['dias_sabado']) ? 1 : 0,
    'dias_domingo' => isset($_POST['dias_domingo']) ? 1 : 0,

    'horario_info' => $_POST['horario_info'] ?? '',
    'tutor_id_2' => !empty($_POST['tutor_id_2']) ? (int)$_POST['tutor_id_2'] : null,
    'mostrar_tutor' => isset($_POST['mostrar_tutor']) ? 1 : 0,
    'tutor_reserva_id' => !empty($_POST['tutor_reserva_id']) ? (int)$_POST['tutor_reserva_id'] : null,
    'teleformador_id' => !empty($_POST['teleformador_id']) ? (int)$_POST['teleformador_id'] : null,
    'tecnico_id' => !empty($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : null,

    'fecha_modificado' => !empty($_POST['fecha_modificado']) ? $_POST['fecha_modificado'] : null,
    'coste_hora_aula' => !empty($_POST['coste_hora_aula']) ? (float)$_POST['coste_hora_aula'] : 0.00,
    'coste_hora_profesor' => !empty($_POST['coste_hora_profesor']) ? (float)$_POST['coste_hora_profesor'] : 0.00,
    'encuestas_finales' => !empty($_POST['encuestas_finales']) ? (int)$_POST['encuestas_finales'] : 0,

    'doc_ficha_aula' => isset($_POST['doc_ficha_aula']) ? 1 : 0,
    'doc_cv_profesor' => isset($_POST['doc_cv_profesor']) ? 1 : 0,
    'doc_contrato_profesor' => isset($_POST['doc_contrato_profesor']) ? 1 : 0,
    'doc_contrato_aula' => isset($_POST['doc_contrato_aula']) ? 1 : 0,
    'doc_cert_ejecucion' => isset($_POST['doc_cert_ejecucion']) ? 1 : 0,

    'observaciones' => $_POST['observaciones'] ?? '',
    'modificacion_texto' => $_POST['modificacion_texto'] ?? '',
    'motivo_anulacion' => $_POST['motivo_anulacion'] ?? '',
    'justificacion' => $_POST['justificacion'] ?? '',
    'orientacion_ugt' => $_POST['orientacion_ugt'] ?? '',
    'notas_internas' => $_POST['notas_internas'] ?? '',

    'material_facturado' => $_POST['material_facturado'] ?? 'NO',
    'inspeccionado' => isset($_POST['inspeccionado']) ? 1 : 0,
    'fecha_inspeccion' => !empty($_POST['fecha_inspeccion']) ? $_POST['fecha_inspeccion'] : null
];

try {
    if ($id) {
        // Update
        $fields = [];
        foreach ($data as $key => $val) {
            $fields[] = "`$key` = :$key";
        }
        $sql = "UPDATE grupos SET " . implode(", ", $fields) . " WHERE id = :id";
        $data['id'] = $id;
    } else {
        // Insert
        $cols = array_keys($data);
        $placeholders = array_map(function($c) { return ":$c"; }, $cols);
        $sql = "INSERT INTO grupos (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $placeholders) . ")";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    header("Location: relacion_alumnos.php?grupo_id=" . ($id ?: $pdo->lastInsertId()) . "&success=1");
    exit();

} catch (Exception $e) {
    die("Error al guardar el grupo: " . $e->getMessage());
}
