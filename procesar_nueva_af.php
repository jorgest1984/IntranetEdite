<?php
// procesar_nueva_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit();

$titulo = $_POST['titulo'] ?? '';
$abreviatura = $_POST['abreviatura'] ?? '';
$num_accion = $_POST['num_accion'] ?? '';
$plan_id = !empty($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;
$modalidad = $_POST['modalidad'] ?? 'Teleformación';
$duracion = (int)$_POST['duracion'];
$familia = $_POST['familia_profesional'] ?? '';
$crear_moodle = isset($_POST['crear_moodle']);

try {
    $pdo->beginTransaction();

    $id_plataforma = null;

    // 1. Crear en Moodle si se solicita
    if ($crear_moodle) {
        $moodle = new MoodleAPI($pdo);
        if ($moodle->isConfigured()) {
            $moodleResult = $moodle->createCourse($titulo, $abreviatura);
            if (!empty($moodleResult) && isset($moodleResult[0]['id'])) {
                $id_plataforma = $moodleResult[0]['id'];
            }
        }
    }

    // 2. Insertar en DB local
    $sql = "INSERT INTO acciones_formativas (
        titulo, abreviatura, num_accion, plan_id, modalidad, 
        duracion, familia_profesional, id_plataforma, estado
    ) VALUES (
        :titulo, :abreviatura, :num_accion, :plan_id, :modalidad, 
        :duracion, :familia, :id_plataforma, 'Programable'
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'titulo' => $titulo,
        'abreviatura' => $abreviatura,
        'num_accion' => $num_accion,
        'plan_id' => $plan_id,
        'modalidad' => $modalidad,
        'duracion' => $duracion,
        'familia' => $familia,
        'id_plataforma' => $id_plataforma
    ]);

    $new_id = $pdo->lastInsertId();
    $pdo->commit();

    header("Location: ficha_accion_formativa.php?id=$new_id&created=1");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error al crear la acción formativa: " . $e->getMessage());
}
?>
