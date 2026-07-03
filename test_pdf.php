<?php
// Mock
define('ROLE_ADMIN', 1);
define('ROLE_COORD', 2);
define('ROLE_TUTOR', 3);
function has_permission($roles) { return true; }

$_GET['id'] = 12;

$script = file_get_contents('pdf_informe_seguimiento.php');
$script = str_replace("require_once 'includes/auth.php';", "", $script);
file_put_contents('test_pdf_runner.php', $script);
require_once 'test_pdf_runner.php';
