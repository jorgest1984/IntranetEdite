<?php
// scratch/test_db.php
try {
    // Try to connect to 127.0.0.1 with production credentials
    $host = '127.0.0.1';
    $user = 'gestion.efp2026';
    $pass = 'Oy0v?ggswFBr6d0~';
    $db   = 'intranet_formacion';
    
    echo "Connecting to MySQL via TCP (127.0.0.1)...\n";
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESSFULLY CONNECTED!\n\n";
    
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $val = $row['valor'];
        if ($row['clave'] === 'moodle_token') {
            $val = substr($val, 0, 5) . '...' . substr($val, -5);
        }
        echo "{$row['clave']}: {$val}\n";
    }
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
