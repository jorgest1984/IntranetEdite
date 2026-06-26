<?php
// nueva_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Obtener planes y convocatorias para los selectores
$planes = $pdo->query("SELECT id, nombre, codigo FROM planes ORDER BY nombre ASC")->fetchAll();
$familias = [
    'Actividades Físicas y Deportivas',
    'Actividades y Competencias Transversales',
    'Administración y Gestión',
    'Agraria',
    'Artes Gráficas',
    'Artes y Artesanías',
    'Comercio y Marketing',
    'Edificación y Obra Civil',
    'Electricidad y Electrónica',
    'Energía y Agua',
    'Fabricación Mecánica',
    'Hostelería y Turismo',
    'Imagen Personal',
    'Imagen y Sonido',
    'Industrias Alimentarias',
    'Industrias Extractivas',
    'Informática y Comunicaciones',
    'Instalación y Mantenimiento',
    'Inteligencia Artificial y Data',
    'Madera, Mueble y Corcho',
    'Marítimo-Pesquera',
    'Química',
    'Sanidad',
    'Seguridad y Medio Ambiente',
    'Servicios Socioculturales y a la Comunidad',
    'Textil, Confección y Piel',
    'Transporte y Mantenimiento de Vehículos',
    'Vidrio y Cerámica',
    'Transversal'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Acción Formativa - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            max-width: 900px;
            margin: 0 auto;
        }

        .section-title {
            color: #1e3a8a;
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            margin: 30px 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title svg { color: #3b82f6; }

        .grid-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .full-width { grid-column: span 2; }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .moodle-sync-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .moodle-sync-box input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="max-width: 900px; margin: 0 auto 30px auto;">
            <div class="page-title">
                <h1>Nueva Acción Formativa</h1>
                <p>Configure los parámetros base para el nuevo curso</p>
            </div>
        </header>

        <form action="procesar_nueva_af.php" method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            
            <div class="section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                Identificación de la Acción
            </div>
            
            <div class="grid-form">
                <div class="form-group full-width">
                    <label>Título Completo del Curso:</label>
                    <input type="text" name="titulo" class="form-control" placeholder="Ej: Gestión de Equipos de Trabajo" required>
                </div>
                <div class="form-group">
                    <label>Nombre Corto / Abrev:</label>
                    <input type="text" name="abreviatura" class="form-control" placeholder="Ej: GET-2024" required>
                </div>
                <div class="form-group">
                    <label>Nº de Acción (Código):</label>
                    <input type="text" name="num_accion" class="form-control" placeholder="0001">
                </div>
            </div>

            <div class="section-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
                Configuración Didáctica
            </div>

            <div class="grid-form">
                <div class="form-group">
                    <label>Plan Estratégico:</label>
                    <select name="plan_id" class="form-control">
                        <option value="">Seleccione un plan...</option>
                        <?php foreach($planes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($_GET['plan_id']) && $_GET['plan_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modalidad:</label>
                    <select name="modalidad" class="form-control">
                        <option value="Teleformación">Teleformación</option>
                        <option value="Presencial">Presencial</option>
                        <option value="Mixta">Mixta</option>
                        <option value="Aula Virtual">Aula Virtual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Duración (Horas Totales):</label>
                    <input type="number" name="duracion" class="form-control" value="60">
                </div>
                <div class="form-group">
                    <label>Familia Profesional:</label>
                    <select name="familia_profesional" class="form-control">
                        <option value=""></option>
                        <?php foreach($familias as $f): ?>
                            <option value="<?= $f ?>"><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="moodle-sync-box">
                <input type="checkbox" name="crear_moodle" id="crear_moodle" checked>
                <div>
                    <label for="crear_moodle" style="font-weight: 700; color: #1e40af; cursor: pointer; display: block;">Aprovisionar automáticamente en el Aula Virtual</label>
                    <span style="font-size: 0.75rem; color: #3b82f6;">Se creará un curso en Moodle con los datos de esta acción y se vinculará automáticamente.</span>
                </div>
            </div>

            <div style="margin-top: 40px; text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 15px 50px; font-size: 1rem; border-radius: 10px;">Crear Acción Formativa</button>
            </div>

        </form>
    </main>
</div>

</body>
</html>
