<?php
// api_anexo1_html.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO])) {
    die("Acceso denegado");
}

$accion_id = isset($_GET['accion_id']) ? intval($_GET['accion_id']) : 0;
$alumno_id = isset($_GET['alumno_id']) ? intval($_GET['alumno_id']) : 0;

if (!$accion_id) {
    die("Se requiere el ID de la acción formativa.");
}

$query = "
    SELECT 
        a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.telefono, a.email,
        a.fecha_nacimiento, a.sexo, a.domicilio, a.cp, a.localidad, a.provincia, a.estudios,
        a.tipo_via, a.nombre_via, a.num_domicilio, a.planta, a.puerta,
        g.numero_grupo, g.fecha_inicio, g.fecha_fin,
        af.num_accion, af.modalidad, af.abreviatura as curso_codigo, af.titulo as curso_titulo,
        conv.codigo_expediente
    FROM matriculas m
    JOIN alumnos a ON m.alumno_id = a.id
    JOIN grupos g ON m.grupo_id = g.id
    JOIN acciones_formativas af ON g.accion_id = af.id
    LEFT JOIN planes p ON af.plan_id = p.id
    LEFT JOIN convocatorias conv ON p.convocatoria_id = conv.id
    WHERE g.accion_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
";
$params = [$accion_id];

if ($alumno_id > 0) {
    $query .= " AND a.id = ?";
    $params[] = $alumno_id;
}

$query .= " ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    die("Error de base de datos: " . $e->getMessage());
}

