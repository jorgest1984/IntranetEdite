<?php
// diplomas.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$accion_id = intval($_GET['accion_id'] ?? 0);
$grupo_id = intval($_GET['grupo_id'] ?? 0);

if (!$accion_id || !$grupo_id) {
    die("Faltan parámetros requeridos.");
}

// Obtener datos del grupo y acción
$stmt = $pdo->prepare("SELECT g.numero_grupo, af.titulo FROM grupos g JOIN acciones_formativas af ON g.accion_id = af.id WHERE g.id = ? AND af.id = ?");
$stmt->execute([$grupo_id, $accion_id]);
$grupo_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo_info) {
    die("Grupo o acción formativa no encontrados.");
}

// Obtener alumnos del grupo con sus notas
$stmtAlumnos = $pdo->prepare("SELECT m.alumno_id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, 
                                     m.moodle_e1_grade, m.moodle_e2_grade, m.moodle_e3_grade, m.moodle_final_grade
                              FROM matriculas m
                              JOIN alumnos a ON m.alumno_id = a.id
                              WHERE m.grupo_id = ?
                              ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre");
$stmtAlumnos->execute([$grupo_id]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diplomas y Certificados - <?= htmlspecialchars($grupo_info['titulo']) ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-responsive { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--border-color, #e2e8f0); }
        .data-table th { background: #f8fafc; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; }
        .data-table tr:hover td { background: #f1f5f9; }
        
        .btn-action { padding: 8px 16px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 0.85rem; transition: all 0.2s; }
        .btn-diploma { background: #0ea5e9; color: white; }
        .btn-diploma:hover:not(:disabled) { background: #0284c7; transform: translateY(-1px); }
        .btn-diploma:disabled { background: #cbd5e1; cursor: not-allowed; opacity: 0.7; }
        .btn-certificado { background: #10b981; color: white; }
        .btn-certificado:hover { background: #059669; transform: translateY(-1px); }
        
        .status-badge { font-weight: 700; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
        .status-apto { color: #059669; background: #d1fae5; border: 1px solid #34d399; }
        .status-noapto { color: #dc2626; background: #fee2e2; border: 1px solid #f87171; }
        .btn-volver { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 10px 18px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: all 0.2s; cursor: pointer; }
        .btn-volver:hover { background: #e2e8f0; color: #1e293b; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .bulk-actions { margin-top: 20px; display: flex; gap: 15px; background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); align-items: center; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1>Diplomas y Certificados</h1>
                    <p><?= htmlspecialchars($grupo_info['titulo']) ?> (Grupo <?= htmlspecialchars($grupo_info['numero_grupo']) ?>)</p>
                </div>
                <div class="header-actions">
                    <a href="documentacion.php" class="btn-volver">
                        <i class="fa-solid fa-arrow-left"></i> Volver a Documentación
                    </a>
                </div>
            </header>

            <div class="bulk-actions">
                <strong style="color: #475569;"><i class="fa-solid fa-layer-group"></i> Acciones Masivas:</strong>
                <button class="btn-action btn-diploma" id="btn-send-diplomas">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Diplomas a Seleccionados
                </button>
                <button class="btn-action btn-certificado" id="btn-send-certificados">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Certificados a Seleccionados
                </button>
                <span id="bulk-status" style="margin-left: 10px; font-size: 0.9rem; font-weight: 500;"></span>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all"></th>
                            <th>DNI</th>
                            <th>Nombre y Apellidos</th>
                            <th>Estado Moodle</th>
                            <th>Acciones Documentales</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php foreach ($alumnos as $alumno): 
                        $apto = false;
                        
                        // Si hay nota final calculada por moodle, la usamos
                        if (isset($alumno['moodle_final_grade']) && is_numeric($alumno['moodle_final_grade'])) {
                            if ((float)$alumno['moodle_final_grade'] >= 5) {
                                $apto = true;
                            }
                        } else {
                            // Alternativa: calcular la media de e1, e2, e3 si final_grade no está disponible
                            $grades = [];
                            if (is_numeric($alumno['moodle_e1_grade'])) $grades[] = (float)$alumno['moodle_e1_grade'];
                            if (is_numeric($alumno['moodle_e2_grade'])) $grades[] = (float)$alumno['moodle_e2_grade'];
                            if (is_numeric($alumno['moodle_e3_grade'])) $grades[] = (float)$alumno['moodle_e3_grade'];
                            
                            if (count($grades) > 0) {
                                $media = array_sum($grades) / count($grades);
                                if ($media >= 5) {
                                    $apto = true;
                                }
                            }
                        }
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="alumno-chk" value="<?= $alumno['alumno_id'] ?>" data-apto="<?= $apto ? '1' : '0' ?>">
                            </td>
                            <td><?= htmlspecialchars($alumno['dni']) ?></td>
                            <td><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?></td>
                            <td>
                                <?php if ($apto): ?>
                                    <span class="status-badge status-apto"><i class="fa-solid fa-check" style="margin-right: 4px;"></i> APTO</span>
                                <?php else: ?>
                                    <span class="status-badge status-noapto"><i class="fa-solid fa-xmark" style="margin-right: 4px;"></i> NO APTO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <?php if ($apto): ?>
                                        <a href="pdf_diploma.php?alumno_id=<?= $alumno['alumno_id'] ?>&grupo_id=<?= $grupo_id ?>&accion_id=<?= $accion_id ?>&tipo=diploma" target="_blank" class="btn-action btn-diploma">
                                            <i class="fa-solid fa-award"></i> Diploma
                                        </a>
                                        <a href="pdf_diploma.php?alumno_id=<?= $alumno['alumno_id'] ?>&grupo_id=<?= $grupo_id ?>&accion_id=<?= $accion_id ?>&tipo=certificado" target="_blank" class="btn-action btn-certificado">
                                            <i class="fa-solid fa-file-signature"></i> Certificado
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-action btn-diploma" disabled title="Solo para alumnos APTOS">
                                            <i class="fa-solid fa-award"></i> Diploma
                                        </button>
                                        <button class="btn-action btn-certificado" disabled style="background: #cbd5e1; cursor: not-allowed; opacity: 0.7;" title="Solo para alumnos APTOS">
                                            <i class="fa-solid fa-file-signature"></i> Certificado
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($alumnos)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #64748b;">No hay alumnos matriculados en este grupo.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        const grupoId = <?= $grupo_id ?>;
        const accionId = <?= $accion_id ?>;
        const chkAll = document.getElementById('select-all');
        const chkAlumnos = document.querySelectorAll('.alumno-chk');
        const btnSendDiplomas = document.getElementById('btn-send-diplomas');
        const btnSendCertificados = document.getElementById('btn-send-certificados');
        const bulkStatus = document.getElementById('bulk-status');

        chkAll.addEventListener('change', function() {
            chkAlumnos.forEach(chk => chk.checked = this.checked);
        });

        async function procesarEnvio(tipo) {
            let seleccionados = [];
            chkAlumnos.forEach(chk => {
                if (chk.checked) {
                    // Validar si es diploma que sea APTO
                    if (tipo === 'diploma' && chk.getAttribute('data-apto') !== '1') {
                        // Lo ignoramos
                    } else {
                        seleccionados.push(chk.value);
                    }
                }
            });

            if (seleccionados.length === 0) {
                alert("No hay alumnos válidos seleccionados para esta acción.");
                return;
            }

            if (!confirm(`¿Estás seguro de que deseas enviar ${seleccionados.length} ${tipo}s por email?`)) return;

            // Deshabilitar botones
            btnSendDiplomas.disabled = true;
            btnSendCertificados.disabled = true;
            bulkStatus.style.color = '#0284c7';
            bulkStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando... Por favor, espera.';

            try {
                const response = await fetch('api_send_diplomas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        grupo_id: grupoId,
                        accion_id: accionId,
                        tipo: tipo,
                        alumnos: seleccionados
                    })
                });

                const data = await response.json();
                if (data.success) {
                    bulkStatus.style.color = '#059669';
                    bulkStatus.innerHTML = `<i class="fa-solid fa-check"></i> ${data.message}`;
                } else {
                    bulkStatus.style.color = '#dc2626';
                    bulkStatus.innerHTML = `<i class="fa-solid fa-xmark"></i> Error: ${data.message}`;
                }
            } catch (error) {
                bulkStatus.style.color = '#dc2626';
                bulkStatus.innerHTML = `<i class="fa-solid fa-xmark"></i> Error de conexión.`;
            } finally {
                btnSendDiplomas.disabled = false;
                btnSendCertificados.disabled = false;
            }
        }

        btnSendDiplomas.addEventListener('click', () => procesarEnvio('diploma'));
        btnSendCertificados.addEventListener('click', () => procesarEnvio('certificado'));
    </script>
</body>
</html>
