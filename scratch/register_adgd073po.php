<?php
// scratch/register_adgd073po.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Procesando Matriculación ADGD073PO - DIRECCIÓN POR OBJETIVOS Y GESTIÓN DEL DESEMPEÑO</h2>";

$accion_id = 38;
$grupo_id = 15;

// Obtener datos de la acción formativa
$stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
$stmtAF->execute([$accion_id]);
$af = $stmtAF->fetch(PDO::FETCH_ASSOC);

if (!$af) {
    die("Acción Formativa ID 38 no encontrada.");
}

$convocatoria_id = null;
if (!empty($af['plan_id'])) {
    $stmtP = $pdo->prepare("SELECT convocatoria_id FROM planes WHERE id = ?");
    $stmtP->execute([$af['plan_id']]);
    $convocatoria_id = $stmtP->fetchColumn();
}
if (!$convocatoria_id) {
    $convocatoria_id = $pdo->query("SELECT id FROM convocatorias ORDER BY id DESC LIMIT 1")->fetchColumn();
}

$matriculas_cols = $pdo->query("SHOW COLUMNS FROM matriculas")->fetchAll(PDO::FETCH_COLUMN);
$alumnos_cols = $pdo->query("SHOW COLUMNS FROM alumnos")->fetchAll(PDO::FETCH_COLUMN);

$moodle = new MoodleAPI($pdo);
$moodle_course_id = $af['id_plataforma'] ?? null;

