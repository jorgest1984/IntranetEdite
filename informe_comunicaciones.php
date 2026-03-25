<?php
// informe_comunicaciones.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Validar accesos
if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'informe_comunicaciones.php';

// Obtener lista de usuarios activos para el desplegable
$usuarios = [];
try {
    $stmt = $pdo->query("SELECT id, username, nombre, apellidos FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $usuarios = [];
}

// Datos de ejemplo para mostrar estructura
$resultados_simulados = false;
if (isset($_GET['usuario']) || isset($_GET['desde'])) {
    $resultados_simulados = true;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Comunicaciones - <?= APP_NAME ?></title>
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
            background-color: #e2e8f0;
            padding: 12px 20px;
            border-radius: 4px;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .search-card {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-form {
            padding: 2rem;
        }

        /* En esta pantalla las labels van arriba y los inputs abajo */
        .search-row-columns {
            display: flex;
            align-items: flex-end;
            gap: 20px;
        }

        .form-group-col {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group-col label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-control {
            font-size: 0.9rem;
            padding: 8px 12px;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            background: #fff;
        }
        
        input[type="date"].form-control {
            min-width: 150px;
        }
        select.form-control {
            min-width: 250px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
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

        .btn-limpiar {
            background: #ef4444; /* Según captura es rojo relleno, nosotros usamos estilo outline habitualmente, pero lo haré relleno para que destaque si lo marca el diseño */
            color: white;
            border: 1px solid #dc2626;
            padding: 8px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-limpiar:hover { background: #dc2626; }

        /* Contenedor de tabla */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .table-custom th {
            padding: 12px 15px;
            text-align: left;
            color: #fff;
            font-weight: 700;
            background: #1e293b; /* Fondo oscuro */
            border-bottom: 2px solid #0f172a;
        }

        .table-custom td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 15px;
            color: #334155;
            vertical-align: top;
        }
        
        .table-custom tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Formateado rápido del cell de grupo */
        .group-code {
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
            display: block;
        }
        .group-course {
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }
        .group-family {
            font-size: 0.75rem;
            color: #94a3b8;
            display: block;
        }

    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a> / 
                <a href="formacion_profesional.php">Formación</a> / 
                <a href="informes.php">Informes</a> / 
                Informe de comunicaciones por usuario
            </div>

            <div class="search-card">
                <form class="search-form" method="GET">
                    
                    <div class="search-row-columns">
                        <div class="form-group-col">
                            <label>Desde</label>
                            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group-col">
                            <label>hasta</label>
                            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>">
                        </div>

                        <div class="form-group-col">
                            <label>Usuario</label>
                            <select name="usuario" class="form-control">
                                <option value="">- Todos -</option>
                                <?php foreach($usuarios as $u): ?>
                                    <?php 
                                        $nombreCompleto = trim($u['nombre'] . ' ' . ($u['apellidos'] ?? '')); 
                                        $selected = (isset($_GET['usuario']) && $_GET['usuario'] == $u['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $u['id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($nombreCompleto . ' (' . $u['username'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-buscar">Enviar</button>
                        <a href="informe_comunicaciones.php" class="btn-limpiar">Eliminar filtros</a>
                    </div>

                </form>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Grupo</th>
                            <th>Quién</th>
                            <th>Forma</th>
                            <th>Motivo</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($resultados_simulados): ?>
                            <!-- Dummy Data para el previo -->
                            <tr>
                                <td>25/03/2026</td>
                                <td>
                                    <span class="group-code">A30-G4</span>
                                    <span class="group-course">Diseño Gráfico: Illustrator, Indesign y Photoshop</span>
                                    <span class="group-family">Artes Gráficas. Manip Papel. Cartón. Editor. e Ind 2014</span>
                                </td>
                                <td>Nosotros</td>
                                <td>Whatsapp</td>
                                <td>Información</td>
                                <td>1</td>
                            </tr>
                            <tr>
                                <td>24/03/2026</td>
                                <td>
                                    <span class="group-code">A6-G7</span>
                                    <span class="group-course">IMSV031PO - Retoque Fotográfico</span>
                                    <span class="group-family">Artes Gráficas 2018</span>
                                </td>
                                <td>Nosotros</td>
                                <td>Whatsapp</td>
                                <td>Información</td>
                                <td>1</td>
                            </tr>
                            <tr>
                                <td>24/03/2026</td>
                                <td>
                                    <span class="group-code">A5-G2</span>
                                    <span class="group-course">ARGN04 - DISEÑO EDITORIAL CON ADOBE INDESIGN</span>
                                    <span class="group-family">Artes Gráficas 2024</span>
                                </td>
                                <td>Nosotros</td>
                                <td>Llamada Telefónica</td>
                                <td>Captación</td>
                                <td>3</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">
                                    Pulsa <strong>Enviar</strong> para visualizar las comunicaciones del usuario seleccionado.
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
