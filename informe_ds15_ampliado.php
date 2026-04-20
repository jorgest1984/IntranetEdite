<?php
// informe_ds15_ampliado.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_ds15_ampliado.php';

// Obtener datos para los filtros
try {
    $convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $planes = $pdo->query("SELECT id, nombre, expediente FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $convocatorias = [];
    $planes = [];
}

// Lógica de búsqueda
$resultados = [];
$buscando = false;
$filtro_convocatoria = $_GET['convocatoria'] ?? '';
$filtro_plan = $_GET['plan'] ?? '';

if ($filtro_convocatoria || $filtro_plan) {
    $buscando = true;
    $where = ["1=1"];
    $params = [];

    if ($filtro_convocatoria) {
        $where[] = "m.convocatoria_id = ?";
        $params[] = $filtro_convocatoria;
    }
    if ($filtro_plan) {
        // Asumiendo que el plan se vincula a través de la convocatoria o directamente en matriculas
        // Según inscripciones.php: LEFT JOIN planes p ON c.id = p.convocatoria_id
        $where[] = "p.id = ?";
        $params[] = $filtro_plan;
    }

    $sql = "SELECT 
                COALESCE(p.expediente, c.codigo_expediente) as exp_report,
                '1' as nA, -- Placeholder para Num Acción
                '1' as nG, -- Placeholder para Num Grupo
                c.nombre as curso_nombre,
                CONCAT(a.primer_apellido, ' ', a.segundo_apellido, ', ', a.nombre) as alumno_nombre,
                a.dni as nif,
                a.seguridad_social as nss,
                a.provincia,
                a.fecha_nacimiento,
                TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, COALESCE(c.fecha_inicio_prevista, CURDATE())) as edad,
                a.sexo,
                'RG' as colectivo, -- Placeholder
                '' as dsp_ld,
                'Indefinido' as contrato, -- Placeholder
                '7' as grupo_cotizacion, -- Placeholder
                'Grado Medio' as estudios, -- Placeholder
                'No' as dis,
                e.nombre as empresa_nombre,
                e.cif as empresa_cif,
                e.sector as empresa_sector,
                'Sí' as pyme,
                m.estado,
                'Sí' as certifica
            FROM matriculas m
            INNER JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN convocatorias c ON m.convocatoria_id = c.id
            LEFT JOIN planes p ON c.id = p.convocatoria_id
            LEFT JOIN empresas e ON a.id = e.id -- Placeholder: Ajustar vínculo Alumno-Empresa si existe tabla intermedia
            WHERE " . implode(" AND ", $where) . "
            ORDER BY a.primer_apellido ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al obtener datos: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe DS15 Ampliado - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
            --header-dark: #2d2d2d;
            --btn-blue: #2563eb;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .main-content { padding: 2rem; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header-custom {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-bottom: 2px solid var(--border-gray);
            text-align: left;
        }

        .card-header-custom h2 {
            margin: 0;
            color: var(--title-red);
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .search-form { padding: 1.5rem; }

        .search-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-group label {
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.9rem;
            min-width: 100px;
            text-align: right;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 6px 12px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
            min-width: 350px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 1.5rem;
            padding-left: 115px;
        }

        .btn-buscar {
            background: var(--btn-blue);
            color: white;
            border: none;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-buscar:hover { background: #1d4ed8; }

        .btn-limpiar {
            background: #fff;
            color: #dc2626;
            border: 1px solid #dc2626;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-limpiar:hover { background: #fef2f2; }

        /* Report Table Styling */
        .report-section {
            margin-top: 2rem;
        }

        .report-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .table-custom {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: var(--header-dark);
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            text-transform: uppercase;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }

        .table-custom td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            color: #334155;
            white-space: nowrap;
        }

        .table-custom tr.total-row {
            background: #f8fafc;
            font-weight: 800;
        }

        .table-custom tr.total-row td {
            color: #000;
            border-bottom: 2px solid var(--border-gray);
        }

        /* Export Buttons */
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .btn-export {
            background: var(--btn-blue);
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-export:hover { filter: brightness(0.9); }

        .btn-export svg { width: 16px; height: 16px; }

        /* Legend Section */
        .legend-box {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-gray);
        }

        .legend-box h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .legend-box p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }

        .footnote {
            vertical-align: super;
            font-size: 0.6rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>LISTADO DE DS15 AMPLIADO DEL PLAN</h2>
                </div>
                <form class="search-form" method="GET">
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria</label>
                            <select name="convocatoria" class="form-control">
                                <option value="">-</option>
                                <?php foreach ($convocatorias as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $filtro_convocatoria == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="search-row">
                        <div class="form-group">
                            <label>Plan</label>
                            <select name="plan" class="form-control">
                                <option value="">-</option>
                                <?php foreach ($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $filtro_plan == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Enviar</button>
                        <a href="informe_ds15_ampliado.php" class="btn-limpiar">Eliminar filtros</a>
                    </div>
                </form>
            </div>

            <?php if ($buscando): ?>
            <div class="report-section">
                <h1 class="report-title">- Plan</h1>

                <div class="table-container">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Expediente</th>
                                <th>nA</th>
                                <th>nG</th>
                                <th>Curso</th>
                                <th>Alumno/a</th>
                                <th>NIF</th>
                                <th>NSS</th>
                                <th>Provincia</th>
                                <th>Fecha nac.</th>
                                <th>Edad<span class="footnote">1</span></th>
                                <th>Sexo</th>
                                <th>Col.</th>
                                <th>DSP LD</th>
                                <th>Contrato</th>
                                <th>Grupo cotización</th>
                                <th>Estudios</th>
                                <th>Dis.</th>
                                <th>Empresa</th>
                                <th>CIF</th>
                                <th>Sector</th>
                                <th>PYME</th>
                                <th>Estado</th>
                                <th>Certifica</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- TOTAL ROW AS IN IMAGE -->
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td></td>
                                <td></td>
                                <td colspan="21"><?= count($resultados) ?> resultados encontrados.</td>
                            </tr>

                            <?php if (empty($resultados)): ?>
                                <tr>
                                    <td colspan="24" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                                        No se han encontrado registros para los filtros seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($resultados as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['exp_report'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['nA']) ?></td>
                                        <td><?= htmlspecialchars($row['nG']) ?></td>
                                        <td><?= htmlspecialchars($row['curso_nombre']) ?></td>
                                        <td><strong><?= htmlspecialchars($row['alumno_nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['nif']) ?></td>
                                        <td><?= htmlspecialchars($row['nss'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['provincia'] ?? '-') ?></td>
                                        <td><?= $row['fecha_nacimiento'] ? date('d/m/Y', strtotime($row['fecha_nacimiento'])) : '-' ?></td>
                                        <td><?= $row['edad'] ?? '-' ?></td>
                                        <td><?= htmlspecialchars($row['sexo'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['colectivo']) ?></td>
                                        <td><?= htmlspecialchars($row['dsp_ld']) ?></td>
                                        <td><?= htmlspecialchars($row['contrato']) ?></td>
                                        <td><?= htmlspecialchars($row['grupo_cotizacion']) ?></td>
                                        <td><?= htmlspecialchars($row['estudios']) ?></td>
                                        <td><?= htmlspecialchars($row['dis']) ?></td>
                                        <td><?= htmlspecialchars($row['empresa_nombre'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['empresa_cif'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['empresa_sector'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['pyme']) ?></td>
                                        <td>
                                            <span style="padding: 2px 6px; background: #e2e8f0; border-radius: 4px; font-weight: 700; font-size: 0.65rem;">
                                                <?= htmlspecialchars($row['estado']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($row['certifica']) ?></td>
                                        <td>
                                            <a href="ficha_alumno.php?dni=<?= $row['nif'] ?>" style="color: var(--btn-blue); font-weight: 600;">Ver Ficha</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="export-actions">
                    <a href="#" class="btn-export">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Exportar a Excel
                    </a>
                    <a href="#" class="btn-export">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                        Exportar a CSV
                    </a>
                    <a href="#" class="btn-export">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
                        Exportar a JSON
                    </a>
                </div>

                <div class="legend-box">
                    <h3>Leyenda</h3>
                    <p><span class="footnote">1</span> Edad en la fecha de inicio del curso.</p>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