$students = [
    [
        'nombre_completo' => 'TAMARA ISABEL AHUMADA RUIZ',
        'dni' => '75764209R',
        'fecha_nac' => '1982-03-12',
        'empresa_nombre' => 'SERVICIOS REUNIDOS EXTERNALIZACION SL',
        'cif' => 'B70330709',
        'localidad' => 'CADIZ',
        'provincia' => 'CÁDIZ'
    ],
    [
        'nombre_completo' => 'CRISTINA ARRATIA GARCIA',
        'dni' => '16605629C',
        'fecha_nac' => '1982-04-30',
        'empresa_nombre' => 'CRISTINA ARRATIA GARCIA',
        'cif' => '16605629C',
        'localidad' => 'LOGROÑO',
        'provincia' => 'RIOJA (LA)'
    ],
    [
        'nombre_completo' => 'MAYLING ESTHER BARROS SAMANIEGO',
        'dni' => 'Z1907877D',
        'fecha_nac' => '2000-11-23',
        'empresa_nombre' => 'VEXTER OUTSOURCING S.A.U',
        'cif' => 'A79492286',
        'localidad' => 'GIRONA',
        'provincia' => 'GIRONA'
    ],
    [
        'nombre_completo' => 'JOSE ANGEL CELEMIN POMARES',
        'dni' => '02206368R',
        'fecha_nac' => '1964-08-01',
        'empresa_nombre' => 'ILUNION SERVICIOS TI SA',
        'cif' => 'A79310819',
        'localidad' => 'MADRID',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'BLANCA DIAZ DEL VALLE',
        'dni' => '77871339D',
        'fecha_nac' => '2000-05-27',
        'empresa_nombre' => 'BLANCA DIAZ DEL VALLE',
        'cif' => '77871339D',
        'localidad' => 'PILAS',
        'provincia' => 'SEVILLA'
    ],
    [
        'nombre_completo' => 'IVET FONT PIQUE',
        'dni' => '48269972X',
        'fecha_nac' => '1995-08-30',
        'empresa_nombre' => 'CENTRE TECNOLOGIC DE TELECOMUNICACIONS DE CATALUNYA',
        'cif' => 'G62616586',
        'localidad' => 'TERRASSA',
        'provincia' => 'BARCELONA'
    ],
    [
        'nombre_completo' => 'CARLOS ALBERTO GARCIA GARCIA',
        'dni' => '03463069M',
        'fecha_nac' => '1973-10-13',
        'empresa_nombre' => 'SOFTTEK DIGITAL SOLUTIONS SL',
        'cif' => 'B83209015',
        'localidad' => 'MADRID',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'TERESA GARCIA PAZOS',
        'dni' => '46915920Z',
        'fecha_nac' => '1981-01-23',
        'empresa_nombre' => 'CIBERNOS BPO SL',
        'cif' => 'B78899853',
        'localidad' => 'A CORUÑA',
        'provincia' => 'A CORUÑA'
    ],
    [
        'nombre_completo' => 'LETICIA GONZALEZ SANTOS',
        'dni' => '71638757J',
        'fecha_nac' => '1981-06-16',
        'empresa_nombre' => 'MANCOMUNIDAD CINCOVILLAS',
        'cif' => 'P3300006H',
        'localidad' => 'OVIEDO',
        'provincia' => 'ASTURIAS'
    ],
    [
        'nombre_completo' => 'NOYLIN VANESSA GRAJAL RODRIGUEZ',
        'dni' => 'Y4810875W',
        'fecha_nac' => '1992-06-28',
        'empresa_nombre' => 'ENTEL IT CONSULTING S.A',
        'cif' => 'A83456202',
        'localidad' => 'MADRID',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'VIOLETA LORA HERNANDEZ',
        'dni' => '41504908M',
        'fecha_nac' => '1979-10-26',
        'empresa_nombre' => 'ASSOCIACIO LEADER ILLA DE MENORCA',
        'cif' => 'G57111833',
        'localidad' => 'MAHON',
        'provincia' => 'ILLES BALEARS'
    ],
    [
        'nombre_completo' => 'MONICA MARZO CUEVAS',
        'dni' => '29173152K',
        'fecha_nac' => '1969-09-22',
        'empresa_nombre' => 'TUV SUD IBERIA SA',
        'cif' => 'A81670614',
        'localidad' => 'VALENCIA',
        'provincia' => 'VALENCIA'
    ],
    [
        'nombre_completo' => 'JUAN LUIS MIRANDA PINEDA',
        'dni' => '42097075Z',
        'fecha_nac' => '1968-03-28',
        'empresa_nombre' => 'NOVOTEC CONSULTORES, S.A.',
        'cif' => 'A78068202',
        'localidad' => 'EL RIO DE ARICO',
        'provincia' => 'SANTA CRUZ DE TENERIFE'
    ],
    [
        'nombre_completo' => 'FRANCISCO MOLERO AGUERA',
        'dni' => '22961387G',
        'fecha_nac' => '1964-04-28',
        'empresa_nombre' => 'ANDANZA EMPLEA MURCIA',
        'cif' => 'B42898924',
        'localidad' => 'TORRE PACHECO',
        'provincia' => 'MURCIA'
    ],
    [
        'nombre_completo' => 'MANUEL MIGUEL MONEDO LOPEZ',
        'dni' => '01183604R',
        'fecha_nac' => '1977-08-25',
        'empresa_nombre' => 'PROCESIA PROYECTOS Y SERVICIO S.L.',
        'cif' => 'B87335071',
        'localidad' => 'MADRID',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'NEREA ROBAINA BELTRAN',
        'dni' => '48833393T',
        'fecha_nac' => '2001-11-09',
        'empresa_nombre' => 'NEREA ROBAINA BELTRAN',
        'cif' => '48833393T',
        'localidad' => 'YECLA',
        'provincia' => 'MURCIA'
    ],
    [
        'nombre_completo' => 'MIGUEL ANGEL RODRIGUEZ CASAPERALTA',
        'dni' => '80246424E',
        'fecha_nac' => '1975-07-11',
        'empresa_nombre' => 'EUROP ASSISTANCE S.I.G S.A',
        'cif' => 'A81098600',
        'localidad' => 'BADAJOZ',
        'provincia' => 'BADAJOZ'
    ],
    [
        'nombre_completo' => 'BEATRIZ RODRIGUEZ GARCIA',
        'dni' => '53557599Y',
        'fecha_nac' => '1985-09-07',
        'empresa_nombre' => 'UNISONO SOLUCIONES DE NEGOCIO S.A',
        'cif' => 'A82365412',
        'localidad' => 'GIJÓN',
        'provincia' => 'ASTURIAS'
    ],
    [
        'nombre_completo' => 'ROMAN URRUTIA RUIZ DE LA FUENTE',
        'dni' => '74676629E',
        'fecha_nac' => '1980-08-02',
        'empresa_nombre' => 'BABYDOG ARTE Y COMUNICACION S.L',
        'cif' => 'B18784587',
        'localidad' => 'LA ZUBIA',
        'provincia' => 'GRANADA'
    ]
];

