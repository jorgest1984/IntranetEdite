<?php
// matricular_alumnos_tiktok.php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

echo "=== MATRICULACIÓN AUTOMÁTICA DE ALUMNOS CTRD0016 ===\n";

$alumnos_data = [
    [
        'nombre_completo' => 'ANGEL ALBERTO AGUILERA JIMENEZ',
        'dni' => '15474740H',
        'fecha_nac' => '1989-02-10',
        'empresa' => 'AGUILERA JIMENEZ ANGEL ALBERTO',
        'cif' => '15474740H',
        'localidad' => 'ALMUÑECAR',
        'provincia' => 'GRANADA'
    ],
    [
        'nombre_completo' => 'JUAN CRISTOBAL ALVAREZ CEBRIAN',
        'dni' => '52746478A',
        'fecha_nac' => '1968-07-11',
        'empresa' => 'LUMER CREATIVE STUDIO S.L.',
        'cif' => 'B23864903',
        'localidad' => 'PICASSENT',
        'provincia' => 'VALENCIA'
    ],
    [
        'nombre_completo' => 'ADELA CANTALLOPS JERONIMO',
        'dni' => '43126411P',
        'fecha_nac' => '1975-12-23',
        'empresa' => 'ADELA CANTALLOPS JERONIMO',
        'cif' => '43126411P',
        'localidad' => 'INCA',
        'provincia' => 'ILLES BALEARS'
    ],
    [
        'nombre_completo' => 'DANIEL FERNANDEZ JUTZ',
        'dni' => '33566259J',
        'fecha_nac' => '1978-04-01',
        'empresa' => 'DANIEL FERNANDEZ JUTZ',
        'cif' => '33566259J',
        'localidad' => 'DENIA',
        'provincia' => 'ALICANTE'
    ],
    [
        'nombre_completo' => 'IRENE FUENTES GONZALEZ',
        'dni' => '53051042R',
        'fecha_nac' => '1975-02-17',
        'empresa' => 'CIUDAD DE LAS ARTES Y DE LAS CIENCIAS',
        'cif' => 'A46483095',
        'localidad' => 'VALENCIA',
        'provincia' => 'VALENCIA'
    ],
    [
        'nombre_completo' => 'ALICIA GALVEZ GARCIA',
        'dni' => '48684041X',
        'fecha_nac' => '1998-08-29',
        'empresa' => 'AVATEL TELECOM SA',
        'cif' => 'A93135218',
        'localidad' => 'ORIHUELA',
        'provincia' => 'ALICANTE'
    ],
    [
        'nombre_completo' => 'LAURA HEVIA BRAGA',
        'dni' => '71776224D',
        'fecha_nac' => '1989-12-21',
        'empresa' => 'LAURA HEVIA BRAGA',
        'cif' => '71776224D',
        'localidad' => 'POLA DE LENA',
        'provincia' => 'ASTURIAS'
    ],
    [
        'nombre_completo' => 'ENRIQUE ALFREDO MARTIN PARODI',
        'dni' => 'Z0044851Y',
        'fecha_nac' => '1969-05-12',
        'empresa' => 'DALE U MADRID S.L.',
        'cif' => 'B70691803',
        'localidad' => 'LAS ROZAS',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'INSAF MOHAMED AZOUAGH',
        'dni' => '45315783X',
        'fecha_nac' => '1999-02-24',
        'empresa' => 'INSAF MOHAMED AZOUAGH',
        'cif' => '45315783X',
        'localidad' => 'MELILLA',
        'provincia' => 'MELILLA'
    ],
    [
        'nombre_completo' => 'ANA JOSE OLCOZ SESMA',
        'dni' => '18203637B',
        'fecha_nac' => '1966-02-23',
        'empresa' => 'ANA JOSE OLCOZ SESMA',
        'cif' => '18203637B',
        'localidad' => 'TAFALLA',
        'provincia' => 'NAVARRA'
    ],
    [
        'nombre_completo' => 'MIGUEL ANGEL ORTELLS MORENO',
        'dni' => '22695340K',
        'fecha_nac' => '1966-06-03',
        'empresa' => 'MIGUEL ANGEL ORTELLS',
        'cif' => '22695340K',
        'localidad' => 'VALENCIA',
        'provincia' => 'VALENCIA'
    ],
    [
        'nombre_completo' => 'GONZALO PEREZ ZUNZUNEGUI',
        'dni' => '30683105R',
        'fecha_nac' => '1974-11-30',
        'empresa' => 'EDICIONES IZORIA 2004 S.L',
        'cif' => 'B1373489',
        'localidad' => 'MIRANDA DE EBRO',
        'provincia' => 'BURGOS'
    ],
    [
        'nombre_completo' => 'JAVIER PRADOS HIPOLITO',
        'dni' => '05926565V',
        'fecha_nac' => '1982-03-28',
        'empresa' => 'B3MEDIA SERVICIOS AUDIOVISUALES SL',
        'cif' => 'B21735691',
        'localidad' => 'SAN SEBASTIAN DE LOS REYES',
        'provincia' => 'MADRID'
    ],
    [
        'nombre_completo' => 'MARIA LUISA RUBIO MARTINEZ',
        'dni' => '44375137H',
        'fecha_nac' => '1971-06-04',
        'empresa' => 'MARIA LUISA RUBIO MARTINEZ',
        'cif' => '44375137H',
        'localidad' => 'ALBACETE',
        'provincia' => 'ALBACETE'
    ],
    [
        'nombre_completo' => 'ESTEFANIA SANCHEZ LOPEZ',
        'dni' => '32736760D',
        'fecha_nac' => '1999-04-07',
        'empresa' => 'MULTICOPIAS GALICIA, SL',
        'cif' => 'B15506561',
        'localidad' => 'VALDOVIÑO',
        'provincia' => 'A CORUÑA'
    ],
    [
        'nombre_completo' => 'ANDREW THOMPSON',
        'dni' => 'X4550143F',
        'fecha_nac' => '1977-10-16',
        'empresa' => 'ANDREW THOMPSON',
        'cif' => 'X4550143F',
        'localidad' => 'SANT BOI DE LLOBREGAT',
        'provincia' => 'BARCELONA'
    ]
];

