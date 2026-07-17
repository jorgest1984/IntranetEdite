<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=intranet_formacion;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('SHOW COLUMNS FROM acciones_formativas');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo $c['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
