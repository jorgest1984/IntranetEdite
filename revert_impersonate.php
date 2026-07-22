<?php
// revert_impersonate.php
require_once 'includes/config.php';

if (isset($_SESSION['impersonator_id'])) {
    $admin_id = $_SESSION['impersonator_id'];
    
    // Cargar datos del administrador original
    $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ? AND u.activo = 1");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nombre_completo'] = trim($user['nombre'] . ' ' . $user['apellidos']);
        $_SESSION['rol_id'] = $user['rol_id'];
        $_SESSION['rol_nombre'] = $user['rol_nombre'];
        $_SESSION['centro_id'] = $user['centro_id'] ?? null;
        
        audit_log($pdo, 'USUARIO_REVERT_IMPERSONATE', 'usuarios', $admin_id);
    }
    
    unset($_SESSION['impersonator_id']);
    
    header("Location: usuarios.php");
    exit();
} else {
    header("Location: home.php");
    exit();
}
