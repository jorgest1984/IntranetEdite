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
$provincia = trim($_GET['provincia'] ?? '');

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
        $searchIn = match($tag) {
            'a' => ['alumnos'],
            'c' => ['cursos'],
            'g' => ['grupos'],
            'ct' => ['contactos'],
            default => $searchIn
        };
    }

    // --- BÚSQUEDA ALUMNOS ---
    if (in_array('alumnos', $searchIn)) {
        $sql = "SELECT id, nombre, primer_apellido, segundo_apellido, dni, email, telefono, localidad, provincia 
                FROM alumnos 
                WHERE (nombre LIKE ? OR primer_apellido LIKE ? OR segundo_apellido LIKE ? OR dni LIKE ? OR telefono LIKE ? OR email LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike];
        if (!empty($provincia)) { $sql .= " AND provincia = ?"; $params[] = $provincia; }
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
        if (!empty($provincia)) { $sql .= " AND provincia = ?"; $params[] = $provincia; }
        $sql .= " LIMIT $limit";
        $results['empresas'] = $pdo->prepare($sql);
        $results['empresas']->execute($params);
        $results['empresas'] = $results['empresas']->fetchAll();
    }

    // --- BÚSQUEDA CONTACTOS (En Empresas) ---
    if (in_array('contactos', $searchIn)) {
        $sql = "SELECT id, nombre as empresa, contacto_nombre, contacto_email, contacto_telefono 
                FROM empresas 
                WHERE (contacto_nombre LIKE ? OR contacto_email LIKE ? OR contacto_telefono LIKE ?)";
        $params = [$searchLike, $searchLike, $searchLike];
        if (!empty($provincia)) { $sql .= " AND provincia = ?"; $params[] = $provincia; }
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
            $sql = "SELECT g.id, g.codigo_grupo, af.titulo as curso_nombre 
                    FROM grupos g 
                    JOIN acciones_formativas af ON g.accion_formativa_id = af.id 
                    WHERE af.num_accion = ?";
            $params = [$accNum];
            if ($grpCode) { $sql .= " AND g.codigo_grupo LIKE ?"; $params[] = "%$grpCode%"; }
        } else {
            $sql = "SELECT g.id, g.codigo_grupo, af.titulo as curso_nombre 
                    FROM grupos g 
                    LEFT JOIN acciones_formativas af ON g.accion_formativa_id = af.id 
                    WHERE g.codigo_grupo LIKE ? LIMIT $limit";
            $params = [$searchLike];
        }
        $results['grupos'] = $pdo->prepare($sql);
        $results['grupos']->execute($params);
        $results['grupos'] = $results['grupos']->fetchAll();
    }
}

// Datos para la UI
$provincias = ["Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"];

$allAreas = [
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
                            <label>Provincia</label>
                            <select name="provincia" class="select-custom">
                                <option value="">Todas las provincias</option>
                                <?php foreach ($provincias as $p): ?>
                                    <option value="<?= $p ?>" <?= $provincia == $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
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
                                                $sub = "Empresa: " . $row['empresa'];
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
                                                $main = "Grupo " . $row['codigo_grupo'];
                                                $sub = $row['curso_nombre'];
                                                break;
                                        }
                                    ?>
                                    <a href="<?= $url ?>" class="result-item">
                                        <div class="result-main"><?= htmlspecialchars($main) ?></div>
                                        <div class="result-sub"><?= htmlspecialchars($sub) ?></div>
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
