<?php
// inscripciones.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'search';
$error = '';
$success = '';

// Listas para dropdowns (placeholder para la integración futura con DB)
$provincias = ['MADRID', 'PONTEVEDRA', 'BARCELONA', 'VALENCIA', 'SEVILLA'];
$comerciales = [];
$tutores = [];
$convocatorias = [];
$planes = [];

try {
    $convocatorias = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC LIMIT 50")->fetchAll();
    // $planes = $pdo->query("SELECT id, nombre FROM planes ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}

$current_page = 'inscripciones.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripciones - <?= APP_NAME ?></title>
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

        /* Sidebar highlighting */
        .sidebar-menu li a.active {
            background: rgba(30, 64, 175, 0.1);
            color: #1e40af;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <!-- BUSCADOR -->
            <div class="search-card">
                <div class="card-header-custom">
                    <h2>INSCRIPCIONES - CAMPOS DE BÚSQUEDA</h2>
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
                            <select name="comercial" class="form-control" style="width: 180px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 250px;"><option value="">---</option></select>
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
                            <select name="provincia" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <?php foreach($provincias as $prov): ?><option value="<?= $prov ?>"><?= $prov ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Motivo No-Admisión:</label>
                            <select name="motivo_rechazo" class="form-control" style="width: 150px;"><option value="">---</option></select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Solicitante:</label>
                            <select name="solicitante" class="form-control" style="width: 250px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Sexo:</label>
                            <select name="sexo" class="form-control"><option value="">---</option><option value="M">Hombre</option><option value="F">Mujer</option></select>
                        </div>
                        <div class="form-group">
                            <label>Colectivo:</label>
                            <select name="colectivo" class="form-control" style="width: 380px;"><option value="">---</option></select>
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
                            <select name="grupo_cotizacion" class="form-control" style="width: 320px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Centro impartición:</label>
                            <select name="centro" class="form-control" style="width: 500px;"><option value="">---</option></select>
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
                            <select name="estado" class="form-control" style="width: 150px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 150px;"><option value="excepto_presencial">Excepto presencial</option></select>
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
                            <select name="prioridad" class="form-control"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Inscripciones:</label>
                            <select name="filtro_inscripciones" class="form-control" style="width: 100px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Cursos nuestros:</label>
                            <select name="nuestros" class="form-control" style="width: 150px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Entregado mat:</label>
                            <select name="entregado" class="form-control" style="width: 50px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>Captado:</label>
                            <select name="captado" class="form-control" style="width: 80px;"><option value="">---</option></select>
                        </div>
                        <div class="form-group">
                            <label>CERTIFICABLES:</label>
                            <select name="certificables" class="form-control" style="width: 50px;"><option value="">---</option></select>
                        </div>
                    </div>

                    <!-- Fila 8 (Fechas) -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Inicio desde:</label><input type="text" class="form-control" style="width:80px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:80px;">
                        </div>
                        <div class="form-group" style="margin-left: 15px;">
                            <label>Mitad desde:</label><input type="text" class="form-control" style="width:80px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:80px;">
                        </div>
                        <div class="form-group" style="margin-left: 15px;">
                            <label>Fin desde:</label><input type="text" class="form-control" style="width:80px;">
                            <label>hasta:</label><input type="text" class="form-control" style="width:80px;">
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
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Modal.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Nº Acc.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Nº gr.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Cod Grupo</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Curso</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Empresa</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Sector empresa</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Provincia</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Comercial</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Inicio</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Mitad</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fin</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Estado</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>No admision</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha Ins.</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Cambio estado</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Doc pte</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Prioridad</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Prefiere</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Numero</th>
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

        </main>
    </div>
</body>
</html>
