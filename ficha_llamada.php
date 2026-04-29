<?php
// ficha_llamada.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
$success_msg = '';
$error_msg = '';

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
    'notas_importantes' => ''
];

// MOCK: ID del usuario que supuestamente creó esta llamada/nota
$llamada_usuario_id = 1; 
$puede_editar = ($_SESSION['user_id'] == $llamada_usuario_id) || has_permission([ROLE_ADMIN]);

// PROCESAR GUARDADO DE NOTA IMPORTANTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_nota'])) {
    $nota_texto = trim($_POST['nota_texto'] ?? '');
    
    // Aquí guardaríamos en la DB, por ejemplo en alumnos.observaciones o una tabla de notas
    /*
    $stmt = $pdo->prepare("UPDATE alumnos SET observaciones = ? WHERE id = ?");
    $stmt->execute([$nota_texto, $alumno_id]);
    */
    
    // Simulamos el guardado para la vista
    $llamada['notas_importantes'] = $nota_texto;
    $success_msg = "Nota guardada correctamente.";
}

// PROCESAR BORRADO DE NOTA IMPORTANTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_nota'])) {
    // Aquí borraríamos en la DB
    /*
    $stmt = $pdo->prepare("UPDATE alumnos SET observaciones = '' WHERE id = ?");
    $stmt->execute([$alumno_id]);
    */
    
    // Simulamos el borrado para la vista
    $llamada['notas_importantes'] = '';
    $success_msg = "Nota eliminada correctamente.";
}

// PROCESAR ENVÍO DE EMAIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_send_email'])) {
    $to = $_POST['destinatario_email'] ?? '';
    $subject = $_POST['asunto_email'] ?? 'Mensaje de Editeformación';
    $message = $_POST['mensaje'] ?? '';
    $from = $_POST['remitente_email'] ?? 'intranet@grupoefp.es';
    
    // Preparar los datos para el Relay de Vercel
    $postData = http_build_query([
        'token' => 'dbbea329538b1694971d7ee66cc3e4673',
        'to' => $to,
        'from' => $from,
        'subject' => $subject,
        'body' => $message
    ]);

    $ch = curl_init('https://gestion.grupoefp.es/send_mail.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $success_msg = "El e-mail se ha enviado correctamente a $to.";
    } else {
        $error_msg = "Error al enviar el e-mail a través del servidor. (Código: $httpCode)";
    }
}

// PROCESAR GUARDADO DE LLAMADA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_call'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO tutorias_seguimiento 
            (alumno_id, empresa_id, curso_id, usuario_id, fecha, hora, motivo, quien_contacta, forma, modulacion, horarios_pref, resultado, asunto, notas) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            1, // Alumno ID (Mock)
            1, // Empresa ID (Mock)
            1, // Curso ID (Mock)
            $_SESSION['user_id'] ?? null,
            $_POST['fecha'],
            $_POST['hora'],
            $_POST['motivo'],
            $_POST['quien_contacta'],
            $_POST['forma'],
            $_POST['modulacion'],
            $_POST['horarios_pref'],
            $_POST['resultado'],
            $_POST['asunto'],
            $_POST['notas']
        ]);
        $success_msg = "Registro de llamada guardado correctamente.";
    } catch (Exception $e) {
        $error_msg = "Error al guardar la llamada: " . $e->getMessage();
    }
}

// PROCESAR BORRADO DE LLAMADA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_call'])) {
    if ($puede_editar) {
        // Lógica de borrado en DB
        $success_msg = "Llamada eliminada correctamente.";
    } else {
        $error_msg = "No tienes permiso para eliminar esta llamada.";
    }
}

