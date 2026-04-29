<?php
// comerciales_email.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

// Datos mock basados en la imagen
$email_data = [
    'curso' => 'COMT0007 - ATENCIÓN AL CLIENTE CON DISCAPACIDAD EN TRANSPORTE DE VIAJEROS',
    'fecha_fin' => '',
    'destinatario_nombre' => 'BRIAN BUENO GUERRERO',
    'destinatario_email' => 'brian32plas@gmail.com',
    'asunto' => 'Mensaje de Editeformación',
    'remitente_email' => 'elena.adame@editeformacion.com',
    'remitente_fax' => '902 10 30 23',
    'remitente_tlf' => '902 19 51 30',
    'mensaje' => "Estimado :\n\nNos ponemos en contacto contigo en relación con el curso de COMT0007 - ATENCIÓN AL CLIENTE CON DISCAPACIDAD EN TRANSPORTE DE VIAJERO, para recordarte que tenemos pendiente recibir la siguiente documentación:"
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar E-mail - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --efp-blue: #006ce4;
            --label-blue: #1e40af;
            --title-red: #b91c1c;
            --border-gray: #cbd5e1;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .main-content { padding: 1.5rem; }

        .email-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
        }

        .email-header h1 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .email-body { padding: 20px; }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .label {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--label-blue);
            white-space: nowrap;
        }

        .form-control {
            border: 1px solid var(--border-gray);
            border-radius: 2px;
            padding: 4px 8px;
            font-size: 0.85rem;
            background: #f8fafc;
            width: 100%;
        }

        .form-control:focus { background: #fff; border-color: var(--efp-blue); outline: none; }

        .file-inputs-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border: 1px solid #e2e8f0;
        }

        .file-input-wrapper {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
        }

        .editor-container { margin-top: 20px; }

        .editor-toolbar {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            border-bottom: none;
            padding: 8px;
            display: flex;
            gap: 10px;
            border-radius: 4px 4px 0 0;
        }

        .toolbar-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toolbar-btn:hover { color: var(--efp-blue); }

        .message-area {
            width: 100%;
            height: 300px;
            border: 1px solid var(--border-gray);
            border-radius: 0 0 4px 4px;
            padding: 15px;
            font-family: inherit;
            font-size: 1rem;
            line-height: 1.5;
            resize: vertical;
        }

        .actions-footer {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            padding: 20px;
            border-top: 1px solid var(--border-gray);
        }

        .btn-efp {
            padding: 8px 25px;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--border-gray);
            background: #f1f5f9;
            color: var(--label-blue);
        }

        .btn-efp:hover { background: #e2e8f0; }
        
        .btn-primary-efp {
            background: #e2e8f0;
            color: var(--label-blue);
        }

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
            <a href="javascript:history.back()" class="btn-volver">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Volver
            </a>

            <div class="email-card">
                <div class="email-header">
                    <h1>ENVIAR E-MAIL</h1>
                </div>

                <form action="#" method="POST" enctype="multipart/form-data">
                    <div class="email-body">
                        <!-- Fila 1: Curso y Fecha Fin -->
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <span class="label">Curso:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['curso']) ?>" readonly>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">Fecha fin:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['fecha_fin']) ?>">
                            </div>
                        </div>

                        <!-- Fila 2: Nombre Destinatario y Asunto -->
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <span class="label">Nombre destinatario:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['destinatario_nombre']) ?>">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">Asunto:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['asunto']) ?>" style="color: var(--label-blue); text-decoration: underline;">
                            </div>
                        </div>

                        <!-- Fila 3: E-mail Destinatario -->
                        <div class="form-row">
                            <div class="form-group" style="width: 100%;">
                                <span class="label">E-mail destinatario :</span>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($email_data['destinatario_email']) ?>" style="color: var(--label-blue);">
                            </div>
                        </div>

                        <!-- Fila 4: E-mail Remitente -->
                        <div class="form-row">
                            <div class="form-group" style="width: 100%;">
                                <span class="label">E-mail remitente :</span>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($email_data['remitente_email']) ?>" style="color: var(--label-blue);">
                            </div>
                        </div>

                        <!-- Fila 5: Fax y Tlf Remitente -->
                        <div class="form-row">
                            <div class="form-group">
                                <span class="label">Nº de Fax remitente:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['remitente_fax']) ?>" style="width: 150px; color: var(--label-blue);">
                            </div>
                            <div class="form-group">
                                <span class="label">Nº Tlf. remitente:</span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($email_data['remitente_tlf']) ?>" style="width: 150px; color: var(--label-blue);">
                            </div>
                        </div>

                        <!-- Sección Adjuntos -->
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

                        <!-- Editor de Mensaje -->
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
                            <textarea name="mensaje" class="message-area"><?= htmlspecialchars($email_data['mensaje']) ?></textarea>
                        </div>
                    </div>

                    <div class="actions-footer">
                        <button type="submit" class="btn-efp btn-primary-efp">Aceptar</button>
                        <button type="button" class="btn-efp">E-mail encuesta COMFIA</button>
                        <button type="button" class="btn-efp">E-mail encuesta EDITE</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
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
    </script>
</body>
</html>
