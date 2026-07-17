<?php
// moodle_informes.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Verificamos si el usuario tiene acceso (Tutor o superior)
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$cursoid = isset($_GET['cursoid']) ? (int)$_GET['cursoid'] : 0;

if (!$cursoid) {
    $error = "No se ha proporcionado el ID del curso de Moodle.";
} else {
    // Buscar a qué grupo y acción corresponde este curso en Moodle
    $stmt = $pdo->prepare("SELECT g.id as grupo_id, g.numero_grupo, af.id as accion_id, af.titulo, af.num_accion
                           FROM grupos g
                           JOIN acciones_formativas af ON g.accion_id = af.id
                           JOIN cursos c ON af.curso_id = c.id
                           WHERE c.moodle_id = ?");
    $stmt->execute([$cursoid]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        $error = "No se ha encontrado un grupo vinculado a este curso de Moodle ($cursoid) en la Intranet.";
    } else {
        // Cargar los alumnos
        $stmtAlumnos = $pdo->prepare("
            SELECT a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            WHERE m.grupo_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
            ORDER BY a.primer_apellido ASC, a.segundo_apellido ASC, a.nombre ASC
        ");
        $stmtAlumnos->execute([$grupo['grupo_id']]);
        $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <title>Informes de Alumnos - Moodle</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-blue: #1e3a8a;
            --border-gray: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --lec-gradient: linear-gradient(135deg, #6b7280 0%, #374151 100%);
            --adm-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        /* Group Info Card */
        .info-card-premium {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .info-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #3b82f6;
        }

        .info-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #eff6ff;
            color: #2563eb;
        }

        .info-content h2 {
            margin: 0 0 0.25rem 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--title-blue);
        }

        .info-content p {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* List Section */
        .list-section-premium {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-gray);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-top: 1rem;
        }

        .section-header-premium {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header-premium h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--title-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Premium Table */
        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.925rem;
        }

        .premium-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.75px;
            border-bottom: 1px solid var(--border-gray);
        }

        .premium-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: background-color 0.2s;
        }

        .premium-table tr {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .premium-table tr:hover td {
            background-color: #eff6ff;
        }

        /* Avatar & Identity */
        .identity-flex {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-gradient {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .avatar-lec { background: var(--lec-gradient); box-shadow: 0 4px 8px rgba(107, 114, 128, 0.25); }

        .user-info-text {
            display: flex;
            flex-direction: column;
        }

        .user-info-text .username {
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.975rem;
        }

        /* Action Buttons */
        .btn-action-premium {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .btn-action-premium.pdf {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);
        }

        .btn-action-premium.pdf:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.25);
        }

        /* Alerts */
        .premium-alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            border-left: 6px solid transparent;
        }
        .premium-alert-error { background: #fff1f2; color: #991b1b; border-left-color: #ef4444; border: 1px solid #fecdd3; }
        .premium-alert svg { flex-shrink: 0; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Informes Individuales de Seguimiento</h1>
                    <p>Generación de informes desde Moodle</p>
                </div>
            </header>

            <?php if (isset($error)): ?>
                <div class="premium-alert premium-alert-error">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php else: ?>
                <div class="info-card-premium">
                    <div class="info-icon-wrapper">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                    </div>
                    <div class="info-content">
                        <h2><?= htmlspecialchars($grupo['num_accion']) ?> - <?= htmlspecialchars($grupo['titulo']) ?></h2>
                        <p>Grupo: <?= htmlspecialchars($grupo['numero_grupo']) ?></p>
                    </div>
                </div>

                <section class="list-section-premium">
                    <div class="section-header-premium">
                        <h2>Listado de Alumnos</h2>
                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); background: #f1f5f9; padding: 6px 14px; border-radius: 8px;">
                            <?= count($alumnos) ?> alumnos
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>DNI / NIE</th>
                                    <th style="text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alumnos)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; padding: 3rem; color: #64748b; font-weight: 500;">
                                            <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" style="display: block; margin: 0 auto 15px auto; color: #cbd5e1;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                            No hay alumnos matriculados en este grupo.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumnos as $al): ?>
                                        <?php 
                                        $iniciales = strtoupper(mb_substr($al['nombre'], 0, 1) . mb_substr($al['primer_apellido'], 0, 1));
                                        ?>
                                        <tr class="user-row-item">
                                            <td>
                                                <div class="identity-flex">
                                                    <div class="user-avatar-gradient avatar-lec">
                                                        <?= $iniciales ?>
                                                    </div>
                                                    <div class="user-info-text">
                                                        <span class="username"><?= htmlspecialchars($al['primer_apellido'] . ' ' . $al['segundo_apellido'] . ', ' . $al['nombre']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($al['dni']) ?></td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <a href="pdf_informe_alumno.php?accion_id=<?= $grupo['accion_id'] ?>&grupo_id=<?= $grupo['grupo_id'] ?>&alumno_id=<?= $al['id'] ?>" 
                                                   target="_blank" 
                                                   class="btn-action-premium pdf">
                                                   <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                                                   Generar Informe
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
