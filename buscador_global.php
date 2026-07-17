<?php
// buscador_global.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Verificar permisos básicos (cualquier usuario logueado puede buscar)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

    $term = trim($_GET['term'] ?? '');
    $areas = $_GET['areas'] ?? []; // Array de áreas seleccionadas

$results = [
    'alumnos' => [],
    'empresas' => [],
    'contactos' => [],
    'usuarios' => [],
    'cursos' => [], // AAFF
    'grupos' => []
];

if (!empty($term)) {
    // 1. Parsing de Etiquetas (#a, #c, #g, #ct)
    $tag = null;
    if (preg_match('/^#([a-z]+)\s+(.+)$/i', $term, $matches)) {
        $tag = strtolower($matches[1]);
        $termSearch = trim($matches[2]);
    } else {
        $termSearch = $term;
    }

    // 2. Parsing de Patrón A{X}-G{Y}
    $patternMatch = false;
    if (preg_match('/^A(\d+)-G(\d+)?$/i', $termSearch, $pMatches)) {
        $patternMatch = true;
        $accNum = $pMatches[1];
        $grpCode = $pMatches[2] ?? null;
    }

    $limit = 10;
    $searchLike = "%$termSearch%";

    // Determinar qué buscar según etiquetas o selección manual
    $searchIn = (!empty($areas)) ? $areas : ['alumnos', 'empresas', 'contactos', 'usuarios', 'cursos', 'grupos'];
    if ($tag) {
        switch ($tag) {
            case 'a':
                $searchIn = ['alumnos'];
                break;
            case 'c':
                $searchIn = ['cursos'];
                break;
            case 'g':
                $searchIn = ['grupos'];
                break;
            case 'ct':
                $searchIn = ['contactos'];
                break;
        }
    }

    // --- BÚSQUEDA ALUMNOS ---
    if (in_array('alumnos', $searchIn)) {
        $sql = "SELECT id, nombre, primer_apellido, segundo_apellido, dni, email, telefono, localidad, provincia 
                FROM alumnos 
                WHERE (nombre LIKE ? OR primer_apellido LIKE ? OR segundo_apellido LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike];
        $sql .= " LIMIT $limit";
        $results['alumnos'] = $pdo->prepare($sql);
        $results['alumnos']->execute($params);
        $results['alumnos'] = $results['alumnos']->fetchAll();
    }

    // --- BÚSQUEDA EMPRESAS ---
    if (in_array('empresas', $searchIn)) {
        $sql = "SELECT id, nombre, cif, email, telefono, localidad, provincia 
                FROM empresas 
                WHERE (nombre LIKE ? OR cif LIKE ? OR telefono LIKE ? OR email LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        $sql .= " LIMIT $limit";
        $results['empresas'] = $pdo->prepare($sql);
        $results['empresas']->execute($params);
        $results['empresas'] = $results['empresas']->fetchAll();
    }

    // --- BÚSQUEDA CONTACTOS (En Empresas) ---
    if (in_array('contactos', $searchIn)) {
        $sql = "SELECT id, nombre as empresa, contacto_nombre, contacto_telefono 
                FROM empresas 
                WHERE (contacto_nombre LIKE ? OR contacto_telefono LIKE ?)";
        $params = [$searchLike, $searchLike];
        $sql .= " LIMIT $limit";
        $results['contactos'] = $pdo->prepare($sql);
        $results['contactos']->execute($params);
        $results['contactos'] = $results['contactos']->fetchAll();
    }

    // --- BÚSQUEDA USUARIOS ---
    if (in_array('usuarios', $searchIn)) {
        $sql = "SELECT id, nombre, apellidos, username, email 
                FROM usuarios 
                WHERE (nombre LIKE ? OR apellidos LIKE ? OR username LIKE ? OR email LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        $sql .= " LIMIT $limit";
        $results['usuarios'] = $pdo->prepare($sql);
        $results['usuarios']->execute($params);
        $results['usuarios'] = $results['usuarios']->fetchAll();
    }

    // --- BÚSQUEDA CURSOS / AAFF ---
    if (in_array('cursos', $searchIn)) {
        if ($patternMatch) {
            $sql = "SELECT id, titulo, num_accion, abreviatura FROM acciones_formativas WHERE num_accion = ? LIMIT 1";
            $params = [$accNum];
        } else {
            $sql = "SELECT id, titulo, num_accion, abreviatura FROM acciones_formativas WHERE (titulo LIKE ? OR num_accion LIKE ? OR abreviatura LIKE ?) LIMIT $limit";
            $params = [$searchLike, $searchLike, $searchLike];
        }
        $results['cursos'] = $pdo->prepare($sql);
        $results['cursos']->execute($params);
        $results['cursos'] = $results['cursos']->fetchAll();
    }

    // --- BÚSQUEDA GRUPOS ---
    if (in_array('grupos', $searchIn)) {
        if ($patternMatch) {
            $sql = "SELECT g.id, g.numero_grupo, af.titulo as curso_nombre 
                    FROM grupos g 
                    JOIN acciones_formativas af ON g.accion_id = af.id 
                    WHERE af.num_accion = ?";
            $params = [$accNum];
            if ($grpCode) { $sql .= " AND g.numero_grupo LIKE ?"; $params[] = "%$grpCode%"; }
        } else {
            $sql = "SELECT g.id, g.numero_grupo, af.titulo as curso_nombre 
                    FROM grupos g 
                    LEFT JOIN acciones_formativas af ON g.accion_id = af.id 
                    WHERE g.numero_grupo LIKE ? LIMIT $limit";
            $params = [$searchLike];
        }
        $results['grupos'] = $pdo->prepare($sql);
        $results['grupos']->execute($params);
        $results['grupos'] = $results['grupos']->fetchAll();
    }
    // --- BÚSQUEDA SECCIONES (Menú) ---
    // Definir mapa de secciones y palabras clave
    $intranetSections = [
        ['url' => 'usuarios.php', 'title' => 'Usuarios y Roles', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'keywords' => ['usuarios', 'roles', 'permisos', 'tutores', 'administradores', 'staff', 'personal']],
        ['url' => 'alumnos.php', 'title' => 'Gestión de Alumnos y Matrículas', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'keywords' => ['alumnos', 'estudiantes', 'asistentes', 'participantes', 'matricula', 'matriculas', 'gestion', 'matricular', 'altas']],
        ['url' => 'grupos.php', 'title' => 'Grupos', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'keywords' => ['grupos', 'clases', 'aulas', 'ediciones']],
        ['url' => 'acciones_formativas.php', 'title' => 'Acciones Formativas', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'keywords' => ['acciones formativas', 'cursos', 'aa ff', 'aaff', 'accion']],
        ['url' => 'convocatorias.php', 'title' => 'Convocatorias', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'keywords' => ['convocatorias', 'planes', 'expedientes', 'subvenciones']],
        ['url' => 'empresas.php', 'title' => 'Empresas', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'keywords' => ['empresas', 'clientes', 'organizaciones', 'b2b']],
        ['url' => 'proveedores.php', 'title' => 'Proveedores', 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'keywords' => ['proveedores', 'compras', 'abastecimiento']],
        ['url' => 'facturas.php', 'title' => 'Facturas', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'keywords' => ['facturas', 'pagos', 'cobros', 'facturacion', 'importar']],
        ['url' => 'certificados.php', 'title' => 'Certificados', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z', 'keywords' => ['certificados', 'diplomas', 'anexos', 'documentos']],
        ['url' => 'papelera.php', 'title' => 'Papelera', 'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'keywords' => ['papelera', 'borrados', 'eliminados', 'recuperar']],
        ['url' => 'dashboard.php', 'title' => 'Dashboard', 'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', 'keywords' => ['dashboard', 'inicio', 'resumen', 'estadisticas']],
        ['url' => 'documentacion.php', 'title' => 'Documentación', 'icon' => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2', 'keywords' => ['documentacion didactica', 'manuales', 'pdfs', 'anexos', 'documentacion']]
    ];

    $results['secciones'] = [];
    $termLower = strtolower($termSearch);
    foreach ($intranetSections as $section) {
        // Buscar coincidencia en el título o palabras clave
        if (strpos(strtolower($section['title']), $termLower) !== false) {
            $results['secciones'][] = $section;
            continue;
        }
        foreach ($section['keywords'] as $kw) {
            if (strpos(strtolower($kw), $termLower) !== false) {
                $results['secciones'][] = $section;
                break;
            }
        }
    }
}

// Datos para la UI

$allAreas = [
    'secciones' => '🔗 Secciones y Herramientas',
    'alumnos' => 'Alumnos / Trabajadores',
    'empresas' => 'Empresas',
    'contactos' => 'Contactos',
    'usuarios' => 'Usuarios Edite',
    'cursos' => 'Cursos / AAFF',
    'grupos' => 'Grupos'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador Global - Edite Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/buscador.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="search-container">
            <header class="search-header">
                <div class="header-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <h1>Buscador Global</h1>
                    <a href="home.php" class="btn-back" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text-muted); font-weight: 600; font-size: 0.9rem; transition: color 0.2s;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                        Volver al Inicio
                    </a>
                </div>
                <p>Navega a través de personas, empresas y formación de forma inteligente.</p>
            </header>

            <form action="" method="GET" id="search-form">
                <div class="search-form-panel">
                    <div class="search-grid">
                        <div class="form-group">
                            <label>Término de búsqueda</label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-4.35-4.35M19 11a8 8 0 11-16 0 8 8 0 0116 0z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                </span>
                                <input type="text" name="term" class="search-input" value="<?= htmlspecialchars($term) ?>" placeholder="Nombre, NIF, #a alumnos, #c cursos, A31-G1..." autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn-search-main">
                                <span>BUSCAR</span>
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="area-selector" style="margin-top: 1.5rem;">
                        <label>Filtrar por área</label>
                        <div class="area-list">
                            <?php foreach ($allAreas as $key => $label): ?>
                                <div class="area-chip <?= (in_array($key, $areas) || empty($areas)) ? 'active' : '' ?>" data-key="<?= $key ?>">
                                    <?= $label ?>
                                    <input type="checkbox" name="areas[]" value="<?= $key ?>" style="display:none;" <?= (in_array($key, $areas) || empty($areas)) ? 'checked' : '' ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!empty($term)): ?>
                <div class="results-grid">
                    <?php 
                    $foundAny = false;
                    foreach ($results as $cat => $data): 
                        if (empty($data)) continue;
                        $foundAny = true;
                    ?>
                        <section class="category-section">
                            <div class="category-header">
                                <h3 class="category-title"><?= $allAreas[$cat] ?></h3>
                                <span class="badge" style="background: var(--search-primary); color: white; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem;"><?= count($data) ?></span>
                            </div>
                            <div class="result-list">
                                <?php foreach ($data as $row): ?>
                                    <?php 
                                        $url = '#'; $main = ''; $sub = '';
                                        switch($cat) {
                                            case 'secciones':
                                                $url = $row['url'];
                                                $main = $row['title'];
                                                $sub = "Ir a la sección";
                                                break;
                                            case 'alumnos':
                                                $url = "ficha_alumno.php?id=" . $row['id'];
                                                $main = $row['nombre'] . ' ' . $row['primer_apellido'];
                                                $sub = $row['dni'] . ' · ' . ($row['telefono'] ?: $row['email']);
                                                break;
                                            case 'empresas':
                                                $url = "ficha_empresa.php?id=" . $row['id'];
                                                $main = $row['nombre'];
                                                $sub = $row['cif'] . ' · ' . $row['localidad'];
                                                break;
                                            case 'contactos':
                                                $url = "ficha_empresa.php?id=" . $row['id'];
                                                $main = $row['contacto_nombre'];
                                                $sub = "Empresa: " . $row['empresa'] . ($row['contacto_telefono'] ? " · " . $row['contacto_telefono'] : "");
                                                break;
                                            case 'usuarios':
                                                $url = "ficha_usuario.php?id=" . $row['id'];
                                                $main = $row['nombre'] . ' ' . $row['apellidos'];
                                                $sub = "@" . $row['username'];
                                                break;
                                            case 'cursos':
                                                $url = "acciones_formativas.php?id=" . $row['id'];
                                                $main = $row['titulo'];
                                                $sub = "Acción: " . $row['num_accion'];
                                                break;
                                            case 'grupos':
                                                $url = "grupos.php?id=" . $row['id'];
                                                $main = "Grupo " . $row['numero_grupo'];
                                                $sub = $row['curso_nombre'];
                                                break;
                                        }
                                    ?>
                                    <a href="<?= $url ?>" class="result-item" <?= $cat == 'secciones' ? 'style="display: flex; align-items: center; gap: 15px; border-left: 4px solid var(--search-primary); background: #f8fafc;"' : '' ?>>
                                        <?php if($cat == 'secciones' && isset($row['icon'])): ?>
                                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--search-primary);"><path d="<?= $row['icon'] ?>" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        <?php endif; ?>
                                        <div>
                                            <div class="result-main"><?= htmlspecialchars($main) ?></div>
                                            <div class="result-sub"><?= htmlspecialchars($sub) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <?php if (!$foundAny): ?>
                        <div class="no-results">
                            <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" style="margin-bottom: 1rem; opacity: 0.3;"><path d="M21 21l-4.35-4.35M19 11a8 8 0 11-16 0 8 8 0 0116 0z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            <h3>No se han encontrado resultados</h3>
                            <p>Prueba con otros términos o ajusta los filtros de área.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-results" style="opacity: 0.5;">
                    <h3>Sugerencias de búsqueda</h3>
                    <p>Usa <strong>#a</strong> para alumnos, <strong>#c</strong> para cursos o <strong>#g</strong> para grupos.</p>
                    <p style="margin-top: 1rem; font-size: 0.85rem;">Ejemplo: <i>#a perez</i> buscará alumnos con ese apellido.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const chips = document.querySelectorAll('.area-chip');
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chip.classList.toggle('active');
                const checkbox = chip.querySelector('input');
                checkbox.checked = chip.classList.contains('active');
            });
        });
    });
    </script>
</body>
</html>
