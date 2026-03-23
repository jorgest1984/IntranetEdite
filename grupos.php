<?php
// grupos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    header("Location: dashboard.php");
    exit();
}

// Cargar listas para filtros
$planes = [];
$convocatorias = [];
$tutores = [];
$centros = [];

try {
    $planes = $pdo->query("SELECT id, nombre, codigo FROM planes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Tutoress (Docentes)
    $stmtTutores = $pdo->query("SELECT a.id, CONCAT(a.nombre, ' ', a.primer_apellido) as nombre 
                                FROM alumnos a 
                                JOIN profesorado_detalles p ON a.id = p.alumno_id 
                                ORDER BY a.nombre ASC");
    if ($stmtTutores) $tutores = $stmtTutores->fetchAll(PDO::FETCH_ASSOC);

    // Centros (Empresas)
    $stmtCentros = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC");
    if ($stmtCentros) $centros = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Silently fail or log
}

$current_page = 'grupos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .main-content { padding: 2rem; }
        
        .search-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .search-card-header {
            background: #f8fafc;
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            text-align: center;
        }
        .search-card-header h2 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .search-form { padding: 1.5rem; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem;
        }
        .form-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1e3a8a;
            white-space: nowrap;
            min-width: 100px;
            text-align: right;
        }
        .form-control {
            width: 100%;
            padding: 0.35rem 0.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
        }

        /* Responsive grid */
        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }
        .col-2 { grid-column: span 2; }
        .col-2-5 { grid-column: span 2.5; }

        .search-actions {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }
        .btn-search {
            padding: 0.5rem 2rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-search:hover { background: #e2e8f0; }

        /* Results table */
        .results-section {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .results-header {
            background: #fff;
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .results-header h2 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: #b91c1c;
            text-transform: uppercase;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 2500px; /* Very wide table */
        }
        .table-custom th {
            background: #1e293b;
            color: #fff;
            padding: 0.75rem 0.5rem;
            text-align: left;
            font-weight: 600;
            border-right: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
        .table-custom td {
            padding: 0.6rem 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
        }
        .table-custom tr:hover { background: #f8fafc; }
        
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        .badge-red { background: #fee2e2; color: #b91c1c; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="search-card">
            <div class="search-card-header">
                <h2>GRUPOS - CAMPOS DE BÚSQUEDA</h2>
            </div>
            <form class="search-form" method="GET">
                <div class="form-grid">
                    <!-- Fila 1 -->
                    <div class="form-group col-3">
                        <label>Curso:</label>
                        <input type="text" name="curso" class="form-control">
                    </div>
                    <div class="form-group col-2">
                        <label>Código grupo:</label>
                        <input type="text" name="codigo_grupo" class="form-control">
                    </div>
                    <div class="form-group col-2">
                        <label>Situación:</label>
                        <select name="situacion" class="form-control">
                            <option value="">Todas</option>
                            <option value="Valido">Válido</option>
                            <option value="Suspendido">Suspendido</option>
                            <option value="Finalizado">Finalizado</option>
                            <option value="Lista espera">Lista espera</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    <div class="form-group col-2">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control">
                            <option value="">Todas</option>
                            <option value="Presencial">Presencial</option>
                            <option value="Teleformación">Teleformación</option>
                            <option value="Mixta">Mixta</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label>Tutor:</label>
                        <select name="tutor" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 2 -->
                    <div class="form-group col-4">
                        <label>Provincia de impartición:</label>
                        <select name="provincia" class="form-control">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="form-group col-5">
                        <label>Centro impartición:</label>
                        <select name="centro" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($centros as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label>Asignación:</label>
                        <select name="asignacion" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>

                    <!-- Fila 3 -->
                    <div class="form-group col-3">
                        <label>Fecha inicio desde:</label>
                        <input type="date" name="fecha_ini_desde" class="form-control">
                    </div>
                    <div class="form-group col-3">
                        <label>Fecha inicio hasta:</label>
                        <input type="date" name="fecha_ini_hasta" class="form-control">
                    </div>
                    <div class="form-group col-1">
                        <label style="min-width: auto;">Sin fechas:</label>
                        <input type="checkbox" name="sin_fechas">
                    </div>
                    <div class="form-group col-2">
                        <label style="min-width: auto;">Acción:</label>
                        <input type="text" name="accion" class="form-control">
                    </div>
                    <div class="form-group col-1">
                        <label style="min-width: auto;">Grupo:</label>
                        <input type="text" name="grupo_num" class="form-control">
                    </div>
                    <div class="form-group col-2">
                        <label style="min-width: auto;">Cursos nuestros:</label>
                        <select name="nuestros" class="form-control">
                            <option value=""></option>
                        </select>
                    </div>

                    <!-- Fila 4 -->
                    <div class="form-group col-3">
                        <label>Fecha fin desde:</label>
                        <input type="date" name="fecha_fin_desde" class="form-control">
                    </div>
                    <div class="form-group col-3">
                        <label>Fecha fin hasta:</label>
                        <input type="date" name="fecha_fin_hasta" class="form-control">
                    </div>
                    <div class="form-group col-2">
                        <label style="min-width: auto;">Comunicados:</label>
                        <select name="comunicados" class="form-control"><option value=""></option></select>
                    </div>
                    <div class="form-group col-2">
                        <label style="min-width: auto;">Comunicados Solic.:</label>
                        <select name="comunicados_solic" class="form-control"><option value=""></option></select>
                    </div>
                    <div class="form-group col-2">
                        <label style="min-width: auto;">Objetos de control:</label>
                        <select name="objetos_control" class="form-control"><option value=""></option></select>
                    </div>

                    <!-- Fila 5 -->
                    <div class="form-group col-6">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control">
                            <option value="">Todas las convocatorias</option>
                            <?php foreach ($convocatorias as $conv): ?>
                                <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label>Plan:</label>
                        <select name="plan_id" class="form-control">
                            <option value="">Todos los planes</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn-search">Buscar</button>
                </div>
            </form>
        </div>

        <div class="results-section">
            <div class="results-header">
                <h2>RESULTADO DE LA BÚSQUEDA</h2>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>Plan</th>
                            <th>Modalidad</th>
                            <th>Nº Acc</th>
                            <th>Nº Gr</th>
                            <th>Cód Plat.</th>
                            <th>Título</th>
                            <th>Provincia</th>
                            <th>Tutor 1</th>
                            <th>T1 Contr</th>
                            <th>Tutor 2</th>
                            <th>Inicio</th>
                            <th>Mitad</th>
                            <th>7Dias</th>
                            <th>Fin</th>
                            <th>Fin Horario</th>
                            <th>Situación</th>
                            <th>Comunic.</th>
                            <th>Fecha com.</th>
                            <th>Inscr.</th>
                            <th>Admit.</th>
                            <th>Final.</th>
                            <th>Sin grupo</th>
                            <th>Sin grupo válidos</th>
                            <th>Empresas</th>
                            <th>Mat. Facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Demo Row -->
                        <tr>
                            <td>Formación TIC 2024</td>
                            <td>Plan Digitalización</td>
                            <td>Teleformación</td>
                            <td>001</td>
                            <td>G1</td>
                            <td>PLAT-01</td>
                            <td>CIBERSEGURIDAD AVANZADA</td>
                            <td>Madrid</td>
                            <td>JUAN PÉREZ</td>
                            <td>SÍ</td>
                            <td>-</td>
                            <td>01/10/2024</td>
                            <td>15/10/2024</td>
                            <td>24/10/2024</td>
                            <td>30/10/2024</td>
                            <td>18:00</td>
                            <td><span class="badge badge-green">Válido</span></td>
                            <td>ENVIADO</td>
                            <td>20/09/2024</td>
                            <td>25</td>
                            <td>20</td>
                            <td>18</td>
                            <td>0</td>
                            <td>0</td>
                            <td>EFP S.L.</td>
                            <td>SÍ</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
