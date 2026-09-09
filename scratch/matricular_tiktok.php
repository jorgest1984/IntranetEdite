<?php
// matricular_alumnos_tiktok.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

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
            $stmtInsC = $pdo->prepare("INSERT INTO cursos (nombre_corto, nombre_largo, modalidad, duracion) VALUES ('CTRD0016', 'TIK TOK PARA EMPRESAS', 'TELEFORMACIÓN', 60)");
            $stmtInsC->execute();
            $curso_id = $pdo->lastInsertId();
        }

        // Crear Acción Formativa
        $stmtInsAF = $pdo->prepare("INSERT INTO acciones_formativas (curso_id, titulo, abreviatura, num_accion, modalidad, duracion) VALUES (?, 'TIK TOK PARA EMPRESAS', 'CTRD0016', 'CTRD0016', 'TELEFORMACIÓN', 60)");
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
        $stmtInsG = $pdo->prepare("INSERT INTO grupos (accion_id, numero_grupo, fecha_inicio, fecha_fin, estado) VALUES (?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'En curso')");
        $stmtInsG->execute([$af_id]);
        $grupo_id = $pdo->lastInsertId();
    } else {
        $grupo_id = $grupo['id'];
    }
    echo "Grupo asignado ID: $grupo_id\n";

    // Obtener convocatoria_id correspondiente
    $convocatoria_id = null;
    if (!empty($af['plan_id'])) {
        $stmtP = $pdo->prepare("SELECT convocatoria_id FROM planes WHERE id = ?");
        $stmtP->execute([$af['plan_id']]);
        $convocatoria_id = $stmtP->fetchColumn();
    }
    if (!$convocatoria_id) {
        $convocatoria_id = $pdo->query("SELECT id FROM convocatorias ORDER BY id DESC LIMIT 1")->fetchColumn();
        if (!$convocatoria_id) {
            $pdo->query("INSERT INTO convocatorias (codigo_expediente, nombre, tipo, creado_en) VALUES ('EXP-GENERAL', 'Convocatoria General', 'FUNDAE_OCUPADOS', NOW())");
            $convocatoria_id = $pdo->lastInsertId();
        }
    }
    echo "Convocatoria asignada ID: $convocatoria_id\n";

    // Obtener columnas de las tablas para inserts seguros
    $matriculas_cols = $pdo->query("SHOW COLUMNS FROM matriculas")->fetchAll(PDO::FETCH_COLUMN);
    $alumnos_cols = $pdo->query("SHOW COLUMNS FROM alumnos")->fetchAll(PDO::FETCH_COLUMN);
    $empresas_cols = $pdo->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);

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
                $emp_data = [
                    'nombre' => $row['empresa'],
                    'cif' => $row['cif'],
                    'localidad' => $row['localidad'],
                    'provincia' => $row['provincia']
                ];
                $fields = [];
                $placeholders = [];
                $values = [];
                foreach ($emp_data as $col => $val) {
                    if (in_array($col, $empresas_cols)) {
                        $fields[] = "`$col`";
                        $placeholders[] = "?";
                        $values[] = $val;
                    }
                }
                $stmtInsEmp = $pdo->prepare("INSERT INTO empresas (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
                $stmtInsEmp->execute($values);
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
            $al_insert = [
                'dni' => $dni,
                'nombre' => $nombre,
                'apellidos' => trim($primer_apellido . ' ' . $segundo_apellido),
                'primer_apellido' => $primer_apellido,
                'segundo_apellido' => $segundo_apellido,
                'fecha_nacimiento' => $row['fecha_nac'],
                'localidad' => $row['localidad'],
                'provincia' => $row['provincia'],
                'ultima_empresa_id' => $empresa_id,
                'email' => $email_generado
            ];
            $fields = [];
            $placeholders = [];
            $values = [];
            foreach ($al_insert as $col => $val) {
                if (in_array($col, $alumnos_cols)) {
                    $fields[] = "`$col`";
                    $placeholders[] = "?";
                    $values[] = $val;
                }
            }
            $stmtInsAl = $pdo->prepare("INSERT INTO alumnos (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmtInsAl->execute($values);
            $alumno_id = $pdo->lastInsertId();
        } else {
            $alumno_id = $alumno['id'];
            $al_upd = [
                'fecha_nacimiento' => $row['fecha_nac'],
                'localidad' => $row['localidad'],
                'provincia' => $row['provincia'],
                'ultima_empresa_id' => $empresa_id
            ];
            $set_clauses = [];
            $upd_values = [];
            foreach ($al_upd as $col => $val) {
                if (in_array($col, $alumnos_cols)) {
                    $set_clauses[] = "`$col` = ?";
                    $upd_values[] = $val;
                }
            }
            if (!empty($set_clauses)) {
                $upd_values[] = $alumno_id;
                $stmtUpdAl = $pdo->prepare("UPDATE alumnos SET " . implode(', ', $set_clauses) . " WHERE id = ?");
                $stmtUpdAl->execute($upd_values);
            }
        }

        // Crear o actualizar Matrícula en el Grupo
        $where_check = [];
        $check_params = [$alumno_id];
        if (in_array('grupo_id', $matriculas_cols)) {
            $where_check[] = "grupo_id = ?";
            $check_params[] = $grupo_id;
        } elseif (in_array('convocatoria_id', $matriculas_cols)) {
            $where_check[] = "convocatoria_id = ?";
            $check_params[] = $convocatoria_id;
        }

        $stmtMat = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND " . implode(' AND ', $where_check) . " LIMIT 1");
        $stmtMat->execute($check_params);
        $mat_id = $stmtMat->fetchColumn();

        if (!$mat_id) {
            $mat_insert = [
                'alumno_id' => $alumno_id,
                'grupo_id' => $grupo_id,
                'convocatoria_id' => $convocatoria_id,
                'estado' => 'Admitido',
                'fecha_matricula' => date('Y-m-d')
            ];
            $fields = [];
            $placeholders = [];
            $values = [];
            foreach ($mat_insert as $col => $val) {
                if (in_array($col, $matriculas_cols)) {
                    $fields[] = "`$col`";
                    $placeholders[] = "?";
                    $values[] = $val;
                }
            }
            $stmtInsMat = $pdo->prepare("INSERT INTO matriculas (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
            $stmtInsMat->execute($values);
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
