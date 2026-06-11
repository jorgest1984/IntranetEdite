<?php
// api_buscar_alumnos.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$exact = isset($_GET['exact']) && $_GET['exact'] == '1';
$type = trim($_GET['type'] ?? '');

if (strlen($q) < 1) { echo json_encode([]); exit; }

if ($exact) {
    // Búsqueda exacta por DNI o Nombre Completo (concatenado)
    $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                           WHERE dni = ? OR nombre = ? OR CONCAT(nombre, ' ', primer_apellido) = ?
                           LIMIT 1");
    $stmt->execute([$q, $q, $q]);
} else {
    if ($type === 'dni') {
        // Si no contiene ningún número (0-9), no se muestran resultados predictivos para DNI
        if (!preg_match('/[0-9]/', $q)) {
            echo json_encode([]);
            exit;
        }
        // Búsqueda por coincidencia en cualquier parte del DNI (contenga ese número)
        $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                               WHERE dni LIKE ? 
                               ORDER BY dni ASC 
                               LIMIT 10");
        $term = "%$q%";
        $stmt->execute([$term]);
    } else if ($type === 'nombre') {
        // Búsqueda por nombre o apellido
        $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                               WHERE nombre LIKE ? OR primer_apellido LIKE ? 
                               ORDER BY nombre ASC, primer_apellido ASC
                               LIMIT 10");
        $term = "$q%";
        $stmt->execute([$term, $term]);
    } else {
        // Comportamiento original si no se especifica tipo
        $stmt = $pdo->prepare("SELECT id, nombre, primer_apellido, dni FROM alumnos 
                               WHERE nombre LIKE ? OR primer_apellido LIKE ? OR dni LIKE ? 
                               ORDER BY nombre ASC, primer_apellido ASC
                               LIMIT 10");
        $term = "$q%";
        $stmt->execute([$term, $term, $term]);
    }
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
