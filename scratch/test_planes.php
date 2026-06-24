<?php
require_once "../includes/config.php";
try {
    $planes = $pdo->query("SELECT id, nombre, codigo_expediente FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "OK";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
