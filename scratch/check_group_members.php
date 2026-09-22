<?php
// scratch/check_group_members.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_db.php';

echo "=== DIAGNÓSTICO INTEGRANTES GRUPOS MOODLE ===\n\n";

$moodleDb = new MoodleDB();
if ($moodleDb->isConnected()) {
    $mpdo = $moodleDb->getPDO();
    $prefix = $moodleDb->getTablePrefix();

    $stmtG = $mpdo->query("SELECT * FROM {$prefix}groups WHERE courseid = 43");
    $groups = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    foreach ($groups as $g) {
        echo "Group ID: {$g['id']} | Name: {$g['name']}\n";
        $stmtM = $mpdo->prepare("
            SELECT gm.*, u.username, u.firstname, u.lastname, u.email
            FROM {$prefix}groups_members gm
            JOIN {$prefix}user u ON gm.userid = u.id
            WHERE gm.groupid = ?
        ");
        $stmtM->execute([$g['id']]);
        $members = $stmtM->fetchAll(PDO::FETCH_ASSOC);
        echo " Integrantes (" . count($members) . "):\n";
        foreach ($members as $m) {
            echo "   - User ID: {$m['userid']} | {$m['firstname']} {$m['lastname']} ({$m['username']})\n";
        }
        echo "\n";
    }
}
