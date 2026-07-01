<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
    $stmt->execute([12]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "ALL DATABASE FIELDS FOR ID 12:\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
