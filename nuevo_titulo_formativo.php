<?php
// nuevo_titulo_formativo.php
require_once 'includes/auth.php'; // Verifica login y permisos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Título Formativo - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .wizard-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .wizard-header {
            background: #1e293b;
            color: white;
            padding: 12px 20px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .wizard-content {
            padding: 40px;
        }

        /* Advertencia Normativa */
        .normativa-warning {
            background: #ef4444;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 30px;
            border-left: 5px solid #b91c1c;
            line-height: 1.5;
        }

        /* Selector de Tipo */
        .type-selector-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .type-label {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }

        .custom-select-wrapper {
            position: relative;
            max-width: 400px;
        }

        .custom-select {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            color: #334155;
            cursor: pointer;
            appearance: none;
            outline: none;
            transition: all 0.2s;
        }

        .custom-select:focus {
            border-color: #0ea5e9;
            background: white;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }

        .select-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #64748b;
        }

        /* Botones de acción */
        .wizard-footer {
            margin-top: 40px;
            display: flex;
            gap: 15px;
        }

        .btn-wizard {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-wizard-next {
            background: #0ea5e9;
            color: white;
        }

        .btn-wizard-next:hover {
            background: #0284c7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2);
        }

        .btn-wizard-cancel {
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
        }

        .btn-wizard-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Nuevo Título Formativo</h1>
                <p>Asistente de configuración de nueva formación oficial</p>
            </div>
        </header>

        <section class="wizard-container">
            <div class="wizard-header">Títulos Formativos</div>

            <div class="wizard-content">
                <!-- Alerta de Normativa -->
                <div class="normativa-warning">
                    <svg style="width: 20px; height: 20px; vertical-align: middle; margin-right: 10px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Recuerde que al programar estas formaciones no se podrán variar, a menos que se cambie de normativa y se genere otra nueva.
                </div>

                <!-- Selección de Tipo -->
                <form action="editar_titulo_formativo.php" method="GET" class="type-selector-group">
                    <input type="hidden" name="new" value="1">
                    <label class="type-label">Tipo Formación</label>
                    <div class="custom-select-wrapper">
                        <select name="tipo" class="custom-select" required>
                            <option value="" disabled selected>Seleccione el tipo de formación...</option>
                            <option value="fp">formación profesional</option>
                            <option value="cert">certificados profesionales</option>
                        </select>
                        <div class="select-arrow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>

                    <div class="wizard-footer">
                        <a href="formacion_profesional.php" class="btn-wizard btn-wizard-cancel">Cancelar</a>
                        <button type="submit" class="btn-wizard btn-wizard-next">
                            Continuar
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>

</body>
</html>