function splitName($fullName) {
    $fullName = trim($fullName);
    // Custom overrides for multi-word names/surnames
    if ($fullName === 'TAMARA ISABEL AHUMADA RUIZ') return ['TAMARA ISABEL', 'AHUMADA', 'RUIZ'];
    if ($fullName === 'MAYLING ESTHER BARROS SAMANIEGO') return ['MAYLING ESTHER', 'BARROS', 'SAMANIEGO'];
    if ($fullName === 'JOSE ANGEL CELEMIN POMARES') return ['JOSE ANGEL', 'CELEMIN', 'POMARES'];
    if ($fullName === 'BLANCA DIAZ DEL VALLE') return ['BLANCA', 'DIAZ', 'DEL VALLE'];
    if ($fullName === 'CARLOS ALBERTO GARCIA GARCIA') return ['CARLOS ALBERTO', 'GARCIA', 'GARCIA'];
    if ($fullName === 'NOYLIN VANESSA GRAJAL RODRIGUEZ') return ['NOYLIN VANESSA', 'GRAJAL', 'RODRIGUEZ'];
    if ($fullName === 'JUAN LUIS MIRANDA PINEDA') return ['JUAN LUIS', 'MIRANDA', 'PINEDA'];
    if ($fullName === 'MANUEL MIGUEL MONEDO LOPEZ') return ['MANUEL MIGUEL', 'MONEDO', 'LOPEZ'];
    if ($fullName === 'MIGUEL ANGEL RODRIGUEZ CASAPERALTA') return ['MIGUEL ANGEL', 'RODRIGUEZ', 'CASAPERALTA'];
    if ($fullName === 'ROMAN URRUTIA RUIZ DE LA FUENTE') return ['ROMAN', 'URRUTIA', 'RUIZ DE LA FUENTE'];

    $parts = preg_split('/\s+/', $fullName);
    if (count($parts) >= 3) {
        $nombre = $parts[0];
        $p1 = $parts[1];
        $p2 = implode(' ', array_slice($parts, 2));
        return [$nombre, $p1, $p2];
    } elseif (count($parts) == 2) {
        return [$parts[0], $parts[1], ''];
    }
    return [$fullName, '—', ''];
}

$results = [];

