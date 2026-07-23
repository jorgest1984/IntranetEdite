<?php
$_SERVER['HTTP_HOST'] = 'pre-gestion.grupoefp.es';
$_GET['courseid'] = 43;
$_GET['userid'] = 3;
$_GET['tipo'] = 'recibi';
$_GET['ts'] = time();

$secret = "EfpMoodleSecret2026!#";
$_GET['token'] = hash_hmac('sha256', $_GET['courseid'] . '|' . $_GET['userid'] . '|' . $_GET['tipo'] . '|' . $_GET['ts'], $secret);

require 'api_moodle_pdf.php';
