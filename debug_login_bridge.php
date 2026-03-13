<?php
// debug_login_bridge.php
require_once 'includes/config.php';

$username = 'admin';
$password = 'Admin123!';

echo "--- PRUEBA DE LOGIN VÍA BRIDGE ---\n";
echo "Buscando usuario: $username\n";

try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, activo FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "❌ ERROR: Usuario '$username' no encontrado o tabla 'usuarios' no existe.\n";
        
        echo "\nListando tablas disponibles en la base de datos:\n";
        $stmt_tables = $pdo->query("SHOW TABLES");
        $tables = $stmt_tables->fetchAll();
        
        if (empty($tables)) {
            echo "⚠️ La base de datos está VACÍA.\n";
        } else {
            foreach ($tables as $table) {
                echo "- " . current($table) . "\n";
            }
        }
    } else {
        echo "✅ Usuario encontrado.\n";
        echo "Username en DB: " . $user['username'] . "\n";
        echo "Estado Activo: " . $user['activo'] . "\n";
        echo "Hash en DB: " . $user['password_hash'] . "\n";
        
        echo "\nVerificando contraseña '$password'...\n";
        if (password_verify($password, $user['password_hash'])) {
            echo "✅ ¡ÉXITO! La contraseña es correcta.\n";
        } else {
            echo "❌ ERROR: La contraseña NO coincide con el hash.\n";
            
            // Generar un hash nuevo para comparar
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            echo "\nSugerencia: Si el hash es antiguo o incorrecto, deberías actualizarlo.\n";
            echo "Hash correcto para '$password': $new_hash\n";
        }
    }
} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
}

echo "\n--- FIN DE PRUEBA ---\n";
?>
