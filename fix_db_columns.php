<?php
/**
 * Script de reparación de base de datos - Intranet Edite
 * Añade columnas faltantes a la tabla acciones_formativas
 */
require_once 'includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Iniciando reparación de base de datos...\n";

$columns = [
    'solicitante' => 'VARCHAR(255) DEFAULT NULL',
    'sector'      => 'VARCHAR(255) DEFAULT NULL',
    'proveedor'   => 'VARCHAR(255) DEFAULT NULL',
    'catalogo'    => 'VARCHAR(255) DEFAULT NULL',
    'consultora'  => 'VARCHAR(255) DEFAULT NULL'
];

try {
    foreach ($columns as $column => $definition) {
        echo "Verificando columna '$column'...";
        
        // Comprobar si la columna existe
        $check = $pdo->query("SHOW COLUMNS FROM acciones_formativas LIKE '$column'");
        if ($check->rowCount() == 0) {
            echo " No existe. Añadiendo...";
            $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN $column $definition");
            echo " OK.\n";
        } else {
            echo " Ya existe.\n";
        }
    }
    
    echo "\nReparación completada con éxito.\n";
    echo "Ya puedes cerrar esta ventana y volver a la intranet.";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "\nSi el error persiste, contacta con soporte técnico.";
}
?>
