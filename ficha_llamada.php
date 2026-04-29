<?php
// ficha_llamada.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;

// Mock data basado en la imagen proporcionada
$llamada = [
    'alumno' => [
        'nombre' => 'BRIAN BUENO GUERRERO',
        'alias' => '',
        'usuario' => 'bue51709',
        'clave' => '45614999',
        'domicilio' => 'Calle ESPAÑA nº 32',
        'cp' => '18100',
        'localidad' => 'ARMILLA',
        'provincia' => 'GRANADA',
        'telefono' => '',
        'movil' => '601 31 62 47',
        'email' => 'brian32plas@gmail.com',
        'email2' => '',
        'horario' => [
            'manana_desde' => '',
            'manana_hasta' => '',
            'tarde_desde' => '',
            'tarde_hasta' => '',
            'solo_dias' => ''
        ]
    ],
    'empresa' => [
        'nombre' => 'DESEMPLEADO',
        'sector' => 'Seguridad Privada',
        'direccion' => '',
        'cp' => '',
        'localidad' => '',
        'provincia' => 'DESCONOCIDA'
    ],
    'envio' => [
        'direccion' => '',
        'cp' => '',
        'localidad' => '',
        'provincia' => 'DESCONOCIDA',
        'telefono' => '',
        'fax' => ''
    ],
    'curso' => [
        'nombre' => 'COMT0007 - ATENCIÓN AL CLIENTE CON DISCAPACIDAD EN TRANSPORTE DE VIAJEROS',
        'inicio' => '',
        'fin' => '',
        'fecha_25' => ''
    ],
    'notas_importantes' => []
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Llamada - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --efp-blue: #006ce4;
            --efp-dark-blue: #004a99;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-section: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .main-content { padding: 1.5rem; }

        .ficha-container {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .ficha-main {
            flex: 1;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .ficha-header {
            background: var(--efp-blue);
            color: white;
            padding: 10px 20px;
            text-align: center;
        }

        .ficha-header h1 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-box {
            padding: 20px;
            border-bottom: 1px solid var(--border-gray);
        }

        .section-box:last-child { border-bottom: none; }

        .section-title {
            color: var(--efp-blue);
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .data-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .data-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 12px;
        }

        .label {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--label-blue);
            text-transform: uppercase;
        }

        .value {
            font-size: 0.9rem;
            font-weight: 500;
            color: #334155;
        }

        .value-bold {
            font-weight: 700;
        }

        .value-large {
            font-size: 1.5rem;
            font-weight: 800;
            color: #000;
        }

        .horario-box {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .horario-title {
            font-weight: 800;
            color: #166534;
            text-transform: uppercase;
            margin-right: 10px;
        }

        /* Sidebar Style */
        .ficha-sidebar {
            width: 300px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            background: #f8fafc;
            border-bottom: 1px solid var(--border-gray);
            padding: 10px;
            text-align: center;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--label-blue);
            text-transform: uppercase;
        }

        .sidebar-body {
            padding: 15px;
            min-height: 100px;
            background: #f1f5f9;
        }

        .status-badge {
            padding: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .status-danger { background: #fee2e2; color: #991b1b; border-top: 1px solid #fecaca; border-bottom: 1px solid #fecaca; }

        .btn-link {
            display: block;
            width: 100%;
            padding: 10px;
            text-align: center;
            background: #f8fafc;
            color: #b91c1c;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
        }

        .btn-link:hover { background: #f1f5f9; }

        .btn-add-note {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            margin-left: 10px;
        }

        .btn-add-note:hover { background: #fecaca; }

        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fff;
            border: 1px solid var(--border-gray);
            color: #475569;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
        }

        .btn-volver:hover { background: #f1f5f9; color: var(--efp-blue); }

    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <a href="comerciales_llamadas.php" class="btn-volver">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver al listado
            </a>

            <div class="ficha-container">
                <!-- COLUMNA PRINCIPAL -->
                <div class="ficha-main">
                    <div class="ficha-header">
                        <h1>FICHA DE LLAMADA DE SEGUIMIENTO</h1>
                    </div>

                    <!-- SECCIÓN 1: DATOS DEL ALUMNO -->
                    <section class="section-box">
                        <h2 class="section-title">DATOS DEL ALUMNO</h2>
                        
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Alumno:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['alumno']['nombre']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Alias:</span>
                                <span class="value"><?= htmlspecialchars($llamada['alumno']['alias']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Usuario:</span>
                                <span class="value value-bold" style="color: var(--label-blue);"><?= htmlspecialchars($llamada['alumno']['usuario']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Clave:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['alumno']['clave']) ?></span>
                            </div>
                        </div>

                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Domicilio particular:</span>
                                <span class="value"><?= htmlspecialchars($llamada['alumno']['domicilio']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">CP:</span>
                                <span class="value"><?= htmlspecialchars($llamada['alumno']['cp']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Localidad:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['alumno']['localidad']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Provincia:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['alumno']['provincia']) ?></span>
                            </div>
                        </div>

                        <div class="data-row" style="align-items: center;">
                            <div class="data-item">
                                <span class="label">Tlf. particular:</span>
                                <span class="value"><?= htmlspecialchars($llamada['alumno']['telefono']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Móvil:</span>
                                <span class="value value-large"><?= htmlspecialchars($llamada['alumno']['movil']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">E-mail:</span>
                                <span class="value" style="color: var(--efp-blue); text-decoration: underline;"><?= htmlspecialchars($llamada['alumno']['email']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">E-mail 2:</span>
                                <span class="value"><?= htmlspecialchars($llamada['alumno']['email2']) ?></span>
                            </div>
                        </div>

                        <div class="horario-box">
                            <span class="horario-title">HORARIO PREFERENTE</span>
                            <span class="label">Mañanas desde:</span> <span class="value"><?= $llamada['alumno']['horario']['manana_desde'] ?: '---' ?></span>
                            <span class="label" style="margin-left:10px;">hasta:</span> <span class="value"><?= $llamada['alumno']['horario']['manana_hasta'] ?: '---' ?></span>
                            <span class="label" style="margin-left:20px;">Tardes desde:</span> <span class="value"><?= $llamada['alumno']['horario']['tarde_desde'] ?: '---' ?></span>
                            <span class="label" style="margin-left:10px;">hasta:</span> <span class="value"><?= $llamada['alumno']['horario']['tarde_hasta'] ?: '---' ?></span>
                            <span class="label" style="margin-left:20px;">Sólo días:</span> <span class="value"><?= $llamada['alumno']['horario']['solo_dias'] ?: '---' ?></span>
                        </div>
                    </section>

                    <!-- SECCIÓN 2: DATOS DE LA EMPRESA -->
                    <section class="section-box">
                        <h2 class="section-title">DATOS DE LA EMPRESA</h2>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Empresa:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['empresa']['nombre']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Sector:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['empresa']['sector']) ?></span>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Dirección:</span>
                                <span class="value"><?= htmlspecialchars($llamada['empresa']['direccion']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">CP:</span>
                                <span class="value"><?= htmlspecialchars($llamada['empresa']['cp']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Localidad:</span>
                                <span class="value"><?= htmlspecialchars($llamada['empresa']['localidad']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Provincia:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['empresa']['provincia']) ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- SECCIÓN 3: DIRECCIÓN DE ENVÍO -->
                    <section class="section-box">
                        <h2 class="section-title">DIRECCIÓN DE ENVÍO</h2>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Dirección:</span>
                                <span class="value"><?= htmlspecialchars($llamada['envio']['direccion']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">CP:</span>
                                <span class="value"><?= htmlspecialchars($llamada['envio']['cp']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Localidad:</span>
                                <span class="value"><?= htmlspecialchars($llamada['envio']['localidad']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Provincia:</span>
                                <span class="value value-bold"><?= htmlspecialchars($llamada['envio']['provincia']) ?></span>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Teléfono:</span>
                                <span class="value"><?= htmlspecialchars($llamada['envio']['telefono']) ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Fax:</span>
                                <span class="value"><?= htmlspecialchars($llamada['envio']['fax']) ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- SECCIÓN 4: DATOS DEL CURSO -->
                    <section class="section-box">
                        <h2 class="section-title">DATOS DEL CURSO</h2>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Curso:</span>
                                <span class="value value-bold" style="color: var(--label-blue);"><?= htmlspecialchars($llamada['curso']['nombre']) ?></span>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-item">
                                <span class="label">Inicio:</span>
                                <span class="value value-bold" style="color: var(--label-blue);"><?= htmlspecialchars($llamada['curso']['inicio'] ?: '---') ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label">Fin:</span>
                                <span class="value value-bold" style="color: var(--label-blue);"><?= htmlspecialchars($llamada['curso']['fin'] ?: '---') ?></span>
                            </div>
                            <div class="data-item">
                                <span class="label" style="color: #b91c1c;">Fecha 25% del curso:</span>
                                <span class="value value-bold" style="color: #b91c1c;"><?= htmlspecialchars($llamada['curso']['fecha_25'] ?: '---') ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- SECCIÓN 5: NOTAS IMPORTANTES -->
                    <section class="section-box">
                        <h2 class="section-title">NOTAS IMPORTANTES</h2>
                        <?php if (empty($llamada['notas_importantes'])): ?>
                            <div style="color: #b91c1c; font-weight: 700; font-size: 0.9rem;">
                                Este alumno no tiene registrada ninguna nota importante
                                <button class="btn-add-note">Añadir nota imp.</button>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- COLUMNA LATERAL (SIDEBAR) -->
                <div class="ficha-sidebar">
                    <div class="sidebar-card">
                        <div class="sidebar-header">
                            <h3>Doc pendiente:</h3>
                        </div>
                        <div class="sidebar-body">
                            <!-- Contenido vacío o lista de docs -->
                        </div>
                        <div class="status-badge status-danger">
                            Encuesta NO realizada
                        </div>
                        <a href="#" class="btn-link">Mostrar doc pendiente otros cursos</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
