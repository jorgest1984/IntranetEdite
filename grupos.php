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
            display: flex;
            flex-wrap: wrap;
            gap: 15px 10px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .form-group.row-layout {
            flex-direction: row;
            align-items: center;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1e3a8a;
            white-space: nowrap;
            text-transform: capitalize;
        }
        .form-group.row-layout label {
            min-width: auto;
            margin-right: 5px;
        }
        .form-control {
            padding: 0.4rem 0.6rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            width: 100%;
            box-sizing: border-box;
        }

        /* Layout Helpers */
        .w-10 { width: calc(10% - 10px); }
        .w-15 { width: calc(15% - 10px); }
        .w-20 { width: calc(20% - 10px); }
        .w-25 { width: calc(25% - 10px); }
        .w-30 { width: calc(30% - 10px); }
        .w-33 { width: calc(33.33% - 10px); }
        .w-40 { width: calc(40% - 10px); }
        .w-50 { width: calc(50% - 10px); }
        .w-60 { width: calc(60% - 10px); }
        .w-100 { width: 100%; }

        @media (max-width: 1024px) {
            .w-10, .w-15, .w-20, .w-25, .w-30, .w-33, .w-40, .w-50, .w-60 { width: calc(50% - 10px); }
        }
        @media (max-width: 640px) {
            .w-10, .w-15, .w-20, .w-25, .w-30, .w-33, .w-40, .w-50, .w-60 { width: 100%; }
        }

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
                    <div class="form-group w-25">
                        <label>Curso:</label>
                        <input type="text" name="curso" class="form-control">
                    </div>
                    <div class="form-group w-20">
                        <label>Código grupo:</label>
                        <input type="text" name="codigo_grupo" class="form-control">
                    </div>
                    <div class="form-group w-15">
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
                    <div class="form-group w-15">
                        <label>Modalidad:</label>
                        <select name="modalidad" class="form-control">
                            <option value="">Todas</option>
                            <option value="Presencial">Presencial</option>
                            <option value="Teleformación">Teleformación</option>
                            <option value="Mixta">Mixta</option>
                        </select>
                    </div>
                    <div class="form-group w-25">
                        <label>Tutor:</label>
                        <select name="tutor" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($tutores as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 2 -->
                    <div class="form-group w-30">
                        <label>Provincia de impartición:</label>
                        <input type="text" name="provincia" id="provincia-input" class="form-control" list="provincias-list" placeholder="Escriba la provincia...">
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
                    <div class="form-group w-40">
                        <label>Centro impartición:</label>
                        <input type="text" name="centro" id="centro-input" class="form-control" list="centros-list" placeholder="Escriba el centro...">
                        <datalist id="centros-list">
                            <?php foreach ($centros as $c): ?>
                                <option value="<?= htmlspecialchars($c['nombre']) ?>">
                            <?php endforeach; ?>
                            <!-- Centros adicionales de la imagen -->
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
                            <!-- Segunda tanda de centros -->
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
                            <!-- Tercera tanda de centros -->
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
                            <!-- Cuarta tanda de centros -->
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
                            <!-- Quinta tanda de centros -->
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
                            <!-- Sexta tanda de centros -->
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
                            <!-- Séptima tanda de centros -->
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
                            <!-- Octava y última tanda de centros -->
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
                    <div class="form-group w-30">
                        <label>Asignación:</label>
                        <select name="asignacion" class="form-control">
                            <option value="">Todas</option>
                            <option value="I">I</option>
                            <option value="E">E</option>
                            <option value="M">M</option>
                        </select>
                    </div>

                    <!-- Fila 3 -->
                    <div class="form-group w-15">
                        <label>Fecha inicio desde:</label>
                        <input type="date" name="fecha_ini_desde" class="form-control">
                    </div>
                    <div class="form-group w-15">
                        <label>Fecha inicio hasta:</label>
                        <input type="date" name="fecha_ini_hasta" class="form-control">
                    </div>
                    <div class="form-group row-layout w-10">
                        <label>Sin fechas:</label>
                        <input type="checkbox" name="sin_fechas">
                    </div>
                    <div class="form-group w-25">
                        <label>Acción:</label>
                        <input type="text" name="accion" class="form-control">
                    </div>
                    <div class="form-group w-10">
                        <label>Grupo:</label>
                        <input type="text" name="grupo_num" class="form-control">
                    </div>
                    <div class="form-group w-25">
                        <label>Cursos propios:</label>
                        <select name="cursos_propios" class="form-control">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <!-- Fila 4 -->
                    <div class="form-group w-15">
                        <label>Fecha fin desde:</label>
                        <input type="date" name="fecha_fin_desde" class="form-control">
                    </div>
                    <div class="form-group w-15">
                        <label>Fecha fin hasta:</label>
                        <input type="date" name="fecha_fin_hasta" class="form-control">
                    </div>
                    <div class="form-group w-15">
                        <label>Comunicados:</label>
                        <select name="comunicados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group w-15">
                        <label>Comunicados solic.:</label>
                        <select name="comunicados_solicitados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group w-20">
                        <label>Objetos de control:</label>
                        <select name="objetos_control" class="form-control">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group w-20">
                        <label>Desempleados:</label>
                        <select name="desempleados" class="form-control">
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <!-- Fila 5 -->
                    <div class="form-group w-50">
                        <label>Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control">
                            <option value="">Todas las convocatorias</option>
                            <?php foreach ($convocatorias as $conv): ?>
                                <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group w-50">
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
