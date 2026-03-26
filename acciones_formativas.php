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
        .search-container-fp {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .search-header-fp {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .search-form-fp {
            padding: 20px 25px;
        }

        .form-grid-fp {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            align-items: flex-end;
        }

        .form-group-fp {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-label-fp {
            font-weight: 700;
            font-size: 0.75rem;
            color: #1e3a8a;
            white-space: nowrap;
            text-align: right;
            min-width: 80px;
        }

        .form-control-fp {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.8rem;
            background: #f8fafc;
            font-family: inherit;
        }

        .form-control-fp:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }

        .search-actions-fp {
            grid-column: span 12;
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-fp-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            text-decoration: none;
        }

        .btn-fp-action:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        .btn-fp-action.primary {
            background: #f8fafc;
            border-color: #cbd5e1;
            font-weight: 700;
        }

        .btn-fp-action.pdf {
            color: #b91c1c;
        }

        .btn-fp-action svg {
            width: 14px;
            height: 14px;
        }

        /* Tabla de Resultados */
        .results-container-fp {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
        }

        .results-header-fp {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-title-fp {
            color: var(--primary-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            flex: 1;
            text-align: center;
        }

        .table-fp {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.71rem;
        }

        .table-fp th {
            background: #1e293b;
            color: white;
            padding: 8px 4px;
            text-align: left;
            border-right: 1px solid rgba(255,255,255,0.1);
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .header-inner {
            display: flex;
            align-items: center;
            gap: 2px;
        }

        .sort-icon {
            width: 10px;
            height: 10px;
            opacity: 0.6;
        }

        .table-fp td {
            padding: 8px 4px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .table-fp tr:hover td {
            background: #fffafa;
        }

        .ordenar-multiple-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #1e40af;
        }

        .btn-volver-footer {
            margin-top: 25px;
            text-align: center;
            padding-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .form-grid-fp { grid-template-columns: repeat(6, 1fr); }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="fp-layout">
            <?php include 'includes/fp_sidebar.php'; ?>
            <section class="fp-content-main">
        <header class="page-header">
            <div class="page-title">
                <h1>Acciones Formativas (Hija)</h1>
                <p>Gestión técnica de acciones formativas finales</p>
            </div>
            <div class="page-actions" style="display: flex; gap: 10px;">
                <a href="ficha_accion_formativa.php" class="btn btn-primary" style="background: #059669; border: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    NUEVA ACCIÓN
                </a>
                <a href="formacion_profesional.php" class="btn btn-primary" style="background: var(--primary-color); border-radius: 0; font-weight: 700;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER
                </a>
            </div>
        </header>

        <section class="search-container-fp">
            <div class="search-header-fp">Acciones Formativas - Campos de Búsqueda</div>
            <form class="search-form-fp" method="GET">
                <div class="form-grid-fp">
                    <!-- Fila 1 -->
                    <div class="form-group-fp" style="grid-column: span 12;">
                        <label class="form-label-fp" style="min-width: 60px;">Nombre:</label>
                        <input type="text" name="nombre" class="form-control-fp">
                    </div>

                    <!-- Fila 2 -->
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Convocatoria:</label>
                        <select name="convocatoria_id" class="form-control-fp">
                            <option value="">Todas</option>
                            <?php foreach ($convocatorias as $conv): ?>
                                <option value="<?= $conv['id'] ?>"><?= htmlspecialchars($conv['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Plan:</label>
                        <select name="plan" class="form-control-fp">
                            <option value="">Todos los planes</option>
                            <?php foreach ($planes as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 3 -->
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Solicitante:</label>
                        <select name="solicitante" class="form-control-fp">
                            <option value="">Seleccione...</option>
                            <?php foreach ($solicitantes as $sol): ?>
                                <option><?= $sol ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Sector:</label>
                        <select name="sector" class="form-control-fp">
                            <option value="">Seleccione...</option>
                            <?php foreach ($sectores as $sec): ?>
                                <option><?= $sec ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 4 -->
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Proveedor:</label>
                        <select name="proveedor" class="form-control-fp">
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option><?= $prov ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 6;">
                        <label class="form-label-fp">Catálogo:</label>
                        <select name="catalogo" class="form-control-fp">
                            <option value="">Seleccione...</option>
                            <?php foreach ($catalogos as $cat): ?>
                                <option><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fila 5 -->
                    <div class="form-group-fp" style="grid-column: span 4;">
                        <label class="form-label-fp">Consultora:</label>
                        <select name="consultora" class="form-control-fp">
                            <option value="">Seleccione...</option>
                            <?php foreach ($consultoras as $cons): ?>
                                <option><?= $cons ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 2;">
                        <label class="form-label-fp" style="min-width: auto;">Num. acción:</label>
                        <input type="text" name="id_accion" class="form-control-fp" style="background: white;">
                    </div>
                    <div class="form-group-fp" style="grid-column: span 2;">
                        <label class="form-label-fp" style="min-width: auto;">Prioridad:</label>
                        <select name="prioridad" class="form-control-fp">
                            <option value=""></option>
                            <?php foreach ($prioridades as $p): ?>
                                <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 2;">
                        <label class="form-label-fp" style="min-width: auto;">Modalidad:</label>
                        <select name="modalidad" class="form-control-fp">
                            <option value=""></option>
                            <?php foreach ($modalidades as $mod): ?>
                                <option><?= $mod ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-fp" style="grid-column: span 2;">
                        <label class="form-label-fp" style="min-width: auto;">Reserva:</label>
                        <select name="reserva" class="form-control-fp">
                            <option value=""></option>
                        </select>
                    </div>
                </div>

                <div class="search-actions-fp">
                    <button type="submit" class="btn-fp-action primary">Buscar</button>
                    <button type="button" class="btn-fp-action pdf">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM14 12h1V8h-1v4zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                        Imprimir Contenidos
                    </button>
                    <button type="button" class="btn-fp-action pdf">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM14 12h1V8h-1v4zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                        Contenidos resumidos
                    </button>
                    <button type="button" class="btn-fp-action pdf">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                        Imprimir
                    </button>
                </div>
            </form>
        </section>

        <section class="results-container-fp">
            <div class="results-header-fp">
                <div class="ordenar-multiple-wrap">
                    Ordenar múltiple <input type="checkbox">
                </div>
                <div class="results-title-fp">Resultado de la Búsqueda</div>
                <div style="width: 120px; text-align: right;">
                    <a href="formacion_profesional.php" class="btn-fp-action" style="padding: 4px 10px; font-size: 0.7rem;">Volver</a>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="table-fp">
                    <thead>
                        <tr>
                            <th><div class="header-inner">Nº Acc <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Título <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Abrev. <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Modalidad <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Duración <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Plan <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Partic. <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Mostrar <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Estado <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Tutor1 <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Tutor2 <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Win <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Mac <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Proveedor <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th><div class="header-inner">Pr. venta <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                            <th style="border-right: none;"><div class="header-inner">U. inicio <svg class="sort-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z" fill="currentColor"/></svg></div></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="16" style="text-align: center; padding: 40px; color: #64748b; font-style: italic;">
                                Realice una búsqueda para cargar las acciones formativas...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="btn-volver-footer">
            <button class="btn btn-primary" onclick="history.back()" style="background: var(--primary-color); padding: 8px 30px; border-radius: 0; font-weight: 700; text-transform: uppercase; font-size: 0.8rem;">
                Volver
            </button>
        </div>
            </section>
        </div>
    </main>
</div>

</body>
</html>
