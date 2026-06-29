<?php
// grupos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
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
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== GRUPOS PREMIUM STYLES ===== */

        /* Search Card Premium */
        .search-card-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            transition: background-color 0.4s ease, border-color 0.4s ease;
        }

        .card-header-premium {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 108, 228, 0.15);
        }

        .card-header-premium h2 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: white;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            padding: 2rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom.span-12 { grid-column: span 12; }
        .form-group-custom.span-10 { grid-column: span 10; }
        .form-group-custom.span-8 { grid-column: span 8; }
        .form-group-custom.span-6 { grid-column: span 6; }
        .form-group-custom.span-5 { grid-column: span 5; }
        .form-group-custom.span-4 { grid-column: span 4; }
        .form-group-custom.span-3 { grid-column: span 3; }
        .form-group-custom.span-2 { grid-column: span 2; }
        .form-group-custom.span-1 { grid-column: span 1; }

        .form-group-custom label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Results Card Layout */
        .results-section-premium {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .results-header-premium {
            background: rgba(0, 108, 228, 0.03);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-header-premium h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Table responsive wrapper */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Table */
        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 2500px; /* Horizontally scrollable wide table */
        }
        
        .table-premium th {
            background: rgba(0, 108, 228, 0.04);
            border-bottom: 2px solid var(--border-color);
            padding: 0.85rem 0.6rem;
            text-align: left;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table-premium td {
            padding: 0.75rem 0.6rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
            white-space: nowrap;
        }

        .table-premium tr:last-child td {
            border-bottom: none;
        }

        .table-premium tr:hover td {
            background-color: rgba(0, 108, 228, 0.015);
        }

        /* Responsive Media Queries */
        @media (max-width: 1024px) {
            .form-grid {
                padding: 1.5rem !important;
                gap: 1rem !important;
            }
            .form-group-custom {
                grid-column: span 6 !important;
            }
            .form-group-custom.span-12 {
                grid-column: span 12 !important;
            }
        }
        
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column !important;
            }
            .main-content {
                padding: 15px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            .card-header-premium {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 12px !important;
                padding: 15px !important;
            }
            .card-header-premium h2 {
                text-align: center !important;
            }
            .form-group-custom {
                grid-column: span 12 !important;
            }
        }

        /* Badge system */
        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
        }
        .badge-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-green { background: rgba(22, 163, 74, 0.1); color: #16a34a; }
        .badge-blue { background: rgba(37, 99, 235, 0.1); color: #2563eb; }
        .badge-yellow { background: rgba(202, 138, 4, 0.1); color: #ca8a04; }

        /* Custom header button colors */
        .btn-blue {
            background-color: rgba(0, 108, 228, 0.08) !important;
            color: var(--primary-color) !important;
            border: 1px solid rgba(0, 108, 228, 0.15) !important;
            box-shadow: 0 4px 12px 0 rgba(0, 108, 228, 0.05);
        }
        .btn-blue:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px 0 rgba(0, 108, 228, 0.3);
        }

        .btn-red {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.15) !important;
            box-shadow: 0 4px 12px 0 rgba(239, 68, 68, 0.05);
        }
        .btn-red:hover {
            background-color: #ef4444 !important;
            color: white !important;
            border-color: #ef4444 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px 0 rgba(239, 68, 68, 0.3);
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            transition: all 0.2s;
            text-decoration: none;
            padding: 0;
            cursor: pointer;
            box-sizing: border-box;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.15);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto;">
        <header class="page-header">
            <div class="page-title">
                <h1>Grupos de FP</h1>
                <p>Búsqueda y gestión de grupos de formación profesional</p>
            </div>
            <div class="page-actions" style="display: flex; gap: 12px;">
                <a href="home.php" class="btn btn-blue" style="font-weight: 700; text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Inicio (Home)
                </a>
                <a href="formacion_profesional.php" class="btn btn-red" style="font-weight: 700; text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver a FP
                </a>
            </div>
        </header>

        <div class="search-card-premium">
            <div class="card-header-premium">
                <h2>GRUPOS - FILTROS DE BÚSQUEDA</h2>
            </div>
            <form method="GET" style="margin: 0;">
                <div class="form-grid">
                    <!-- Grupo 1: Curso, Convocatoria y Plan -->
                    <div class="form-group-custom span-4">
                        <label>Curso:</label>
                        <input type="text" name="curso" class="form-control" value="<?= htmlspecialchars($_GET['curso'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-4">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control">
                            <option value="">Todas las convocatorias</option>
                            <?php foreach ($convocatorias as $conv): ?>
                                <option value="<?= $conv['id'] ?>" <?= (isset($_GET['convocatoria_id']) && $_GET['convocatoria_id'] == $conv['id']) ? 'selected' : '' ?>><?= htmlspecialchars($conv['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-custom span-4">
                        <label>Plan:</label>
                        <select name="plan_id" class="form-control">
                            <option value="">Todos los planes</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_GET['plan_id']) && $_GET['plan_id'] == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Grupo 2: Detalles del Grupo y Asignación -->
                    <div class="form-group-custom span-3">
                        <label>Código grupo:</label>
                        <input type="text" name="codigo_grupo" class="form-control" value="<?= htmlspecialchars($_GET['codigo_grupo'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Acción:</label>
                        <input type="text" name="accion" class="form-control" value="<?= htmlspecialchars($_GET['accion'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-2">
                        <label>Grupo (Nº):</label>
                        <input type="text" name="grupo_num" class="form-control" value="<?= htmlspecialchars($_GET['grupo_num'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-2">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control">
                            <option value="">Todas</option>
                            <option value="Presencial" <?= (isset($_GET['modalidad']) && $_GET['modalidad'] == 'Presencial') ? 'selected' : '' ?>>Presencial</option>
                            <option value="Teleformación" <?= (isset($_GET['modalidad']) && $_GET['modalidad'] == 'Teleformación') ? 'selected' : '' ?>>Teleformación</option>
                            <option value="Mixta" <?= (isset($_GET['modalidad']) && $_GET['modalidad'] == 'Mixta') ? 'selected' : '' ?>>Mixta</option>
                        </select>
                    </div>
                    <div class="form-group-custom span-2">
                        <label>Asignación:</label>
                        <select name="asignacion" class="form-control">
                            <option value="">Todas</option>
                            <option value="I" <?= (isset($_GET['asignacion']) && $_GET['asignacion'] == 'I') ? 'selected' : '' ?>>I</option>
                            <option value="E" <?= (isset($_GET['asignacion']) && $_GET['asignacion'] == 'E') ? 'selected' : '' ?>>E</option>
                            <option value="M" <?= (isset($_GET['asignacion']) && $_GET['asignacion'] == 'M') ? 'selected' : '' ?>>M</option>
                        </select>
                    </div>

                    <!-- Grupo 3: Tutores y Ubicación -->
                    <div class="form-group-custom span-4">
                        <label>Tutor:</label>
                        <select name="tutor" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (isset($_GET['tutor']) && $_GET['tutor'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-custom span-5">
                        <label>Centro impartición:</label>
                        <input type="text" name="centro" id="centro-input" class="form-control" list="centros-list" placeholder="Escriba el centro..." value="<?= htmlspecialchars($_GET['centro'] ?? '') ?>">
                        <datalist id="centros-list">
                            <?php foreach ($centros as $c): ?>
                                <option value="<?= htmlspecialchars($c['nombre']) ?>">
                            <?php endforeach; ?>
                            <!-- Centros adicionales -->
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
                    <div class="form-group-custom span-3">
                        <label>Provincia de impartición:</label>
                        <input type="text" name="provincia" id="provincia-input" class="form-control" list="provincias-list" placeholder="Escriba la provincia..." value="<?= htmlspecialchars($_GET['provincia'] ?? '') ?>">
                        <datalist id="provincias-list">
                            <option value="Álava">
                            <option value="Albacete">
                            <option value="Alicante">
                            <option value="Almería">
                            <option value="Asturias">
                            <option value="Ávila">
                            <option value="Badajoz">
                            <option value="Baleares">
                            <option value="Barcelona">
                            <option value="Burgos">
                            <option value="Cáceres">
                            <option value="Cádiz">
                            <option value="Cantabria">
                            <option value="Castellón">
                            <option value="Ciudad Real">
                            <option value="Córdoba">
                            <option value="Cuenca">
                            <option value="Gerona">
                            <option value="Granada">
                            <option value="Guadalajara">
                            <option value="Guipúzcoa">
                            <option value="Huelva">
                            <option value="Huesca">
                            <option value="Jaén">
                            <option value="La Coruña">
                            <option value="La Rioja">
                            <option value="Las Palmas">
                            <option value="León">
                            <option value="Lérida">
                            <option value="Lugo">
                            <option value="Madrid">
                            <option value="Málaga">
                            <option value="Murcia">
                            <option value="Navarra">
                            <option value="Orense">
                            <option value="Palencia">
                            <option value="Pontevedra">
                            <option value="Salamanca">
                            <option value="Segovia">
                            <option value="Sevilla">
                            <option value="Soria">
                            <option value="Tarragona">
                            <option value="Santa Cruz de Tenerife">
                            <option value="Teruel">
                            <option value="Toledo">
                            <option value="Valencia">
                            <option value="Valladolid">
                            <option value="Vizcaya">
                            <option value="Zamora">
                            <option value="Zaragoza">
                            <option value="Ceuta">
                            <option value="Melilla">
                        </datalist>
                    </div>

                    <!-- Grupo 4: Fechas de Inicio y Fin -->
                    <div class="form-group-custom span-3">
                        <label>Fecha inicio desde:</label>
                        <input type="date" name="fecha_ini_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_ini_desde'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Fecha inicio hasta:</label>
                        <input type="date" name="fecha_ini_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_ini_hasta'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Fecha fin desde:</label>
                        <input type="date" name="fecha_fin_desde" class="form-control" value="<?= htmlspecialchars($_GET['fecha_fin_desde'] ?? '') ?>">
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Fecha fin hasta:</label>
                        <input type="date" name="fecha_fin_hasta" class="form-control" value="<?= htmlspecialchars($_GET['fecha_fin_hasta'] ?? '') ?>">
                    </div>

                    <!-- Grupo 5: Estado, Origen e Indicadores -->
                    <div class="form-group-custom span-3" style="flex-direction: row; align-items: center; gap: 8px; margin-top: auto; margin-bottom: 8px;">
                        <input type="checkbox" name="sin_fechas" id="sin_fechas_cb" style="width: 18px; height: 18px; cursor: pointer;" <?= isset($_GET['sin_fechas']) ? 'checked' : '' ?>>
                        <label for="sin_fechas_cb" style="cursor: pointer; margin-bottom: 0; white-space: nowrap;">Sin fechas</label>
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Cursos propios:</label>
                        <select name="cursos_propios" class="form-control">
                            <option value="">Todos</option>
                            <option value="1" <?= (isset($_GET['cursos_propios']) && $_GET['cursos_propios'] == '1') ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= (isset($_GET['cursos_propios']) && $_GET['cursos_propios'] == '0') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Situación:</label>
                        <select name="situacion" class="form-control">
                            <option value="">Todas</option>
                            <option value="Valido" <?= (isset($_GET['situacion']) && $_GET['situacion'] == 'Valido') ? 'selected' : '' ?>>Válido</option>
                            <option value="Suspendido" <?= (isset($_GET['situacion']) && $_GET['situacion'] == 'Suspendido') ? 'selected' : '' ?>>Suspendido</option>
                            <option value="Finalizado" <?= (isset($_GET['situacion']) && $_GET['situacion'] == 'Finalizado') ? 'selected' : '' ?>>Finalizado</option>
                            <option value="Lista espera" <?= (isset($_GET['situacion']) && $_GET['situacion'] == 'Lista espera') ? 'selected' : '' ?>>Lista espera</option>
                            <option value="Inactivo" <?= (isset($_GET['situacion']) && $_GET['situacion'] == 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="form-group-custom span-3">
                        <label>Desempleados:</label>
                        <select name="desempleados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1" <?= (isset($_GET['desempleados']) && $_GET['desempleados'] == '1') ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= (isset($_GET['desempleados']) && $_GET['desempleados'] == '0') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <!-- Grupo 6: Comunicaciones e Inspecciones -->
                    <div class="form-group-custom span-4">
                        <label>Comunicados:</label>
                        <select name="comunicados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1" <?= (isset($_GET['comunicados']) && $_GET['comunicados'] == '1') ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= (isset($_GET['comunicados']) && $_GET['comunicados'] == '0') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group-custom span-4">
                        <label>Comunicados solic.:</label>
                        <select name="comunicados_solicitados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1" <?= (isset($_GET['comunicados_solicitados']) && $_GET['comunicados_solicitados'] == '1') ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= (isset($_GET['comunicados_solicitados']) && $_GET['comunicados_solicitados'] == '0') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="form-group-custom span-4">
                        <label>Objetos de control:</label>
                        <select name="objetos_control" class="form-control">
                            <option value="">Todos</option>
                            <option value="1" <?= (isset($_GET['objetos_control']) && $_GET['objetos_control'] == '1') ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= (isset($_GET['objetos_control']) && $_GET['objetos_control'] == '0') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <!-- Botón de Búsqueda -->
                    <div style="grid-column: span 12; display: flex; justify-content: center; margin-top: 15px;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 2.5rem;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="results-section-premium">
            <div class="results-header-premium">
                <h2>Resultado de la Búsqueda</h2>
            </div>
            <div class="table-responsive" style="overflow-x: auto; width: 100%; background: transparent; border-radius: 0 0 16px 16px; box-shadow: none; border-bottom: none;">
                <table class="table-premium">
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
                            <th style="border-right:none; text-align: right; padding-right: 2rem;">Acciones</th>
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
                            <td style="font-weight: 700; white-space: normal; min-width: 250px;"><?= htmlspecialchars('CIBERSEGURIDAD AVANZADA') ?></td>
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
                            <td style="text-align: center; font-weight: 700;">25</td>
                            <td style="text-align: center; font-weight: 700;">20</td>
                            <td style="text-align: center; font-weight: 700;">18</td>
                            <td style="text-align: center;">0</td>
                            <td style="text-align: center;">0</td>
                            <td>EFP S.L.</td>
                            <td>SÍ</td>
                            <td style="border-right:none;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end; padding-right: 1.5rem;">
                                    <a href="ficha_grupo_edicion.php?id=1" class="btn-action" title="Editar Grupo">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                </div>
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
