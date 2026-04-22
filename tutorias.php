<?php
// tutorias.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$active_view = $_GET['view'] ?? 'dashboard';
$error = '';
$success = '';

// Listas para dropdowns (placeholder para la integración futura con DB)
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];
$comerciales = [];
$tutores = [];
$convocatorias = [];
$planes = [];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 50")->fetchAll();
    
    // Obtener Comerciales (Rol 'Comercial' o buscando por nombre de rol similar)
    $stmtComerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtComerciales->fetchAll();

    // Obtener Tutores (Rol 'Formador' o 'Tutor')
    $stmtTutores = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Formador%' OR r.nombre LIKE '%Tutor%') AND u.activo = 1 ORDER BY u.nombre ASC");
    $tutores = $stmtTutores->fetchAll();

    // Obtener Centros (Tabla empresas)
    $stmtEmpresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    $centros_db = $stmtEmpresas->fetchAll();

    // Lógica de búsqueda para Llamadas de Seguimiento
    $llamadas = [];
    if ($active_view === 'llamadas' && isset($_GET['fecha_desde'])) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($_GET['fecha_desde'])) { $where[] = "fecha >= ?"; $params[] = $_GET['fecha_desde']; }
        if (!empty($_GET['fecha_hasta'])) { $where[] = "fecha <= ?"; $params[] = $_GET['fecha_hasta']; }
        if (!empty($_GET['nombre'])) { $where[] = "a.nombre LIKE ?"; $params[] = "%".$_GET['nombre']."%"; }
        if (!empty($_GET['apellidos'])) { $where[] = "a.primer_apellido LIKE ?"; $params[] = "%".$_GET['apellidos']."%"; }
        if (!empty($_GET['empresa'])) { $where[] = "(ts.empresa LIKE ? OR e.nombre LIKE ?)"; $params[] = "%".$_GET['empresa']."%"; $params[] = "%".$_GET['empresa']."%"; }
        if (!empty($_GET['usuario_id'])) { $where[] = "ts.usuario_id = ?"; $params[] = $_GET['usuario_id']; }
        if (!empty($_GET['motivo'])) { $where[] = "ts.motivo = ?"; $params[] = $_GET['motivo']; }
        if (!empty($_GET['forma'])) { $where[] = "ts.forma = ?"; $params[] = $_GET['forma']; }

        $sql = "SELECT ts.*, a.nombre as alumno_nombre, a.primer_apellido as alumno_apellido, e.nombre as empresa_nombre, c.nombre as curso_nombre
                FROM tutorias_seguimiento ts
                LEFT JOIN alumnos a ON ts.alumno_id = a.id
                LEFT JOIN empresas e ON ts.empresa_id = e.id
                LEFT JOIN cursos c ON ts.curso_id = c.id
                WHERE " . implode(" AND ", $where) . " ORDER BY ts.fecha DESC, ts.hora DESC LIMIT 100";
        $stmtS = $pdo->prepare($sql);
        $stmtS->execute($params);
        $llamadas = $stmtS->fetchAll();
    }

} catch (Exception $e) {}

