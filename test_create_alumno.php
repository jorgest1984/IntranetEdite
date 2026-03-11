<?php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

$moodle = new MoodleAPI($pdo);

// Mocking POST data
$_POST['action'] = 'create';
$_POST['nombre'] = 'Test';
$_POST['apellidos'] = 'User';
$_POST['dni'] = '99999999X';
$_POST['email'] = 'testuser_' . time() . '@example.com';
$_POST['telefono'] = '123456789';

echo "Simulando creación de alumno...\n";

try {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $dni = trim($_POST['dni']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    
    $pdo->beginTransaction();
    echo "Transacción iniciada.\n";
    
    $moodleUserId = null;
    if ($moodle->isConfigured()) {
        echo "Moodle configurado. Intentando sincronización...\n";
        try {
            $tempPassword = 'ef_' . strtoupper(substr($dni, -4)) . '!' . rand(10,99);
            $username = strtolower(explode('@', $email)[0]) . '_' . substr($dni, -3);
            
            $moodleCreate = $moodle->createUser($username, $tempPassword, $nombre, $apellidos, $email);
            if (isset($moodleCreate[0]['id'])) {
                $moodleUserId = $moodleCreate[0]['id'];
                echo "Usuario creado en Moodle ID: $moodleUserId\n";
            } else {
                echo "Moodle no devolvió ID.\n";
                print_r($moodleCreate);
            }
        } catch (Exception $me) {
            echo "Error Moodle: " . $me->getMessage() . "\n";
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO alumnos (nombre, apellidos, dni, email, telefono, moodle_user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $res = $stmt->execute([$nombre, $apellidos, $dni, $email, $telefono, $moodleUserId]);
    
    if ($res) {
        echo "Insertado en DB local con éxito.\n";
    } else {
        echo "Fallo en INSERT local.\n";
    }
    
    $pdo->commit();
    echo "Commit realizado.\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ERROR GLOBAL: " . $e->getMessage() . "\n";
}
?>
