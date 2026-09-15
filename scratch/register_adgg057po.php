<?php
// scratch/register_adgg057po.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Procesando Matriculación ADGG057PO - Antonio Vico (Grupo ID 14)</h2>";

$accion_id = 45;
$grupo_id = 14;

try {
    // Obtener datos de la acción formativa
    $stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
    $stmtAF->execute([$accion_id]);
    $af = $stmtAF->fetch(PDO::FETCH_ASSOC);

    if (!$af) {
        die("Acción Formativa ID 45 no encontrada.");
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
            'nombre_completo' => 'CONSUELO ARIAS MENENDEZ',
            'dni' => '09413490G',
            'fecha_nac' => '1972-03-01',
            'empresa_nombre' => 'MEDICINA ASTURIANA S.A',
            'cif' => 'A33018292',
            'localidad' => 'OVIEDO',
            'provincia' => 'ASTURIAS'
        ],
        [
            'nombre_completo' => 'JUAN MARCOS DARDIÑA CUESTAS',
            'dni' => '72102937F',
            'fecha_nac' => '1967-09-01',
            'empresa_nombre' => 'AUTOMOTIVE MOULDING COMPANY ENGINEERING SL',
            'cif' => 'B39744164',
            'localidad' => 'SANTANDER',
            'provincia' => 'CANTABRIA'
        ],
        [
            'nombre_completo' => 'AIDA DE LUIS RODRIGUEZ',
            'dni' => '71898978N',
            'fecha_nac' => '1998-12-22',
            'empresa_nombre' => 'ASOC CULTURAL ABIERTO ASTURIAS',
            'cif' => 'G33677808',
            'localidad' => 'AVILES',
            'provincia' => 'ASTURIAS'
        ],
        [
            'nombre_completo' => 'SANTIAGO DOUEIHI MOHREZ',
            'dni' => '44841288M',
            'fecha_nac' => '1964-08-16',
            'empresa_nombre' => 'EMPRESA GOMEZ DE MESIA SL',
            'cif' => 'B15087745',
            'localidad' => 'SANTIAGO DE COMPOSTELA',
            'provincia' => 'A CORUÑA'
        ],
        [
            'nombre_completo' => 'NURIA FERNANDEZ FERNANDEZ',
            'dni' => '52410165L',
            'fecha_nac' => null,
            'empresa_nombre' => 'ASADOR RESTAURANTE LOS ARCOS S',
            'cif' => 'B37389103',
            'localidad' => 'SALAMANCA',
            'provincia' => 'SALAMANCA'
        ],
        [
            'nombre_completo' => 'KIMBERLY JOSEFINA GARCIA ESPINOSA',
            'dni' => 'Z2469236F',
            'fecha_nac' => '1996-07-30',
            'empresa_nombre' => 'EL CORTE INGLES',
            'cif' => 'A28017895',
            'localidad' => 'MADRID',
            'provincia' => 'MADRID'
        ],
        [
            'nombre_completo' => 'EVELYN YANINA GASPAR VALERO',
            'dni' => 'Y9680957H',
            'fecha_nac' => '1992-11-22',
            'empresa_nombre' => 'EVELYN YANINA GASPAR VALERO',
            'cif' => 'Y9680957H',
            'localidad' => 'SANTA COMBA',
            'provincia' => 'A CORUÑA'
        ],
        [
            'nombre_completo' => 'ERIKA AILUJ HERNANDEZ BLANCHAR',
            'dni' => '60597095F',
            'fecha_nac' => '1979-04-19',
            'empresa_nombre' => 'ARROYO FUSION S.L.',
            'cif' => 'B10825438',
            'localidad' => 'BENALMADENA',
            'provincia' => 'MÁLAGA'
        ],
        [
            'nombre_completo' => 'ALEJANDRO JIMENEZ ALVAREZ',
            'dni' => '20889288K',
            'fecha_nac' => '2002-08-15',
            'empresa_nombre' => 'ILUMEP PINOS PUENTE SL',
            'cif' => 'B18385724',
            'localidad' => 'PINOS PUENTE',
            'provincia' => 'GRANADA'
        ],
        [
            'nombre_completo' => 'JUAN FELIPE MARTIN LIZARRALDE',
            'dni' => 'Z4659471K',
            'fecha_nac' => '1996-06-30',
            'empresa_nombre' => 'LUNAFASHION TEXTIL S.L.',
            'cif' => 'B02714285',
            'localidad' => 'SEVILLA',
            'provincia' => 'SEVILLA'
        ],
        [
            'nombre_completo' => 'LORENA MORAS ISLA',
            'dni' => '70814451G',
            'fecha_nac' => '1983-01-03',
            'empresa_nombre' => 'ALTHENIA, S.L.',
            'cif' => 'B92445493',
            'localidad' => 'LEGANES',
            'provincia' => 'MADRID'
        ],
        [
            'nombre_completo' => 'FELIPE PINO ESPARRAGO',
            'dni' => '80066547M',
            'fecha_nac' => '1981-05-13',
            'empresa_nombre' => 'PARTIDO SOCIALISTA OBRERO ESPAÑOL',
            'cif' => 'G28477727',
            'localidad' => 'VALDELACALZADA',
            'provincia' => 'BADAJOZ'
        ],
        [
            'nombre_completo' => 'BRIAN RAMOS FALCON',
            'dni' => '54052194D',
            'fecha_nac' => '1991-09-27',
            'empresa_nombre' => 'METROPOLITANO DE TENERIFE S.A',
            'cif' => 'A38620209',
            'localidad' => 'TENERIFE',
            'provincia' => 'S/C TENERIFE'
        ],
        [
            'nombre_completo' => 'JULIO CESAR RODRIGUEZ VILLAFANA',
            'dni' => '49491888M',
            'fecha_nac' => '1976-08-22',
            'empresa_nombre' => 'MERCHANSERVIS SA',
            'cif' => 'A58648031',
            'localidad' => 'HOSPITALET',
            'provincia' => 'BARCELONA'
        ]
    ];

    function splitName($fullName) {
        $fullName = trim($fullName);
        if ($fullName === 'JUAN MARCOS DARDIÑA CUESTAS') return ['JUAN MARCOS', 'DARDIÑA', 'CUESTAS'];
        if ($fullName === 'AIDA DE LUIS RODRIGUEZ') return ['AIDA', 'DE LUIS', 'RODRIGUEZ'];
        if ($fullName === 'KIMBERLY JOSEFINA GARCIA ESPINOSA') return ['KIMBERLY JOSEFINA', 'GARCIA', 'ESPINOSA'];
        if ($fullName === 'EVELYN YANINA GASPAR VALERO') return ['EVELYN YANINA', 'GASPAR', 'VALERO'];
        if ($fullName === 'ERIKA AILUJ HERNANDEZ BLANCHAR') return ['ERIKA AILUJ', 'HERNANDEZ', 'BLANCHAR'];
        if ($fullName === 'JUAN FELIPE MARTIN LIZARRALDE') return ['JUAN FELIPE', 'MARTIN', 'LIZARRALDE'];
        if ($fullName === 'JULIO CESAR RODRIGUEZ VILLAFANA') return ['JULIO CESAR', 'RODRIGUEZ', 'VILLAFANA'];

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
        if (isset($moodle) && method_exists($moodle, 'isConfigured') && $moodle->isConfigured() && $moodle_course_id) {
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

} catch (Throwable $e) {
    echo "<h3 style='color:red;'>Error fatal: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
