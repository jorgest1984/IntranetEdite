<?php
require_once dirname(__DIR__) . '/includes/config.php';
$s = $pdo->query('DESCRIBE acciones_formativas');
while($r = $s->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . ' -> ' . $r['Type'] . "\n";
}
