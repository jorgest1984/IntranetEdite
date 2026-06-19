<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    require 'includes/config.php';
    require 'includes/moodle_db.php';

    $moodleDb = new MoodleDB();
    if (!$moodleDb->isConnected()) {
        echo json_encode(['error' => 'Not connected to Moodle', 'msg' => $moodleDb->getError()]);
        exit;
    }

    $ref = new ReflectionClass($moodleDb);
    $prop = $ref->getProperty('mpdo');
    $prop->setAccessible(true);
    $mpdo = $prop->getValue($moodleDb);

    $stmtMod = $mpdo->prepare("SHOW TABLES");
    $stmtMod->execute();
    $tables = $stmtMod->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['tables' => $tables]);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Exception', 'msg' => $e->getMessage()]);
}
