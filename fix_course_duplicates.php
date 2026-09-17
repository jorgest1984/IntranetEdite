<?php
// fix_course_duplicates.php
// Script de consolidación y deduplicación de Cursos e IdPlataforma
header('Content-Type: text/plain; charset=utf-8');

require_once 'includes/config.php';

echo "=== INICIO DE CONSOLIDACIÓN Y DEDUPLICACIÓN DE CURSOS ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Sincronizar id_plataforma en acciones_formativas desde cursos.moodle_id cuando falte
    $stmt1 = $pdo->query("SELECT af.id as af_id, c.moodle_id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE c.moodle_id IS NOT NULL AND c.moodle_id != '' AND (af.id_plataforma IS NULL OR TRIM(af.id_plataforma) = '' OR af.id_plataforma = '0')");
    $toUpdateAF = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    $updAF = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?");
    $countAF = 0;
    foreach ($toUpdateAF as $r) {
        $updAF->execute([$r['moodle_id'], $r['af_id']]);
        $countAF++;
    }
    echo "1. Sincronizadas $countAF acciones formativas con el moodle_id de su curso maestro.\n";

    // 2. Sincronizar cursos.moodle_id desde acciones_formativas.id_plataforma cuando falte
    $stmt2 = $pdo->query("SELECT c.id as curso_id, af.id_plataforma FROM cursos c JOIN acciones_formativas af ON af.curso_id = c.id WHERE (c.moodle_id IS NULL OR c.moodle_id = '' OR c.moodle_id = 0) AND af.id_plataforma IS NOT NULL AND TRIM(af.id_plataforma) != '' AND TRIM(af.id_plataforma) != '0'");
    $toUpdateCurso = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $updCurso = $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?");
    $countCurso = 0;
    foreach ($toUpdateCurso as $r) {
        $updCurso->execute([$r['id_plataforma'], $r['curso_id']]);
        $countCurso++;
    }
    echo "2. Sincronizados $countCurso cursos maestros con id_plataforma de sus acciones formativas.\n";

    // 3. Buscar grupos de cursos duplicados en la tabla 'cursos' por nombre_corto (código)
    $duplicates = $pdo->query("
        SELECT LOWER(TRIM(nombre_corto)) as code, COUNT(*) as count 
        FROM cursos 
        WHERE nombre_corto IS NOT NULL AND TRIM(nombre_corto) != ''
        GROUP BY LOWER(TRIM(nombre_corto)) 
        HAVING count > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "3. Encontrados " . count($duplicates) . " grupos de cursos duplicados por código/abreviatura.\n";

    foreach ($duplicates as $dup) {
        $code = $dup['code'];
        // Obtener todos los IDs de esta colección
        $stmtC = $pdo->prepare("SELECT id, moodle_id FROM cursos WHERE LOWER(TRIM(nombre_corto)) = ? ORDER BY id ASC");
        $stmtC->execute([$code]);
        $rows = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) <= 1) continue;

        // Ordenar en PHP priorizando filas que tengan moodle_id válido
        usort($rows, function($a, $b) {
            $hasA = (!empty($a['moodle_id']) && $a['moodle_id'] !== '0') ? 1 : 0;
            $hasB = (!empty($b['moodle_id']) && $b['moodle_id'] !== '0') ? 1 : 0;
            if ($hasA !== $hasB) return $hasB <=> $hasA;
            return (int)$a['id'] <=> (int)$b['id'];
        });

        // El primero es nuestro canónico
        $canonical = $rows[0];
        $canonical_id = (int)$canonical['id'];
        $canonical_moodle = $canonical['moodle_id'];

        $duplicate_ids = [];
        for ($i = 1; $i < count($rows); $i++) {
            $duplicate_ids[] = (int)$rows[$i]['id'];
            if ((empty($canonical_moodle) || $canonical_moodle === '0') && !empty($rows[$i]['moodle_id'])) {
                $canonical_moodle = $rows[$i]['moodle_id'];
                $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?")->execute([$canonical_moodle, $canonical_id]);
            }
        }

        $dupStr = implode(',', $duplicate_ids);
        echo "   - Consolidando código '$code': Canónico ID #$canonical_id | Eliminar duplicados IDs #$dupStr\n";

        // Reasignar acciones formativas al curso canónico
        $stmtReassign = $pdo->prepare("UPDATE acciones_formativas SET curso_id = ? WHERE curso_id IN ($dupStr)");
        $stmtReassign->execute([$canonical_id]);
        echo "     -> Reasignadas " . $stmtReassign->rowCount() . " acciones formativas al curso #$canonical_id.\n";

        // Eliminar duplicados en 'cursos'
        $stmtDelete = $pdo->query("DELETE FROM cursos WHERE id IN ($dupStr)");
        echo "     -> Eliminadas " . $stmtDelete->rowCount() . " filas duplicadas de la tabla 'cursos'.\n";
    }

    // 4. Asegurar de nuevo coherencia final en id_plataforma
    $stmtFinal = $pdo->query("SELECT af.id as af_id, c.moodle_id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE c.moodle_id IS NOT NULL AND c.moodle_id != '' AND c.moodle_id != '0'");
    $finalRows = $stmtFinal->fetchAll(PDO::FETCH_ASSOC);
    $updFinal = $pdo->prepare("UPDATE acciones_formativas SET id_plataforma = ? WHERE id = ?");
    foreach ($finalRows as $r) {
        $updFinal->execute([$r['moodle_id'], $r['af_id']]);
    }

    $pdo->commit();
    echo "\n=== PROCESO FINALIZADO CON ÉXITO ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nERROR: " . $e->getMessage() . "\n";
}
