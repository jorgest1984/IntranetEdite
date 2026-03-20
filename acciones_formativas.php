<?php
// acciones_formativas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_FORMADOR])) {
    die("No tiene permisos suficientes para acceder a Acciones Formativas. Su rol actual es: " . ($_SESSION['rol_nombre'] ?? 'Desconocido'));
}

// Fetch lists for selects
// Definición de Listas Base (según imágenes y requerimientos)
$base_proveedores = [
    '1&1 INTERNET ESPAÑA S.L.U.', '10DENCEHISPAHARD S.L.', 'ADAMS', 'ALBERCA PROYECTOS S. L.', 
    'ALBROK MEDIACION S. A.', 'ALLIANZ SEGUROS', 'ALSEVA ALMACENAJES Y SERVICIOS SL', 'AMAZON.COM', 
    'AMV-EDICIONES (ANTONIO MADRID VICENTE, EDICIONES)', 'APPFORBRANDS', 'ARTUAL S.L. EDICIONES', 
    'ASEPEYO SOCIEDAD DE PREVENCIÓN', 'ASOCIACIÓN ÁREA DE FORMACIÓN', 'ASOCIACIÓN PUERTA ALCALÁ', 
    'AVANSIL', 'BITLAN ASESORES INFORMATICOS CB', 'BRUNEAU', 'BUM CREACIONES S. L.', 
    'CAE (COMPUTER AIDER ELEARNING S.A)', 'CAJAMAR CAJA RURAL, SOCIEDAD COOPERATIVA DE CRÉDIT',
    'CESA PREVENCIÓN', 'CISS (GRUPO WOLTERS KLUWER)', 'CODIAL DOCUMENTOS Y SOLUCIONES S. L. U.',
    'COMERCIAL GRUPO ANAYA', 'COMVIVE SERVIDORES S. L.', 'CONEXIA', 'CORREOS',
    'CRESPO COMERCIAL ESPAÑOLA DE PROTECCIÓN', 'CRESPO COMERCIAL ESPAÑOLA DE PROTECCIÓN S.L',
    'CURSOFORUM, S.L.U', 'DASERIN', 'DELL COMPUTER S.A', 'DGF', 'EDICIONES PROTOCOLO',
    'EDITORIAL CEP, S.L', 'EDITORIAL PAIDOTRIBO', 'EMASAGRA', 'ENCUADERNACIONES SAN MIGUEL',
    'ENDESA', 'ENTABLA MM S. L.', 'ESIC, BUSINESS&MARKETING SCHOOL', 'ESTACIÓN DISEÑO',
    'EVALUASUR', 'FAXTECO', 'FIATC MUTUA DE SEGUROS Y REASEGUROS', 'FORMACIÓN 2020 S.A',
    'FORMATEL', 'FORMATOS COLOR Y DISEÑO S.L', 'FOTOCOPIAS ROMÁN', 'FOTOMECÁNICA INDALO SL',
    'FUNDACIÓ PRIVADA ROMÁN PRADO JUNIOR', 'FUNDAE (FUNDACIÓN TRIPARTITA)', 'G17',
    'GENERAL SEGUROS', 'GRÁFICAS ALHAMBRA', 'GRAFICAS AZACAYAS', 'GRAFICAS MALPICA',
    'GRAFICAS ZAIDIN', 'GRAPHIBOOK', 'GUARDIA CIVIL. DIRECCIÓN GENERAL', 'IDFO',
    'IEDITORIAL', 'IMADOC', 'IMAGESA21 CB', 'IMPRESIÓN DIGITAL GAMI S. L.',
    'INURRIETA CONSULTORÍA INTEGRAL', 'ITE-CECE', 'JESATEL', 'JUAN ANTONIO MONTILLA JIMENES',
    'JUMIALPRINT S.L. (ACOPIAS)', 'LA VENTANA INVISIBLE', 'LIBRERIA RENO',
    'LINEA DIRECTA ASEGURADORA', 'LOGOSS', 'MAINFOR.EDU.ES', 'MANZANO PROTECCIÓN CONTRA ROBO E INCENDIOS',
    'MARCOMBO, S.A.', 'MARGAR S.L', 'MARÍA BEL GALACHE (ANGOMA)', 'MARSDIGITAL S.L',
    'MENSAJERÍA DE LA FUNDACIÓN UNIPOST// REDISER', 'MOVISTAR', 'MRW (MENSAJERO DEL TIEMPO SLU)',
    'MUTUAVENIR', 'NOSOLORED S.L.', 'OFICINA ESPAÑOLA DE PATENTES Y MARCA', 'ONO',
    'ORBE FORMACION TECNOLOGICA Y DISTRIBUCION SL', 'PAPELERIA MIZAR', 'PATRIA HISPANA S. A.',
    'PC COSTE', 'PC-BOX', 'PC-ONLINE.NET', 'PRS SEGURIDAD', 'R. OLID MERCA',
    'REALE SEGUROS GENERALES, S.A.', 'RICOH', 'SECUENCIA INVERSORA SL', 'SELUAN S. L.',
    'SGS ICS IBÉRICA S.A.', 'SISTEMA INTERNO', 'SIT CADIZ', 'TAINE', 'TECNITEL S.L.',
    'TOURLINE EXPRESS', 'TRANSPORTES VIANA S. L.', 'UNIDAD ALIMENTARIA DE VALLADOLID S. A. (MERCAOLID)',
    'VERTICE', 'VIVA COPIER, S.L.', 'VODAFONE', 'YELLOW CONSULTORES, S.L'
];

