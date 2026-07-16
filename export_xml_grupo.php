<?php
// export_xml_grupo.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_ADMINISTRATIVO])) {
    die("Acceso denegado.");
}

$grupo_id = intval($_GET['grupo_id'] ?? 0);
$accion_id = intval($_GET['accion_id'] ?? 0);

if (!$grupo_id || !$accion_id) {
    die("Faltan parámetros.");
}

// 1. Obtener datos básicos
$stmt = $pdo->prepare("SELECT g.fecha_inicio, g.fecha_fin, g.numero_grupo, 
                              af.num_accion, af.titulo as denominacion, 
                              c.codigo_expediente, 
                              u.nif as tutor_nif, u.nombre as tutor_nombre, u.apellidos as tutor_apellidos, u.email as tutor_email, u.telefono as tutor_telefono
                       FROM grupos g
                       JOIN acciones_formativas af ON g.accion_id = af.id
                       LEFT JOIN planes p ON af.plan_id = p.id
                       LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
                       LEFT JOIN usuarios u ON g.tutor_id = u.id
                       WHERE g.id = ? AND af.id = ?");
$stmt->execute([$grupo_id, $accion_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Grupo no encontrado.");
}

// Configurar el documento XML
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$root = $dom->createElement('grupos');
$dom->appendChild($root);

$grupo = $dom->createElement('grupo');
$root->appendChild($grupo);

// Datos básicos del grupo
$grupo->appendChild($dom->createElement('expediente', htmlspecialchars($data['codigo_expediente'] ?? '')));
$grupo->appendChild($dom->createElement('num_accion', htmlspecialchars($data['num_accion'] ?? '')));
$grupo->appendChild($dom->createElement('num_grupo', htmlspecialchars($data['numero_grupo'] ?? '')));
$grupo->appendChild($dom->createElement('denominacion', htmlspecialchars($data['denominacion'] ?? '')));

$f_inicio = date('d/m/Y', strtotime($data['fecha_inicio']));
$f_fin = date('d/m/Y', strtotime($data['fecha_fin']));

$grupo->appendChild($dom->createElement('fecha_inicio', $f_inicio));
$grupo->appendChild($dom->createElement('fecha_fin', $f_fin));
$grupo->appendChild($dom->createElement('solicitud_servicios_empleo_candidatos_desemp', '0'));
$grupo->appendChild($dom->createElement('comprobacion_requisitos', '1'));

// Centros
$centros = $dom->createElement('centros');
$grupo->appendChild($centros);

$centro = $dom->createElement('centro');
$centros->appendChild($centro);

$centro->appendChild($dom->createElement('tipo_formacion', '4'));
$centro->appendChild($dom->createElement('dias_imparticion', '1111100'));

// Horarios
$horarios = $dom->createElement('horarios');
$centro->appendChild($horarios);

// Calcular fechas de horario
$start = new DateTime($data['fecha_inicio']);
$end = new DateTime($data['fecha_fin']);
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end->modify('+1 day'));

$dias_lectivos = [];
foreach ($period as $date) {
    // 1 = Monday, 7 = Sunday. Ignoramos 6 y 7
    if ($date->format('N') < 6) {
        $dias_lectivos[] = $date->format('d/m/Y');
    }
}

$total_dias = count($dias_lectivos);

foreach ($dias_lectivos as $index => $fecha) {
    $horario = $dom->createElement('horario');
    $horario->appendChild($dom->createElement('fecha_imparticion', $fecha));
    
    // Todos a las 17:00
    $horario->appendChild($dom->createElement('horario_tarde_desde', '17:00'));
    
    // Si es el último día, cuadrar a 17:30, el resto 18:30
    if ($index === $total_dias - 1) {
        $horario->appendChild($dom->createElement('horario_tarde_hasta', '17:30'));
    } else {
        $horario->appendChild($dom->createElement('horario_tarde_hasta', '18:30'));
    }
    
    $horarios->appendChild($horario);
}

// Datos de contacto fijos
$centro->appendChild($dom->createElement('persona_contacto', 'Enrique Cirera Salas'));
$centro->appendChild($dom->createElement('telefono_contacto', '958089725'));
$centro->appendChild($dom->createElement('email_contacto', 'edite@editeformacion.com'));

// Lugar de imparticion
$lugar = $dom->createElement('lugar_imparticion');
$lugar->appendChild($dom->createElement('cif', 'B18579953'));
$lugar->appendChild($dom->createElement('razon_social', 'MARSDIGITAL, S.L.'));
$lugar->appendChild($dom->createElement('direccion', 'Calle Benjamin Franklin nº1 2ª Planta'));
$lugar->appendChild($dom->createElement('codigo_postal', '18100'));
$lugar->appendChild($dom->createElement('poblacion', 'Armilla'));
$lugar->appendChild($dom->createElement('aclaracion', ''));
$centro->appendChild($lugar);

// Centro gestor
$gestor = $dom->createElement('centro_gestor_teleformacion');
$gestor->appendChild($dom->createElement('url', 'https://www.escueladeformacionprofesional.es'));
$gestor->appendChild($dom->createElement('usuario', 'e24027g2'));
$gestor->appendChild($dom->createElement('clave', 'I2W4nmwMd5'));
$gestor->appendChild($dom->createElement('aclaracion_conexion', ''));
$centro->appendChild($gestor);

// Formador
$formador = $dom->createElement('formador');
$formador->appendChild($dom->createElement('idTipoDocumento', '1'));
$formador->appendChild($dom->createElement('nif', htmlspecialchars($data['tutor_nif'] ?? '')));

// Separar nombre y primer apellido si fuera necesario. En BBDD tenemos nombre y apellidos unidos o separados.
// Supongamos nombre = tutor_nombre, y el apellido 1 es la primera palabra de tutor_apellidos.
$apellidos_arr = explode(' ', $data['tutor_apellidos'] ?? '');
$apellido1 = array_shift($apellidos_arr);
$apellido2 = implode(' ', $apellidos_arr);

$formador->appendChild($dom->createElement('nombre', htmlspecialchars($data['tutor_nombre'] ?? '')));
$formador->appendChild($dom->createElement('apellido1', htmlspecialchars($apellido1 ?? '')));
$formador->appendChild($dom->createElement('apellido2', htmlspecialchars($apellido2 ?? '')));
$formador->appendChild($dom->createElement('email', htmlspecialchars($data['tutor_email'] ?? '')));
$formador->appendChild($dom->createElement('telefono', htmlspecialchars($data['tutor_telefono'] ?? '')));
$formador->appendChild($dom->createElement('horasDisponibles', '20'));
$centro->appendChild($formador);

// Generar respuesta
$xmlContent = $dom->saveXML();

header('Content-Type: text/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="grupo_' . $data['numero_grupo'] . '.xml"');
header('Content-Length: ' . strlen($xmlContent));

echo $xmlContent;
exit;
