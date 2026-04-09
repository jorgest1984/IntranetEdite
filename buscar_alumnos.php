<?php
// buscar_alumnos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

// Listas para dropdowns
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];

$estados = [
    "Abandono", "Admitido", "Baja", "Baja por colocación", "Empleado en curso", "Espera", "Finalizado", "Finalizado sobrante", "Inscrito", "Pendiente docu", "Pendiente estado", "Pendiente otro curso", "Pendiente validacion", "Preinscrito", "Reserva"
];

$colectivos = [
    "Cuidadores no profesionales de las personas en situación de dependencia", "Empleado hogar", "ERE (Art. 51 y 52 del Estatuto de los Trabajadores)", "ERTE (Art. 47 del Estatuto de los Trabajadores)", "Fijos discontinuos en periodo de no ocupación", "Mutualistas de Colegios Profesionales no incluidos como autónomos", "Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.", "Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.", "Régimen especial agrario por cuenta ajena", "Régimen especial agrario por cuenta propia", "Régimen especial autónomos", "Régimen general", "Regulación de empleo en periodos de no ocupación", "Trabajador con contrato a tiempo parcial", "Trabajador con contrato temporal", "Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación", "Trabajadores con convenio especial con la Seguridad Social", "Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores", "Trabajadores incluidos en el Régimen especial del mar", "Trabajadores no ocupados inscritos como demandantes de empleo en los servicios públicos de empleo", "administración pública"
];

$comerciales = [];
$convocatorias = [];
$planes = [];
$centros_db = [];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 50")->fetchAll();
    $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC LIMIT 50")->fetchAll();
    
    // Obtener Comerciales (Rol 'Comercial')
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();

    // Obtener Centros (Tabla empresas para el datalist)
    $stmtEmpresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100");
    $centros_db = $stmtEmpresas->fetchAll();

} catch (Exception $e) {}

$current_page = 'buscar_alumnos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Alumno - <?= APP_NAME ?></title>
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
        .main-content { padding: 1.5rem; }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header-custom {
            background: #fff;
            padding: 0.5rem;
            border-bottom: 2px solid var(--border-gray);
            text-align: center;
        }

        .card-header-custom h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .search-form { padding: 1rem; }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--label-blue);
            white-space: nowrap;
        }

        .form-control {
            font-size: 0.8rem;
            padding: 2px 5px;
            border: 1px solid var(--border-gray);
            border-radius: 2px;
            background: #fff;
        }

        select.form-control { height: 24px; padding: 0 5px; }
        input[type="text"].form-control, input[type="date"].form-control { height: 22px; }

        .btn-buscar {
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            padding: 4px 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 3px;
        }

        .btn-buscar:hover { background: #e2e8f0; }
        
        .btn-print {
            background: #fff;
            border: 1px solid var(--border-gray);
            padding: 2px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Results Table */
        .results-section {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .results-header {
            padding: 0.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
        }

        .results-header h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--title-red);
            text-transform: uppercase;
        }

        .table-responsive { overflow-x: auto; }
        
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 6px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
        }

        .table-custom td {
            border: 1px solid #f1f5f9;
            padding: 6px;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #f1f5f9; }

        .btn-volver {
            margin-top: 10px;
            padding: 4px 15px;
            font-size: 0.75rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>ALUMNOS - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Cod. Alumno:</label>
                            <input type="text" name="id" class="form-control" style="width: 60px;">
                        </div>
                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" class="form-control" style="width: 150px;">
                        </div>
                        <div class="form-group">
                            <label>Apellidos:</label>
                            <input type="text" name="apellidos" class="form-control" style="width: 250px;">
                        </div>
                        <div class="form-group">
                            <label>Cod. Grupo:</label>
                            <input type="text" name="cod_grupo" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <?php foreach($estados as $est): ?><option value="<?= $est ?>"><?= $est ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial" class="form-control" style="width: 250px;">
                                <option value="">---</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" class="form-control" list="centros_list" placeholder="Escriba empresa..." style="width: 250px;">
                            <datalist id="centros_list">
                                <?php foreach($centros_db as $cent): ?><option value="<?= htmlspecialchars($cent['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Colectivo:</label>
                            <select name="colectivo" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach($colectivos as $col): ?><option value="<?= $col ?>"><?= $col ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>NIF:</label>
                            <input type="text" name="dni" class="form-control" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>Tlfno/móvil:</label>
                            <input type="text" name="telefono" class="form-control" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>Provincia:</label>
                            <input type="text" name="provincia" class="form-control" list="provincias_list" style="width: 150px;">
                            <datalist id="provincias_list">
                                <?php foreach($provincias as $prov): ?><option value="<?= mb_strtoupper($prov, 'UTF-8') ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>E-mail:</label>
                            <input type="text" name="email" class="form-control" style="width: 150px;">
                        </div>
                    </div>

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria" class="form-control" style="width: 80px;">
                                <option value="Todas">Todas</option>
                                <?php foreach($convocatorias as $conv): ?><option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan" class="form-control" style="width: 450px;">
                                <option value="">------------ Todos los planes ------------</option>
                                <?php foreach($planes as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Incluir contactos:</label>
                            <input type="checkbox" name="incluir_1">
                            <input type="checkbox" name="incluir_2">
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        <button type="button" class="btn-print">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/8/87/PDF_file_icon.svg" width="16" alt="PDF">
                            Imprimir
                        </button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->
            <div class="results-section">
                <div class="results-header">
                    <div style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                        <input type="checkbox"> Ordenar múltiple
                    </div>
                    <h2>RESULTADO DE LA BÚSQUEDA</h2>
                </div>
                
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Apellidos</th>
                                <th>NIF</th>
                                <th>Provincia</th>
                                <th>Empresa</th>
                                <th>E-mail</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">
                                    Utilice los filtros para realizar una búsqueda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align: center;">
                <button class="btn-volver" onclick="window.history.back()">Volver</button>
            </div>

        </main>
    </div>
</body>
</html>
