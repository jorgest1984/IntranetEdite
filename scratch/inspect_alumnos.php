<?php
// scratch/inspect_alumnos.php
// Directly connect to the database to avoid session issues from CLI

$hosts = ['127.0.0.1', 'localhost'];
$ports = ['3306', '3307', '3308'];
$user = 'root';
$pass = '';
$db = 'intranet_formacion';

$connected = false;
$pdo = null;

foreach ($hosts as $host) {
    foreach ($ports as $port) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            echo "Successfully connected to $host:$port\n";
            $connected = true;
            break 2;
        } catch (Exception $e) {
            echo "Failed to connect to $host:$port: " . $e->getMessage() . "\n";
        }
    }
}

if (!$connected) {
    // Try production DB credentials but on 127.0.0.1
    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", "gestion.efp2026", "Oy0v?ggswFBr6d0~");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                echo "Successfully connected to $host:$port with production user\n";
                $connected = true;
                break 2;
            } catch (Exception $e) {
                echo "Failed to connect to $host:$port (prod credentials): " . $e->getMessage() . "\n";
            }
        }
    }
}

if ($connected && $pdo) {
    try {
        echo "--- SCHEMA OF alumnos ---\n";
        $stmt = $pdo->query("DESCRIBE alumnos");
        while ($row = $stmt->fetch()) {
            print_r($row);
        }
        
        echo "\n--- TOTAL ROWS IN alumnos ---\n";
        $count = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
        echo "Count: $count\n";
        
        echo "\n--- SAMPLE ROWS IN alumnos ---\n";
        $rows = $pdo->query("SELECT id, nombre, primer_apellido, dni, email FROM alumnos LIMIT 10")->fetchAll();
        foreach ($rows as $r) {
            print_r($r);
        }
    } catch (Exception $e) {
        echo "Database query error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Could not connect to any local MySQL instance.\n";
}
