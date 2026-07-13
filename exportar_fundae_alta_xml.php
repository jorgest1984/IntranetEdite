<?php
// exportar_fundae_alta_xml.php
// Generador XML FUNDAE para Alta de Participantes
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
    http_response_code(403);
    die("Acceso denegado.");
}

$grupo_id = isset($_GET['grupo_id']) ? (int)$_GET['grupo_id'] : 0;
$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : 0;

if (!$grupo_id && !$accion_id) {
    die("Se requiere grupo_id o accion_id.");
}

// Buscar información del grupo y acción formativa
$whereClause = $grupo_id ? "g.id = ?" : "af.id = ?";
$paramValue = $grupo_id ?: $accion_id;

$stmtGrupo = $pdo->prepare("
    SELECT g.*, af.num_accion, c.codigo_expediente, af.titulo as curso_nombre
    FROM grupos g
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
    WHERE $whereClause
    LIMIT 1
");
$stmtGrupo->execute([$paramValue]);
$grupoData = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

if (!$grupoData) {
    die("Grupo o Acción Formativa no encontrados.");
}

// Consultar los alumnos matriculados en ese grupo/acción
$stmtAlumnos = $pdo->prepare("
    SELECT m.*, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.sexo, a.fecha_nacimiento,
           a.telefono, a.email, a.estudios, a.profesion, a.seguridad_social, a.domicilio,
           a.cp, a.localidad, a.provincia, e.cif as empresa_cif, e.nombre as empresa_nombre,
           e.localidad as empresa_localidad, e.cp as empresa_cp
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    LEFT JOIN empresas e ON a.ultima_empresa_id = e.id
    WHERE m.estado IN ('Inscrito', 'Activo', 'Finalizada') AND m.grupo_id = ?
");
// Si venía por accion_id, cogemos el ID del primer grupo encontrado
$grupo_id_real = $grupo_id ?: $grupoData['id'];
$stmtAlumnos->execute([$grupo_id_real]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

if (empty($alumnos)) {
    die("No hay alumnos matriculados en este grupo para exportar.");
}

// HELPERS
function cleanNif($nif) {
    return preg_replace('/[^a-zA-Z0-9]/', '', (string)$nif);
}

function cleanTelefono($tel) {
    return substr(preg_replace('/[^0-9]/', '', (string)$tel), 0, 12);
}

function formatDateFundae($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '';
    try {
        $d = new DateTime($dateStr);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return '';
    }
}

function mapSexo($sexo) {
    if (empty($sexo)) return '0'; // default femenino o sin especificar
    $s = strtoupper(substr(trim($sexo), 0, 1));
    return ($s === 'H' || $s === 'V' || $s === 'M' && strtolower($sexo) !== 'mujer') ? '1' : '0';
}

function mapEstudios($estudios) {
    if (empty($estudios)) return '1'; // Sin titulación
    $e = strtolower($estudios);
    if (strpos($e, 'licenciad') !== false || strpos($e, 'grado univ') !== false) return '8';
    if (strpos($e, 'diplomad') !== false) return '7';
    if (strpos($e, 'superior') !== false || strpos($e, 'fpii') !== false || strpos($e, 'fp2') !== false) return '5';
    if (strpos($e, 'medio') !== false || strpos($e, 'fpi') !== false || strpos($e, 'fp1') !== false) return '4';
    if (strpos($e, 'bachiller') !== false) return '3';
    if (strpos($e, 'eso') !== false || strpos($e, 'graduado') !== false) return '2';
    return '6'; // Otra titulación
}

// CONFIGURACIÓN DEL XML
$dom = new DOMDocument('1.0', 'utf-8');
$dom->formatOutput = true;

$root = $dom->createElement('grupos');
$dom->appendChild($root);

$grupoElem = $dom->createElement('grupo');
$root->appendChild($grupoElem);

// Limpiar expediente (máximo 9)
$exp = trim($grupoData['codigo_expediente'] ?? '');
if (empty($exp)) $exp = '000000000';
$exp = substr(preg_replace('/[^0-9A-Za-z]/', '', $exp), 0, 9);
$grupoElem->appendChild($dom->createElement('expediente', htmlspecialchars($exp)));
$grupoElem->appendChild($dom->createElement('num_accion', (int)($grupoData['num_accion'] ?: 1)));

// num_grupo: Quitar la 'G' inicial si la tiene
$numG = preg_replace('/[^0-9]/', '', $grupoData['numero_grupo']);
if (empty($numG)) $numG = 1;
$grupoElem->appendChild($dom->createElement('num_grupo', (int)$numG));

$participantesElem = $dom->createElement('participantes');
$grupoElem->appendChild($participantesElem);

foreach ($alumnos as $al) {
    $part = $dom->createElement('participante');
    $participantesElem->appendChild($part);

    $part->appendChild($dom->createElement('nif', htmlspecialchars(cleanNif($al['dni']))));
    
    if (!empty($al['seguridad_social'])) {
        $niss = preg_replace('/[^0-9]/', '', $al['seguridad_social']);
        if (strlen($niss) <= 12) {
            $part->appendChild($dom->createElement('niss', htmlspecialchars($niss)));
        }
    }
    
    $part->appendChild($dom->createElement('nombre', htmlspecialchars(substr($al['nombre'], 0, 20))));
    $part->appendChild($dom->createElement('apellido1', htmlspecialchars(substr($al['primer_apellido'] ?? 'Apellido', 0, 20))));
    
    if (!empty($al['segundo_apellido'])) {
        $part->appendChild($dom->createElement('apellido2', htmlspecialchars(substr($al['segundo_apellido'], 0, 20))));
    }
    
    // 1 = Régimen general por defecto si no tenemos cómo adivinarlo
    $sitLab = 1; 
    if (stripos($al['profesion'] ?? '', 'autonomo') !== false || stripos($al['profesion'] ?? '', 'autónomo') !== false) {
        $sitLab = 6;
    } elseif (stripos($al['profesion'] ?? '', 'desempleado') !== false || stripos($al['profesion'] ?? '', 'paro') !== false) {
        $sitLab = 15;
    }
    $part->appendChild($dom->createElement('situacion_laboral', $sitLab));
    
    // Reserva
    $part->appendChild($dom->createElement('reserva', 0));
    
    // Genero
    $part->appendChild($dom->createElement('genero', mapSexo($al['sexo'])));
    
    // Fecha Nacimiento
    $fn = formatDateFundae($al['fecha_nacimiento']);
    if ($fn) {
        $part->appendChild($dom->createElement('fecha_nacimiento', $fn));
    }
    
    // Telefono
    $tel = cleanTelefono($al['telefono']);
    if (strlen($tel) >= 9) {
        $part->appendChild($dom->createElement('telefono', $tel));
    }
    
    // Email
    if (!empty($al['email'])) {
        $part->appendChild($dom->createElement('email', htmlspecialchars(substr($al['email'], 0, 100))));
    }
    
    // Empresa
    if (!empty($al['empresa_cif'])) {
        $part->appendChild($dom->createElement('cif_nif_empresa', htmlspecialchars(cleanNif($al['empresa_cif']))));
    }
    if (!empty($al['empresa_nombre'])) {
        $part->appendChild($dom->createElement('nombre_empresa', htmlspecialchars(substr($al['empresa_nombre'], 0, 150))));
    }
    if (!empty($al['empresa_localidad'])) {
        $part->appendChild($dom->createElement('localidad_centro_trabajo', htmlspecialchars(substr($al['empresa_localidad'], 0, 75))));
    }
    if (!empty($al['empresa_cp'])) {
        $cp = substr(preg_replace('/[^0-9]/', '', $al['empresa_cp']), 0, 5);
        if (strlen($cp) === 5) {
            $part->appendChild($dom->createElement('codigo_postal_centro_trabajo', $cp));
        }
    }
    
    // Domicilio Participante
    if (!empty($al['domicilio'])) {
        $part->appendChild($dom->createElement('domicilio_participante', htmlspecialchars(substr($al['domicilio'], 0, 250))));
    }
    if (!empty($al['localidad'])) {
        $part->appendChild($dom->createElement('localidad_participante', htmlspecialchars(substr($al['localidad'], 0, 75))));
    }
    if (!empty($al['cp'])) {
        $cp = substr(preg_replace('/[^0-9]/', '', $al['cp']), 0, 5);
        if (strlen($cp) === 5) {
            $part->appendChild($dom->createElement('codigo_postal_participante', $cp));
        }
    }
    if (!empty($al['provincia'])) {
        $part->appendChild($dom->createElement('provincia_participante', htmlspecialchars(substr($al['provincia'], 0, 30))));
    }
    
    // Nivel de estudios
    $estudiosId = mapEstudios($al['estudios']);
    $nelem = $dom->createElement('nivel_estudios');
    $nelem->appendChild($dom->createElement('estudios', $estudiosId));
    if ($estudiosId === '6' && !empty($al['estudios'])) {
        $nelem->appendChild($dom->createElement('otra_titulacion', htmlspecialchars(substr($al['estudios'], 0, 200))));
    }
    $part->appendChild($nelem);
}

// OUTPUT
$filename = "Fundae_Alta_" . $grupoData['numero_grupo'] . "_" . date('Ymd_His') . ".xml";
header("Content-Type: text/xml; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo $dom->saveXML();
exit();
