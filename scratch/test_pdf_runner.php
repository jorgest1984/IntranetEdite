<?php
session_start();
$_SESSION['user_role'] = 'admin';

$_GET['id'] = 13; // ADGD29
ob_start();
include __DIR__ . '/../pdf_informe_seguimiento.php';
$pdf_out = ob_get_clean();

echo "PDF length: " . strlen($pdf_out) . " bytes\n";
echo "PDF header: " . substr($pdf_out, 0, 15) . "\n";