$current_page = 'tutorias.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutorías - <?= APP_NAME ?></title>
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

        .status-header {
            display: flex;
            gap: 5px;
            padding: 5px;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
        }

        .status-box { padding: 2px 5px; border-radius: 2px; }
        .bg-orange { background: #f97316; }
        .bg-cyan { background: #06b6d4; }
        .bg-pink { background: #ec4899; }
        .bg-teal { background: #14b8a6; }
        .bg-green { background: #16a34a; }

        .table-responsive { overflow-x: auto; }
        
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }

        .table-custom th {
            background: #f8fafc;
            border: 1px solid var(--border-gray);
            padding: 4px;
            text-align: left;
            color: var(--label-blue);
            font-weight: 700;
            position: relative;
        }

        .table-custom th .sort-icon {
            display: inline-block;
            margin-right: 3px;
            vertical-align: middle;
        }

        .table-custom td {
            border: 1px solid #f1f5f9;
            padding: 4px;
            white-space: nowrap;
        }

        .table-custom tr:nth-child(even) { background: #f8fafc; }
        .table-custom tr:hover { background: #f1f5f9; }

        /* Action Bar for Tutorias */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 10px 15px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .action-bar .btn-action {
            background: #f1f5f9;
            border: 1px solid var(--border-gray);
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--label-blue);
            cursor: pointer;
            border-radius: 3px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .action-bar .btn-action:hover {
            background: #e2e8f0;
            color: var(--title-red);
        }

        /* Sidebar highlighting */
        .sidebar-menu li a.active {
            background: rgba(30, 64, 175, 0.1);
            color: #1e40af;
            font-weight: 600;
        }

        .active-btn {
            background: #e2e8f0 !!important;
            border-color: var(--title-red) !!important;
            color: var(--title-red) !!important;
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <!-- ACTION BAR -->
            <div class="action-bar">
                <a href="tutorias.php?view=calcular" class="btn-action <?= $active_view == 'calcular' ? 'active-btn' : '' ?>">Calcular llamadas</a>
                <a href="email_masivo.php" class="btn-action">E-mails masivos</a>
                <button type="button" class="btn-action">Inicio curso ()</button>
                <button type="button" class="btn-action">Mitad de curso ()</button>
                <button type="button" class="btn-action">7 días fin ()</button>
                <button type="button" class="btn-action">Documentación ()</button>
                <button type="button" class="btn-action">Subir evals</button>
                <button type="button" class="btn-action">Imprimir evals</button>
                <a href="tutorias.php?view=llamadas" class="btn-action <?= $active_view == 'llamadas' ? 'active-btn' : '' ?>">Llamadas seguimiento</a>
                <a href="calendario_tutorias.php" class="btn-action">Calendario de tutorias</a>
            </div>

            <?php if ($active_view === 'calcular'): ?>
            <!-- VISTA: CALCULAR LLAMADAS (BUSCADOR AVANZADO v1.5) -->
            <div class="search-card">
                <div class="card-header-custom" style="display:flex; justify-content: space-between; align-items: center; padding: 2px 10px; border-bottom: 3px solid #1e40af;">
                    <div class="form-group" style="gap: 10px;">
                        <label style="color: #000; font-weight: 700;">TUTOR:</label>
                        <select class="form-control" style="width: 200px;">
                            <option value="">---</option>
                            <?php foreach ($tutores as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'].' '.$t['apellidos']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="gap: 10px;">
                        <label style="color: #000; font-weight: 700;">Solicitante:</label>
                        <select class="form-control" style="width: 250px;">
                            <option value="">---</option>
                            <option value="COMFIA">COMFIA</option>
                            <option value="FED. COM. Y TTE. CCOO MADRID">FED. COM. Y TTE. CCOO MADRID</option>
                            <!-- ... resto de opciones ... -->
                        </select>
                    </div>
                    <div class="form-group" style="gap: 10px;">
                        <label style="color: #000; font-weight: 700;">Comercial:</label>
                        <select class="form-control" style="width: 200px;">
                            <option value="">---</option>
                            <?php foreach ($comerciales as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn-action" style="padding: 2px 8px; font-size: 0.7rem;">Quitar autofiltro</button>
                </div>
                
                <form class="search-form" method="GET" style="padding: 10px;">
                    <input type="hidden" name="view" value="calcular">
                    
                    <!-- Fila 2: Datos Personales -->
                    <div class="search-row" style="justify-content: center; background: #f8fafc; padding: 5px; border-radius: 4px; border: 1px solid #e2e8f0; margin-bottom: 10px;">
                        <div class="form-group"><label>Nombre:</label><input type="text" name="nombre" class="form-control" style="width: 150px;"></div>
                        <div class="form-group"><label>Apellidos:</label><input type="text" name="apellidos" class="form-control" style="width: 200px;"></div>
                        <div class="form-group"><label>NIF:</label><input type="text" name="nif" class="form-control" style="width: 100px;"></div>
                        <div class="form-group"><label>E-mail:</label><input type="text" name="email" class="form-control" style="width: 180px;"></div>
                        <div class="form-group">
                            <label><svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: middle;"><path fill="#ca8a04" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></label>
                            <label>Teléfono:</label><input type="text" name="telefono" class="form-control" style="width: 100px;">
                        </div>
                    </div>

                    <!-- Fila 3: Ubicación y Empresa -->
                    <div class="search-row" style="justify-content: center; margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Provincia:</label>
                            <select name="provincia" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <?php foreach($provincias as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Empresa:</label><input type="text" name="empresa" class="form-control" style="width: 180px;"></div>
                        <div class="form-group">
                            <label>Colectivo:</label>
                            <select name="colectivo" class="form-control" style="width: 300px;">
                                <option value="">---</option>
                                <option value="Régimen general">Régimen general</option>
                                <option value="Autónomo">Autónomo</option>
                                <option value="Desempleado">Desempleado</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 4: Académico -->
                    <div class="search-row" style="justify-content: center; margin-bottom: 10px;">
                        <div class="form-group"><label>Acción:</label><input type="text" name="accion" class="form-control" style="width: 60px;"></div>
                        <div class="form-group"><label>Grupo:</label><input type="text" name="grupo" class="form-control" style="width: 60px;"></div>
                        <div class="form-group"><label>Curso:</label><input type="text" name="curso" class="form-control" style="width: 150px;"></div>
                        <div class="form-group">
                            <label>Grupos en curso:</label>
                            <select name="grupos_en_curso" class="form-control" style="width: 60px;"><option value=""></option></select>
                        </div>
                        <div class="form-group"><label>Cod grupo:</label><input type="text" name="cod_grupo" class="form-control" style="width: 100px;"></div>
                    </div>

                    <!-- Fila 5: Plan y Estado -->
                    <div class="search-row" style="justify-content: center; background: #eff6ff; padding: 5px; border-radius: 4px; border: 1px solid #dbeafe; margin-bottom: 10px;">
                        <div class="form-group"><label>Convocatoria:</label><select class="form-control" style="width: 250px;"><option value="Todas">Todas</option></select></div>
                        <div class="form-group"><label>Plan:</label><select class="form-control" style="width: 400px;"><option value="">Todos los planes</option></select></div>
                        <div class="form-group"><label>Estado:</label><select class="form-control" style="width: 150px;"><option value=""></option></select></div>
                    </div>
                    <div class="search-row" style="justify-content: center; margin-bottom: 10px;">
                        <div class="form-group"><label>Modalidad:</label><select class="form-control" style="width: 150px;"><option value=""></option></select></div>
                    </div>

                    <!-- Fila 6: Estados Documentales -->
                    <div class="search-row" style="justify-content: center; font-size: 0.7rem; gap: 15px;">
                        <div class="form-group"><label>Entregado mat:</label><select class="form-control" style="width: 50px;"><option value=""></option></select></div>
                        <div class="form-group"><label>Horario:</label><input type="text" class="form-control" style="width: 80px;"></div>
                        <div class="form-group"><label>Conectados:</label><select class="form-control" style="width: 60px;"><option value=""></option></select></div>
                        <div class="form-group"><label>Realizó encuesta:</label><select class="form-control" style="width: 60px;"><option value=""></option></select></div>
                        <div class="form-group"><label>Docu alumno pte:</label><select class="form-control" style="width: 60px;"><option value=""></option></select></div>
                        <div class="form-group"><label>Docu curso pte:</label><select class="form-control" style="width: 60px;"><option value=""></option></select></div>
                        <div class="form-group"><label>Evaluaciones hechas sin subir:</label><input type="checkbox"></div>
                    </div>

                    <!-- Fila 7: Fechas v2 -->
                    <div class="search-row" style="justify-content: center; margin-top: 10px; font-size: 0.7rem;">
                        <div class="form-group"><label>Inicio desde:</label><input type="text" class="form-control" placeholder="dd/mm/aaaa" style="width: 80px;"> <label>hasta:</label><input type="text" class="form-control" style="width: 80px;"></div>
                        <div class="form-group" style="margin-left: 10px;"><label>25% desde:</label><input type="text" class="form-control" style="width: 80px;"> <label>hasta:</label><input type="text" class="form-control" style="width: 80px;"></div>
                        <div class="form-group" style="margin-left: 10px;"><label>Mitad desde:</label><input type="text" class="form-control" style="width: 80px;"> <label>hasta:</label><input type="text" class="form-control" style="width: 80px;"></div>
                        <div class="form-group" style="margin-left: 10px;"><label>Fin desde:</label><input type="text" class="form-control" style="width: 80px;"> <label>hasta:</label><input type="text" class="form-control" style="width: 80px;"></div>
                    </div>

                    <!-- Fila 8: No conectados -->
                    <div class="search-row" style="justify-content: center; margin-top: 10px;">
                        <div class="form-group" style="color: var(--title-red); font-weight: 700;"><label>Alumnos NO conectados antes del 15%</label><input type="checkbox"></div>
                        <div class="form-group" style="color: var(--title-red); font-weight: 700; margin-left: 20px;"><label>Alumnos NO conectados antes del 25%</label><input type="checkbox"></div>
                    </div>

                    <div style="text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 10px;">
                        <button type="submit" class="btn-buscar" style="background: #1e40af; color: #fff; padding: 4px 30px;">Buscar</button>
                        <button type="reset" class="btn-buscar">Limpiar filtros de búsqueda</button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS: RELACION DE CURSOS (27 COLUMNAS) -->
            <div class="results-section" style="margin-top: 20px;">
                <div class="results-header" style="background: #fff; padding: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <div style="font-size: 0.75rem; font-weight: 700;"><input type="checkbox"> Ordenar múltiple</div>
                        <div style="color: var(--title-red); font-size: 1rem; font-weight: 900;">RELACIÓN DE CURSOS</div>
                        <div style="font-size: 0.7rem; color: #64748b;">(registros)</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-custom" style="min-width: 2500px;">
                        <thead>
                            <tr style="background: #eff6ff;">
                                <th><span class="sort-icon">⬇</span>Plan</th>
                                <th><span class="sort-icon">⬇</span>Ac/Gr</th>
                                <th><span class="sort-icon">⬇</span>Curso</th>
                                <th><span class="sort-icon">⬇</span>Alumno</th>
                                <th><span class="sort-icon">⬇</span>NIF</th>
                                <th><span class="sort-icon">⬇</span>Empresa</th>
                                <th><span class="sort-icon">⬇</span>Fecha ins</th>
                                <th><span class="sort-icon">⬇</span>Inicio</th>
                                <th><span class="sort-icon">⬇</span>25%</th>
                                <th><span class="sort-icon">⬇</span>Mitad</th>
                                <th><span class="sort-icon">⬇</span>Fin</th>
                                <th><span class="sort-icon">⬇</span>Estado</th>
                                <th><span class="sort-icon">⬇</span>Ult Fecha Mat.</th>
                                <th><span class="sort-icon">⬇</span>Ultima conex</th>
                                <th style="color: var(--title-red); background: #fee2e2;"><span class="sort-icon">⬇</span>Fecha ult. llam.</th>
                                <th><span class="sort-icon">⬇</span>Eval N</th>
                                <th><span class="sort-icon">⬇</span>Eval I</th>
                                <th><span class="sort-icon">⬇</span>Eval F</th>
                                <th><span class="sort-icon">⬇</span>Fecha final. alumno</th>
                                <th><span class="sort-icon">⬇</span>Doc conexión</th>
                                <th><span class="sort-icon">⬇</span>Encu</th>
                                <th><span class="sort-icon">⬇</span>Dipl</th>
                                <th><span class="sort-icon">⬇</span>Fecha Dipl.</th>
                                <th><span class="sort-icon">⬇</span>Doc inic. pte</th>
                                <th><span class="sort-icon">⬇</span>Datos ptes</th>
                                <th><span class="sort-icon">⬇</span>Docu alumno pte</th>
                                <th><span class="sort-icon">⬇</span>Docu curso pte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="27" style="text-align: center; padding: 3rem; color: #94a3b8; font-style: italic;">
                                    Realice una búsqueda para generar resultados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- LEYENDAS Y EXPORTACIÓN -->
            <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 15px; font-size: 0.8rem; font-weight: 600; padding: 10px; background: #fff; border-radius: 4px; border: 1px solid var(--border-gray);">
                <div style="display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #ffedd5; border: 1px solid #fb923c;"></div> Autónom@</div>
                <div style="display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #dcfce7; border: 1px solid #4ade80;"></div> Desemplead@</div>
                <div style="display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #fef9c3; border: 1px solid #facc15;"></div> Suspendid@</div>
                <div style="display: flex; align-items: center; gap: 5px;"><div style="width: 15px; height: 15px; background: #7e22ce;"></div> Restringid@</div>
                <div style="display: flex; align-items: center; gap: 5px;"><span style="font-size: 1rem;">⚙️</span> Incid. pendientes < 4 días</div>
                <div style="display: flex; align-items: center; gap: 5px;"><span style="font-size: 1rem; color: red;">💥</span> Incid. pendientes más de 4 días</div>
            </div>

            <div style="text-align: center; margin-top: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                <button type="button" class="btn-action" style="background: #f1f5f9; color: var(--label-blue);">Listado en Excel</button>
                <a href="tutorias.php" class="btn-buscar" style="text-decoration: none;">Volver</a>
            </div>

            <?php elseif ($active_view === 'llamadas'): ?>
            <!-- VISTA: LLAMADAS DE SEGUIMIENTO -->
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>LLAMADAS REALIZADAS - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    <input type="hidden" name="view" value="llamadas">
                    
                    <!-- Fila 1: Desde, Hasta, Nombre, Apellidos -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Desde:</label>
                            <input type="date" name="fecha_desde" class="form-control" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                        </div>
                        <div class="form-group">
                            <label>»</label>
                        </div>
                        <div class="form-group">
                            <label>Hasta:</label>
                            <input type="date" name="fecha_hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>»</label>
                        </div>
                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" class="form-control" style="width: 150px;">
                        </div>
                        <div class="form-group">
                            <label>Apellidos:</label>
                            <input type="text" name="apellidos" class="form-control" style="width: 200px;">
                        </div>
                    </div>

                    <!-- Fila 2: Empresa, Usuario, Motivo, Forma -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" class="form-control" style="width: 250px;" list="centros_list">
                        </div>
                        <div class="form-group">
                            <label>Usuario:</label>
                            <select name="usuario_id" class="form-control" style="width: 200px;">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Motivo:</label>
                            <select name="motivo" class="form-control" style="width: 200px;">
                                <option value="">---</option>
                                <option value="Seguimiento">Seguimiento</option>
                                <option value="Bienvenida">Bienvenida</option>
                                <option value="Incidencia">Incidencia</option>
                                <option value="Encuesta">Encuesta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Forma:</label>
                            <select name="forma" class="form-control" style="width: 100px;">
                                <option value="">---</option>
                                <option value="Teléfono">Teléfono</option>
                                <option value="Email">Email</option>
                                <option value="WhatsApp">WhatsApp</option>
                            </select>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-buscar">Buscar</button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS LLAMADAS -->
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
                                <th><span class="sort-icon">⬇</span>Empresa</th>
                                <th><span class="sort-icon">⬇</span>Alumno</th>
                                <th><span class="sort-icon">⬇</span>Curso</th>
                                <th><span class="sort-icon">⬇</span>Motivo</th>
                                <th><span class="sort-icon">⬇</span>Forma</th>
                                <th><span class="sort-icon">⬇</span>Fecha</th>
                                <th><span class="sort-icon">⬇</span>Hora</th>
                                <th><span class="sort-icon">⬇</span>Asunto</th>
                                <th><span class="sort-icon">⬇</span>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($llamadas)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 1rem; color: var(--label-blue); font-weight: bold;">
                                        0 registros.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($llamadas as $ll): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ll['empresa_nombre'] ?: $ll['empresa']) ?></td>
                                        <td><?= htmlspecialchars($ll['alumno_nombre'] . ' ' . $ll['alumno_apellido']) ?></td>
                                        <td><?= htmlspecialchars($ll['curso_nombre']) ?></td>
                                        <td><?= htmlspecialchars($ll['motivo']) ?></td>
                                        <td><?= htmlspecialchars($ll['forma']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($ll['fecha'])) ?></td>
                                        <td><?= date('H:i', strtotime($ll['hora'])) ?></td>
                                        <td><?= htmlspecialchars($ll['asunto']) ?></td>
                                        <td>
                                            <span title="<?= htmlspecialchars($ll['notas']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($ll['notas'], 0, 30, "...")) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="tutorias.php" class="btn-buscar" style="text-decoration: none; display: inline-block;">Volver</a>
            </div>

            <?php else: ?>
            <!-- BUSCADOR -->
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>TUTORÍAS - CAMPOS DE BÚSQUEDA</h2>
                </div>
                <form class="search-form" method="GET">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Curso:</label>
                            <input type="text" name="curso" class="form-control" style="width: 150px;">
                        </div>
                        <div class="form-group">
                            <label>Código grupo:</label>
                            <input type="text" name="cod_grupo" class="form-control" style="width: 100px;">
                        </div>
                        <div class="form-group">
                            <label>Mostrar alumnos sin grupo</label>
                            <input type="checkbox" name="sin_grupo">
                        </div>
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 250px;">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha inscripción desde:</label>
                            <input type="date" name="fecha_desde" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>inscripción hasta:</label>
                            <input type="date" name="fecha_hasta" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Provincia:</label>
                            <input type="text" name="provincia" class="form-control" list="provincias_list" placeholder="Escriba provincia..." style="width: 150px;">
                            <datalist id="provincias_list">
                                <?php foreach($provincias as $prov): ?><option value="<?= mb_strtoupper($prov, 'UTF-8') ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Motivo No-Admisión:</label>
                            <select name="motivo_rechazo" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Acción agotada">Acción agotada</option>
                                <option value="Acción no programable">Acción no programable</option>
                                <option value="Falta doc">Falta doc</option>
                                <option value="Falta grupo">Falta grupo</option>
                                <option value="Falta tutor">Falta tutor</option>
                                <option value="Otro curso">Otro curso</option>
                                <option value="Plan agotado">Plan agotado</option>
                                <option value="Sector NO Correcto">Sector NO Correcto</option>
                                <option value="Tutor completo">Tutor completo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <!-- Campo Solicitante - Actualizado v1.1 -->
                        <div class="form-group">
                            <label>Solicitante:</label>
                            <select name="solicitante" class="form-control" style="width: 280px;">
                                <option value="">---</option>
                                <option value="COMFIA">COMFIA</option>
                                <option value="FED. COM. Y TTE. CCOO MADRID">FED. COM. Y TTE. CCOO MADRID</option>
                                <option value="UGT DE CATALUNYA">UGT DE CATALUNYA</option>
                                <option value="UGT Madrid">UGT Madrid</option>
                                <option value="FETCM-UGT">FETCM-UGT</option>
                                <option value="FETE-UGT">FETE-UGT</option>
                                <option value="FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA">FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZA</option>
                                <option value="MARS">MARS</option>
                                <option value="FITAG">FITAG</option>
                                <option value="Comunidad de Madrid">Comunidad de Madrid</option>
                                <option value="FAECTA">FAECTA</option>
                                <option value="UGT Andalucía">UGT Andalucía</option>
                                <option value="FTFE">FTFE</option>
                                <option value="Criteria">Criteria</option>
                                <option value="FSP-UGT Palencia">FSP-UGT Palencia</option>
                                <option value="JUNTA DE CASTILLA Y LEON">JUNTA DE CASTILLA Y LEON</option>
                                <option value="JUNTA DE ANDALUCIA">JUNTA DE ANDALUCIA</option>
                                <option value="CRUZ ROJA ESPAÑOLA">CRUZ ROJA ESPAÑOLA</option>
                                <option value="MARSDIGITAL S.L.">MARSDIGITAL S.L.</option>
                                <option value="FUNDACIÓN PIQUER">FUNDACIÓN PIQUER</option>
                                <option value="Fed. Estatal de servicios - UGT">Fed. Estatal de servicios - UGT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sexo:</label>
                            <select name="sexo" class="form-control"><option value="">---</option><option value="M">Hombre</option><option value="F">Mujer</option></select>
                        </div>
                        <div class="form-group">
                            <label>Colectivo:</label>
                            <select name="colectivo" class="form-control" style="width: 380px;">
                                <option value="">---</option>
                                <option value="Cuidadores no profesionales de las personas en situación de dependencia">Cuidadores no profesionales de las personas en situación de dependencia</option>
                                <option value="Empleado hogar">Empleado hogar</option>
                                <option value="ERE (Art. 51 y 52 del Estatuto de los Trabajadores)">ERE (Art. 51 y 52 del Estatuto de los Trabajadores)</option>
                                <option value="ERTE (Art. 47 del Estatuto de los Trabajadores)">ERTE (Art. 47 del Estatuto de los Trabajadores)</option>
                                <option value="Fijos discontinuos en periodo de no ocupación">Fijos discontinuos en periodo de no ocupación</option>
                                <option value="Mutualistas de Colegios Profesionales no incluidos como autónomos">Mutualistas de Colegios Profesionales no incluidos como autónomos</option>
                                <option value="Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.">Persona actualmente desempleada que anteriormente ha estado en situación de ERTE.</option>
                                <option value="Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.">Persona que actualmente está trabajando pero que anteriormente ha estado en situación de ERTE.</option>
                                <option value="Régimen especial agrario por cuenta ajena">Régimen especial agrario por cuenta ajena</option>
                                <option value="Régimen especial agrario por cuenta propia">Régimen especial agrario por cuenta propia</option>
                                <option value="Régimen especial autónomos">Régimen especial autónomos</option>
                                <option value="Régimen general">Régimen general</option>
                                <option value="Regulación de empleo en periodos de no ocupación">Regulación de empleo en periodos de no ocupación</option>
                                <option value="Trabajador con contrato a tiempo parcial">Trabajador con contrato a tiempo parcial</option>
                                <option value="Trabajador con contrato temporal">Trabajador con contrato temporal</option>
                                <option value="Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación">Trabajadores a tiempo parcial de carácter indefinido con trabajos discontinuos en sus periodos de no ocupación</option>
                                <option value="Trabajadores con convenio especial con la Seguridad Social">Trabajadores con convenio especial con la Seguridad Social</option>
                                <option value="Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores">Trabajadores con relaciones laborales de carácter especial que se recogen en el art.2 del Estatuto de los Trabajadores</option>
                                <option value="Trabajadores incluidos en el Régimen especial del mar">Trabajadores incluidos en el Régimen especial del mar</option>
                                <option value="Trabajadores no ocupados inscritos como demandantes de empleo en los servicios públicos de empleo">Trabajadores no ocupados inscritos como demandantes de empleo en los servicios públicos de empleo</option>
                                <option value="administración pública">administración pública</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>No válido:</label>
                            <select name="no_valido" class="form-control"><option value="">---</option><option value="S">SÍ</option><option value="N" selected>NO</option></select>
                        </div>
                        <div class="form-group">
                            <label>Mayor de 45:</label>
                            <select name="mayor_45" class="form-control"><option value="">---</option><option value="S">SÍ</option><option value="N">NO</option></select>
                        </div>
                    </div>

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group" style="margin-left: 360px;">
                            <label>Discapacitado:</label>
                            <select name="discapacitado" class="form-control"><option value="">---</option><option value="S">SÍ</option><option value="N">NO</option></select>
                        </div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Grupo cotización:</label>
                            <select name="grupo_cotizacion" class="form-control" style="width: 320px;">
                                <option value="">---</option>
                                <option value="1.- Ingenieros y licenciados">1.- Ingenieros y licenciados</option>
                                <option value="2.- Ingenieros técnicos, peritos y Aytes. titulados">2.- Ingenieros técnicos, peritos y Aytes. titulados</option>
                                <option value="3.- Jefes Advos. y de taller">3.- Jefes Advos. y de taller</option>
                                <option value="4.- Ayudantes no titulados">4.- Ayudantes no titulados</option>
                                <option value="5.- Oficiales administrativos">5.- Oficiales administrativos</option>
                                <option value="6.- Subalternos">6.- Subalternos</option>
                                <option value="7.- Auxiliares administrativos">7.- Auxiliares administrativos</option>
                                <option value="8.- Oficiales de primera y segunda">8.- Oficiales de primera y segunda</option>
                                <option value="9.- Oficiales de tercera y especialistas">9.- Oficiales de tercera y especialistas</option>
                                <option value="10.- Peones">10.- Peones</option>
                                <option value="11.- Trabajadores menores de 18 años">11.- Trabajadores menores de 18 años</option>
                                <option value="Trabajadores mayores de 18 años no cualif.">Trabajadores mayores de 18 años no cualif.</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Centro impartición:</label>
                            <input type="text" name="centro" class="form-control" list="centros_list" placeholder="Escriba el centro..." style="width: 500px;">
                            <datalist id="centros_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                                <option value="A. F. C. CONSULTING DEPORTIVO">
                                <option value="ACADEMIA CERVANTES , CARLOS AMEZ LAIZ CB">
                                <option value="ACADEMIA FIPP">
                                <option value="ACADEMIA SOCE S.L.U.">
                                <option value="ACADEMIA TECNAS">
                                <option value="ACADEMIA VIGILANT S.L.">
                                <option value="ACADEMIA VISAN">
                                <option value="ADAMS">
                                <option value="AE S. MARTIN">
                                <option value="AEFOL EXPOELEARNING S.L.">
                                <option value="AESS">
                                <option value="AFA-FORMACION CONTINUA S.L.">
                                <option value="AGE">
                                <option value="AMUSAL">
                                <option value="AREA FORMACION AULAS">
                                <option value="asimag servicios empresariales, s.l">
                                <option value="ASIMAG SERVICIOS EMPRESARIALES, S.L.">
                                <option value="Association Puerta de Alcalá">
                                <option value="ATENTO TELESERVICIOS ESPAÑA, S.A.">
                                <option value="AUDEMA">
                                <option value="AUTOESCUELA EMERITA S.L.">
                                <option value="AVEFOR ARAGÓN DAIDA PEREZ HERNANDEZ">
                                <option value="AVIZOR, CENTRO SUPERIOR DE FORMACIÓN EN ESTUDIOS D">
                                <option value="Ayuntamiento de Cajar">
                                <option value="AZUVIS S.C.A">
                                <option value="BODYFACTORY SOMOSAGUAS">
                                <option value="BOROXSPORT CLUB SPORT">
                                <option value="C/ CORCEGA,371">
                                <option value="CAD-SEGURIDAD">
                                <option value="CENTRO DE ENSEÑANZAS PROFESIONALES Y TECNOLOGICAS">
                                <option value="Centro de Estudio Arsenio Toral S.A.L.">
                                <option value="Centro de Estudio Arsenio Toral S.A.L.. 2012">
                                <option value="CENTRO DE ESTUDIOS APPA SCL">
                                <option value="CENTRO DE ESTUDIOS DE FORMACION ALFER">
                                <option value="CENTRO DE ESTUDIOS DE FORMACION ALFER S.L.">
                                <option value="CENTRO DE ESTUDIOS LA ACADEMIA CB">
                                <option value="Centro de Estudios y Experimentación de Obras Públ">
                                <option value="CENTRO DE FORMACION ALFER">
                                <option value="CENTRO DE FORMACION ARSENIO JIMENO">
                                <option value="centro de formación oasis">
                                <option value="CENTRO DE FORMACION PRAXIS">
                                <option value="CENTRO DE FORMACION PRAXIS II">
                                <option value="CENTRO EMPRESARIAL CEMEI">
                                <option value="CEPAL">
                                <option value="CFI SEGURIDAD">
                                <option value="CICE S.A">
                                <option value="CIS-FORMACION ESPECIALIZADA SEGURIDAD-SALUD S.L.">
                                <option value="Ciudad Escuela de Formacion">
                                <option value="CLUB DE GOLF GUADALMINA">
                                <option value="CLUB DE TENIS Y PADEL MONTEVERDE">
                                <option value="Club Natació Barcelona">
                                <option value="CLUB NAUTICO DE GANDIA">
                                <option value="COMERCIANTES DEL PONIENTE, S.A.">
                                <option value="Consultores de Formacion">
                                <option value="CONSULTORIA Y FORMACION BALBO S.L">
                                <option value="CONTROL DE FORMACION">
                                <option value="CREATI MOMENTUM">
                                <option value="D.D. SPORT FG S.L. (CIS)">
                                <option value="Dedalo Proyectos XYZ (Vicar)">
                                <option value="EDIFICIO SINDICATOS (A CORUÑA)">
                                <option value="EDITEFORMACION (Madrid)">
                                <option value="EDITEFORMACION-MERCAOLID">
                                <option value="EDITRAIN SL">
                                <option value="EDITRAIN, S.L. (P.E.LA FINCA)">
                                <option value="El Ser Creativo SL">
                                <option value="EL VENTAL DE OCASION S.L.">
                                <option value="ELOGOS, S.L.">
                                <option value="EMPRESA MIXTA DE SERVICIOS FUNERARIOS DE MADRID">
                                <option value="ENSEÑANZAS ORTHOS">
                                <option value="ESCUELA DE FORMACIÓN PROFESIONAL">
                                <option value="ESCUELA DE FORMACIÓN PROFESIONAL (Vícar)">
                                <option value="Escuela Internacional de Gerencia">
                                <option value="ESTACION DISEÑO">
                                <option value="ESTACION DISEÑO (Antiguo)">
                                <option value="EUROPEANQUALITY S.L.">
                                <option value="F.I.P.P">
                                <option value="FEDERAC. PROV. DE MINUSVALIDOS FISICOS DE CORDOBA">
                                <option value="FESS LA SALLE">
                                <option value="FONDO DE PROMOCION Y DESARROLLO PROFESIONAL">
                                <option value="FPDP">
                                <option value="FPDP-VALENCIA">
                                <option value="FUNDACIÓN SAN VALERO">
                                <option value="GENERAL PLAN">
                                <option value="GESTIÓN DE LA EXCELENCIA Y COACHING APLICADO A LOS">
                                <option value="Gimnasio Triunfo S.A.">
                                <option value="Green Apple School">
                                <option value="GREEN TAL S.A.">
                                <option value="Grupo Coremsa">
                                <option value="GRUPO DTM CONSULTING S.L.U.">
                                <option value="GRUPO EDNE, S.L.">
                                <option value="GRUPO SUR RECICLAJE Y FORMACIÓN S.L.">
                                <option value="Hotel Avenida">
                                <option value="IDFO">
                                <option value="IFES">
                                <option value="IFES ( ZARAGOZA)">
                                <option value="IFES (EUSKADI)">
                                <option value="IFES NAVARRA">
                                <option value="IFES UGT">
                                <option value="IFES-CENTRO DE FORMACION ARSENIO JIMENO">
                                <option value="IFES-SEVILLA">
                                <option value="IFES-UGT (ALICANTE)">
                                <option value="INGAFOR">
                                <option value="INSFORCAN, S.L CENTRO DE ESTUDIOS EMPRESARIALES">
                                <option value="Instituto Educacion Secundaria Elaios">
                                <option value="INSTITUTO FORMACION ESTUDIOS SOCIALES">
                                <option value="INSTITUTO MADRILEÑO DE FORMACION S.L">
                                <option value="LA MIRADA DIGITAL">
                                <option value="LA MIRADA DIGITAL, S.L.">
                                <option value="MAREN">
                                <option value="MARSDIGITAL S.L (antiguo)">
                                <option value="Marsdigital S.L (Granada )">
                                <option value="Marsdigital S.L. (Barcelona)">
                                <option value="Marsdigital S.L. (la Mirada)">
                                <option value="MASTER (CENTRO DE ESTUDIOS - TIENDA DE INFORMATICA">
                                <option value="MBNA EUROPE BANK LIMITED ESPAÑA">
                                <option value="Método Consultores, S.L">
                                <option value="METODO ESTUDIOS CONSULTORES ( ARENAL)">
                                <option value="METODO ESTUDIOS CONSULTORES, S.L.">
                                <option value="METODO ESTUDIOS CONSULTORES,S.L (C/DIEGO)">
                                <option value="MGI NEVA CENTROS DE FORMACION">
                                <option value="MORTUALBA SCL ( TANATORIO MUNICIPAL ALBACETE)">
                                <option value="OROVIDA S.L.">
                                <option value="PARCESA, PARQUES DE LA PAZ S.A">
                                <option value="PARCESA, PARQUES DE LA PAZ S.A ( segundo centro)">
                                <option value="PARCESA, PARQUES DE LA PAZ S.A ( tercer centro)">
                                <option value="POLIDEPORTIVO LAS CRUCES">
                                <option value="PRODUCCIONES HINOJOSA BECERRA MEDIA2 S.L">
                                <option value="PROINTEC S.A.">
                                <option value="PROMAX S.L.L">
                                <option value="Remo RCNGandia">
                                <option value="SANTAGADEA GESTIÓN S.L. ( CENTRO DE DEPORTIVO DEHESA">
                                <option value="SEGURIDAD CERES S.A.">
                                <option value="SERVICIOS FUNERARIOS DE BARCELONA">
                                <option value="SERVICIOS SECURITAS S.A.">
                                <option value="Soom Management S.L">
                                <option value="SQUASH GYM SIERRA S.L.">
                                <option value="Swiss Sports Club">
                                <option value="TALKING ENGLISH">
                                <option value="TANATORIO MONTSERRAT TRUYOLS">
                                <option value="TANATORIO MUNICIPAL CIUDAD DE VALENCIA">
                                <option value="TANATORIO SAN LAZARO S.L.">
                                <option value="TANATORIO SERVICIOS FUNERARIOS SAGUNTO. FUALRUB S.">
                                <option value="TANATORIO TORRERO">
                                <option value="TANATORIO VELATORIO LUCENSES">
                                <option value="Tecnas">
                                <option value="TWENTY4HELP KNOWLEDGE SERVICE ESPAÑA">
                                <option value="ULTRAGYM/BODY FACTORY">
                                <option value="Universidad de Granada">
                                <option value="VALLADOLID 1402 S.L. ESCUELA DE SEGURIDAD">
                                <option value="vigilantes">
                            </datalist>
                        </div>
                    </div>

                    <!-- Fila 6 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria" class="form-control" style="width: 80px;">
                                <option value="Todas">Todas</option>
                                <?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan" class="form-control" style="width: 450px;"><option value="">------------ Todos los planes ------------</option></select>
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Abandono">Abandono</option>
                                <option value="Admitido">Admitido</option>
                                <option value="Baja">Baja</option>
                                <option value="Baja por colocación">Baja por colocación</option>
                                <option value="Empleado en curso">Empleado en curso</option>
                                <option value="Espera">Espera</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Finalizado sobrante">Finalizado sobrante</option>
                                <option value="Inscrito">Inscrito</option>
                                <option value="Pendiente docu">Pendiente docu</option>
                                <option value="Pendiente estado">Pendiente estado</option>
                                <option value="Pendiente otro curso">Pendiente otro curso</option>
                                <option value="Pendiente validacion">Pendiente validacion</option>
                                <option value="Preinscrito">Preinscrito</option>
                                <option value="Reserva">Reserva</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                                <option value="Excepto presencial">Excepto presencial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 7 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Acción:</label>
                            <input type="text" name="accion" class="form-control" style="width: 50px;">
                        </div>
                        <div class="form-group">
                            <label>Grupo:</label>
                            <input type="text" name="grupo" class="form-control" style="width: 50px;">
                        </div>
                        <div class="form-group">
                            <label>Prioridad:</label>
                            <select name="prioridad" class="form-control">
                                <option value="">---</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Inscripciones:</label>
                            <select name="filtro_inscripciones" class="form-control" style="width: 100px;">
                                <option value="">---</option>
                                <option value="Web">Web</option>
                                <option value="Manual">Manual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cursos nuestros:</label>
                            <select name="nuestros" class="form-control" style="width: 150px;">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Entregado mat:</label>
                            <select name="entregado" class="form-control" style="width: 80px;">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Captado:</label>
                            <select name="captado" class="form-control" style="width: 80px;">
                                <option value="">Todos</option>
                                <option value="IDFO">IDFO</option>
                                <option value="UGT">UGT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>CERTIFICABLES:</label>
                            <select name="certificables" class="form-control" style="width: 80px;">
                                <option value="">Todos</option>
                                <option value="S">Sí</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 8 (Fechas) -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Inicio desde:</label><input type="text" class="form-control" style="width:70px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:70px;">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>25% desde:</label><input type="text" class="form-control" style="width:70px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:70px;">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Mitad desde:</label><input type="text" class="form-control" style="width:70px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:70px;">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Fin desde:</label><input type="text" class="form-control" style="width:70px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:70px;">
                        </div>
                    </div>

                    <div class="search-row" style="margin-top: 10px;">
                        <div class="form-group" style="color: var(--title-red); font-weight: 700;">
                            <label style="color: inherit;">Alumnos NO conectados antes del 15%</label>
                            <input type="checkbox" name="no_conec_15">
                        </div>
                        <div class="form-group" style="color: var(--title-red); font-weight: 700; margin-left: 20px;">
                            <label style="color: inherit;">Alumnos NO conectados antes del 25%</label>
                            <input type="checkbox" name="no_conec_25">
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-buscar">Buscar</button>
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
                
                <div class="status-header">
                    <div class="status-box bg-orange">Curso suspendido</div>
                    <div class="status-box bg-cyan">Curso regalo</div>
                    <div class="status-box bg-pink">Grupo 1</div>
                    <div class="status-box bg-pink">Grupo 2</div>
                    <div class="status-box bg-orange" style="color:#000;">Colec. prio.</div>
                    <div class="status-box bg-cyan" style="color:#000;">Bonificado</div>
                    <div class="status-box bg-green">No valido</div>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Plan</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ac/Gr</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Curso</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>NIF</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Empresa</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha ins</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Inicio</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>25%</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Mitad</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fin</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Estado</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ult Fecha Mat.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Ultima conex</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha ult. llam.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>N.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>I.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>F.Eval</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha final. alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Doc conexión</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Encu</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Dipl</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha Dipl.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Doc. inic. pte</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Datos ptes</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Docu alumno pte</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Docu curso pte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="22" style="text-align: center; padding: 2rem; color: #64748b;">
                                    Utilice los filtros para realizar una búsqueda.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; // End view check ?>

        </main>
    </div>
</body>
</html>
