<?php
// scratch/sync_db_schemas.php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';

function call_bridge($sql) {
    $url = 'https://pre-gestion.grupoefp.es/api_bridge.php';
    $token = 'dbbea329538b1694971d7ee66cc3e4673';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'token' => $token,
        'sql' => $sql,
        'action' => 'query'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Bridge Error: ' . curl_error($ch) . "\n";
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

// 1. Obtener tablas de preproducción
$resRemote = call_bridge("SHOW TABLES");
if (!$resRemote || !isset($resRemote['data'])) {
    die("Error obteniendo tablas de preproducción.\n");
}
$remoteTables = [];
foreach ($resRemote['data'] as $row) {
    $remoteTables[] = array_values($row)[0];
}

// 2. Obtener tablas locales
$localTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// 3. Determinar tablas faltantes
$missingTables = array_diff($remoteTables, $localTables);
if (empty($missingTables)) {
    echo "¡La estructura de base de datos local está completamente al día!\n";
    exit();
}

echo "Tablas faltantes localmente: " . implode(", ", $missingTables) . "\n\n";

// Desactivar claves foráneas temporalmente para poder crear las tablas en cualquier orden
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($missingTables as $table) {
    echo "Sincronizando tabla: $table...\n";
    
    // Obtener estructura
    $resCreate = call_bridge("SHOW CREATE TABLE `$table`");
    if ($resCreate && isset($resCreate['data'][0]['Create Table'])) {
        $createStatement = $resCreate['data'][0]['Create Table'];
        
        try {
            $pdo->exec($createStatement);
            echo "-> ¡Creada con éxito!\n";
        } catch (Exception $e) {
            echo "-> Error al crear: " . $e->getMessage() . "\n";
        }
    } else {
        echo "-> No se pudo obtener la estructura de preproducción.\n";
    }
}

// Reactivar claves foráneas
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "\nSincronización finalizada.\n";
?>
