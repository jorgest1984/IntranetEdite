<?php
require_once __DIR__ . '/../includes/config.php';

echo "<pre>\n";
echo "=== INICIANDO IMPORTACIÓN DE 14 ALUMNOS EN AF ADGD352PO (ID: 34, GRUPO ID: 18) ===\n";

try {
    $af_id = 34;
    $grupo_id = 18;

    // Obtener la AF para sacar convocatoria_id
    $stmtAF = $pdo->prepare("SELECT * FROM acciones_formativas WHERE id = ?");
    $stmtAF->execute([$af_id]);
    $af = $stmtAF->fetch(PDO::FETCH_ASSOC);

    $convocatoria_id = $af['convocatoria_id'] ?? null;
    if (!$convocatoria_id) {
        $stmtConv = $pdo->query("SELECT id FROM convocatorias ORDER BY id DESC LIMIT 1");
        $convocatoria_id = $stmtConv->fetchColumn();
    }

    echo "Acción Formativa: " . ($af['titulo'] ?? 'ADGD352PO') . " (ID: $af_id)\n";
    echo "Grupo ID: $grupo_id\n";
    echo "Convocatoria ID: $convocatoria_id\n\n";

    $alumnosData = [
        [
            'nombre' => 'LAURA VANESA',
            'primer_apellido' => 'CASTRO',
            'segundo_apellido' => 'HERNANDEZ',
            'dni' => '55906434N',
            'fecha_nacimiento' => '1986-02-06',
            'localidad' => 'ALCACER',
            'provincia' => 'VALENCIA',
            'empresa_nombre' => 'MY MARLET VALENCIA SL',
            'empresa_cif' => 'B42950832'
        ],
        [
            'nombre' => 'PABLO FERNANDO',
            'primer_apellido' => 'DE QUINTA',
            'segundo_apellido' => 'ASIAN',
            'dni' => '45809425A',
            'fecha_nacimiento' => '1986-11-12',
            'localidad' => 'TOMARES',
            'provincia' => 'SEVILLA',
            'empresa_nombre' => 'CRUZ ROJA ESPAÑOLA',
            'empresa_cif' => 'Q2866001G'
        ],
        [
            'nombre' => 'DESPINA-LARISA',
            'primer_apellido' => 'URUCATU',
            'segundo_apellido' => '',
            'dni' => 'X9223256A',
            'fecha_nacimiento' => '1986-04-07',
            'localidad' => 'ALCORCON',
            'provincia' => 'MADRID',
            'empresa_nombre' => 'HOSPITAL LA PAZ',
            'empresa_cif' => 'Q2877009G'
        ],
        [
            'nombre' => 'CRISTOPHER SALVADOR',
            'primer_apellido' => 'DURAN',
            'segundo_apellido' => 'PINTO',
            'dni' => '60585060R',
            'fecha_nacimiento' => '1996-03-22',
            'localidad' => 'MADRID',
            'provincia' => 'MADRID',
            'empresa_nombre' => 'GRUPO ADLNATER SA',
            'empresa_cif' => 'A59053355'
        ],
        [
            'nombre' => 'IVAN',
            'primer_apellido' => 'GARCIA',
            'segundo_apellido' => 'GOMEZ',
            'dni' => '70048846T',
            'fecha_nacimiento' => '1978-04-27',
            'localidad' => 'MOLLEDO',
            'provincia' => 'CANTABRIA',
            'empresa_nombre' => 'IVAN GARCIA GOMEZ',
            'empresa_cif' => '70048846T'
        ],
        [
            'nombre' => 'INMACULADA',
            'primer_apellido' => 'GUZMÁN',
            'segundo_apellido' => 'PASTOR',
            'dni' => '28485291K',
            'fecha_nacimiento' => '1971-07-22',
            'localidad' => 'BORMUJOS',
            'provincia' => 'SEVILLA',
            'empresa_nombre' => 'INMACULADA GUZMÁN PASTOR',
            'empresa_cif' => '28485291K'
        ],
        [
            'nombre' => 'ROSA MARIA',
            'primer_apellido' => 'MARTINEZ',
            'segundo_apellido' => 'MARTINEZ',
            'dni' => '76144772Y',
            'fecha_nacimiento' => '1979-08-31',
            'localidad' => 'BENAMAUREL',
            'provincia' => 'GRANADA',
            'empresa_nombre' => 'PULSIA TECHNOLOGY SL',
            'empresa_cif' => 'B92828946'
        ],
        [
            'nombre' => 'ESPERANZA EMPERATRIZ',
            'primer_apellido' => 'OCHOA',
            'segundo_apellido' => 'LOZANO',
            'dni' => 'Z0877175Y',
            'fecha_nacimiento' => '1999-05-12',
            'localidad' => 'GETAFE',
            'provincia' => 'MADRID',
            'empresa_nombre' => 'RANDSTAD EMPLEO ETT, S.A',
            'empresa_cif' => 'A80652928'
        ],
        [
            'nombre' => 'VERONICA',
            'primer_apellido' => 'PIÑEIRO',
            'segundo_apellido' => 'LEIRA',
            'dni' => '32683608X',
            'fecha_nacimiento' => '1979-10-31',
            'localidad' => 'OURENSE',
            'provincia' => 'OURENSE',
            'empresa_nombre' => 'TRAGSATEC S.A. (TECNOLOGIAS Y SERVICIOS AGRARIOS)',
            'empresa_cif' => 'A79365821'
        ],
        [
            'nombre' => 'FRANCISCO JAVIER',
            'primer_apellido' => 'ROLLAN',
            'segundo_apellido' => 'REBOLLO',
            'dni' => '09199103T',
            'fecha_nacimiento' => '1976-06-18',
            'localidad' => 'DON ALVARO',
            'provincia' => 'BADAJOZ',
            'empresa_nombre' => 'MERCATEL CONSULTING S.L.',
            'empresa_cif' => 'B06651152'
        ],
        [
            'nombre' => 'CAROLINA TRINIDAD',
            'primer_apellido' => 'SEPULVEDA',
            'segundo_apellido' => 'MARTINEZ',
            'dni' => 'Z2119104G',
            'fecha_nacimiento' => '1978-05-20',
            'localidad' => 'DONOSTIA',
            'provincia' => 'GIPUZKOA',
            'empresa_nombre' => 'CAROLINA TRINIDAD SEPULVEDA MARTINEZ',
            'empresa_cif' => 'Z2119104G'
        ],
        [
            'nombre' => 'ILENIA',
            'primer_apellido' => 'SILER',
            'segundo_apellido' => 'RODRIGUEZ',
            'dni' => '47538885R',
            'fecha_nacimiento' => '1997-06-10',
            'localidad' => 'LOS PALACIOS Y VILLAFRANCA',
            'provincia' => 'SEVILLA',
            'empresa_nombre' => 'UNIVERSAL SUPPORT SA',
            'empresa_cif' => 'A15556525'
        ],
        [
            'nombre' => 'MANUEL',
            'primer_apellido' => 'SUAREZ',
            'segundo_apellido' => 'NAVARRO',
            'dni' => '80080635V',
            'fecha_nacimiento' => '1987-07-04',
            'localidad' => 'ALMENDRALEJO',
            'provincia' => 'BADAJOZ',
            'empresa_nombre' => 'REVERGY SOCIEDAD LIMITADA',
            'empresa_cif' => 'B92946888'
        ],
        [
            'nombre' => 'LINETH PAOLA',
            'primer_apellido' => 'VEGA',
            'segundo_apellido' => 'SERRANO',
            'dni' => 'Z2396953J',
            'fecha_nacimiento' => '1997-03-05',
            'localidad' => 'ARANJUEZ',
            'provincia' => 'MADRID',
            'empresa_nombre' => 'EUROFIRMS EMPLEO, ETT.SLU',
            'empresa_cif' => 'B17880550'
        ]
    ];

    $procesados = 0;
    $matriculados = 0;

    foreach ($alumnosData as $item) {
        try {
            // 1. Gestionar Empresa
            $empresa_id = null;
            if (!empty($item['empresa_cif'])) {
                $stmtEmp = $pdo->prepare("SELECT id FROM empresas WHERE cif = ?");
                $stmtEmp->execute([$item['empresa_cif']]);
                $empresa_id = $stmtEmp->fetchColumn();

                if (!$empresa_id) {
                    $stmtInsEmp = $pdo->prepare("INSERT INTO empresas (nombre, cif, localidad, provincia) VALUES (?, ?, ?, ?)");
                    $stmtInsEmp->execute([
                        $item['empresa_nombre'],
                        $item['empresa_cif'],
                        $item['localidad'],
                        $item['provincia']
                    ]);
                    $empresa_id = $pdo->lastInsertId();
                    echo "✓ Empresa creada: " . $item['empresa_nombre'] . " (ID: $empresa_id)\n";
                }
            }

            // 2. Gestionar Alumno (Buscar por DNI)
            $stmtAl = $pdo->prepare("SELECT id, email FROM alumnos WHERE dni = ?");
            $stmtAl->execute([$item['dni']]);
            $alumno = $stmtAl->fetch(PDO::FETCH_ASSOC);

            if ($alumno) {
                $alumno_id = $alumno['id'];
                $stmtUpdAl = $pdo->prepare("UPDATE alumnos SET nombre = ?, primer_apellido = ?, segundo_apellido = ?, fecha_nacimiento = ?, localidad = ?, provincia = ?, ultima_empresa_id = ? WHERE id = ?");
                $stmtUpdAl->execute([
                    $item['nombre'],
                    $item['primer_apellido'],
                    $item['segundo_apellido'],
                    $item['fecha_nacimiento'],
                    $item['localidad'],
                    $item['provincia'],
                    $empresa_id,
                    $alumno_id
                ]);
                echo "✓ Alumno actualizado: " . $item['nombre'] . " " . $item['primer_apellido'] . " (DNI: " . $item['dni'] . ", ID: $alumno_id)\n";
            } else {
                // Usar cadena vacía '' para email si la columna es NOT NULL
                $stmtInsAl = $pdo->prepare("INSERT INTO alumnos (nombre, primer_apellido, segundo_apellido, dni, fecha_nacimiento, localidad, provincia, email, ultima_empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, '', ?)");
                $stmtInsAl->execute([
                    $item['nombre'],
                    $item['primer_apellido'],
                    $item['segundo_apellido'],
                    $item['dni'],
                    $item['fecha_nacimiento'],
                    $item['localidad'],
                    $item['provincia'],
                    $empresa_id
                ]);
                $alumno_id = $pdo->lastInsertId();
                echo "✓ Alumno creado (Sin correo): " . $item['nombre'] . " " . $item['primer_apellido'] . " (DNI: " . $item['dni'] . ", ID: $alumno_id)\n";
            }

            $procesados++;

            // 3. Matricular en el grupo
            $stmtMatCheck = $pdo->prepare("SELECT id FROM matriculas WHERE alumno_id = ? AND grupo_id = ?");
            $stmtMatCheck->execute([$alumno_id, $grupo_id]);
            $mat_id = $stmtMatCheck->fetchColumn();

            if ($mat_id) {
                $stmtMatUpd = $pdo->prepare("UPDATE matriculas SET estado = 'Admitido', convocatoria_id = ? WHERE id = ?");
                $stmtMatUpd->execute([$convocatoria_id, $mat_id]);
                echo "  -> Matrícula actualizada en Grupo ID $grupo_id (Matrícula ID: $mat_id)\n";
            } else {
                $stmtMatIns = $pdo->prepare("INSERT INTO matriculas (convocatoria_id, alumno_id, grupo_id, estado, fecha_matricula) VALUES (?, ?, ?, 'Admitido', NOW())");
                $stmtMatIns->execute([$convocatoria_id, $alumno_id, $grupo_id]);
                $mat_id = $pdo->lastInsertId();
                $matriculados++;
                echo "  -> Alumno matriculado con éxito en Grupo ID $grupo_id (Matrícula ID: $mat_id)\n";
            }
        } catch (Exception $eItem) {
            echo "❌ ERROR procesando " . $item['nombre'] . ": " . $eItem->getMessage() . "\n";
        }
    }

    echo "\n=== IMPORTACIÓN FINALIZADA DE 14 ALUMNOS ===\n";
    echo "Alumnos procesados: $procesados\n";
    echo "Nuevas matrículas: $matriculados\n";
} catch (Exception $eGlobal) {
    echo "❌ ERROR GLOBAL: " . $eGlobal->getMessage() . "\n";
}
echo "</pre>";
