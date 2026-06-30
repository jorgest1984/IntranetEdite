<?php
// scratch/test_render.php
require_once '../includes/config.php';

$where = ["1=1"];
$params = [];

$sql = "SELECT m.*, a.nombre as alumno_nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.provincia, a.pref_presencial,
               c.nombre as convocatoria_nombre,
               (SELECT pl.nombre FROM planes pl WHERE pl.convocatoria_id = c.id ORDER BY pl.id ASC LIMIT 1) as plan_nombre,
               e.nombre as empresa_nombre, e.sector as empresa_sector,
               g.numero_grupo, g.codigo_plataforma as grupo_cod, g.fecha_inicio as grupo_inicio, g.fecha_mitad as grupo_mitad, g.fecha_fin as grupo_fin,
               af.abreviatura as af_abreviatura, af.prioridad as af_prioridad, cu.nombre_corto as curso_nombre,
               u_com.nombre as comercial_nombre, u_com.apellidos as comercial_apellidos,
               COALESCE(af.modalidad, g.modalidad) as modalidad_real
        FROM matriculas m
        INNER JOIN alumnos a ON m.alumno_id = a.id
        LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
        LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
        LEFT JOIN grupos g ON m.grupo_id = g.id
        LEFT JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos cu ON af.curso_id = cu.id
        LEFT JOIN usuarios u_com ON m.comercial_id = u_com.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY m.id DESC
        LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Rendering start...<br>";
    foreach ($resultados as $idx => $res) {
        try {
            ob_start();
            ?>
            <tr>
                <td><?= htmlspecialchars($res['plan_nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['modalidad_real'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['af_abreviatura'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['numero_grupo'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['grupo_cod'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['curso_nombre'] ?? $res['convocatoria_nombre'] ?? '') ?></td>
                <td>
                    <div style="font-weight: 600;"><?= htmlspecialchars($res['primer_apellido'] . ' ' . $res['segundo_apellido'] . ', ' . $res['alumno_nombre']) ?></div>
                    <div style="font-size: 0.65rem; color: #64748b;"><?= htmlspecialchars($res['dni']) ?></div>
                </td>
                <td><?= htmlspecialchars($res['empresa_nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['empresa_sector'] ?? '') ?></td>
                <td><?= htmlspecialchars($res['provincia'] ?? '') ?></td>
                <td><?= htmlspecialchars(trim(($res['comercial_nombre'] ?? '') . ' ' . ($res['comercial_apellidos'] ?? ''))) ?></td>
                <td><?= !empty($res['grupo_inicio']) && $res['grupo_inicio'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_inicio'])) : '' ?></td>
                <td><?= !empty($res['grupo_mitad']) && $res['grupo_mitad'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_mitad'])) : '' ?></td>
                <td><?= !empty($res['grupo_fin']) && $res['grupo_fin'] != '0000-00-00' ? date('d/m/Y', strtotime($res['grupo_fin'])) : '' ?></td>
                <td>
                    <?php
                    $estado_val = strtolower($res['estado'] ?? '');
                    $badge_class = 'badge-default';
                    if (str_contains($estado_val, 'admitido')) $badge_class = 'badge-admitido';
                    elseif (str_contains($estado_val, 'inscrito') || str_contains($estado_val, 'preinscrito')) $badge_class = 'badge-inscrito';
                    elseif (str_contains($estado_val, 'espera') || str_contains($estado_val, 'pendiente')) $badge_class = 'badge-espera';
                    elseif (str_contains($estado_val, 'baja') || str_contains($estado_val, 'abandono')) $badge_class = 'badge-baja';
                    elseif (str_contains($estado_val, 'finalizado')) $badge_class = 'badge-finalizado';
                    ?>
                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($res['estado']) ?></span>
                </td>
                <td></td> <!-- No admisión -->
                <td><?= !empty($res['fecha_matricula']) && $res['fecha_matricula'] != '0000-00-00' ? date('d/m/Y', strtotime($res['fecha_matricula'])) : '' ?></td>
                <td></td> <!-- Cambio estado -->
                <td style="color: #ef4444; font-size: 0.72rem;">
                    <?php
                    $doc_pte = [];
                    if (empty($res['dni_entregado'])) $doc_pte[] = 'DNI';
                    if (empty($res['nomina_entregada'])) $doc_pte[] = 'Nómina';
                    if (empty($res['anexo1_entregado'])) $doc_pte[] = 'Anexo';
                    echo empty($doc_pte) ? '' : 'Falta: ' . implode(', ', $doc_pte);
                    ?>
                </td>
                <td style="text-align: center; font-weight: 700;"><?= htmlspecialchars($res['af_prioridad'] ?? '') ?></td>
                <td style="text-align: center;"><?= htmlspecialchars($res['pref_presencial'] ?? '') ?></td>
                <td style="text-align: center;">1</td>
                <td style="text-align: center;">
                    <a href="ficha_alumno.php?id=<?= $res['alumno_id'] ?>" class="btn-action" title="Ver ficha del alumno">
                        Ficha
                    </a>
                </td>
            </tr>
            <?php
            ob_end_clean();
        } catch (Throwable $t) {
            echo "ERROR at index $idx (ID: " . $res['id'] . "): " . $t->getMessage() . "<br>";
        }
    }
    echo "Rendering finished successfully.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
