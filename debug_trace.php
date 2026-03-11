<?php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

$moodle = new MoodleAPI($pdo);
try {
    $mUsers = $moodle->getAllUsers();
    if (isset($mUsers['users'])) {
        echo "Total en Moodle: " . count($mUsers['users']) . "\n";
        foreach ($mUsers['users'] as $mu) {
            echo "Procesando: " . $mu['email'] . " (ID Moodle: " . $mu['id'] . ")\n";
            
            if ($mu['username'] == 'admin' || $mu['username'] == 'guest') {
                echo "  -> Saltando admin/guest\n";
                continue;
            }
            
            $stC = $pdo->prepare("SELECT id, dni, email FROM alumnos WHERE email = ? OR moodle_user_id = ?");
            $stC->execute([$mu['email'], $mu['id']]);
            $existing = $stC->fetch();
            
            if ($existing) {
                echo "  -> YA EXISTE: ID Local " . $existing['id'] . " (DNI: " . $existing['dni'] . ")\n";
            } else {
                echo "  -> NO existe. Procediendo a insertar...\n";
                // Verificamos si el DNI 'M-id' ya existe (por si acaso alguien borró y el ID se repitió - improbable pero bueno)
                $dni = 'M-' . $mu['id'];
                $stD = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ?");
                $stD->execute([$dni]);
                if ($stD->rowCount() > 0) {
                     echo "  -> ERROR: El DNI generado $dni ya existe para otro alumno!\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
