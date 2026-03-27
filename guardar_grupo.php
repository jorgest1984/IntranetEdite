<?php
// guardar_grupo.php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: grupos.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : null;
$accion_id = (int)$_POST['accion_id'];

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
    'horas' => !empty($_POST['horas']) ? (int)$_POST['horas'] : 0
];

try {
    if ($id) {
        // Update
        $sql = "UPDATE grupos SET 
            accion_id = :accion_id, numero_grupo = :numero_grupo, codigo_plataforma = :codigo_plataforma, 
            id_plataforma = :id_plataforma, centro_id = :centro_id, tutor_id = :tutor_id, 
            fecha_inicio = :fecha_inicio, fecha_mitad = :fecha_mitad, fecha_7_dias = :fecha_7_dias, 
            fecha_fin = :fecha_fin, modalidad = :modalidad, asignacion = :asignacion, 
            situacion = :situacion, horas = :horas
            WHERE id = :id";
        $data['id'] = $id;
    } else {
        // Insert
        $sql = "INSERT INTO grupos (
            accion_id, numero_grupo, codigo_plataforma, id_plataforma, centro_id, tutor_id, 
            fecha_inicio, fecha_mitad, fecha_7_dias, fecha_fin, modalidad, asignacion, 
            situacion, horas
        ) VALUES (
            :accion_id, :numero_grupo, :codigo_plataforma, :id_plataforma, :centro_id, :tutor_id, 
            :fecha_inicio, :fecha_mitad, :fecha_7_dias, :fecha_fin, :modalidad, :asignacion, 
            :situacion, :horas
        )";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    header("Location: ficha_accion_formativa.php?id=$accion_id&tab=grupos&success=1");
    exit();

} catch (Exception $e) {
    die("Error al guardar el grupo: " . $e->getMessage());
}
