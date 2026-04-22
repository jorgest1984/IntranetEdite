<?php
require_once 'includes/config.php';
function desc($table, $pdo) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "Table: $table\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error describing $table: " . $e->getMessage() . "\n";
    }
}
desc('empresas', $pdo);
desc('usuarios', $pdo);
desc('facturas', $pdo);
?>
