<?php
// login_dev.php - Temporary bypass script for local testing
require_once 'includes/config.php';

$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['nombre_completo'] = 'Administrador Local';
$_SESSION['rol_id'] = 1; // Administrador
$_SESSION['rol_nombre'] = 'Administrador';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header("Location: buscar_alumnos.php");
exit();
