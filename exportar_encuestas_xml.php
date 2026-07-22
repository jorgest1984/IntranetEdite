<?php
// exportar_encuestas_xml.php
// Generador XML FUNDAE de encuestas de satisfacción
// Esquema: XSD oficial FUNDAE (cuestionarios > cuestionario > BloqueI + BloqueII + BloqueIII)
//
// Parámetros GET:
//   accion_id  → exporta todas las encuestas de esa acción formativa
//   grupo_id   → exporta sólo las encuestas de ese grupo
//
// Conforme a: ISO 27001:2022

require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR])) {
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
    $whereClause = $grupo_id
        ? "m.grupo_id = ?"
        : "g.accion_id = ?";
    $paramValue = $grupo_id ?: $accion_id;

    $stmt = $pdo->prepare("
        SELECT
            er.*,
            m.id          AS matricula_id,
            g.numero_grupo,
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
        LEFT JOIN planes pl         ON af.plan_id = pl.id
        LEFT JOIN convocatorias co  ON pl.convocatoria_id = co.id
        WHERE $whereClause
        ORDER BY er.fecha_realizacion ASC
    ");
    $stmt->execute([$paramValue]);
    $encuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al consultar las encuestas: " . $e->getMessage());
}

if (empty($encuestas)) {
    die("No hay encuestas completadas para exportar con los parámetros indicados.");
}

// ──────────────────────────────────────────────────────────────────────────────
// 2. HELPERS DE CONVERSIÓN
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Devuelve el valor si no es null/vacío, o $default en caso contrario.
 */
function xval($value, $default = '99') {
    if ($value === null || $value === '') return $default;
    return (string)$value;
}

/**
 * Convierte el sexo almacenado en la DB al código FUNDAE.
 * DB almacena: 'H', 'Hombre', 'Varón', 'M', 'Mujer', etc.
 * XSD: 1=Mujer, 2=Varón
 */
function mapSexo($sexo) {
    if ($sexo === null || $sexo === '') return null; // elemento opcional
    $s = strtoupper(trim($sexo));
    // Si viene del campo de la encuesta (II_2 ya mapeado como 1 o 2)
    if ($s === '1') return '1';
    if ($s === '2') return '2';
    // Si viene del campo de alumno (H/M)
    if (in_array($s, ['M', 'MUJER', 'FEMENINO', 'F'])) return '1';
    if (in_array($s, ['H', 'HOMBRE', 'VARON', 'V', 'MASCULINO'])) return '2';
    return '99'; // No contesta
}

/**
 * Sanitiza texto para XML: elimina caracteres de control ilegales en XML 1.0.
 */
function xmlSafe($str) {
    if ($str === null) return '';
    // Eliminar chars de control ilegales en XML
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$str);
    return htmlspecialchars($str, ENT_XML1, 'UTF-8');
}

/**
 * Devuelve el valor o lo omite si es null (para elementos opcionales).
 * Retorna la cadena XML del elemento, o '' si no debe incluirse.
 */
function xmlOptional($tag, $value, $default = null) {
    if ($value === null || $value === '') {
        if ($default !== null) {
            return "<{$tag}>" . xmlSafe($default) . "</{$tag}>";
        }
        return '';
    }
    return "<{$tag}>" . xmlSafe($value) . "</{$tag}>";
}

/**
 * Formatea fecha al formato FUNDAE: dd/mm/yyyy
 */
function formatFechaFundae($datetime) {
    if (!$datetime) return date('d/m/Y');
    try {
        $d = new DateTime($datetime);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return date('d/m/Y');
    }
}

/**
 * Sanitiza el número de expediente FUNDAE (máx 9 caracteres).
 * Solo toma los dígitos del código o los últimos 9 chars.
 */
function sanitizeExpediente($codigo) {
    if (!$codigo) return '';
    $codigo = trim($codigo);
    // Si contiene solo números, lo dejamos como está
    if (ctype_digit($codigo)) return substr($codigo, 0, 9);
    // Si tiene formato tipo "2024/1234", tomar solo los dígitos relevantes
    $digits = preg_replace('/[^0-9]/', '', $codigo);
    return substr($digits ?: $codigo, 0, 9);
}

// ──────────────────────────────────────────────────────────────────────────────
// 3. GENERAR XML
// ──────────────────────────────────────────────────────────────────────────────

$nombreAccion = $encuestas[0]['curso_nombre'] ?? 'encuesta';
$filenameBase = 'Encuestas_FUNDAE_'
    . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombreAccion)
    . '_' . date('Ymd_Hi');

