<?php
// test_moodle_photo.php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

$moodle = new MoodleAPI($pdo);

$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE foto IS NOT NULL AND foto != '' AND moodle_user_id IS NOT NULL AND moodle_user_id > 0 LIMIT 1");
$stmt->execute();
$alumno = $stmt->fetch();

if (!$alumno) {
    // Si no hay ninguno con moodle_user_id, buscar sólo con foto y le asignamos un ID temporal o avisamos
    $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE foto IS NOT NULL AND foto != '' LIMIT 1");
    $stmt->execute();
    $alumno = $stmt->fetch();
}

if (!$alumno) {
    die("Alumno no encontrado.");
}

echo "Alumno: " . $alumno['nombre'] . " " . $alumno['primer_apellido'] . "<br>\n";
echo "Moodle User ID: " . $alumno['moodle_user_id'] . "<br>\n";
echo "Foto Path: " . $alumno['foto'] . "<br>\n";

if (empty($alumno['moodle_user_id'])) {
    die("El alumno no tiene Moodle User ID.");
}

if (empty($alumno['foto']) || !file_exists($alumno['foto'])) {
    die("El alumno no tiene foto o el archivo no existe: " . $alumno['foto']);
}

try {
    echo "Subiendo y sincronizando foto a Moodle...<br>\n";
    $result = $moodle->updateUserPicture($alumno['moodle_user_id'], $alumno['foto']);
    echo "Resultado:<br>\n";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} catch (Exception $e) {
    echo "EXCEPCIÓN CAPTURADA: " . $e->getMessage() . "<br>\n";
    echo "Detalles:<br>\n<pre>" . $e->getTraceAsString() . "</pre>\n";
}