$base_sectores = [
    'Abogados', 'Acción e Intervención Social', 'Administracion y gestion', 'Agencias de Viaje', 
    'Agricultura y otro sector ganaderia', 'Agroalimentaria', 'Alimentación', 'Alojamientos turísticos', 
    'Ambulancias', 'Arquitectura', 'Artes Gráficas', 'Artistas y Técnicos en Salas de Fiestas, Bailes y Discotecas', 
    'Asesorías', 'Asociaciones', 'Atención a personas con discapacidad', 'Atención Domiciliaria', 
    'Atención Especializada Familia', 'Automoción', 'Ayuda a domicilio', 'Banca', 'Cajas de ahorro', 
    'Centros de Asistencia Administrativa', 'Centros de día', 'Cerámicas y Artesanos', 'Chapa y pintura', 
    'cliente_artoblanco', 'Clinicas Privadas', 'Colegios/Institutos', 'Comercio', 'Construcción', 
    'Consultoría', 'Consultoría Informática', 'Contact Center', 'Coperativas', 'copisterias/fotocopias', 
    'Decoración', 'Desconocido', 'Desempleados', 'Dietetica y Nutricion', 'Diseño especializado', 
    'Economía social', 'Educación y Formación', 'Empleados Fincas Urbanas', 'Empresas de trabajo temporal', 
    'Energía y Agua', 'Enseñanza Privada', 'Entidades de Seguros', 'Estaciones de Servicio', 'Estética', 
    'Estudio de tatuajes', 'Estudios de mercado', 'Exhibición Cinematográfica', 'Farmacia', 'Fisioterapeutas', 
    'Fotografía', 'Fundaciones', 'Gestorías administrativas', 'Gimnasios', 'Guarderías', 'Hostelería', 
    'Imagen y sonido', 'Industria manufacturera', 'Industria vinícola', 'Industrias Químicas', 'Ingenierías', 
    'Inmobiliarias', 'Instalaciones Deportivas', 'Limpieza de Edificios y Locales', 'Madera y Mueble', 
    'Metal', 'Minería', 'Ocio y Tiempo Libre', 'Parques Temáticos', 'Peluquería y Estética', 'Peluquerías', 
    'Pesca', 'Pintura', 'Pompas Fúnebres', 'Prensa', 'Prensa diaria', 'Prensa no diaria', 'Producción Audiovisual', 
    'Publicidad', 'Público', 'Químicas', 'Recreativos', 'Residencias privadas', 'Sanidad', 'Seguridad Privada', 
    'Seguros', 'Serveis Financiers i Oficines', 'Servicio Doméstico', 'Servicios a la Comunidad', 
    'Servicios a las empresas', 'Servicios Auxiliares', 'Servicios de Prevención Ajenos', 'Servicios Funerarios', 
    'Servicios Sociales', 'Siderurgia', 'Suministros agrícolas', 'Talleres de restauración', 
    'Telecomunicaciones', 'Textil', 'Textil y Confección', 'Tintorerías', 'Transporte', 'Transportes', 
    'Turismo', 'Universidades', 'Vidrio y Cerámica'
];

$base_solicitantes = [
    'FED. ESTATAL DE SERVICIOS-UGT', 'COMFIA', 'FED. COM. Y TTE. CCOO MADRID', 'UGT DE CATALUNYA', 
    'UGT Madrid', 'FETCM-UGT', 'FETE-UGT', 'FED. NACIONAL DE DETALLISTAS DE FRUTAS Y HORTALIZAS', 
    'MARS', 'FITAG', 'Comunidad de Madrid', 'FAECTA', 'UGT Andalucía', 'FTFE', 'Criteria', 
    'FSP-UGT Palencia', 'JUNTA DE CASTILLA Y LEON', 'JUNTA DE ANDALUCIA', 'CRUZ ROJA ESPAÑOLA', 
    'MARSDIGITAL S.L.', 'Fundación Piquer'
];

