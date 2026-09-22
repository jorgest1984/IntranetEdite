<?php
// scratch/fix_grupo16_id_plataforma.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';
require_once __DIR__ . '/../includes/moodle_db.php';

echo "=== CORRIGIENDO GRUPO 16 EN INTRANET Y MOODLE ===\n\n";

// 1. Actualizar id_plataforma del grupo 16 a 39
$stmtFix = $pdo->prepare("UPDATE grupos SET id_plataforma = 39 WHERE id = 16");
$stmtFix->execute();
echo "1. Intranet Grupo 16 actualizado con id_plataforma = 39 (Moodle GRUPO-6).\n";

// 2. Moodle DB checks & cleanup
$moodleDb = new MoodleDB();
if ($moodleDb->isConnected()) {
    $mpdo = $moodleDb->getPDO();
    $prefix = $moodleDb->getTablePrefix();

    // Asegurar que el usuario 1306 (Ramon Suarez) esté en el grupo 39
    $stmtCheckM = $mpdo->prepare("SELECT * FROM {$prefix}groups_members WHERE groupid = 39 AND userid = 1306");
    $stmtCheckM->execute();
    if (!$stmtCheckM->fetch()) {
        $mpdo->prepare("INSERT INTO {$prefix}groups_members (groupid, userid, timeadded) VALUES (39, 1306, ?)")->execute([time()]);
        echo "2. Usuario 1306 (Ramon Suarez) añadido a Moodle Grupo 39 (GRUPO-6).\n";
    } else {
        echo "2. Usuario 1306 (Ramon Suarez) ya estaba en Moodle Grupo 39 (GRUPO-6).\n";
    }

    // Quitar del grupo 41 si estaba
    $mpdo->prepare("DELETE FROM {$prefix}groups_members WHERE groupid = 41 AND userid = 1306")->execute();
    
    // Eliminar grupo vacío 41 (GRUPO-16) en Moodle si no tiene integrantes
    $stmtCount41 = $mpdo->query("SELECT COUNT(*) FROM {$prefix}groups_members WHERE groupid = 41");
    if ($stmtCount41->fetchColumn() == 0) {
        $mpdo->query("DELETE FROM {$prefix}groups WHERE id = 41");
        echo "3. Grupo vacío 41 (GRUPO-16) eliminado de Moodle.\n";
    }
}

echo "\n--- VERIFICACIÓN FINAL ---\n";
$stmtCheckG = $pdo->query("SELECT id, accion_id, numero_grupo, id_plataforma, codigo_plat FROM grupos WHERE id = 16");
print_r($stmtCheckG->fetch(PDO::FETCH_ASSOC));
