<?php
// exportar_encuestas_xml.php
// Generador XML FUNDAE de encuestas de satisfacción
// Estructura oficial validada por FUNDAE (cuestionarios > cuestionario > BloqueI + BloqueII + BloqueIII)

require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_PRACTICAS])) {
    http_response_code(403);
    die("Acceso denegado.");
}

$accion_id = isset($_GET['accion_id']) ? (int)$_GET['accion_id'] : 0;
$grupo_id  = isset($_GET['grupo_id'])  ? (int)$_GET['grupo_id']  : 0;

if (!$accion_id && !$grupo_id) {
    die("Parámetro requerido: accion_id o grupo_id.");
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. CONSULTAR ENCUESTAS
// ──────────────────────────────────────────────────────────────────────────────
try {
    $sqlBase = "
        SELECT
            er.*,
            m.id          AS matricula_id,
            g.id          AS grupo_id_real,
            g.numero_grupo,
            g.expediente  AS grupo_expediente,
            g.fecha_inicio,
            g.fecha_fin,
            af.num_accion,
            af.titulo     AS curso_nombre,
            af.modalidad,
            co.codigo_expediente,
            a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.sexo AS sexo_alumno
        FROM encuestas_resultados er
        JOIN matriculas m           ON er.matricula_id = m.id
        JOIN alumnos a              ON m.alumno_id = a.id
        JOIN grupos g               ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c          ON af.curso_id = c.id
        LEFT JOIN planes pl         ON af.plan_id = pl.id
        LEFT JOIN convocatorias co  ON pl.convocatoria_id = co.id
    ";

    $encuestas = [];

    // Intento 1: Buscar por ID estricto de Intranet (grupo_id o accion_id)
    if ($grupo_id) {
        $stmt = $pdo->prepare("$sqlBase WHERE m.grupo_id = ? ORDER BY er.fecha_realizacion ASC, er.id ASC");
        $stmt->execute([$grupo_id]);
        $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("$sqlBase WHERE g.accion_id = ? ORDER BY er.fecha_realizacion ASC, er.id ASC");
        $stmt->execute([$accion_id]);
        $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Intento 2 (Fallback): Si no hay resultados y se indicó grupo_id o accion_id, buscar por ID de Moodle / Plataforma
    if (empty($encuestas)) {
        $paramId = $grupo_id ?: $accion_id;
        $stmt = $pdo->prepare("$sqlBase WHERE (af.id_plataforma = ? OR g.id_plataforma = ? OR c.moodle_id = ?) ORDER BY er.fecha_realizacion ASC, er.id ASC");
        $stmt->execute([$paramId, $paramId, $paramId]);
        $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("Error al consultar las encuestas: " . $e->getMessage());
}

if (empty($encuestas)) {
    die("No hay encuestas completadas para exportar con los parámetros indicados.");
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. HELPERS DE FORMATEO Y CONVERSIÓN
// ──────────────────────────────────────────────────────────────────────────────

function xval($value, $default = '99') {
    if ($value === null || $value === '') return $default;
    return (string)$value;
}

function mapSexo($sexo) {
    if ($sexo === null || $sexo === '') return '1';
    $s = strtoupper(trim($sexo));
    if (in_array($s, ['1', 'M', 'MUJER', 'FEMENINO', 'F'])) return '1';
    if (in_array($s, ['2', 'H', 'VARON', 'HOMBRE', 'V', 'MASCULINO'])) return '2';
    return '1';
}

function xmlSafe($str) {
    if ($str === null) return '';
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$str);
    return htmlspecialchars($str, ENT_XML1, 'UTF-8');
}

function formatFechaFundae($datetime) {
    if (!$datetime) return date('d/m/Y');
    try {
        $d = new DateTime($datetime);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return date('d/m/Y');
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. GENERAR XML EXACTO SEGÚN EL ESQUEMA VALIDADO DE FUNDAE
// ──────────────────────────────────────────────────────────────────────────────

$nombreAccion = $encuestas[0]['curso_nombre'] ?? 'encuesta';
$filenameBase = 'Encuestas_FUNDAE_'
    . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombreAccion)
    . '_' . date('Ymd_Hi');

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.xml"');

echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
echo '<cuestionarios>' . "\n";

foreach ($encuestas as $row) {
    $expediente  = !empty($row['grupo_expediente']) ? $row['grupo_expediente'] : (!empty($row['codigo_expediente']) ? $row['codigo_expediente'] : 'F250000AA');
    $numAccion   = (int)($row['num_accion'] ?? 1);
    if ($numAccion <= 0) $numAccion = 1;
    $numGrupo    = (int)($row['numero_grupo'] ?? 1);
    if ($numGrupo <= 0) $numGrupo = 1;

    $edad = xval($row['edad'], '40');
    $genero = mapSexo($row['sexo'] ?? $row['sexo_alumno']);

    $titulacion = xval($row['titulacion'], '3');
    $otraTitTxt = !empty($row['otra_titulacion_txt']) ? trim($row['otra_titulacion_txt']) : '99';

    $situacion = xval($row['situacion_laboral'], '2'); // 1=Desempleado, 2=Trabajador

    $provinciaVal = '99';
    if ($situacion == '1') {
        $provinciaVal = !empty($row['residencia_provincia']) ? trim($row['residencia_provincia']) : '99';
    } else {
        $provinciaVal = !empty($row['trabajo_provincia']) ? trim($row['trabajo_provincia']) : (!empty($row['residencia_provincia']) ? trim($row['residencia_provincia']) : '99');
    }

    $comoConocio = xval($row['como_conocio'], '5');
    $categoria = xval($row['categoria_profesional'], '4');
    $horario = xval($row['horario_curso'], '2');
    $jornadaPct = !empty($row['jornada_porcentaje']) ? xval($row['jornada_porcentaje'], null) : null;
    $tamanoEmpresa = xval($row['tamano_empresa'], '1');

    $modalidad = strtolower($row['modalidad'] ?? '');
    $esPresencial = strpos($modalidad, 'presencial') !== false;

    $p4_1_f = $row['p4_1_f'] ?? null;
    $p4_2_f = $row['p4_2_f'] ?? null;
    $p4_1_t = $row['p4_1_t'] ?? null;
    $p4_2_t = $row['p4_2_t'] ?? null;

    $fechaCumplim = formatFechaFundae($row['fecha_realizacion'] ?? null);

    echo "  <cuestionario>\n";
    echo "    <BloqueI>\n";
    echo "      <Expediente>" . xmlSafe($expediente) . "</Expediente>\n";
    echo "      <Num_accion>{$numAccion}</Num_accion>\n";
    echo "      <Num_grupo>{$numGrupo}</Num_grupo>\n";
    echo "    </BloqueI>\n";

    echo "    <BloqueII>\n";
    echo "      <II_1>" . xmlSafe($edad) . "</II_1>\n";
    echo "      <II_2>{$genero}</II_2>\n";
    echo "      <II_3>" . xmlSafe($titulacion) . "</II_3>\n";
    echo "      <II_31>\n";
    echo "        <II_31_1>1</II_31_1>\n";
    echo "        <otratitulacion>" . xmlSafe($otraTitTxt) . "</otratitulacion>\n";
    echo "      </II_31>\n";
    echo "      <II_4>" . xmlSafe($situacion) . "</II_4>\n";
    echo "      <II_5>\n";
    if ($situacion == '1') {
        echo "        <desempleado>" . xmlSafe($provinciaVal) . "</desempleado>\n";
    } else {
        echo "        <ocupado>" . xmlSafe($provinciaVal) . "</ocupado>\n";
    }
    echo "      </II_5>\n";
    echo "      <II_6>\n";
    echo "        <existencia>" . xmlSafe($comoConocio) . "</existencia>\n";
    if (!empty($row['como_conocio_txt'])) {
        echo "        <otra_existencia>" . xmlSafe(substr($row['como_conocio_txt'], 0, 1000)) . "</otra_existencia>\n";
    }
    echo "      </II_6>\n";
    echo "      <II_7>\n";
    echo "        <categoria>" . xmlSafe($categoria) . "</categoria>\n";
    if (!empty($row['categoria_profesional_txt'])) {
        echo "        <otra_categoria>" . xmlSafe(substr($row['categoria_profesional_txt'], 0, 1000)) . "</otra_categoria>\n";
    }
    echo "      </II_7>\n";
    echo "      <II_8>" . xmlSafe($horario) . "</II_8>\n";
    if ($jornadaPct !== null) {
        echo "      <II_8_1>" . xmlSafe($jornadaPct) . "</II_8_1>\n";
    }
    echo "      <II_9>" . xmlSafe($tamanoEmpresa) . "</II_9>\n";
    echo "    </BloqueII>\n";

    echo "    <BloqueIII>\n";
    echo "      <III_1_1>" . xmlSafe(xval($row['p1_1'], '4')) . "</III_1_1>\n";
    echo "      <III_1_2>" . xmlSafe(xval($row['p1_2'], '4')) . "</III_1_2>\n";
    echo "      <III_2_1>" . xmlSafe(xval($row['p2_1'], '4')) . "</III_2_1>\n";
    echo "      <III_2_2>" . xmlSafe(xval($row['p2_2'], '4')) . "</III_2_2>\n";
    echo "      <III_3_1>" . xmlSafe(xval($row['p3_1'], '4')) . "</III_3_1>\n";
    echo "      <III_3_2>" . xmlSafe(xval($row['p3_2'], '4')) . "</III_3_2>\n";

    echo "      <III_4_1>\n";
    if ($esPresencial && $p4_1_f !== null) {
        echo "        <III_4_1_Formador>" . xmlSafe($p4_1_f) . "</III_4_1_Formador>\n";
    } else {
        echo "        <III_4_1_Tutor>" . xmlSafe(xval($p4_1_t ?: $p4_1_f, '4')) . "</III_4_1_Tutor>\n";
    }
    echo "      </III_4_1>\n";

    echo "      <III_4_2>\n";
    if ($esPresencial && $p4_2_f !== null) {
        echo "        <III_4_2_Formador>" . xmlSafe($p4_2_f) . "</III_4_2_Formador>\n";
    } else {
        echo "        <III_4_2_Tutor>" . xmlSafe(xval($p4_2_t ?: $p4_2_f, '4')) . "</III_4_2_Tutor>\n";
    }
    echo "      </III_4_2>\n";

    echo "      <III_5_1>" . xmlSafe(xval($row['p5_1'], '4')) . "</III_5_1>\n";
    echo "      <III_5_2>" . xmlSafe(xval($row['p5_2'], '4')) . "</III_5_2>\n";

    if ($row['p6_1'] !== null) echo "      <III_6_1>" . xmlSafe($row['p6_1']) . "</III_6_1>\n";
    if ($row['p6_2'] !== null) echo "      <III_6_2>" . xmlSafe($row['p6_2']) . "</III_6_2>\n";

    if ($row['p7_1'] !== null || !$esPresencial) echo "      <III_7_1>" . xmlSafe(xval($row['p7_1'], '4')) . "</III_7_1>\n";
    if ($row['p7_2'] !== null || !$esPresencial) echo "      <III_7_2>" . xmlSafe(xval($row['p7_2'], '4')) . "</III_7_2>\n";

    $p8_1_val = (strtolower($row['p8_1'] ?? '') === 'no' || $row['p8_1'] === '0') ? '0' : '1';
    $p8_2_val = (strtolower($row['p8_2'] ?? '') === 'no' || $row['p8_2'] === '0') ? '0' : '1';

    echo "      <III_8_1>{$p8_1_val}</III_8_1>\n";
    echo "      <III_8_2>{$p8_2_val}</III_8_2>\n";

    echo "      <III_9_1>" . xmlSafe(xval($row['p9_1'], '4')) . "</III_9_1>\n";
    echo "      <III_9_2>" . xmlSafe(xval($row['p9_2'], '4')) . "</III_9_2>\n";
    echo "      <III_9_3>" . xmlSafe(xval($row['p9_3'], '4')) . "</III_9_3>\n";
    echo "      <III_9_4>" . xmlSafe(xval($row['p9_4'], '4')) . "</III_9_4>\n";
    echo "      <III_9_5>" . xmlSafe(xval($row['p9_5'], '4')) . "</III_9_5>\n";
    echo "      <III_10>"  . xmlSafe(xval($row['p10_1'], '4')) . "</III_10>\n";
    echo "      <III_10_1>99</III_10_1>\n";

    if (!empty($row['comentarios'])) {
        echo "      <III_11>" . xmlSafe(substr(trim($row['comentarios']), 0, 5000)) . "</III_11>\n";
    }

    if ($row['p12_1'] !== null) echo "      <III_12_1>" . xmlSafe($row['p12_1']) . "</III_12_1>\n";
    if ($row['p12_2'] !== null) echo "      <III_12_2>" . xmlSafe($row['p12_2']) . "</III_12_2>\n";
    if ($row['p12_3'] !== null) echo "      <III_12_3>" . xmlSafe($row['p12_3']) . "</III_12_3>\n";
    if ($row['p12_4'] !== null) echo "      <III_12_4>" . xmlSafe($row['p12_4']) . "</III_12_4>\n";
    if (!empty($row['p12_5']))  echo "      <III_12_5>" . xmlSafe(substr(trim($row['p12_5']), 0, 5000)) . "</III_12_5>\n";

    echo "    </BloqueIII>\n";
    echo "    <fecha_cumplimentacion>{$fechaCumplim}</fecha_cumplimentacion>\n";
    echo "  </cuestionario>\n";
}

echo '</cuestionarios>' . "\n";
exit;
