<?php
$lines = file('ficha_accion_formativa.php');
foreach ($lines as $i => $l) {
    if (strpos($l, 'class="tab-content"') !== false) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}
