<?php
// envio_claves.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'envio_claves.php';

// Datos para combos (Simulados o de DB)
$planes = [['id'=>1, 'nombre'=>'Plan 2024'], ['id'=>2, 'nombre'=>'Plan 2025']];
$modalidades = ['Teleformación', 'Presencial', 'Mixta'];
$estados = ['Activo', 'Admitido', 'Finalizado', 'Cancelado'];
$comerciales = [['id'=>1, 'nombre'=>'Juan Pérez'], ['id'=>2, 'nombre'=>'Ana García']];
$tutores = [['id'=>1, 'nombre'=>'Prof. Martínez'], ['id'=>2, 'nombre'=>'Prof. Rodríguez']];

$resultados_simulados = isset($_GET['buscar']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío de Claves - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-light: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 2rem; }

        .breadcrumb {
            background-color: #f8fafc;
            padding: 12px 20px;
            border-radius: 4px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 1.5rem;
        }

        .form-group-col {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group-col label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.85rem;
        }

        .form-control {
            font-size: 0.85rem;
            padding: 6px 10px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
            width: 100%;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.5rem;
            margin-top: 1rem;
        }

        .btn-buscar {
            background: #2563eb;
            color: white;
            border: 1px solid #1d4ed8;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-buscar:hover { background: #1d4ed8; }

        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bulk-actions select {
            width: 250px;
        }

        /* Tabla densa con muchos campos */
        .table-container {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            position: relative;
        }

        .table-custom {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem; /* Más pequeña por la cantidad de columnas */
        }

        .table-custom th {
            padding: 10px 8px;
            text-align: left;
            color: #fff;
            font-weight: 700;
            background: #333;
            border-bottom: 2px solid #000;
            white-space: nowrap;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            color: #334155;
            white-space: nowrap;
        }

        .table-custom tr:hover { background-color: #f8fafc; }

        .badge-status {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-yes { background: #dcfce7; color: #166534; }
        .status-no { background: #fee2e2; color: #991b1b; }

    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a> / 
                <a href="formacion_profesional.php">Formación</a> / 
                Envío de claves a plataforma
            </div>

            <div class="search-card">
                <form method="GET">
                    <input type="hidden" name="buscar" value="1">
                    
                    <div class="search-grid">
                        <div class="form-group-col" style="grid-column: span 2;">
                            <label>Curso</label>
                            <input type="text" name="curso" class="form-control" placeholder="Nombre del curso...">
                        </div>
                        <div class="form-group-col">
                            <label>Código grupo</label>
                            <input type="text" name="grupo" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Plan</label>
                            <select name="plan" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($planes as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Modalidad</label>
                            <select name="modalidad" class="form-control">
                                <option value="">- Todas -</option>
                                <?php foreach($modalidades as $m): ?>
                                    <option value="<?= $m ?>"><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-grid">
                        <div class="form-group-col">
                            <label>Inicio desde</label>
                            <input type="date" name="inicio_desde" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Inicio hasta</label>
                            <input type="date" name="inicio_hasta" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Fin desde</label>
                            <input type="date" name="fin_desde" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Fin hasta</label>
                            <input type="date" name="fin_hasta" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Empresa</label>
                            <input type="text" name="empresa" class="form-control">
                        </div>
                        <div class="form-group-col">
                            <label>Estado</label>
                            <select name="estado" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($estados as $e): ?>
                                    <option value="<?= $e ?>"><?= $e ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="search-grid">
                        <div class="form-group-col">
                            <label>Comercial</label>
                            <select name="comercial" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Claves enviadas?</label>
                            <select name="claves_env" class="form-control">
                                <option value="">-</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Conectados?</label>
                            <select name="conectados" class="form-control">
                                <option value="">-</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>E-mail?</label>
                            <select name="has_email" class="form-control">
                                <option value="">-</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Tutor</label>
                            <select name="tutor" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= $t['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-col">
                            <label>Tipo cursos</label>
                            <select name="tipo_curso" class="form-control">
                                <option value="CP">Contratos-programa</option>
                                <option value="B">Bonificados</option>
                                <option value="O">Otros</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        
                        <div class="bulk-actions">
                            <span style="font-size: 0.85rem; font-weight: 600; color: #475569;">Con seleccionados:</span>
                            <select class="form-control" style="width: 200px;">
                                <option value="">- Elegir acción -</option>
                                <option value="send">Enviar Claves por Email</option>
                                <option value="create">Crear en Plataforma</option>
                                <option value="update">Actualizar Datos</option>
                            </select>
                            <button type="button" class="btn-buscar" style="padding: 6px 15px; font-size: 0.8rem; background: #64748b; border-color: #475569;">Aceptar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="background: #333; color: white; padding: 10px 15px; font-size: 1rem; font-weight: 700; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center;">
                ALUMNOS
                <span style="font-size: 0.8rem; font-weight: 400;">Mostrando registros</span>
            </div>
            
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th><input type="checkbox"></th>
                            <th>Curso</th>
                            <th>Plan</th>
                            <th>Alumno</th>
                            <th>NumAcc</th>
                            <th>Grupo</th>
                            <th>Empresa</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Claves</th>
                            <th>Fecha</th>
                            <th>Idgrupo</th>
                            <th>Idplat2016</th>
                            <th>User</th>
                            <th>Clave</th>
                            <th>Email</th>
                            <th>Conec</th>
                            <th>Fecha</th>
                            <th>EvI</th>
                            <th>EvF</th>
                            <th>Cs_Hab</th>
                            <th>EvSen</th>
                            <th>Enc</th>
                            <th>Admis</th>
                            <th>Crear grupo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($resultados_simulados): ?>
                        <tr>
                            <td><input type="checkbox"></td>
                            <td>Diseño Gráfico Avanzado</td>
                            <td>Plan 2024</td>
                            <td>PEREZ GARCIA, JUAN</td>
                            <td>123</td>
                            <td>A30-G4</td>
                            <td>Grupo EFP S.L.</td>
                            <td>01/03/2026</td>
                            <td>30/03/2026</td>
                            <td><span class="badge-status status-yes">SI</span></td>
                            <td>20/03/2026</td>
                            <td>554</td>
                            <td>998</td>
                            <td>jperez</td>
                            <td>*****</td>
                            <td>juan@email.com</td>
                            <td><span class="badge-status status-no">NO</span></td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>S</td>
                            <td>N</td>
                            <td>P</td>
                            <td>OK</td>
                            <td><button style="font-size: 0.7rem;">Crear</button></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="25" style="text-align: center; padding: 3rem; color: #64748b; font-style: italic;">
                                Filtre por grupo o plan para listar los alumnos y gestionar sus claves.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</body>
</html>
