<?php
$file = 'ficha_matricula.php';
$content = file_get_contents($file);
$parts = explode('?>'."\n".'<!DOCTYPE html>', $content);

$php_logic = $parts[0] . '?>';

$modern_html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Matrícula - <?= htmlspecialchars($matricula['nombre'] ?? '') ?> - <?= APP_NAME ?? 'Gestión' ?></title>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .card-resumen {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .resumen-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .resumen-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .resumen-value {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            border-right: 1px solid var(--border-color);
            text-decoration: none;
            display: inline-block;
        }
        .tab-btn.active { 
            background: white; 
            color: var(--primary-color); 
            font-weight: 600; 
            border-bottom: 2px solid var(--primary-color); 
        }
        .tab-panel {
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            border: 1px solid var(--border-color);
            border-top: none;
        }
        
        .form-section-title {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .grid-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
        }
        
        .form-control {
            width: 100%;
            padding: 0.65rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-modern {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-primary-modern {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(37,99,235,0.2);
        }
        .btn-primary-modern:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        
        /* Simulating the old top buttons inside a modern dropdown or action bar */
        .actions-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .btn-outline {
            background: white;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
        }
        .btn-outline:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        
        <!-- HEADER -->
        <div class="header-premium" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
            <div>
                <a href="ficha_alumno.php?id=<?= $matricula['alumno_id'] ?>" class="btn-back" style="text-decoration:none; color: var(--primary-color); font-weight:700;">⬅ Volver a Ficha de Alumno</a>
                <h1 style="margin-top: 0.8rem; margin-bottom:0.4rem; font-size: 1.8rem; color: #1e293b;">
                    Edición: <?= htmlspecialchars($matricula['nombre'] ?? '') ?> <?= htmlspecialchars($matricula['primer_apellido'] ?? '') ?>
                </h1>
                <p style="margin:0; color:#64748b; font-weight:500;">
                    DNI: <strong style="color:#0f172a;"><?= htmlspecialchars($matricula['dni'] ?? 'N/A') ?></strong> | 
                    Matrícula ID: <strong style="color:#0f172a;">#<?= $matricula['matricula_id'] ?></strong>
                </p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #ef4444;">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce3; color: #15803d; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #22c55e;">
                ✅ Datos guardados correctamente.
            </div>
        <?php endif; ?>

        <!-- TARJETA RESUMEN CURSO -->
        <div class="card-resumen">
            <div class="resumen-grid">
                <div class="resumen-item">
                    <span class="resumen-label">Acción / Grupo</span>
                    <span class="resumen-value">
                        <span style="background: #e0f2fe; color: #0284c7; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-right: 4px;">AC</span><?= htmlspecialchars($matricula['af_abreviatura'] ?? 'N/A') ?> 
                        <span style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 8px; margin-right: 4px;">GR</span><?= htmlspecialchars($matricula['numero_grupo'] ?? 'N/A') ?>
                    </span>
                </div>
                <div class="resumen-item" style="grid-column: span 2;">
                    <span class="resumen-label">Plan / Curso</span>
                    <span class="resumen-value">
                        <div style="color: #64748b; font-size: 0.8rem; margin-bottom: 2px;"><?= htmlspecialchars($matricula['plan_nombre'] ?? 'Sin Plan') ?></div>
                        <div style="color: #1e3a8a;"><?= htmlspecialchars($matricula['curso_titulo'] ?? 'Sin Curso') ?></div>
                    </span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-label">Fechas</span>
                    <span class="resumen-value">
                        📅 <?= !empty($matricula['grupo_inicio']) ? date('d/m/Y', strtotime($matricula['grupo_inicio'])) : '--/--/----' ?> <br>
                        🏁 <?= !empty($matricula['grupo_fin']) ? date('d/m/Y', strtotime($matricula['grupo_fin'])) : '--/--/----' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ACCIONES RÁPIDAS (Migradas del Top Bar antiguo) -->
        <div class="actions-bar">
            <button class="btn-outline">📄 Documentos</button>
            <button class="btn-outline">⚠️ Incidencia</button>
            <button class="btn-outline">🔄 Sincronizar Moodle</button>
            <button class="btn-outline">📥 Notificar Baja/Aban</button>
            <button class="btn-outline">🔑 Envío Claves</button>
        </div>

        <!-- TABS MODERNOS -->
        <div class="tabs-header">
            <a href="#" class="tab-btn active">Datos Personales</a>
            <a href="#" class="tab-btn">Datos Laborales</a>
            <a href="#" class="tab-btn">Datos Curso</a>
            <a href="#" class="tab-btn">Material y doc.</a>
        </div>

        <div class="tab-panel">
            <form method="POST">
                <input type="hidden" name="action" value="update_datos_personales">
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="submit" class="btn-modern btn-primary-modern">
                        💾 Guardar Cambios
                    </button>
                </div>

                <!-- SECCIÓN: IDENTIFICACIÓN -->
                <h3 class="form-section-title">Información Básica</h3>
                <div class="grid-form">
                    <div class="form-group">
                        <label>NIF / NIE</label>
                        <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($matricula['dni'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Seguridad Social</label>
                        <input type="text" name="ss" class="form-control" value="<?= htmlspecialchars($matricula['seguridad_social'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?= !empty($matricula['fecha_nacimiento']) ? date('Y-m-d', strtotime($matricula['fecha_nacimiento'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Sexo</label>
                        <select name="sexo" class="form-control">
                            <option value=""></option>
                            <option value="Hombre" <?= ($matricula['sexo'] ?? '') == 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                            <option value="Mujer" <?= ($matricula['sexo'] ?? '') == 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                        </select>
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($matricula['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Primer Apellido</label>
                        <input type="text" name="primer_apellido" class="form-control" value="<?= htmlspecialchars($matricula['primer_apellido'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Segundo Apellido</label>
                        <input type="text" name="segundo_apellido" class="form-control" value="<?= htmlspecialchars($matricula['segundo_apellido'] ?? '') ?>">
                    </div>
                </div>

                <!-- SECCIÓN: PERFIL -->
                <h3 class="form-section-title" style="margin-top: 2rem;">Perfil Académico / Profesional</h3>
                <div class="grid-form">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Profesión</label>
                        <input type="text" name="profesion" class="form-control" value="<?= htmlspecialchars($matricula['profesion'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nivel de Estudios</label>
                        <select name="estudios" class="form-control">
                            <option value=""></option>
                            <option value="Secundaria" <?= ($matricula['estudios'] ?? '') == 'Secundaria' ? 'selected' : '' ?>>Secundaria</option>
                            <option value="Bachillerato" <?= ($matricula['estudios'] ?? '') == 'Bachillerato' ? 'selected' : '' ?>>Bachillerato</option>
                            <option value="Grado" <?= ($matricula['estudios'] ?? '') == 'Grado' ? 'selected' : '' ?>>Grado</option>
                        </select>
                    </div>
                </div>

                <!-- SECCIÓN: DIRECCIÓN -->
                <h3 class="form-section-title" style="margin-top: 2rem;">Dirección y Contacto</h3>
                <div class="grid-form">
                    <div class="form-group">
                        <label>Tipo de Vía</label>
                        <select name="tipo_via" class="form-control">
                            <option value=""></option>
                            <option value="Calle" <?= ($matricula['tipo_via'] ?? '') == 'Calle' ? 'selected' : '' ?>>Calle</option>
                            <option value="Avenida" <?= ($matricula['tipo_via'] ?? '') == 'Avenida' ? 'selected' : '' ?>>Avenida</option>
                            <option value="Camino" <?= ($matricula['tipo_via'] ?? '') == 'Camino' ? 'selected' : '' ?>>Camino</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nombre de Vía</label>
                        <input type="text" name="nombre_via" class="form-control" value="<?= htmlspecialchars($matricula['nombre_via'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nº Domicilio</label>
                        <input type="text" name="num_domicilio" class="form-control" value="<?= htmlspecialchars($matricula['num_domicilio'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-form" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="form-group">
                        <label>Escalera</label>
                        <input type="text" name="escalera" class="form-control" value="<?= htmlspecialchars($matricula['escalera'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Planta</label>
                        <input type="text" name="planta" class="form-control" value="<?= htmlspecialchars($matricula['planta'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Puerta</label>
                        <input type="text" name="puerta" class="form-control" value="<?= htmlspecialchars($matricula['puerta'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Código Postal</label>
                        <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars($matricula['cp'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($matricula['localidad'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Provincia</label>
                        <select name="provincia" class="form-control">
                            <option value=""></option>
                            <option value="MADRID" <?= ($matricula['provincia'] ?? '') == 'MADRID' ? 'selected' : '' ?>>MADRID</option>
                            <option value="BARCELONA" <?= ($matricula['provincia'] ?? '') == 'BARCELONA' ? 'selected' : '' ?>>BARCELONA</option>
                            <option value="VALENCIA" <?= ($matricula['provincia'] ?? '') == 'VALENCIA' ? 'selected' : '' ?>>VALENCIA</option>
                            <option value="SEVILLA" <?= ($matricula['provincia'] ?? '') == 'SEVILLA' ? 'selected' : '' ?>>SEVILLA</option>
                            <option value="VALLADOLID" <?= ($matricula['provincia'] ?? '') == 'VALLADOLID' ? 'selected' : '' ?>>VALLADOLID</option>
                            <!-- Idealmente rellenar con un array PHP -->
                        </select>
                    </div>
                </div>

                <div class="grid-form">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($matricula['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($matricula['email'] ?? '') ?>">
                    </div>
                </div>
            </form>
        </div>

    </main>
</div>

</body>
</html>
HTML;

file_put_contents($file, $php_logic . "\n" . $modern_html);
echo "File successfully rewritten.";
