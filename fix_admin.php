<?php
/**
 * fix_admin.php - SCRIPT DE EMERGENCIA
 * Sube este archivo a la raíz de tu servidor Plesk (grupoefp.es)
 * y ábrelo en tu navegador para resetear la contraseña del administrador.
 */

// --- CONFIGURACIÓN DE BASE DE DATOS LOCAL ---
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'intranet_formacion');
define('LOCAL_DB_USER', 'gestion.efp2026');
define('LOCAL_DB_PASS', 'Oy0v?ggswFBr6d0~');

echo "--- RESETEANDO USUARIO ADMINISTRADOR ---\n<pre>";

try {
    $pdo = new PDO("mysql:host=" . LOCAL_DB_HOST . ";dbname=" . LOCAL_DB_NAME . ";charset=utf8mb4", LOCAL_DB_USER, LOCAL_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username = 'admin';
    $password = 'Admin123!';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 1. Verificar si existe, si no, crear
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ?, activo = 1 WHERE username = ?");
        $stmt->execute([$hash, $username]);
        echo "✅ Usuario 'admin' actualizado con la contraseña: $password\n";
    } else {
        // Crear
        $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, nombre, apellidos, email, rol_id, activo) VALUES (?, ?, 'Admin', 'Sistema', 'admin@empresa.com', 1, 1)");
        $stmt->execute([$username, $hash]);
        echo "✅ Usuario 'admin' no existía. Se ha creado con la contraseña: $password\n";
    }

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>--- OPERACIÓN FINALIZADA ---\n";
echo "Por favor, BORRA este archivo del servidor una vez hayas podido entrar.";
?>
