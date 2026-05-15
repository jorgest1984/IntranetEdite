<?php
// api_buscar_alumnos.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$exact = isset($_GET['exact']) && $_GET['exact'] == '1';

if (strlen($q) < 3 && !$exact) { echo json_encode([]); exit; }

if ($exact) {
    // Búsqueda exacta por DNI o Nombre Completo (concatenado)
    $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                           WHERE dni = ? OR nombre = ? OR CONCAT(nombre, ' ', primer_apellido) = ?
                           LIMIT 1");
    $stmt->execute([$q, $q, $q]);
} else {
    // Búsqueda parcial
    $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                           WHERE nombre LIKE ? OR primer_apellido LIKE ? OR dni LIKE ? 
                           LIMIT 10");
    $term = "%$q%";
    $stmt->execute([$term, $term, $term]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
