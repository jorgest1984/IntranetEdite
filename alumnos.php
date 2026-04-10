<?php
// alumnos.php - Versión Premium Unificada con Sincronización Moodle
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$moodle = new MoodleAPI($pdo);
$error = '';
$success = '';

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        // 1. Recoger datos locales
        $data = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'primer_apellido' => trim($_POST['primer_apellido'] ?? ''),
            'segundo_apellido' => trim($_POST['segundo_apellido'] ?? ''),
            'dni' => trim($_POST['dni'] ?? ''),
            'comercial_id' => !empty($_POST['comercial_id']) ? $_POST['comercial_id'] : null,
            'bloqueado' => isset($_POST['bloqueado']) ? 1 : 0,
            'restringido' => isset($_POST['restringido']) ? 1 : 0,
            'baja' => isset($_POST['baja']) ? 1 : 0,
            'alias' => trim($_POST['alias'] ?? ''),
            'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
            'seguridad_social' => trim($_POST['seguridad_social'] ?? ''),
            'profesion' => trim($_POST['profesion'] ?? ''),
            'sexo' => $_POST['sexo'] ?? 'Hombre',
            'estudios' => $_POST['estudios'] ?? '',
            'tipo_via' => $_POST['tipo_via'] ?? '',
            'nombre_via' => trim($_POST['nombre_via'] ?? ''),
            'tipo_num' => $_POST['tipo_num'] ?? '',
            'num_domicilio' => trim($_POST['num_domicilio'] ?? ''),
            'calificador' => $_POST['calificador'] ?? '',
            'bloque' => trim($_POST['bloque'] ?? ''),
            'portal' => trim($_POST['portal'] ?? ''),
            'escalera' => trim($_POST['escalera'] ?? ''),
            'planta' => trim($_POST['planta'] ?? ''),
            'puerta' => trim($_POST['puerta'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'domicilio' => trim($_POST['domicilio_full'] ?? ''),
            'cp' => trim($_POST['cp'] ?? ''),
            'localidad' => trim($_POST['localidad'] ?? ''),
            'provincia' => $_POST['provincia'] ?? '',
            'telefono' => trim($_POST['telefono'] ?? ''),
            'telefono_empresa' => trim($_POST['telefono_empresa'] ?? ''), // Usado para móvil en el form
            'mananas_desde' => trim($_POST['mananas_desde'] ?? ''),
            'mananas_hasta' => trim($_POST['mananas_hasta'] ?? ''),
            'tardes_desde' => trim($_POST['tardes_desde'] ?? ''),
            'tardes_hasta' => trim($_POST['tardes_hasta'] ?? ''),
            'solo_los' => trim($_POST['solo_los'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'email_2' => trim($_POST['email_2'] ?? ''),
            'ultima_empresa_id' => !empty($_POST['ultima_empresa_id']) ? $_POST['ultima_empresa_id'] : null,
            'centro_trabajo' => trim($_POST['centro_trabajo'] ?? ''),
            'enviar_emails' => isset($_POST['enviar_emails']) ? 1 : 0,
            'plat_usuario' => trim($_POST['plat_usuario'] ?? ''),
            'plat_clave' => trim($_POST['plat_clave'] ?? ''),
            'id_plat_2015' => trim($_POST['id_plat_2015'] ?? ''),
            'id_plat_2016' => trim($_POST['id_plat_2016'] ?? ''),
            'pref_presencial' => trim($_POST['pref_presencial'] ?? ''),
            'modulacion' => trim($_POST['modulacion'] ?? ''),
            'horarios' => trim($_POST['horarios'] ?? ''),
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            'entrega_atencion' => trim($_POST['entrega_atencion'] ?? ''),
            'entrega_domicilio' => trim($_POST['entrega_domicilio'] ?? ''),
            'entrega_cp' => trim($_POST['entrega_cp'] ?? ''),
            'entrega_localidad' => trim($_POST['entrega_localidad'] ?? ''),
            'entrega_provincia' => $_POST['entrega_provincia'] ?? '',
            'creado_en' => date('Y-m-d H:i:s')
        ];

        // Validaciones básicas locales
        if (empty($data['nombre']) || empty($data['primer_apellido']) || empty($data['dni']) || empty($data['email'])) {
            throw new Exception("Nombre, Primer Apellido, NIF y Email son obligatorios.");
        }

        // 1.5 Comprobar duplicados en local antes de seguir
        $stmtCheck = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? OR email = ?");
        $stmtCheck->execute([$data['dni'], $data['email']]);
        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Ya existe un alumno con ese DNI o Email en el sistema.");
        }

        // 2. Sincronización con Moodle (Opcional, no debe bloquear el registro local)
        $moodleUserId = null;
        if ($moodle->isConfigured()) {
            try {
                // Generar credenciales moodle si no vienen dadas
                $username = !empty($data['plat_usuario']) ? $data['plat_usuario'] : strtolower(explode('@', $data['email'])[0]) . '_' . substr($data['dni'], -3);
                $password = !empty($data['plat_clave']) ? $data['plat_clave'] : 'ef_' . strtoupper(substr($data['dni'], -4)) . '!' . rand(10,99);
                
                // Buscar si existe
                $moodleSearch = $moodle->getUsersByField('email', [$data['email']]);
                if (!empty($moodleSearch) && !empty($moodleSearch['users'])) {
                    $moodleUserId = $moodleSearch['users'][0]['id'];
                } else {
                    // Crear en Moodle
                    $moodleCreate = $moodle->createUser(
                        $username, 
                        $password, 
                        $data['nombre'], 
                        $data['primer_apellido'] . ' ' . $data['segundo_apellido'], 
                        $data['email']
                    );
                    if (isset($moodleCreate[0]['id'])) {
                        $moodleUserId = $moodleCreate[0]['id'];
                        // Actualizamos las claves en el array data para que se guarden localmente
                        if (empty($data['plat_usuario'])) $data['plat_usuario'] = $username;
                        if (empty($data['plat_clave'])) $data['plat_clave'] = $password;
                    }
                }
            } catch (Exception $mEx) {
                // No lanzamos excepcion para no bloquear, pero guardamos el error para avisar
                $moodleError = "Ocurrió un aviso en Moodle: " . $mEx->getMessage();
            }
        }
        
        // Añadir moodle_id al registro
        $data['moodle_user_id'] = $moodleUserId;

        // 3. Insertar en DB Local
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO alumnos ($columns) VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $nuevoId = $pdo->lastInsertId();

        audit_log($pdo, 'ALUMNO_CREADO', 'alumnos', $nuevoId, null, ['dni' => $data['dni'], 'moodle_id' => $moodleUserId]);
        
        // Redirigir a la ficha
        $mErrorParam = isset($moodleError) ? "&m_error=" . urlencode($moodleError) : "";
        header("Location: ficha_alumno.php?id=$nuevoId&success=1" . $mErrorParam);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Datos para Selects
$provincias = ["Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"];
$comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();
$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Alumno - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --title-red: #b91c1c;
            --label-blue: #1e40af;
            --border-gray: #cbd5e1;
            --bg-gray: #f1f5f9;
        }
        body { background-color: var(--bg-gray); font-size: 0.85rem; }
        .ficha-container { background: #fff; border: 1px solid var(--border-gray); padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .ficha-title { color: var(--title-red); font-weight: 800; text-align: center; text-transform: uppercase; border-bottom: 2px solid var(--border-gray); padding-bottom: 10px; margin-bottom: 20px; font-size: 1rem; }
        
        .form-section { border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .form-section:last-child { border-bottom: none; }
        
        .field-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px; align-items: center; }
        .field-group { display: flex; align-items: center; gap: 5px; }
        .field-group label { font-weight: 700; color: var(--label-blue); white-space: nowrap; font-size: 0.75rem; }
        .field-group input, .field-group select, .field-group textarea { font-size: 0.8rem; padding: 3px 6px; border: 1px solid var(--border-gray); border-radius: 2px; }
        
        .label-red { color: var(--title-red) !important; font-weight: 800 !important; }
        .checkbox-group { display: flex; align-items: center; gap: 4px; font-weight: 700; color: var(--title-red); font-size: 0.75rem; }

        input[type="text"]:focus, select:focus { outline: none; border-color: var(--label-blue); box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1); }
        
        .section-header { font-weight: 800; color: var(--label-blue); text-transform: uppercase; margin-bottom: 12px; font-size: 0.7rem; border-left: 3px solid var(--label-blue); padding-left: 8px; }
        
        .btn-submit { background: #f8fafc; border: 1px solid var(--border-gray); padding: 6px 20px; font-weight: 700; cursor: pointer; border-radius: 3px; font-size: 0.8rem; }
        .btn-submit:hover { background: #e2e8f0; }

        /* Helpers Ancho */
        .w-60 { width: 60px; } .w-100 { width: 100px; } .w-150 { width: 150px; } .w-200 { width: 200px; } .w-250 { width: 250px; } .w-full { flex: 1; }
        
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="page-header">
                <div class="page-title">
                    <h1>Sincronización Moodle</h1>
                    <p>Alta de alumno con provisionamiento automático</p>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="ficha-container">
                <input type="hidden" name="action" value="create">
                <div class="ficha-title">Ficha de Inscripción / Alta de Alumno</div>

                <!-- SECCIÓN 1: DATOS PERSONALES -->
                <div class="form-section">
                    <div class="section-header">Datos Personales y de Control</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label class="label-red">NOMBRE *</label>
                            <input type="text" name="nombre" class="w-150" required>
                        </div>
                        <div class="field-group">
                            <label class="label-red">1º APELLIDO *</label>
                            <input type="text" name="primer_apellido" class="w-150" required>
                        </div>
                        <div class="field-group">
                            <label>2º APELLIDO</label>
                            <input type="text" name="segundo_apellido" class="w-150">
                        </div>
                        <div class="field-group">
                            <label class="label-red">NIF/NIE *</label>
                            <input type="text" name="dni" class="w-100" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>COMERCIAL</label>
                            <select name="comercial_id" class="w-150">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['nombre'] . ' ' . $c['apellidos'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" name="bloqueado" id="bloqueado">
                            <label for="bloqueado">BLOQUEADO</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="restringido" id="restringido">
                            <label for="restringido">RESTRINGIDO</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="baja" id="baja">
                            <label for="baja">BAJA</label>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>ALIAS</label>
                            <input type="text" name="alias" class="w-150">
                        </div>
                        <div class="field-group">
                            <label>F. NACIMIENTO</label>
                            <input type="date" name="fecha_nacimiento" style="padding: 1px 6px;">
                        </div>
                        <div class="field-group">
                            <label>Nº S. SOCIAL</label>
                            <input type="text" name="seguridad_social" class="w-100">
                        </div>
                        <div class="field-group">
                            <label>PROFESIÓN</label>
                            <input type="text" name="profesion" class="w-150">
                        </div>
                        <div class="field-group">
                            <label>SEXO</label>
                            <select name="sexo">
                                <option>Hombre</option>
                                <option>Mujer</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>ESTUDIOS</label>
                            <select name="estudios">
                                <option value="">---</option>
                                <option>Sin estudios</option>
                                <option>Primaria</option>
                                <option>ESO/EGB</option>
                                <option>Bachillerato</option>
                                <option>FP Grado Medio</option>
                                <option>FP Grado Superior</option>
                                <option>Universidad</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: DIRECCIÓN -->
                <div class="form-section">
                    <div class="section-header">Domicilio y Contacto</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label>TIPO VÍA</label>
                            <select name="tipo_via" class="w-100">
                                <option>Calle</option>
                                <option>Avenida</option>
                                <option>Plaza</option>
                                <option>Carretera</option>
                                <option>Paseo</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>NOMBRE VÍA</label>
                            <input type="text" name="nombre_via" class="w-250">
                        </div>
                        <div class="field-group">
                            <label>TIPO Nº</label>
                            <select name="tipo_num">
                                <option>Número</option>
                                <option>Kilómetro</option>
                                <option>Sin Número</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Nº</label>
                            <input type="text" name="num_domicilio" class="w-60">
                        </div>
                        <div class="field-group">
                            <label>CALIFICADOR</label>
                            <select name="calificador">
                                <option value=""></option>
                                <option>Bis</option>
                                <option>Duplicado</option>
                                <option>Moderno</option>
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group"><label>BLOQUE</label><input type="text" name="bloque" class="w-60"></div>
                        <div class="field-group"><label>PORTAL</label><input type="text" name="portal" class="w-60"></div>
                        <div class="field-group"><label>ESCALERA</label><input type="text" name="escalera" class="w-60"></div>
                        <div class="field-group"><label>PLANTA</label><input type="text" name="planta" class="w-60"></div>
                        <div class="field-group"><label>PUERTA</label><input type="text" name="puerta" class="w-60"></div>
                        <div class="field-group"><label>COMPLEMENTO</label><input type="text" name="complemento" class="w-100"></div>
                    </div>
                    
                    <!-- Campo oculto para domicilio_full si es necesario -->
                    <input type="hidden" name="domicilio_full" id="domicilio_full">

                    <div class="field-row" style="margin-top: 10px;">
                        <div class="field-group"><label>CP</label><input type="text" name="cp" class="w-60"></div>
                        <div class="field-group"><label>LOCALIDAD</label><input type="text" name="localidad" class="w-150"></div>
                        <div class="field-group">
                            <label>PROVINCIA</label>
                            <select name="provincia" class="w-150">
                                <option value="">---</option>
                                <?php foreach ($provincias as $p): ?>
                                    <option value="<?= $p ?>"><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field-row" style="margin-top: 15px;">
                        <div class="field-group">
                            <label>TELÉFONO</label>
                            <input type="text" name="telefono" class="w-100">
                        </div>
                        <div class="field-group">
                            <label>MÓVIL / EMPRESA</label>
                            <input type="text" name="telefono_empresa" class="w-100">
                        </div>
                        <div class="field-group">
                            <label class="label-red">EMAIL PRINCIPAL *</label>
                            <input type="email" name="email" class="w-200" required>
                        </div>
                        <div class="field-group">
                            <label>EMAIL SECUNDARIO</label>
                            <input type="email" name="email_2" class="w-200">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: PLATAFORMA MOODLE -->
                <div class="form-section" style="background: #fdf2f2;">
                    <div class="section-header">Configuración Moodle (Provisionamiento)</div>
                    <div class="field-row">
                        <div class="field-group">
                            <label>USUARIO PLAT.</label>
                            <input type="text" name="plat_usuario" class="w-150" placeholder="Auto si vacío">
                        </div>
                        <div class="field-group">
                            <label>CLAVE PLAT.</label>
                            <input type="text" name="plat_clave" class="w-150" placeholder="Auto si vacío">
                        </div>
                        <div class="checkbox-group" style="margin-left: 20px;">
                            <input type="checkbox" name="enviar_emails" id="enviar_emails" checked>
                            <label for="enviar_emails">NOTIFICAR POR EMAIL</label>
                        </div>
                    </div>
                    <p style="font-size: 0.7rem; color: #666; margin-top: 5px;">* Si no indicas usuario/clave, se generarán según el DNI y se sincronizarán con Moodle automáticamente.</p>
                </div>

                <!-- SECCIÓN 4: INFORMACIÓN LABORAL Y OTROS -->
                <div class="form-section">
                    <div class="section-header">Información Adicional</div>
                    <div class="field-row">
                         <div class="field-group">
                            <label>ÚLTIMA EMPRESA</label>
                            <select name="ultima_empresa_id" class="w-200">
                                <option value="">---</option>
                                <?php foreach ($empresas as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= $e['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>CENTRO TRABAJO</label>
                            <input type="text" name="centro_trabajo" class="w-200">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label>OBSERVACIONES</label>
                            <textarea name="observaciones" rows="3" style="width: 500px; border: 1px solid var(--border-gray); border-radius: 2px;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- BOTÓN SUBMIT -->
                <div style="text-align: right; padding: 20px 0;">
                    <button type="submit" class="btn-submit" style="background: var(--title-red); color: white; border: none; padding: 10px 40px;">
                        REGISTRAR ALUMNO Y SINCRONIZAR MOODLE
                    </button>
                    <div style="margin-top: 10px; font-size: 0.7rem; color: #666;">
                        Se creará el registro en la base de datos local y se enviará la petición a la API de Moodle.
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Script simple para concatenar domicilio si se desea
        document.querySelector('form').addEventListener('submit', function() {
            const via = document.querySelector('[name="tipo_via"]').value;
            const nombre = document.querySelector('[name="nombre_via"]').value;
            const num = document.querySelector('[name="num_domicilio"]').value;
            document.getElementById('domicilio_full').value = via + ' ' + nombre + ', ' + num;
        });
    </script>
</body>
</html>