// PROCESAR PROGRAMACIÓN DE CITA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_schedule'])) {
    try {
        $stmt = $pdo->prepare("UPDATE tutorias_seguimiento SET 
            cita_fecha = ?, cita_hora = ?, cita_asunto = ?, cita_descripcion = ? 
            WHERE id = ?");
        
        // Si es una nueva cita sin haber guardado la llamada primero, 
        // en un sistema real buscaríamos el último ID o crearíamos uno.
        // Por simplicidad en este mock, asumimos que se actualiza el último insertado si existe.
        $last_id = $pdo->lastInsertId();
        if ($last_id) {
            $stmt->execute([
                $_POST['cita_fecha'],
                $_POST['cita_hora'],
                $_POST['cita_asunto'],
                $_POST['cita_descripcion'],
                $last_id
            ]);
            $success_msg = "Cita programada correctamente.";
        } else {
            // Si no hay ID previo, insertamos uno nuevo mínimo
            $stmtIns = $pdo->prepare("INSERT INTO tutorias_seguimiento (alumno_id, cita_fecha, cita_hora, cita_asunto, cita_descripcion) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->execute([1, $_POST['cita_fecha'], $_POST['cita_hora'], $_POST['cita_asunto'], $_POST['cita_descripcion']]);
            $success_msg = "Cita programada correctamente.";
        }
    } catch (Exception $e) {
        $error_msg = "Error al programar la cita: " . $e->getMessage();
    }
}

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

        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: #fff;
            border-radius: 4px;
            width: 100%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-gray);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #b91c1c; /* title-red */
            text-transform: uppercase;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: #64748b;
        }

        .modal-close:hover { color: #0f172a; }

        /* Estilos del formulario de email */
        .email-body { padding: 20px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        .form-group { display: flex; align-items: center; gap: 10px; }
        .file-inputs-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; background: #f8fafc; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #e2e8f0; }
        .file-input-wrapper { display: flex; align-items: center; gap: 5px; font-size: 0.75rem; }
        .editor-container { margin-top: 20px; }
        .editor-toolbar { background: #f8fafc; border: 1px solid var(--border-gray); border-bottom: none; padding: 8px; display: flex; gap: 10px; border-radius: 4px 4px 0 0; }
        .toolbar-btn { background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; }
        .toolbar-btn:hover { color: var(--efp-blue); }
        .message-area { width: 100%; height: 250px; border: 1px solid var(--border-gray); border-radius: 0 0 4px 4px; padding: 15px; font-family: inherit; font-size: 1rem; line-height: 1.5; resize: vertical; }
        .actions-footer { display: flex; justify-content: center; gap: 20px; margin-top: 20px; padding: 20px; border-top: 1px solid var(--border-gray); }
        .btn-efp { padding: 8px 25px; font-size: 0.85rem; font-weight: 700; border-radius: 3px; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border-gray); background: #f1f5f9; color: var(--label-blue); }
        .btn-efp:hover { background: #e2e8f0; }
        .btn-primary-efp { background: #e2e8f0; color: var(--label-blue); }

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

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?= $error_msg ?></div>
            <?php endif; ?>

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
                                <?php if ($puede_editar): ?>
                                <button type="button" class="btn-add-note" onclick="openNotaModal()">Añadir nota imp.</button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #0f172a; font-weight: 600; font-size: 0.9rem; background: #fff; padding: 10px; border-radius: 4px; border: 1px solid var(--border-gray); display: flex; justify-content: space-between; align-items: flex-start; gap: 15px;">
                                <div style="flex: 1;"><?= nl2br(htmlspecialchars($llamada['notas_importantes'])) ?></div>
                                <?php if ($puede_editar): ?>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn-add-note" onclick="openNotaModal()" style="margin: 0; background: #f1f5f9; color: #1e293b; border-color: #cbd5e1;">Editar nota</button>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Estás seguro de que deseas borrar esta nota?');">
                                        <input type="hidden" name="action_delete_nota" value="1">
                                        <button type="submit" class="btn-add-note" style="margin: 0;">Borrar nota</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- SECCIÓN 6: DATOS DE LA LLAMADA -->
                    <section class="section-box" style="background: #f8fafc;">
                        <h2 class="section-title">DATOS DE LA LLAMADA</h2>
                        
                        <form method="POST">
                            <input type="hidden" name="action_save_call" value="1">
                            <div class="call-data-container" style="display: flex; gap: 20px;">
                                <div style="flex: 1;">
                                    <div class="data-row" style="gap: 15px;">
                                        <div class="data-item">
                                            <label class="label">Fecha contacto:</label>
                                            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Hora contacto:</label>
                                            <input type="time" name="hora" class="form-control" value="<?= date('H:i') ?>">
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Motivo ():</label>
                                            <select name="motivo" class="form-control">
                                                <option value="Información">Información</option>
                                                <option value="Seguimiento" selected>Seguimiento</option>
                                                <option value="Reclamación">Reclamación</option>
                                            </select>
                                        </div>
                                        <div class="data-item" style="flex-direction: row; align-items: center; gap: 10px; margin-top: 20px;">
                                            <label style="font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                                <input type="radio" name="quien_contacta" value="Nosotros" checked> Contactamos nosotros
                                            </label>
                                            <label style="font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                                <input type="radio" name="quien_contacta" value="Ellos"> Contactan ellos
                                            </label>
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Forma:</label>
                                            <select name="forma" class="form-control">
                                                <option value="Teléfono" selected>Teléfono</option>
                                                <option value="Email">Email</option>
                                                <option value="Presencial">Presencial</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="data-row" style="gap: 15px; margin-top: 15px;">
                                        <div class="data-item">
                                            <label class="label">Preferencias impartición presencial:</label>
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Modulación:</label>
                                            <select name="modulacion" class="form-control" style="width: 120px;">
                                                <option value="">---</option>
                                                <option value="Mañana">Mañana</option>
                                                <option value="Tarde">Tarde</option>
                                            </select>
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Horarios:</label>
                                            <select name="horarios_pref" class="form-control" style="width: 120px;">
                                                <option value="">---</option>
                                                <option value="L-V">L-V</option>
                                                <option value="Sábados">Sábados</option>
                                            </select>
                                        </div>
                                        <div class="data-item">
                                            <label class="label">Resultado llamada:</label>
                                            <select name="resultado" class="form-control" style="width: 180px;">
                                                <option value="">---</option>
                                                <option value="Interesado">Interesado</option>
                                                <option value="No interesa">No interesa</option>
                                                <option value="Pendiente">Pendiente</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="data-item" style="margin-top: 20px;">
                                        <label class="label">Asunto:</label>
                                        <textarea name="asunto" class="form-control" style="height: 80px; width: 100%; resize: vertical; font-family: inherit;">TURISMO</textarea>
                                    </div>

                                    <div class="data-item" style="margin-top: 20px;">
                                        <label class="label" style="color: var(--label-blue);">Observaciones internas:</label>
                                        <textarea name="notas" class="form-control" style="height: 80px; width: 100%; resize: vertical; font-family: inherit; color: var(--label-blue); font-weight: 600;">NO LE INTERESA, ESTÁ TRABAJANDO</textarea>
                                    </div>
                                </div>

                                <!-- Panel de iconos lateral derecho -->
                                <div style="width: 180px; display: flex; flex-direction: column; gap: 20px; border-left: 1px solid var(--border-gray); padding-left: 20px;">
                                    <div style="text-align: center;">
                                        <a href="javascript:void(0)" onclick="openEmailModal()" style="text-decoration: none; color: inherit; display: block;">
                                            <svg viewBox="0 0 24 24" width="32" height="32" style="color: #0ea5e9;"><path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                            <div style="font-size: 0.7rem; font-weight: 700; color: var(--label-blue);">e-mail</div>
                                        </a>
                                    </div>
                                    
                                    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 15px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 32px; height: 32px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #b91c1c;">
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                            </div>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--label-blue);">Emitir queja</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg viewBox="0 0 24 24" width="24" height="24" style="color: #475569;"><path fill="currentColor" d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--label-blue);">Agenda alertas</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg viewBox="0 0 24 24" width="24" height="24" style="color: #0ea5e9;"><path fill="currentColor" d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-7H6V5h12v2z"/></svg>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--label-blue);">Mensajería</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($puede_editar): ?>
                            <div style="text-align: center; margin-top: 30px; display: flex; justify-content: center; gap: 15px;">
                                <button type="submit" class="btn btn-primary" style="padding: 10px 40px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Guardar registro</button>
                                <button type="submit" name="action_delete_call" value="1" class="btn" style="padding: 10px 30px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;" onclick="return confirm('¿Estás seguro de que deseas eliminar esta llamada permanentemente?');">Eliminar llamada</button>
                            </div>
                            <?php else: ?>
                            <div style="text-align: center; margin-top: 30px;">
                                <span style="color: #64748b; font-size: 0.85rem; font-weight: 600;">No tienes permisos para editar o eliminar esta llamada.</span>
                            </div>
                            <?php endif; ?>
                        </form>
                    </section>

                    <!-- SECCIÓN 7: PROGRAMAR CITA -->
                    <section class="section-box" style="margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;">
                        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; font-weight: 700; color: #334155;">Programar cita</h3>
                        
                        <form method="POST">
                            <input type="hidden" name="action_schedule" value="1">
                            <div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px;">
                                <div class="data-item">
                                    <label class="label">Fecha y hora:</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="date" name="cita_fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                                        <input type="time" name="cita_hora" class="form-control" value="10:00">
                                    </div>
                                </div>
                                <div class="data-item">
                                    <label class="label">Asunto:</label>
                                    <input type="text" name="cita_asunto" class="form-control" value="Llamar a BRIAN BUENO GUERRERO" style="width: 100%;">
                                </div>
                            </div>
                            <div class="data-item" style="margin-top: 15px;">
                                <label class="label">Descripción:</label>
                                <textarea name="cita_descripcion" class="form-control" style="height: 60px; width: 100%; resize: vertical; font-family: inherit;"></textarea>
                            </div>
                            <div style="margin-top: 15px;">
                                <button type="submit" class="btn" style="background: #f1f5f9; border: 1px solid var(--border-gray); font-weight: 600; padding: 6px 20px;">Programar</button>
                            </div>
                        </form>
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

    <!-- Modal Email -->
    <div id="emailModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ENVIAR E-MAIL</h2>
                <button type="button" class="modal-close" onclick="closeEmailModal()">&times;</button>
            </div>
            <form action="#" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_send_email" value="1">
                <div class="email-body">
                    <!-- Fila 1 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <span class="label">Curso:</span>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($llamada['curso']['nombre']) ?>" readonly>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <span class="label">Fecha fin:</span>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($llamada['curso']['fin']) ?>">
                        </div>
                    </div>
                    <!-- Fila 2 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <span class="label">Nombre destinatario:</span>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($llamada['alumno']['nombre']) ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <span class="label">Asunto:</span>
                            <input type="text" name="asunto_email" class="form-control" value="Mensaje de Editeformación" style="color: var(--label-blue); text-decoration: underline;">
                        </div>
                    </div>
                    <!-- Fila 3 -->
                    <div class="form-row">
                        <div class="form-group" style="width: 100%;">
                            <span class="label">E-mail destinatario :</span>
                            <input type="email" name="destinatario_email" class="form-control" value="<?= htmlspecialchars($llamada['alumno']['email']) ?>" style="color: var(--label-blue);">
                        </div>
                    </div>
                    <!-- Fila 4 -->
                    <div class="form-row">
                        <div class="form-group" style="width: 100%;">
                            <span class="label">E-mail remitente :</span>
                            <input type="email" name="remitente_email" class="form-control" value="elena.adame@editeformacion.com" style="color: var(--label-blue);">
                        </div>
                    </div>
                    <!-- Fila 5 -->
                    <div class="form-row">
                        <div class="form-group">
                            <span class="label">Nº de Fax remitente:</span>
                            <input type="text" class="form-control" value="902 10 30 23" style="width: 150px; color: var(--label-blue);">
                        </div>
                        <div class="form-group">
                            <span class="label">Nº Tlf. remitente:</span>
                            <input type="text" class="form-control" value="902 19 51 30" style="width: 150px; color: var(--label-blue);">
                        </div>
                    </div>
                    <!-- Adjuntos -->
                    <div class="form-group" style="margin-top: 15px;">
                        <span class="label" style="font-size: 0.9rem;">Adjuntar:</span>
                    </div>
                    <div class="file-inputs-container">
                        <?php for($i=1; $i<=10; $i++): ?>
                        <div class="file-input-wrapper">
                            <input type="file" name="adjunto_<?= $i ?>" id="adjunto_<?= $i ?>" style="display: none;" onchange="updateFileName(this)">
                            <button type="button" class="btn-efp" style="padding: 2px 8px; font-size: 0.7rem;" onclick="document.getElementById('adjunto_<?= $i ?>').click()">Elegir archivo</button>
                            <span id="filename_<?= $i ?>" style="color: #64748b;">No se ha seleccionado ningún archivo</span>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <!-- Editor -->
                    <div class="editor-container">
                        <span class="label" style="display: block; margin-bottom: 10px;">Texto del Mensaje:</span>
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg></button>
                            <button type="button" class="toolbar-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg></button>
                            <div style="width: 1px; height: 20px; background: #e2e8f0;"></div>
                            <button type="button" class="toolbar-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M9.64 7.64c.2.2.5.2.7 0L12 5.99l1.66 1.65c.2.2.51.2.71 0l.7-.7c.2-.2.2-.51 0-.71L12.71 3.88a.996.996 0 0 0-1.41 0L8.94 6.23c-.2.2-.2.51 0 .71l.7.7zM12 21l-1.66-1.65a.996.996 0 0 0-1.41 0l-.7.7c-.2.2-.2.51 0 .71l2.35 2.35c.2.2.51.2.71 0l2.35-2.35c.2-.2.2-.51 0-.71l-.7-.7c-.2-.2-.51-.2-.71 0L12 21z"/></svg></button>
                            <button type="button" class="toolbar-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm7 16H5V5h2v3h10V5h2v14z"/></svg></button>
                            <div style="width: 1px; height: 20px; background: #e2e8f0;"></div>
                            <select class="form-control" style="width: 120px; height: 24px; padding: 0 5px;"><option>Párrafo</option></select>
                            <button type="button" class="toolbar-btn"><b>B</b></button>
                            <button type="button" class="toolbar-btn"><i>I</i></button>
                            <button type="button" class="toolbar-btn">x²</button>
                            <button type="button" class="toolbar-btn">x₂</button>
                            <button type="button" class="toolbar-btn">•••</button>
                        </div>
                        <textarea name="mensaje" class="message-area">Estimado <?= htmlspecialchars($llamada['alumno']['nombre']) ?>:

Nos ponemos en contacto contigo en relación con el curso de <?= htmlspecialchars($llamada['curso']['nombre']) ?>, para recordarte que tenemos pendiente recibir la siguiente documentación:</textarea>
                    </div>
                </div>
                <div class="actions-footer">
                    <button type="submit" class="btn-efp btn-primary-efp">Aceptar</button>
                    <button type="button" class="btn-efp">E-mail encuesta COMFIA</button>
                    <button type="button" class="btn-efp">E-mail encuesta EDITE</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nota Importante -->
    <div id="notaModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="justify-content: center; position: relative;">
                <h2 style="font-size: 1rem;">FICHA DE NOTA ALUMNO</h2>
                <button type="button" class="modal-close" onclick="closeNotaModal()" style="position: absolute; right: 15px; top: 15px;">&times;</button>
            </div>
            <form action="#" method="POST">
                <input type="hidden" name="action_save_nota" value="1">
                <div class="email-body" style="padding: 0;">
                    <div style="padding: 15px 20px; border-bottom: 1px solid var(--border-gray);">
                        <span style="font-weight: 800; color: var(--label-blue);">Alumno:</span>
                        <span style="font-weight: 800; color: #b91c1c; margin-left: 10px;"><?= htmlspecialchars($llamada['alumno']['nombre']) ?></span>
                    </div>
                    <div style="padding: 20px; background: #f8fafc;">
                        <textarea name="nota_texto" style="width: 100%; height: 150px; padding: 10px; border: 1px solid #94a3b8; background: #e2e8f0; font-family: inherit; font-size: 1rem; resize: vertical;"><?= htmlspecialchars($llamada['notas_importantes']) ?></textarea>
                    </div>
                </div>
                <div class="actions-footer" style="margin-top: 0; padding: 15px;">
                    <button type="submit" class="btn-efp btn-primary-efp" style="font-weight: 600;">Guardar nota</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEmailModal() {
            document.getElementById('emailModal').style.display = 'flex';
        }

        function closeEmailModal() {
            document.getElementById('emailModal').style.display = 'none';
        }
        
        function openNotaModal() {
            document.getElementById('notaModal').style.display = 'flex';
        }

        function closeNotaModal() {
            document.getElementById('notaModal').style.display = 'none';
        }

        function updateFileName(input) {
            const id = input.id.split('_')[1];
            const span = document.getElementById('filename_' + id);
            if (input.files && input.files[0]) {
                span.textContent = input.files[0].name;
                span.style.color = '#1e40af';
                span.style.fontWeight = '600';
            } else {
                span.textContent = 'No se ha seleccionado ningún archivo';
                span.style.color = '#64748b';
                span.style.fontWeight = '400';
            }
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const emailModal = document.getElementById('emailModal');
            const notaModal = document.getElementById('notaModal');
            if (event.target == emailModal) {
                closeEmailModal();
            }
            if (event.target == notaModal) {
                closeNotaModal();
            }
        }
    </script>
</body>
</html>
