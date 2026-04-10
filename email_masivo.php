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

} catch (Exception $e) {}

$current_page = 'email_masivo.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Masivo - <?= APP_NAME ?></title>
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
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            


            <!-- BUSCADOR -->
            <div class="search-card">
                <div class="card-header-custom" style="background:#f8fafc;">
                    <h2 style="color:var(--title-red); margin:0;">EMAIL MASIVO - TUTORÍAS</h2>
                </div>
                <form class="search-form" method="POST" enctype="multipart/form-data">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Nº Acción:</label><input type="text" name="n_accion" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Número grupo:</label><input type="text" name="n_grupo" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria" class="form-control" style="width: 150px;">
                                <option value="">Todas</option>
                                <?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan" class="form-control" style="width: 250px;"><option value="">Todos los planes</option></select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 120px;">
                                <option value="">---</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Inicio desde:</label><input type="date" name="inicio_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="inicio_hasta" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Fin desde:</label><input type="date" name="fin_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="fin_hasta" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" class="form-control" list="empresas_list" placeholder="..." style="width: 150px;">
                            <datalist id="empresas_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Admitido">Admitido</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
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
                            <label>Claves enviadas:</label>
                            <select name="claves" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Conectados:</label>
                            <select name="conectados" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Realizaron encuesta:</label>
                            <select name="encuesta" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr style="margin: 10px 0; border: 0; border-top: 1px solid #e2e8f0;">

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label style="color: #ca8a04;">
                                <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: middle;"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg> 
                                E-mail remitente:</label>
                            <input type="email" name="remitente" class="form-control" value="elena.adame@editeformacion.com" style="width: 250px;">
                        </div>
                        <div class="form-group" style="flex: 1; margin-left: 15px;">
                            <label>Asunto:</label>
                            <input type="text" name="asunto" class="form-control" style="width: 100%; min-width: 300px;">
                        </div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha envío diploma desde:</label><input type="date" name="diploma_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="diploma_hasta" class="form-control">
                        </div>
                    </div>

                    <!-- Fila 6 (Archivos) -->
                    <div class="search-row" style="margin-top: 10px; gap: 15px;">
                        <input type="file" name="adjunto1" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto2" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto3" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                    </div>

                    <!-- Fila 7 (Mensaje WYSIWYG) -->
                    <div style="margin-top: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--label-blue); display: block; margin-bottom: 5px;">Mensaje:</label>
                        <textarea id="mensaje_email" name="mensaje_email" style="width: 100%; height: 250px;"></textarea>
                    </div>

                    <!-- Botones -->
                    <div style="text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 15px;">
                        <button type="button" class="btn-buscar">Buscar</button>
                        <button type="submit" class="btn-buscar" style="color: var(--label-blue); border-color: var(--border-gray);">Enviar e-mail a seleccionados</button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->>
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
                                <th><input type="checkbox" id="checkAll"></th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Plan</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Acción</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Grupo</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Alumno</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Empresa</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Inicio</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fin</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Email</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Conec</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Dipl</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>Fecha</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>EvI</th>
                                <th><span class="sort-icon"><svg width="10" viewBox="0 0 24 24"><path d="M12 21l-12-18h24z"/></svg></span>EvF</th>
                                <th>Doc<br>inic.<br>pte</th>
                                <th>Datos<br>ptes</th>
                                <th>Doc pte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="18" style="text-align: center; padding: 2rem; color: #64748b;">
                                    Utilice los filtros para realizar una búsqueda de alumnos a enviar e-mail.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#mensaje_email',
        menubar: false,
        plugins: 'lists link image table code',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code',
        branding: false,
        language: 'es'
      });
    </script>
</body>
</html>
