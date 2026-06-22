<?php
// imprimir_contenidos.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR, ROLE_COMERCIAL, ROLE_COORD])) {
    die("Acceso denegado.");
}

// 1. Replicar la lógica de búsqueda de acciones_formativas.php
$params = [];
$sql = "SELECT af.*, c.nombre_largo as titulo 
        FROM acciones_formativas af
        JOIN cursos c ON af.curso_id = c.id
        LEFT JOIN planes p ON af.plan_id = p.id
        WHERE 1=1";

if (!empty($_GET['nombre'])) {
    $sql .= " AND c.nombre_largo LIKE ?";
    $params[] = "%" . $_GET['nombre'] . "%";
}
if (!empty($_GET['convocatoria_id'])) {
    $sql .= " AND p.convocatoria_id = ?";
    $params[] = $_GET['convocatoria_id'];
}
if (!empty($_GET['plan_id'])) {
    $sql .= " AND af.plan_id = ?";
    $params[] = $_GET['plan_id'];
}
if (!empty($_GET['solicitante'])) {
    $sql .= " AND af.solicitante = ?";
    $params[] = $_GET['solicitante'];
}
if (!empty($_GET['sector'])) {
    $sql .= " AND af.sector = ?";
    $params[] = $_GET['sector'];
}
if (!empty($_GET['proveedor'])) {
    $sql .= " AND af.proveedor = ?";
    $params[] = $_GET['proveedor'];
}
if (!empty($_GET['catalogo'])) {
    $sql .= " AND af.catalogo = ?";
    $params[] = $_GET['catalogo'];
}
if (!empty($_GET['consultora'])) {
    $sql .= " AND af.consultora = ?";
    $params[] = $_GET['consultora'];
}
if (!empty($_GET['id_accion'])) {
    $sql .= " AND af.id = ?";
    $params[] = $_GET['id_accion'];
}
if (!empty($_GET['prioridad'])) {
    $sql .= " AND af.prioridad = ?";
    $params[] = $_GET['prioridad'];
}
if (!empty($_GET['modalidad'])) {
    $sql .= " AND af.modalidad = ?";
    $params[] = $_GET['modalidad'];
}

$sql .= " ORDER BY c.nombre_largo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$acciones = $stmt->fetchAll();

if (empty($acciones)) {
    die("No hay acciones formativas para imprimir con estos filtros.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contenidos de Acciones Formativas - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f2f5;
            -webkit-print-color-adjust: exact;
            color: #333;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            position: relative;
            page-break-after: always;
        }
        
        .page:last-child {
            page-break-after: auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logos {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .logo { height: 45px; width: auto; }

        .header-title {
            color: #666;
            font-size: 1rem;
            text-transform: uppercase;
        }

        /* Gray bar */
        .title-bar {
            background: #f1f5f9;
            padding: 10px 15px;
            margin-bottom: 15px;
        }

        .title-bar h1 {
            color: #0284c7; /* Sky blue matching the image roughly */
            margin: 0;
            font-size: 1.1rem;
            text-transform: uppercase;
        }

        .subtitle {
            color: #0284c7;
            font-size: 0.95rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .content-heading {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #000;
        }

        .content {
            flex-grow: 1;
            font-size: 0.9rem;
            line-height: 1.6;
            color: #111;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .footer-left {
            font-size: 0.8rem;
            color: #333;
        }

        .footer-left .phone {
            font-size: 2.2rem;
            color: #666;
            margin-bottom: 5px;
            line-height: 1;
            font-weight: 300;
        }

        .footer-right {
            text-align: right;
            font-size: 0.8rem;
            color: #333;
        }

        /* Utilidades */
        .no-print {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
        }
        
        .btn-print {
            padding: 10px 25px;
            background: #1e70b3;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        .btn-print:hover { background: #155a92; }

        @media print {
            body { background: white; }
            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                min-height: 100%;
                padding: 10mm 15mm;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">Imprimir / Guardar PDF</button>
    <button onclick="window.close()" class="btn-print" style="background: #64748b;">Cerrar</button>
</div>

<?php foreach ($acciones as $af): ?>
<div class="page">
    <div class="header">
        <div class="logos">
            <img src="/img/logo_efp.png" alt="Logo Escuela de Formación Profesional" class="logo">
        </div>
        <div class="header-title">CONTENIDOS ACCIONES FORMATIVAS</div>
    </div>

    <div class="title-bar">
        <h1><?= htmlspecialchars($af['titulo'] ?? '') ?></h1>
    </div>

    <div class="subtitle">
        <?= htmlspecialchars($af['duracion'] ?? '0') ?> horas. Modalidad <?= htmlspecialchars($af['modalidad'] ?? '') ?>
    </div>

    <div class="content-heading">CONTENIDOS</div>

    <div class="content">
        <?= $af['contenidos'] ?>
    </div>

    <div class="footer">
        <div class="footer-left">
            <div class="phone">958 089 725</div>
            C/ Benjamin Franklin, 1. 18100 Armilla (Granada).
        </div>
        <div class="footer-right">
            www.escueladeformacionprofesional.es<br>
            info@escueladeformacionprofesional.es
        </div>
    </div>
</div>
<?php endforeach; ?>

</body>
</html>
