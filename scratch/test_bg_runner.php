<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

global $moodle_bypass_auth;
$moodle_bypass_auth = true;

require_once __DIR__ . '/../includes/config.php';

// Test 1: pdf_hoja_bienvenida.php
$_GET['accion_id'] = 22;
ob_start();
include __DIR__ . '/../pdf_hoja_bienvenida.php';
$pdf1 = ob_get_clean();

// Test 2: pdf_recibi_material.php
ob_start();
include __DIR__ . '/../pdf_recibi_material.php';
$pdf2 = ob_get_clean();

header('Content-Type: text/plain; charset=utf-8');
echo "Bienvenida PDF size: " . strlen($pdf1) . " bytes | Header: " . substr($pdf1, 0, 10) . "\n";
echo "Recibí Material PDF size: " . strlen($pdf2) . " bytes | Header: " . substr($pdf2, 0, 10) . "\n";