foreach ($students as $st) {
    $dni = strtoupper(trim($st['dni']));
    $empresa_nombre = trim($st['empresa_nombre']);
    $cif = strtoupper(trim($st['cif']));
    $localidad = trim($st['localidad']);
    $provincia = trim($st['provincia']);
    $fecha_nac = $st['fecha_nac'];
    
    list($nombre, $primer_apellido, $segundo_apellido) = splitName($st['nombre_completo']);

    // 1. Empresa
    $empresa_id = null;
    if (!empty($empresa_nombre)) {
        $stmtEmp = $pdo->prepare("SELECT id FROM empresas WHERE (cif IS NOT NULL AND cif = ?) OR nombre = ? LIMIT 1");
        $stmtEmp->execute([$cif, $empresa_nombre]);
        $empresa_id = $stmtEmp->fetchColumn();

        if (!$empresa_id) {
            $stmtInsEmp = $pdo->prepare("INSERT INTO empresas (nombre, cif, localidad, provincia) VALUES (?, ?, ?, ?)");
            $stmtInsEmp->execute([$empresa_nombre, $cif ?: null, $localidad ?: null, $provincia ?: null]);
            $empresa_id = $pdo->lastInsertId();
        }
    }

    // 2. Alumno
    $alumno_id = null;
    $email = null;
    $stmtAl = $pdo->prepare("SELECT id, email FROM alumnos WHERE dni = ? LIMIT 1");
    $stmtAl->execute([$dni]);
    $alRow = $stmtAl->fetch(PDO::FETCH_ASSOC);

    if ($alRow) {
        $alumno_id = $alRow['id'];
        $email = $alRow['email'];
        $stmtUpdAl = $pdo->prepare("UPDATE alumnos SET 
                                        nombre = ?,
                                        primer_apellido = ?,
                                        segundo_apellido = ?,
                                        fecha_nacimiento = COALESCE(?, fecha_nacimiento),
                                        localidad = COALESCE(?, localidad),
                                        provincia = COALESCE(?, provincia),
                                        ultima_empresa_id = COALESCE(?, ultima_empresa_id)
                                    WHERE id = ?");
        $stmtUpdAl->execute([$nombre, $primer_apellido, $segundo_apellido, $fecha_nac, $localidad, $provincia, $empresa_id, $alumno_id]);
        $al_action = "Actualizado";
    } else {
        $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dni)) . '@alumnos.grupoefp.es';
        $stmtInsAl = $pdo->prepare("INSERT INTO alumnos (dni, nombre, primer_apellido, segundo_apellido, fecha_nacimiento, localidad, provincia, email, ultima_empresa_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInsAl->execute([
            $dni,
            $nombre,
            $primer_apellido,
            $segundo_apellido,
            $fecha_nac,
            $localidad,
            $provincia,
            $email,
            $empresa_id
        ]);
        $alumno_id = $pdo->lastInsertId();
        $al_action = "Creado";
    }

    // 3. Matrícula
    $stmtMat = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND grupo_id = ? LIMIT 1");
    $stmtMat->execute([$alumno_id, $grupo_id]);
    $mat_id = $stmtMat->fetchColumn();

    if (!$mat_id) {
        $mat_insert = [
            'alumno_id' => $alumno_id,
            'grupo_id' => $grupo_id,
            'convocatoria_id' => $convocatoria_id,
            'estado' => 'Inscrito',
            'fecha_matricula' => date('Y-m-d')
        ];
        $fields = [];
        $placeholders = [];
        $values = [];
        foreach ($mat_insert as $cName => $cVal) {
            if (in_array($cName, $matriculas_cols)) {
                $fields[] = "`$cName`";
                $placeholders[] = "?";
                $values[] = $cVal;
            }
        }
        $stmtInsMat = $pdo->prepare("INSERT INTO matriculas (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
        $stmtInsMat->execute($values);
        $mat_id = $pdo->lastInsertId();
        $mat_action = "Matriculado (#$mat_id)";
    } else {
        $mat_action = "Ya estaba matriculado (#$mat_id)";
    }

    // 4. Moodle sync si configurado
    $moodle_status = "No configurado";
    if ($moodle->isConfigured() && $moodle_course_id) {
        try {
            $u_username = strtolower(preg_replace('/[^a-z0-9_.-]/', '', $dni));
            $moodleUser = $moodle->getUsersByField('email', [$email]);
            $mUserId = null;
            if (!empty($moodleUser) && isset($moodleUser['users'][0]['id'])) {
                $mUserId = $moodleUser['users'][0]['id'];
            } else {
                $newMUser = $moodle->createUser(
                    $u_username,
                    'Alumno2026*',
                    $nombre,
                    trim("$primer_apellido $segundo_apellido") ?: 'Alumno',
                    $email
                );
                if (isset($newMUser[0]['id'])) {
                    $mUserId = $newMUser[0]['id'];
                }
            }
            if ($mUserId) {
                $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$mUserId, $alumno_id]);
                $moodle->enrolUser($mUserId, $moodle_course_id, 5);
                $moodle_status = "Moodle OK (#$mUserId)";
            }
        } catch (Exception $eM) {
            $moodle_status = "Error Moodle: " . $eM->getMessage();
        }
    }

    $results[] = [
        'nombre' => "$nombre $primer_apellido $segundo_apellido",
        'dni' => $dni,
        'empresa' => $empresa_nombre,
        'localidad' => "$localidad ($provincia)",
        'alumno_id' => $alumno_id,
        'al_action' => $al_action,
        'mat_action' => $mat_action,
        'moodle_status' => $moodle_status
    ];
}

echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; font-family:sans-serif;'>";
echo "<tr style='background:#f1f5f9;'><th>#</th><th>Alumno</th><th>NIF</th><th>Empresa</th><th>Localidad</th><th>Alumno Intranet</th><th>Estado Matrícula</th><th>Moodle</th></tr>";
foreach ($results as $idx => $r) {
    echo "<tr>";
    echo "<td>" . ($idx + 1) . "</td>";
    echo "<td><strong>" . htmlspecialchars($r['nombre']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($r['dni']) . "</td>";
    echo "<td>" . htmlspecialchars($r['empresa']) . "</td>";
    echo "<td>" . htmlspecialchars($r['localidad']) . "</td>";
    echo "<td>" . htmlspecialchars($r['al_action']) . " (ID " . $r['alumno_id'] . ")</td>";
    echo "<td style='color:green; font-weight:bold;'>" . htmlspecialchars($r['mat_action']) . "</td>";
    echo "<td>" . htmlspecialchars($r['moodle_status']) . "</td>";
    echo "</tr>";
}
echo "</table>";
