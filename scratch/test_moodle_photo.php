<?php
// scratch/test_moodle_photo.php
require_once '../includes/auth.php';
require_once '../includes/moodle_api.php';

$moodle = new MoodleAPI($pdo);

$alumno_id = 48; // Let's use Jorge's ID (which was shown in the screenshot)
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->execute([$alumno_id]);
$alumno = $stmt->fetch();

if (!$alumno) {
    die("Alumno no encontrado.");
}

echo "Alumno: " . $alumno['nombre'] . " " . $alumno['primer_apellido'] . "\n";
echo "Moodle User ID: " . $alumno['moodle_user_id'] . "\n";
echo "Foto Path: " . $alumno['foto'] . "\n";

if (empty($alumno['moodle_user_id'])) {
    die("El alumno no tiene Moodle User ID.");
}

if (empty($alumno['foto']) || !file_exists('../' . $alumno['foto'])) {
    die("El alumno no tiene foto o el archivo no existe: " . '../' . $alumno['foto']);
}

try {
    echo "Subiendo y sincronizando foto a Moodle...\n";
    $result = $moodle->updateUserPicture($alumno['moodle_user_id'], '../' . $alumno['foto']);
    echo "Resultado:\n";
    print_r($result);
} catch (Exception $e) {
    echo "EXCEPCIÓN CAPTURADA: " . $e->getMessage() . "\n";
    echo "Detalles:\n" . $e->getTraceAsString() . "\n";
}
