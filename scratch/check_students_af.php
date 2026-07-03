<?php
require_once __DIR__ . '/../includes/config.php';

$title = 'ADGN0108%';
$stmt = $pdo->prepare("SELECT id FROM acciones_formativas WHERE titulo LIKE ?");
$stmt->execute([$title]);
$af = $stmt->fetch();

if (!$af) {
    echo "No action found.\n";
    exit;
}

$af_id = $af['id'];
echo "AF ID: $af_id\n";

$stmt = $pdo->query("SELECT id FROM grupos WHERE accion_id = $af_id");
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Grupos:\n";
print_r($grupos);

if ($grupos) {
    foreach ($grupos as $g) {
        $g_id = $g['id'];
        $stmt = $pdo->query("SELECT * FROM matriculas WHERE grupo_id = $g_id");
        $matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Matriculas for Grupo $g_id:\n";
        print_r($matriculas);
        
        if ($matriculas) {
            foreach ($matriculas as $m) {
                $a_id = $m['alumno_id'];
                $stmt = $pdo->query("SELECT * FROM alumnos WHERE id = $a_id");
                $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "Alumno $a_id:\n";
                print_r($alumno);
            }
        }
    }
}