if (empty($alumnos)) {
    die("<p>No se encontraron alumnos matriculados para generar el Anexo I.</p>");
}
?>
<style>
    @page { margin: 0; size: A4 portrait; }
    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        font-size: 10px;
        background: #fff;
    }
    .page {
        box-sizing: border-box;
        background: #fff;
    }
    .page-break {
        page-break-after: always;
    }
    /* Estilos base */
    table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
    td, th { border: 1px solid #000; padding: 1px 3px; vertical-align: middle; }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bold { font-weight: bold; }
    
    /* Cabeceras y barras */
    .header-red {
        background-color: #f14135;
        color: white;
        text-align: center;
        font-weight: bold;
        font-size: 13px;
        padding: 4px;
        border: 1px solid #f14135;
    }
    .header-blue {
        background-color: #5b9bd5;
        color: white;
        text-align: center;
        font-weight: bold;
        font-size: 11px;
        padding: 2px;
        border: 1px solid #5b9bd5;
        margin-top: 5px;
    }
    .header-orange {
        background-color: #ed7d31;
        color: white;
        text-align: center;
        font-weight: bold;
        font-size: 11px;
        padding: 2px;
        border: 1px solid #ed7d31;
        margin-top: 5px;
    }

    .border-orange { border: 2px solid #ed7d31; padding: 3px; margin-top: 1px; }
    
    /* Checkboxes falsos */
    .checkbox {
        display: inline-block;
        width: 9px;
        height: 9px;
        border: 1px solid #000;
        text-align: center;
        line-height: 9px;
        font-size: 9px;
        margin-right: 3px;
        vertical-align: middle;
        font-weight: bold;
    }
    .check-label { margin-right: 12px; font-size: 9px; vertical-align: middle; }

    /* Inputs simulados (fondos grises) */
    .input-box {
        background-color: #e7e6e6;
        border: 1px solid #000;
        padding: 2px;
        min-height: 12px;
        font-size: 10px;
    }

    .row { display: flex; width: 100%; margin-bottom: 3px; }
    .col { flex: 1; padding: 0 2px; }
    
    .section-title { color: #2e74b5; font-weight: bold; margin-top: 5px; font-size: 11px; border-bottom: 1px solid #2e74b5; display: inline-block; width: 100%; }

    /* Página 2 */
    .p2-title { color: #f00; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
    .p2-q { color: #f00; font-weight: bold; margin-top: 10px; font-size: 10px; }
    .p2-text { font-size: 9px; margin-bottom: 5px; text-align: justify; }

</style>
<?php foreach($alumnos as $alumno): 
    $cursoTitulo = strtoupper($alumno['curso_codigo'] ?? '') . ' - ' . mb_strtoupper($alumno['curso_titulo'] ?? '', 'UTF-8');
    $nif_full = trim($alumno['dni'] ?? '');
    $nif_letter = '';
    $nif_numbers = $nif_full;
    if (preg_match('/([a-zA-Z])$/', $nif_full, $matches)) {
        $nif_letter = strtoupper($matches[1]);
        $nif_numbers = substr($nif_full, 0, -1);
    }
    
    $nombre = mb_strtoupper($alumno['nombre'], 'UTF-8');
    $apellidos = mb_strtoupper($alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido'], 'UTF-8');
    
    $sexo = strtolower(trim($alumno['sexo'] ?? ''));
    $chkMujer = ($sexo === 'mujer' || $sexo === 'f') ? 'X' : '&nbsp;';
    $chkHombre = ($sexo === 'hombre' || $sexo === 'm') ? 'X' : '&nbsp;';
    
    $fechaNac = $alumno['fecha_nacimiento'] ?? '';
    if ($fechaNac && $fechaNac !== '0000-00-00') {
        $fechaNac = date('d/m/Y', strtotime($fechaNac));
    } else {
        $fechaNac = '';
    }
    
    $domicilio = mb_strtoupper(trim($alumno['domicilio'] ?? ''), 'UTF-8');
    
    // Si tenemos campos detallados, los usamos, si no intentamos usar el domicilio general
    $nombreVia = mb_strtoupper(trim($alumno['tipo_via'] . ' ' . $alumno['nombre_via']), 'UTF-8');
    $numDomicilio = trim($alumno['num_domicilio'] ?? '');
    $planta = trim($alumno['planta'] ?? '');
    
    if (empty(trim($nombreVia))) {
        // Fallback: si no tenemos la via separada, intentamos separarlo de "domicilio_full" (CALLE LAGARES, 30)
        if (preg_match('/^(.*?),\s*(\d+.*)$/', $domicilio, $matches)) {
            $nombreVia = $matches[1];
            $numDomicilio = $matches[2];
        } else {
            $nombreVia = $domicilio;
        }
    }
    
    $cp = trim($alumno['cp'] ?? '');
    $localidad = mb_strtoupper(trim($alumno['localidad'] ?? ''), 'UTF-8');
    $provincia = mb_strtoupper(trim($alumno['provincia'] ?? ''), 'UTF-8');

    // Situacion laboral not available in local schema currently, we keep empty
    $chkDesempleado = '&nbsp;';
    $chkOcupado = '&nbsp;';
?>
<!-- PÁGINA 1: FICHA -->
<div class="page">
    
    <!-- Cabecera Logos (usamos imagenes locales si existen, sino texto) -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; height: 40px;">
        <div><img src="img/logo_efp.png" style="max-height: 40px;" onerror="this.style.display='none'"></div>
        <div><img src="img/logo_fundae.png" style="max-height: 40px;" onerror="this.style.display='none'"></div>
        <div><img src="img/logo_ministerio.png" style="max-height: 40px;" onerror="this.style.display='none'"></div>
    </div>

    <div class="header-red">FICHA DEL ALUMNO</div>
    
    <div class="row" style="margin-top: 5px;">
        <div style="width: 10%; line-height: 20px;">CURSO</div>
        <div style="width: 70%;" class="input-box text-center bold"><?= htmlspecialchars($cursoTitulo) ?></div>
        <div style="width: 10%; line-height: 20px; text-align: center;">CÓDIGOS</div>
        <div style="width: 10%;" class="input-box text-center bold"><?= htmlspecialchars($alumno['num_accion']) ?></div>
    </div>
    <div class="row" style="font-size: 8px;">
        <div style="width: 10%;"></div>
        <div style="width: 70%; text-align: center;">(Si es de certificado de profesionalidad poner nombre del certificado completo)</div>
        <div style="width: 10%;"></div>
        <div style="width: 10%; text-align: center;"></div>
    </div>

    <div class="header-blue">DATOS PERSONALES</div>

    <div class="row" style="margin-top: 5px;">
        <div style="width: 50%; display: flex; align-items: center;">
            <div class="checkbox">X</div><span class="check-label">DNI</span>
            <div class="checkbox">&nbsp;</div><span class="check-label">Permiso de residencia</span>
            <div class="checkbox">&nbsp;</div><span class="check-label">Otras autorizaciones</span>
        </div>
        <div style="width: 50%; display: flex; justify-content: flex-end; align-items: center;">
            <span style="margin-right: 5px;">Nº</span>
            <div class="input-box text-center bold" style="width: 120px; margin-right: 5px;"><?= htmlspecialchars($nif_numbers) ?></div>
            <span style="margin-right: 5px;">Letra</span>
            <div class="input-box text-center bold" style="width: 30px; margin-right: 5px;"><?= htmlspecialchars($nif_letter) ?></div>
            <span style="margin-right: 5px;">Nacionalidad</span>
            <div class="input-box text-center bold" style="width: 80px;">ESPAÑOLA</div>
        </div>
    </div>

    <table style="margin-top: 5px;">
        <tr>
            <td style="width: 15%; background: #f0f0f0;">APELLIDOS</td>
            <td style="width: 35%;" class="input-box bold"><?= htmlspecialchars($apellidos) ?></td>
            <td style="width: 15%; background: #f0f0f0;">NOMBRE</td>
            <td style="width: 35%;" class="input-box bold"><?= htmlspecialchars($nombre) ?></td>
        </tr>
    </table>

    <table style="border:none; margin-bottom: 0;">
        <tr>
            <td style="border:none; width: 15%; padding-left:0;">Fecha de nacimiento</td>
            <td style="border:none; width: 15%; padding-left:0;"><div class="input-box text-center"><?= htmlspecialchars($fechaNac) ?></div></td>
            <td style="border:none; width: 20%;">
                <div class="checkbox"><?= $chkMujer ?></div>Mujer 
                <div class="checkbox"><?= $chkHombre ?></div>Hombre
            </td>
            <td style="border:none; width: 10%;">Teléfono 1</td>
            <td style="border:none; width: 20%;"><div class="input-box"><?= htmlspecialchars($alumno['telefono'] ?? '') ?></div></td>
            <td style="border:none; width: 10%;">Teléfono 2</td>
            <td style="border:none; width: 10%;"><div class="input-box"></div></td>
        </tr>
    </table>

    <table style="border:none; margin-bottom: 0;">
        <tr>
            <td style="border:none; width: 15%; padding-left:0;">Correo electrónico</td>
            <td style="border:none; width: 35%; padding-left:0;"><div class="input-box"><?= htmlspecialchars($alumno['email'] ?? '') ?></div></td>
            <td style="border:none; width: 10%;">Dirección</td>
            <td style="border:none; width: 40%;"><div class="input-box"><?= htmlspecialchars($nombreVia) ?></div></td>
        </tr>
    </table>

    <table style="border:none;">
        <tr>
            <td style="border:none; width: 5%; padding-left:0;">Nº</td>
            <td style="border:none; width: 10%;"><div class="input-box text-center"><?= htmlspecialchars($numDomicilio) ?></div></td>
            <td style="border:none; width: 5%;">Piso</td>
            <td style="border:none; width: 10%;"><div class="input-box text-center"><?= htmlspecialchars($planta) ?></div></td>
            <td style="border:none; width: 5%;">CP</td>
            <td style="border:none; width: 10%;"><div class="input-box text-center"><?= htmlspecialchars($cp) ?></div></td>
            <td style="border:none; width: 10%;">Población</td>
            <td style="border:none; width: 20%;"><div class="input-box"><?= htmlspecialchars($localidad) ?></div></td>
            <td style="border:none; width: 10%;">Provincia</td>
            <td style="border:none; width: 15%;"><div class="input-box"><?= htmlspecialchars($provincia) ?></div></td>
        </tr>
    </table>

    <div style="margin-top: 5px;">
        ¿Posee algún tipo de minusvalía certificada (más del 33%)? 
        <div class="checkbox">&nbsp;</div><span class="check-label">Física</span>
        <div class="checkbox">&nbsp;</div><span class="check-label">Psíquica</span>
        <div class="checkbox">&nbsp;</div><span class="check-label">Sensorial</span>
    </div>

    <div class="header-blue">DATOS ACADÉMICOS</div>
    <div class="section-title">3. Titulación actual</div>
    
    <div class="row" style="margin-top: 5px;">
        <div style="width: 50%;">
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Sin titulación</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Graduado Escolar / ESO</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Título de Bachiller / BUP / COU / Acc. mayores 25</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Título de Técnico / FP Grado Medio</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Título de Técnico Superior / FP Superior</span></div>
        </div>
        <div style="width: 50%;">
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">E. Universitarios 1er ciclo (Diplomatura/Grado)</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">E. Universitarios 2º ciclo (Licenciatura/Master)</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">E. Universitarios 3er ciclo (Doctor)</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Título de Doctorado</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Otra titulación (especificar): ..............................</span></div>
        </div>
    </div>

    <div class="header-blue">DATOS LABORALES</div>
    <div class="section-title">4. Situación laboral</div>
    <div style="margin-top: 5px; margin-bottom: 10px;">
        <div class="checkbox"><?= $chkDesempleado ?></div><span class="check-label">Desempleado</span>
        <div class="checkbox">&nbsp;</div><span class="check-label">Trabajador por cuenta propia (empresario, autónomo)</span>
        <div class="checkbox"><?= $chkOcupado ?></div><span class="check-label">Trabajador por cuenta ajena (público, privado)</span>
    </div>

    <div class="section-title">5. Lugar de residencia/trabajo</div>
    <div class="row" style="margin-top: 5px;">
        <div style="width: 50%;">
            Si está desempleado. Lugar de residencia:<br>
            <div class="input-box" style="margin-right: 10px; margin-top: 3px;"></div>
        </div>
        <div style="width: 50%;">
            Si está ocupado. Lugar de centro de trabajo:<br>
            <div class="input-box" style="margin-top: 3px;"></div>
        </div>
    </div>

    <div class="section-title">6. ¿Cómo conoció la existencia de este curso?</div>
    <div class="row" style="margin-top: 5px;">
        <div style="width: 33%;">
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Servicio Público Estatal</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Itinerario Formativo</span></div>
        </div>
        <div style="width: 33%;">
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">A través de mi empresa</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Organización empresarial o sindical</span></div>
        </div>
        <div style="width: 33%;">
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Medios de comunicación</span></div>
            <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Otros (especificar): ........................</span></div>
        </div>
    </div>

    <div class="header-orange">A RESPONDER SOLO POR LOS PARTICIPANTES OCUPADOS</div>
    <div class="border-orange">
        <div class="section-title" style="color: #ed7d31; border-bottom: 1px solid #ed7d31;">7. Categoría profesional</div>
        <div class="row" style="margin-top: 5px;">
            <div style="width: 33%;">
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Directivo</span></div>
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Mando intermedio</span></div>
            </div>
            <div style="width: 33%;">
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Técnico</span></div>
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Trabajador cualificado</span></div>
            </div>
            <div style="width: 33%;">
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Trabajador de baja cualificación</span></div>
                <div style="margin-bottom: 4px;"><div class="checkbox">&nbsp;</div><span class="check-label">Otra categoría (especificar): .........</span></div>
            </div>
        </div>

        <div class="section-title" style="color: #ed7d31; border-bottom: 1px solid #ed7d31;">8. Horario del curso y Tamaño empresa</div>
        <div class="row" style="margin-top: 5px;">
            <div style="width: 100%;">
                <div class="checkbox">&nbsp;</div><span class="check-label">De 1 a 9 empleados</span>
                <div class="checkbox">&nbsp;</div><span class="check-label">De 10 a 49 empleados</span>
                <div class="checkbox">&nbsp;</div><span class="check-label">De 50 a 99 empleados</span>
                <div class="checkbox">&nbsp;</div><span class="check-label">De 100 a 250 empleados</span>
                <div class="checkbox">&nbsp;</div><span class="check-label">Más de 250 empleados</span>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div style="width: 50%; text-align: center; border: 1px solid #ed7d31; padding: 10px; margin-right: 5px;">
            <div style="color: #ed7d31; font-weight: bold; margin-bottom: 10px;">En el caso de ser desempleado</div>
            Fecha de Inscripción como demandante en la oficina de empleo<br><br>
            <div class="input-box" style="width: 100px; display: inline-block;"></div><br><br>
            <span style="color: #ed7d31;">(A rellenar por la entidad)</span>
        </div>
        <div style="width: 50%; text-align: center; border: 1px solid #000; padding: 10px; position: relative;">
            <div style="text-align: left; font-weight: bold;">Firma (*)</div>
            <div style="text-align: right; position: absolute; top: 10px; right: 10px;">Fecha:</div>
            <br><br><br><br>
            <div style="font-weight: bold; font-size: 8px;">Espacio reservado para el sello de la Administración</div>
        </div>
    </div>
    <div style="font-size: 7px; text-align: justify; margin-top: 5px; line-height: 1.2;">
        (*) Declara bajo su responsabilidad que todos los datos expuestos son ciertos. Autoriza a la Consejería de Economía, Hacienda y Empleo al tratamiento automatizado y cesión de los datos personales aquí reflejados para el seguimiento y justificación...
    </div>

</div> <!-- Fin Pagina 1 -->

<div class="page-break"></div>

<!-- PÁGINA 2: LOPD -->
<div class="page">
    <div class="p2-title">Información sobre Protección de Datos</div>
    
    <div class="p2-q">1. Responsable del tratamiento de sus datos</div>
    <div class="p2-text">Responsable: Consejería de Economía, Hacienda y Empleo, D.G. de Formación.<br>Domicilio social: Consultar www.comunidad.madrid/centros<br>Contacto con el Delegado de Protección de Datos: protecciondatos@madrid.org.</div>

    <div class="p2-q">2. ¿En qué actividad de tratamiento están incluidos sus datos personales y con qué fines se tratarán?</div>
    <div class="p2-text">CURSOS.<br>En cumplimiento de lo establecido por el Reglamento (UE) 2016/679, de Protección de Datos Personales, sus datos serán tratados para las siguientes finalidades:<br>Realizar seguimiento del alumnado asistente a los mismos.</div>

    <div class="p2-q">3. ¿Cuál es la legitimación en la que se basa la licitud del tratamiento?</div>
    <div class="p2-text">RGPD 6.1. c) el tratamiento es necesario para el cumplimiento de una obligación legal aplicable al responsable del tratamiento.<br>RGPD 6.1. e) el tratamiento es necesario para el cumplimiento de una misión realizada en interés público o en el ejercicio de poderes públicos conferidos al responsable del tratamiento.<br>Ley 30/2015 por la que se regula el Sistema de Formación Profesional para el Empleo. Real Decreto 694/2017, de 3 de julio, por el que se desarrolla la Ley 30/2015. Orden TAS/718/2008, de 7 de marzo, se desarrolla la formación de oferta prevista en el Real Decreto 395/2007.</div>

    <div class="p2-q">4. ¿Cómo ejercer sus derechos? ¿Cuáles son sus derechos cuando nos facilita sus datos?</div>
    <div class="p2-text">Puede ejercer, si lo desea, los derechos de acceso, rectificación y supresión de datos, así como solicitar que se limite el tratamiento de sus datos personales, oponerse al mismo, solicitar en su caso la portabilidad de sus datos, así como a no ser objeto de una decisión individual basada únicamente en el tratamiento automatizado, incluida la elaboración de perfiles.<br>Según la Ley 39/2015, el RGPD y la Ley Orgánica 3/2018, puede ejercer sus derechos por Registro Electrónico o Registro Presencial o en los lugares y formas previstos en el artículo 16.4 de la Ley 39/2015, preferentemente mediante el formulario de solicitud "Ejercicio de derechos en materia de protección de datos personales".</div>

    <div class="p2-q">5. Tratamientos que incluyen decisiones automatizadas, incluida la elaboración de perfiles, con efectos jurídicos o relevantes.</div>
    <div class="p2-text">No se realizan</div>

    <div class="p2-q">6. ¿Por cuánto tiempo conservaremos sus datos personales?</div>
    <div class="p2-text">Los datos personales proporcionados se conservarán por el siguiente periodo:<br>Periodo indeterminado.<br>Los datos se mantendrán de forma indefinida mientras el interesado no solicite su supresión o ejercite su derecho de oposición.</div>

    <div class="p2-q">7. ¿A qué destinatarios se comunicarán sus datos?</div>
    <div class="p2-text">No se prevén cesiones.</div>

    <div class="p2-q">8. Transferencias internacionales.</div>
    <div class="p2-text">No.</div>

    <div class="p2-q">9. Derecho a retirar el consentimiento prestado para el tratamiento en cualquier momento.</div>
    <div class="p2-text">Cuando el tratamiento esté basado en el consentimiento explícito, tiene derecho a retirar el consentimiento en cualquier momento, sin que ello afecte a la licitud del tratamiento basado en el consentimiento previo a su retirada.</div>

    <div class="p2-q">10. Derecho a presentar una reclamación ante la Autoridad de Control.</div>
    <div class="p2-text">Tiene derecho a presentar una reclamación ante la Agencia Española de Protección de Datos www.aepd.es si no está conforme con el tratamiento que se hace de sus datos personales.</div>

    <div class="p2-q">11. Categoría de datos objeto de tratamiento.</div>
    <div class="p2-text">Datos de carácter identificativo.</div>

    <div class="p2-q">12. Fuente de la que proceden los datos</div>
    <div class="p2-text">Interesado.</div>

    <div class="p2-q">Más información.</div>
    <div class="p2-text">Puede consultar más información y la normativa aplicable en materia de protección de datos en la web de la Agencia Española de Protección de Datos https://www.aepd.es, así como en el siguiente enlace: www.comunidad.madrid/protecciondedatos.</div>

</div> <!-- Fin Pagina 2 -->

<?php if ($alumno !== end($alumnos)): ?>
    <div class="page-break"></div>
<?php endif; ?>

<?php endforeach; ?>
