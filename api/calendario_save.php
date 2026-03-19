<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$fecha = trim($_POST['fecha'] ?? '');
if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida']);
    exit;
}

$es_vacacion = isset($_POST['es_vacacion']) ? 1 : 0;
$es_nacional = isset($_POST['es_nacional']) ? 1 : 0;
$local_granada = isset($_POST['local_granada']) ? 1 : 0;
$local_almeria = isset($_POST['local_almeria']) ? 1 : 0;
$local_valladolid = isset($_POST['local_valladolid']) ? 1 : 0;
$local_vicar = isset($_POST['local_vicar']) ? 1 : 0;
$local_dorfland = isset($_POST['local_dorfland']) ? 1 : 0;
$local_madrid = isset($_POST['local_madrid']) ? 1 : 0;

$is_empty = !($es_vacacion || $es_nacional || $local_granada || $local_almeria || $local_valladolid || $local_vicar || $local_dorfland || $local_madrid);

try {
    if ($is_empty) {
        // Remove from DB if nothing is selected
        $stmt = $pdo->prepare("DELETE FROM calendario_dias WHERE fecha = ?");
        $stmt->execute([$fecha]);
    } else {
        // Insert or update
        $stmt = $pdo->prepare("
            INSERT INTO calendario_dias (
                fecha, es_vacacion, es_nacional, local_granada, local_almeria, local_valladolid, local_vicar, local_dorfland, local_madrid
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                es_vacacion = VALUES(es_vacacion),
                es_nacional = VALUES(es_nacional),
                local_granada = VALUES(local_granada),
                local_almeria = VALUES(local_almeria),
                local_valladolid = VALUES(local_valladolid),
                local_vicar = VALUES(local_vicar),
                local_dorfland = VALUES(local_dorfland),
                local_madrid = VALUES(local_madrid)
        ");
        $stmt->execute([
            $fecha, $es_vacacion, $es_nacional, $local_granada, $local_almeria, 
            $local_valladolid, $local_vicar, $local_dorfland, $local_madrid
        ]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
}
