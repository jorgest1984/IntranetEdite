<?php
require_once 'includes/config.php';
$stmt = $pdo->query("DESCRIBE matriculas"); 
$cols = $stmt->fetchAll();
foreach ($cols as $c) {
    if (isset($c['Field'])) echo $c['Field'] . " ";
    else if (isset($c['field'])) echo $c['field'] . " ";
}
?>