// Cabecera HTTP
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.xml"');

// Apertura del documento
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
echo '<cuestionarios>' . "\n";

foreach ($encuestas as $row) {

    // ── BLOQUE I: Identificación de la acción ──
    $expediente  = sanitizeExpediente($row['codigo_expediente'] ?? '');
    $numAccion   = (int)($row['num_accion'] ?? 0);
    $numGrupo    = (int)($row['numero_grupo'] ?? 1);

    // ── BLOQUE II: Datos sociológicos ──

    // II_1 - Edad
    $edad = xval($row['edad'], null);

    // II_2 - Género (prioridad: campo de la encuesta, fallback: campo del alumno)
    // En la DB, sexo de la encuesta puede valer '1','2' (si se pasó directo) o 'H','M','Hombre','Mujer'
    $sexoEnc = $row['sexo'] ?? null; // campo de encuesta
    $sexoAlumno = $row['sexo_alumno'] ?? null; // campo del alumno
    $genero = null;
    if ($sexoEnc !== null && $sexoEnc !== '') {
        $genero = mapSexo($sexoEnc);
    } elseif ($sexoAlumno !== null && $sexoAlumno !== '') {
        $genero = mapSexo($sexoAlumno);
    }

    // II_3 - Titulación (t_titulaciones: <titulacion>+<otra_titulacion>?)
    $titulacion     = xval($row['titulacion'], '99');
    $otraTitulacion = ($row['otra_titulacion'] == '1' || $row['otra_titulacion'] == 'SI') ? 1 : null;
    $otraTitulacionTxt = $row['otra_titulacion_txt'] ?? null;

    // II_4 - Situación laboral (t_seleccion3: 1-3|99)
    $situacion = xval($row['situacion_laboral'], '99');

    // II_5 - Residencia/Trabajo (ocupado o desempleado, t_provincia)
    // si situación=1 → ocupado → trabajo_provincia
    // si situación=2 → desempleado → residencia_provincia
    $provincia = null;
    $tipoResidencia = null;
    if ($situacion == '1') {
        // Ocupado: se informa la provincia de trabajo
        $tipoResidencia = 'ocupado';
        $provincia = strtoupper(trim($row['trabajo_provincia'] ?? ''));
    } elseif ($situacion == '2') {
        // Desempleado: se informa la provincia de residencia
        $tipoResidencia = 'desempleado';
        $provincia = strtoupper(trim($row['residencia_provincia'] ?? ''));
    } else {
        // Para otras situaciones, usamos residencia si existe
        if (!empty($row['residencia_provincia'])) {
            $tipoResidencia = 'ocupado';
            $provincia = strtoupper(trim($row['residencia_provincia']));
        }
    }
    // Si la provincia no coincide con los valores del XSD o está vacía, usar 99
    if (!$provincia) $tipoResidencia = null; // no incluir II_5

    // II_6 - Cómo conoció (t_existencia: <existencia>+<otra_existencia>?)
    $comoConocio    = xval($row['como_conocio'], '99');
    $comoConocioTxt = $row['como_conocio_txt'] ?? null;

    // II_7 - Categoría profesional (t_categoria: <categoria>+<otra_categoria>?)
    $categoria    = xval($row['categoria_profesional'], null);
    $categoriaTxt = $row['categoria_profesional_txt'] ?? null;

    // II_8 - Horario del curso (t_seleccion3)
    $horarioCurso = xval($row['horario_curso'], null);

    // II_8_1 - Jornada porcentaje (t_seleccion3)
    $jornadaPct = xval($row['jornada_porcentaje'], null);

    // II_9 - Tamaño empresa (t_seleccion5_unica: 1-5|99)
    $tamanoEmpresa = xval($row['tamano_empresa'], null);

    // ── BLOQUE III: Valoraciones ──

    // III_4_1 / III_4_2: Formador y/o Tutor (choice, maxOccurs=2)
    // Si modalidad presencial → Formador; Si teleformación → Tutor; Si mixta → ambos
    $modalidad = strtolower($row['modalidad'] ?? '');
    $esTele     = strpos($modalidad, 'telef') !== false || strpos($modalidad, 'online') !== false || strpos($modalidad, 'distancia') !== false;
    $esPres     = strpos($modalidad, 'presencial') !== false;
    $esMixta    = strpos($modalidad, 'mixta') !== false || strpos($modalidad, 'blended') !== false;

    $p4_1_f = xval($row['p4_1_f'], null);
    $p4_2_f = xval($row['p4_2_f'], null);
    $p4_1_t = xval($row['p4_1_t'], null);
    $p4_2_t = xval($row['p4_2_t'], null);

    // Determinar qué sub-elementos incluir en III_4_1 y III_4_2
    // Regla: si tiene valor → incluirlo; prioridad al que tiene datos
    $incFormador4_1 = ($p4_1_f !== null && $p4_1_f !== '');
    $incTutor4_1    = ($p4_1_t !== null && $p4_1_t !== '');
    $incFormador4_2 = ($p4_2_f !== null && $p4_2_f !== '');
    $incTutor4_2    = ($p4_2_t !== null && $p4_2_t !== '');

    // Si no hay ninguno específico, usar fallback por modalidad
    if (!$incFormador4_1 && !$incTutor4_1) {
        if ($esPres) { $p4_1_f = '99'; $incFormador4_1 = true; }
        else         { $p4_1_t = '99'; $incTutor4_1    = true; }
    }
    if (!$incFormador4_2 && !$incTutor4_2) {
        if ($esPres) { $p4_2_f = '99'; $incFormador4_2 = true; }
        else         { $p4_2_t = '99'; $incTutor4_2    = true; }
    }

    // Fecha de cumplimentación
    $fechaCumplim = formatFechaFundae($row['fecha_realizacion'] ?? null);

    // ── CONSTRUIR XML DEL CUESTIONARIO ──
    echo "  <cuestionario>\n";

    // BloqueI
    echo "    <BloqueI>\n";
    echo "      <Expediente>" . xmlSafe($expediente) . "</Expediente>\n";
    echo "      <Num_accion>{$numAccion}</Num_accion>\n";
    echo "      <Num_grupo>{$numGrupo}</Num_grupo>\n";
    echo "    </BloqueI>\n";

    // BloqueII
    echo "    <BloqueII>\n";
    if ($edad !== null)   echo "      <II_1>" . xmlSafe($edad) . "</II_1>\n";
    if ($genero !== null) echo "      <II_2>{$genero}</II_2>\n";
    echo "      <II_3>\n";
    echo "        <titulacion>" . xmlSafe($titulacion) . "</titulacion>\n";
    if ($otraTitulacion && $otraTitulacionTxt)
        echo "        <otra_titulacion>" . xmlSafe(substr($otraTitulacionTxt, 0, 1000)) . "</otra_titulacion>\n";
    echo "      </II_3>\n";
    echo "      <II_4>" . xmlSafe($situacion) . "</II_4>\n";
    if ($tipoResidencia && $provincia) {
        echo "      <II_5>\n";
        echo "        <{$tipoResidencia}>" . xmlSafe($provincia) . "</{$tipoResidencia}>\n";
        echo "      </II_5>\n";
    }
    echo "      <II_6>\n";
    echo "        <existencia>" . xmlSafe($comoConocio) . "</existencia>\n";
    if ($comoConocioTxt)
        echo "        <otra_existencia>" . xmlSafe(substr($comoConocioTxt, 0, 1000)) . "</otra_existencia>\n";
    echo "      </II_6>\n";
    if ($categoria !== null) {
        echo "      <II_7>\n";
        echo "        <categoria>" . xmlSafe($categoria) . "</categoria>\n";
        if ($categoriaTxt)
            echo "        <otra_categoria>" . xmlSafe(substr($categoriaTxt, 0, 1000)) . "</otra_categoria>\n";
        echo "      </II_7>\n";
    }
    if ($horarioCurso !== null) echo "      <II_8>" . xmlSafe($horarioCurso) . "</II_8>\n";
    if ($jornadaPct   !== null) echo "      <II_8_1>" . xmlSafe($jornadaPct) . "</II_8_1>\n";
    if ($tamanoEmpresa !== null) echo "      <II_9>" . xmlSafe($tamanoEmpresa) . "</II_9>\n";
    echo "    </BloqueII>\n";

    // BloqueIII
    echo "    <BloqueIII>\n";
    echo "      <III_1_1>" . xmlSafe(xval($row['p1_1'], '99')) . "</III_1_1>\n";
    echo "      <III_1_2>" . xmlSafe(xval($row['p1_2'], '99')) . "</III_1_2>\n";
    echo "      <III_2_1>" . xmlSafe(xval($row['p2_1'], '99')) . "</III_2_1>\n";
    echo "      <III_2_2>" . xmlSafe(xval($row['p2_2'], '99')) . "</III_2_2>\n";
    echo "      <III_3_1>" . xmlSafe(xval($row['p3_1'], '99')) . "</III_3_1>\n";
    echo "      <III_3_2>" . xmlSafe(xval($row['p3_2'], '99')) . "</III_3_2>\n";

    // III_4_1 (Formador y/o Tutor)
    echo "      <III_4_1>\n";
    if ($incFormador4_1) echo "        <III_4_1_Formador>" . xmlSafe(xval($p4_1_f, '99')) . "</III_4_1_Formador>\n";
    if ($incTutor4_1)   echo "        <III_4_1_Tutor>"    . xmlSafe(xval($p4_1_t, '99')) . "</III_4_1_Tutor>\n";
    echo "      </III_4_1>\n";

    // III_4_2 (Formador y/o Tutor)
    echo "      <III_4_2>\n";
    if ($incFormador4_2) echo "        <III_4_2_Formador>" . xmlSafe(xval($p4_2_f, '99')) . "</III_4_2_Formador>\n";
    if ($incTutor4_2)    echo "        <III_4_2_Tutor>"    . xmlSafe(xval($p4_2_t, '99')) . "</III_4_2_Tutor>\n";
    echo "      </III_4_2>\n";

    echo "      <III_5_1>" . xmlSafe(xval($row['p5_1'], '99')) . "</III_5_1>\n";
    echo "      <III_5_2>" . xmlSafe(xval($row['p5_2'], '99')) . "</III_5_2>\n";

    // III_6 y III_7 son opcionales (minOccurs=0)
    if ($row['p6_1'] !== null) echo "      <III_6_1>" . xmlSafe($row['p6_1']) . "</III_6_1>\n";
    if ($row['p6_2'] !== null) echo "      <III_6_2>" . xmlSafe($row['p6_2']) . "</III_6_2>\n";
    if ($row['p7_1'] !== null) echo "      <III_7_1>" . xmlSafe($row['p7_1']) . "</III_7_1>\n";
    if ($row['p7_2'] !== null) echo "      <III_7_2>" . xmlSafe($row['p7_2']) . "</III_7_2>\n";

    // III_8_1 / III_8_2 — t_sino (0=No, 1=Si, 99=NC)
    echo "      <III_8_1>" . xmlSafe(xval($row['p8_1'], '99')) . "</III_8_1>\n";
    echo "      <III_8_2>" . xmlSafe(xval($row['p8_2'], '99')) . "</III_8_2>\n";

    echo "      <III_9_1>" . xmlSafe(xval($row['p9_1'], '99')) . "</III_9_1>\n";
    echo "      <III_9_2>" . xmlSafe(xval($row['p9_2'], '99')) . "</III_9_2>\n";
    echo "      <III_9_3>" . xmlSafe(xval($row['p9_3'], '99')) . "</III_9_3>\n";
    echo "      <III_9_4>" . xmlSafe(xval($row['p9_4'], '99')) . "</III_9_4>\n";
    echo "      <III_9_5>" . xmlSafe(xval($row['p9_5'], '99')) . "</III_9_5>\n";
    echo "      <III_10>"  . xmlSafe(xval($row['p10_1'], '99')) . "</III_10>\n";

    // III_11 - comentarios (opcional, max 5000 chars)
    if (!empty($row['comentarios'])) {
        echo "      <III_11>" . xmlSafe(substr($row['comentarios'], 0, 5000)) . "</III_11>\n";
    }

    // III_12 - Prácticas (todos opcionales)
    if ($row['p12_1'] !== null) echo "      <III_12_1>" . xmlSafe($row['p12_1']) . "</III_12_1>\n";
    if ($row['p12_2'] !== null) echo "      <III_12_2>" . xmlSafe($row['p12_2']) . "</III_12_2>\n";
    if ($row['p12_3'] !== null) echo "      <III_12_3>" . xmlSafe($row['p12_3']) . "</III_12_3>\n";
    if ($row['p12_4'] !== null) echo "      <III_12_4>" . xmlSafe($row['p12_4']) . "</III_12_4>\n";
    if (!empty($row['p12_5']))  echo "      <III_12_5>" . xmlSafe(substr($row['p12_5'], 0, 5000)) . "</III_12_5>\n";

    echo "    </BloqueIII>\n";

    // Fecha de cumplimentación (opcional)
    echo "    <fecha_cumplimentacion>{$fechaCumplim}</fecha_cumplimentacion>\n";

    echo "  </cuestionario>\n";
}

echo '</cuestionarios>' . "\n";
exit;
