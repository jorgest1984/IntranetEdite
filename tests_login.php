<?php
require 'includes/config.php';

echo "1. Creando hash manual para 'Admin123!'...\n";
$hash = password_hash('Admin123!', PASSWORD_DEFAULT);
echo "Hash: " . $hash . "\n";
echo "Longitud: " . strlen($hash) . "\n\n";

echo "2. Actualizando DB...\n";
$stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE username = 'admin'");
$stmt->execute([$hash]);

echo "3. Leyendo de la DB...\n";
$stmt = $pdo->query("SELECT id, username, password_hash FROM usuarios WHERE username = 'admin'");
$user = $stmt->fetch();
print_r($user);

echo "\n4. Verificando contraseña...\n";
if (password_verify('Admin123!', $user['password_hash'])) {
    echo "¡ÉXITO! La contraseña coincide con el hash en DB.\n";
} else {
    echo "ERROR. La contraseña NO coincide.\n";
}
