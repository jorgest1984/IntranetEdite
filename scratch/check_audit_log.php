<?php
require 'includes/config.php';
$s = $pdo->query('DESCRIBE audit_log');
while($r = $s->fetch()) {
    echo $r['Field'] . ": " . $r['Type'] . " (Null: " . $r['Null'] . ")\n";
}