$base_catalogos = [
    'Certificado de Profesionalidad', 'Familia- Actividades Físicas y Deportivas', 'Familia- Administración y Gestión', 
    'Familia- Agraria', 'Familia- Artes graficas', 'Familia- Comercio y Marketing', 'Familia- Edificación y Obra Civil', 
    'Familia- Energía y Agua', 'Familia- Hostelería y Turismo', 'Familia- Imagen Personal', 'Familia- Imagen y Sonido', 
    'Familia- Industria alimentaria', 'Familia- Informática y Comunicaciones', 'Familia- Seguridad y Medioambiente', 
    'Familia: Sevicios socioculturales y a la comunidad', 'Oferta 1.Appforbrands', 'Oferta 2.Appforbrands', 
    'Oferta 3. Hosteleria y Restauracion', 'Prevención de Riesgos Laborales', 'SAP', 'Seguridad Privada', 'Transversal'
];

$consultoras = [
    'ACADEMIA VISAN', 'ADAMS', 'AE S. MARTIN', 'AGE', 'AREA FORMACION AULAS', 'Asociación Puerta de Alcalá', 
    'AZUVIS S.C.A', 'BODYFACTORY SOMOSAGUAS', 'BOROXSPORT CLUB SPORT', 'C/ CORCEGA,371', 
    'CENTRO DE FORMACION ALFER', 'CLUB DE TENIS Y PADEL MONTEVERDE', 'EDITRAIN SL', 
    'EDITRAIN, S.L. (P.E.LA FINCA)', 'ELOGOS, S.L.', 'ENSEÑANZAS ORTHOS', 'FESS LA SALLE', 
    'Grupo Coremsa', 'INSTITUTO MADRILEÑO DE FORMACION S.L'
];

// Inicialización de variables definitivas con listas base
$proveedores = $base_proveedores;
$sectores = $base_sectores;
$solicitantes = $base_solicitantes;
$catalogos = $base_catalogos;
$convocatorias = [];
$planes = [];

// Enriquecer con datos de la BD (si están disponibles)
try {
    // Convocatorias
    $stmt = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY nombre ASC");
    if ($stmt) { $convocatorias = $stmt->fetchAll(); }

    // Planes y sus dimensiones (Sector, Solicitante, Entidad/Proveedor)
    $stmt = $pdo->query("SELECT id, nombre, codigo FROM planes ORDER BY nombre ASC");
    if ($stmt) { $planes = $stmt->fetchAll(); }

    // Proveedores dinámicos
    $stmt = $pdo->query("SELECT DISTINCT entidad FROM planes WHERE entidad IS NOT NULL AND entidad != ''");
    if ($stmt) { 
        $db_proveedores = $stmt->fetchAll(PDO::FETCH_COLUMN); 
        $proveedores = array_unique(array_merge($proveedores, $db_proveedores));
    }

    // Sectores dinámicos
    $stmt = $pdo->query("SELECT DISTINCT sector FROM planes WHERE sector IS NOT NULL AND sector != ''");
    if ($stmt) { 
        $db_sectores = $stmt->fetchAll(PDO::FETCH_COLUMN); 
        $sectores = array_unique(array_merge($sectores, $db_sectores));
    }

    // Solicitantes dinámicos
    $stmt = $pdo->query("SELECT DISTINCT solicitante FROM planes WHERE solicitante IS NOT NULL AND solicitante != ''");
    if ($stmt) { 
        $db_solicitantes = $stmt->fetchAll(PDO::FETCH_COLUMN); 
        $solicitantes = array_unique(array_merge($solicitantes, $db_solicitantes));
    }

    // Catálogos dinámicos
    $stmt = $pdo->query("SELECT DISTINCT nombre_corto FROM cursos WHERE nombre_corto IS NOT NULL AND nombre_corto != ''");
    if ($stmt) { 
        $db_catalogos = $stmt->fetchAll(PDO::FETCH_COLUMN); 
        $catalogos = array_unique(array_merge($catalogos, $db_catalogos));
    }

} catch (Throwable $e) { }

// Ordenar todas las listas finales
sort($proveedores);
sort($sectores);
sort($solicitantes);
sort($catalogos);
sort($consultoras);

