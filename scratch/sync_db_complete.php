<?php
// scratch/sync_db_complete.php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'includes/config.php';

function call_bridge($sql, $action = 'query') {
    $url = 'https://pre-gestion.grupoefp.es/api_bridge.php';
    $token = 'dbbea329538b1694971d7ee66cc3e4673';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'token' => $token,
        'sql' => $sql,
        'action' => $action
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

echo "1. Obtener lista de tablas de preproducción...\n";
$resTables = call_bridge("SHOW TABLES");
if (!$resTables || !isset($resTables['data'])) {
    die("Error: No se pudo obtener la lista de tablas de preproducción.\n");
}

$tables = [];
foreach ($resTables['data'] as $row) {
    $tables[] = array_values($row)[0];
}
echo "Encontradas " . count($tables) . " tablas en preproducción.\n\n";

echo "2. Recreando tablas localmente y copiando datos...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tables as $table) {
    echo "Procesando tabla: $table\n";
    
    // 2.1 DROP local table
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
    
    // 2.2 SHOW CREATE TABLE from remote
    $resCreate = call_bridge("SHOW CREATE TABLE `$table`");
    if (!$resCreate || !isset($resCreate['data'][0]['Create Table'])) {
        echo " -> Error: No se pudo obtener CREATE TABLE para $table. Saltando.\n";
        continue;
    }
    $createStatement = $resCreate['data'][0]['Create Table'];
    $pdo->exec($createStatement);
    echo " -> Tabla creada.\n";
    
    // 2.3 Copiar hasta 200 filas de datos para pruebas locales
    $resData = call_bridge("SELECT * FROM `$table` LIMIT 200");
    if ($resData && isset($resData['data']) && !empty($resData['data'])) {
        $rows = $resData['data'];
        $cols = array_keys($rows[0]);
        
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sqlInsert = "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sqlInsert);
        
        $count = 0;
        foreach ($rows as $row) {
            $values = [];
            foreach ($cols as $col) {
                $values[] = $row[$col];
            }
            try {
                $stmt->execute($values);
                $count++;
            } catch (Exception $ex) {
                // Si falla un registro (por ejemplo por triggers, claves duplicadas, etc) lo saltamos
            }
        }
        echo " -> Insertadas $count / " . count($rows) . " filas.\n";
    } else {
        echo " -> Sin datos o tabla vacía.\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "\n¡Sincronización completa finalizada con éxito!\n";
?>