try {
    // 1. Buscar o crear la Acción Formativa CTRD0016
    $stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE abreviatura LIKE '%CTRD0016%' OR num_accion LIKE '%CTRD0016%' OR titulo LIKE '%TIK TOK%' LIMIT 1");
    $stmtAF->execute();
    $af = $stmtAF->fetch(PDO::FETCH_ASSOC);

    if (!$af) {
        echo "Acción formativa no encontrada. Buscando curso CTRD0016...\n";
        $stmtC = $pdo->prepare("SELECT * FROM cursos WHERE nombre_corto LIKE '%CTRD0016%' OR nombre_largo LIKE '%TIK TOK%' LIMIT 1");
        $stmtC->execute();
        $curso = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        $curso_id = $curso['id'] ?? null;
        if (!$curso_id) {
            $stmtInsC = $pdo->prepare("INSERT INTO cursos (nombre_corto, nombre_largo, modalidad, duracion, created_at) VALUES ('CTRD0016', 'TIK TOK PARA EMPRESAS', 'TELEFORMACIÓN', 60, NOW())");
            $stmtInsC->execute();
            $curso_id = $pdo->lastInsertId();
        }

        // Crear Acción Formativa
        $stmtInsAF = $pdo->prepare("INSERT INTO acciones_formativas (curso_id, titulo, abreviatura, num_accion, modalidad, duracion, created_at) VALUES (?, 'TIK TOK PARA EMPRESAS', 'CTRD0016', 'CTRD0016', 'TELEFORMACIÓN', 60, NOW())");
        $stmtInsAF->execute([$curso_id]);
        $af_id = $pdo->lastInsertId();
        
        $stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
        $stmtAF->execute([$af_id]);
        $af = $stmtAF->fetch(PDO::FETCH_ASSOC);
    }

    $af_id = $af['id'];
    echo "Acción Formativa encontrada/creada ID: $af_id ({$af['titulo']})\n";

    // 2. Buscar o crear Grupo para esta Acción Formativa
    $stmtG = $pdo->prepare("SELECT * FROM grupos WHERE accion_id = ? LIMIT 1");
    $stmtG->execute([$af_id]);
    $grupo = $stmtG->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        $stmtInsG = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, fecha_inicio, fecha_fin, estado, created_at) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'En curso', NOW())");
        $stmtInsG->execute([$af_id]);
        $grupo_id = $pdo->lastInsertId();
    } else {
        $grupo_id = $grupo['id'];
    }
    echo "Grupo asignado ID: $grupo_id\n";

    // 3. Procesar cada alumno
    $moodle = new MoodleAPI($pdo);
    $moodle_configured = $moodle->isConfigured();
    $moodle_course_id = $af['id_plataforma'] ?? null;

    $matriculados_count = 0;

    foreach ($alumnos_data as $row) {
        // Separar nombre y apellidos
        $parts = preg_split('/\s+/', trim($row['nombre_completo']));
        if (count($parts) >= 3) {
            $nombre = $parts[0] . ' ' . $parts[1];
            $primer_apellido = $parts[2];
            $segundo_apellido = isset($parts[3]) ? implode(' ', array_slice($parts, 3)) : '';
            // Si el nombre tiene 3 partes (ej: ANGEL ALBERTO AGUILERA JIMENEZ) -> Nombre: ANGEL ALBERTO, Apellidos: AGUILERA JIMENEZ
            if (count($parts) == 4) {
                $nombre = $parts[0] . ' ' . $parts[1];
                $primer_apellido = $parts[2];
                $segundo_apellido = $parts[3];
            }
        } elseif (count($parts) == 2) {
            $nombre = $parts[0];
            $primer_apellido = $parts[1];
            $segundo_apellido = '';
        } else {
            $nombre = $parts[0];
            $primer_apellido = '—';
            $segundo_apellido = '';
        }

        // Buscar/Crear Empresa
        $empresa_id = null;
        if (!empty($row['empresa'])) {
            $stmtEmp = $pdo->prepare("SELECT id FROM empresas WHERE nombre = ? OR (cif IS NOT NULL AND cif = ?) LIMIT 1");
            $stmtEmp->execute([$row['empresa'], $row['cif']]);
            $empresa_id = $stmtEmp->fetchColumn();

            if (!$empresa_id) {
                $stmtInsEmp = $pdo->prepare("INSERT INTO empresas (nombre, cif, localidad, provincia, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmtInsEmp->execute([$row['empresa'], $row['cif'], $row['localidad'], $row['provincia']]);
                $empresa_id = $pdo->lastInsertId();
            }
        }

        // Buscar/Crear Alumno
        $dni = $row['dni'];
        $stmtAl = $pdo->prepare("SELECT * FROM alumnos WHERE dni = ? LIMIT 1");
        $stmtAl->execute([$dni]);
        $alumno = $stmtAl->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) {
            $email_generado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dni)) . '@alumnos.grupoefp.es';
            $stmtInsAl = $pdo->prepare("INSERT INTO alumnos (dni, nombre, primer_apellido, segundo_apellido, fecha_nacimiento, localidad, provincia, ultima_empresa_id, email, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmtInsAl->execute([
                $dni,
                $nombre,
                $primer_apellido,
                $segundo_apellido,
                $row['fecha_nac'],
                $row['localidad'],
                $row['provincia'],
                $empresa_id,
                $email_generado
            ]);
            $alumno_id = $pdo->lastInsertId();
        } else {
            $alumno_id = $alumno['id'];
            // Actualizar datos
            $stmtUpdAl = $pdo->prepare("UPDATE alumnos SET fecha_nacimiento = ?, localidad = ?, provincia = ?, ultima_empresa_id = ? WHERE id = ?");
            $stmtUpdAl->execute([$row['fecha_nac'], $row['localidad'], $row['provincia'], $empresa_id, $alumno_id]);
        }

        // Crear o actualizar Matrícula en el Grupo
        $stmtMat = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND grupo_id = ? LIMIT 1");
        $stmtMat->execute([$alumno_id, $grupo_id]);
        $mat_id = $stmtMat->fetchColumn();

        if (!$mat_id) {
            $stmtInsMat = $pdo->prepare("INSERT INTO matriculas (alumno_id, grupo_id, estado, created_at) VALUES (?, ?, 'Admitido', NOW())");
            $stmtInsMat->execute([$alumno_id, $grupo_id]);
            $mat_id = $pdo->lastInsertId();
            echo "✅ Alumno matriculado: {$row['nombre_completo']} (ID: $alumno_id, Matrícula: $mat_id)\n";
        } else {
            $stmtUpdMat = $pdo->prepare("UPDATE matriculas SET estado = 'Admitido' WHERE id = ?");
            $stmtUpdMat->execute([$mat_id]);
            echo "ℹ️ Matrícula existente actualizada a Admitido: {$row['nombre_completo']} (ID Matrícula: $mat_id)\n";
        }

        $matriculados_count++;
    }

    echo "\n=== PROCESO COMPLETADO EXITOSAMENTE: $matriculados_count ALUMNOS PROCESADOS ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