$modalidades = ['TELEFORMACIÓN', 'PRESENCIAL', 'MIXTA', 'AULA VIRTUAL'];
$prioridades = ['Alta', 'Media', 'Baja'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acciones Formativas - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .search-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            font-weight: 700;
            color: #c2410c;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .search-form {
            padding: 1.5rem;
        }
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
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e40af;
            white-space: nowrap;
        }
        .form-control {
            width: 100%;
            padding: 0.4rem 0.6rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.9rem;
            background: #f1f5f9;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        /* Grid Layout Sizes */
        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-3 { grid-column: span 3; }
        .col-2 { grid-column: span 2; }

        .search-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            text-decoration: none;
        }
        .btn-action:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        .btn-action.primary {
            background: #f1f5f9;
            font-weight: 700;
        }
        .btn-action.pdf {
            color: #dc2626;
        }
        .btn-action svg {
            width: 16px;
            height: 16px;
        }

        /* Results table */
        .results-header {
            margin-bottom: 1rem;
            text-align: center;
        }
        .results-header h2 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #c2410c;
            text-transform: uppercase;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1200px;
        }
        .table-custom th {
            background: #1e293b;
            color: #fff;
            padding: 0.75rem;
            text-align: left;
            font-weight: 500;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .table-custom th svg {
            width: 10px;
            height: 10px;
            margin-left: 4px;
            vertical-align: middle;
        }
        .table-custom td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            color: #334155;
        }
        .table-custom tr:hover {
            background: #f8fafc;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .footer-actions {
            margin-top: 2rem;
            text-align: center;
            padding-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: repeat(6, 1fr); }
            .col-6, .col-4, .col-3, .col-2 { grid-column: span 3; }
            .col-12 { grid-column: span 6; }
        }
        @media (max-width: 640px) {
            .form-grid { display: block; }
            .form-group { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">

            <div class="page-title">
                <h1>Acciones Formativas</h1>
                <p>Gestión y búsqueda de acciones de formación</p>
            </div>
            <div class="page-actions">
                <a href="formacion_profesional.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver
                </a>
            </div>
        </header>

        <section class="search-card">
            <div class="search-card-header">
                <h2>Acciones Formativas - Campos de Búsqueda</h2>
            </div>
            <div class="search-form">
                <form method="GET">
                    <div class="form-grid">
                        <div class="form-group col-12">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Buscar por nombre...">
                        </div>
                        
                        <div class="form-group col-6">
                            <label>Convocatoria:</label>
                            <select name="convocatoria_id" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($convocatorias as $conv): ?>
                                    <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-6">
                            <label>Plan:</label>
                            <select name="plan" class="form-control">
                            <option value="">Seleccione...</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['codigo']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        </div>

                        <div class="form-group col-6">
                            <label>Solicitante:</label>
                            <select name="solicitante" class="form-control">
                            <option value="">Seleccione...</option>
                            <?php foreach ($solicitantes as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                        
                        <div class="form-group col-6">
                            <label>Sector:</label>
                            <select name="sector" class="form-control">
                            <option value="">Seleccione...</option>
                            <?php foreach ($sectores as $sec): ?>
                                <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>

                        <div class="form-group col-6">
                            <label>Proveedor:</label>
                            <select name="proveedor" class="form-control">
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                        
                        <div class="form-group col-6">
                            <label>Catálogo:</label>
                            <select name="catalogo" class="form-control">
                                <option value="">Seleccione...</option>
                                <?php foreach ($catalogos as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <label>Consultora:</label>
                            <select name="consultora" class="form-control">
                                <option value="">Seleccione...</option>
                                <?php foreach ($consultoras as $cons): ?>
                                    <option value="<?= htmlspecialchars($cons) ?>"><?= htmlspecialchars($cons) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-2">
                            <label>Num. acción:</label>
                            <input type="text" name="num_accion" class="form-control">
                        </div>
                        
                        <div class="form-group col-2">
                            <label>Prioridad:</label>
                            <select name="prioridad" class="form-control">
                                <option value=""></option>
                                <?php foreach ($prioridades as $p): ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-2">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control">
                                <option value=""></option>
                                <?php foreach ($modalidades as $m): ?>
                                    <option value="<?= $m ?>"><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-2">
                            <label>Reserva:</label>
                            <input type="text" name="reserva" class="form-control">
                        </div>
                    </div>

                    <div class="search-actions">
                        <button type="submit" class="btn-action primary">Buscar</button>
                        <button type="button" class="btn-action pdf">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM14 12h1V8h-1v4zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                            Imprimir Contenidos
                        </button>
                        <button type="button" class="btn-action pdf">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM14 12h1V8h-1v4zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                            Contenidos resumidos
                        </button>
                        <button type="button" class="btn-action pdf">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                            Imprimir
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="results-container">
            <div class="checkbox-container">
                <input type="checkbox" id="ordenar_multiple" name="ordenar_multiple">
                <label for="ordenar_multiple" style="color: #1e40af; font-weight: 700;">Ordenar múltiple</label>
            </div>
            
            <div class="results-header">
                <h2>Resultado de la Búsqueda</h2>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Nº Acc <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Título <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Abrev. <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Modalidad <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Duración <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Plan <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Partic. <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Mostrar <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Estado <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Tutor1 <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Tutor2 <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Win <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Mac <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Proveedor <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                            <th>Último inicio <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="15" style="text-align: center; padding: 3rem; color: #64748b;">
                                Realice una búsqueda para ver los resultados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="footer-actions">
            <button class="btn-action" onclick="history.back()">Volver</button>
        </div>
    </main>
</div>

</body>
</html>
