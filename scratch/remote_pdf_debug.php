<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    session_start();
    $_SESSION['user_role'] = 'admin';
    require_once __DIR__ . '/../includes/config.php';

    $_GET['id'] = 13; // ADGD29
    ob_start();
    include __DIR__ . '/../pdf_informe_seguimiento.php';
    $pdf_out = ob_get_clean();

    header('Content-Type: text/plain; charset=utf-8');
    echo "SUCCESS: PDF generated!\n";
    echo "Length: " . strlen($pdf_out) . " bytes\n";
    echo "Header: " . substr($pdf_out, 0, 10) . "\n";
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
