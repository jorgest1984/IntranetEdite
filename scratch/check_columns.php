<?php
require 'includes/config.php';
$s = $pdo->query('DESCRIBE alumnos');
while($r = $s->fetch()) {
    echo $r['Field'] . "\n";
}
