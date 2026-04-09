<?php
// nuevo_alumno.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        // Recoger datos
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

        // Validaciones básicas
        if (empty($data['nombre']) || empty($data['primer_apellido']) || empty($data['dni'])) {
            throw new Exception("Nombre, Primer Apellido y NIF son obligatorios.");
        }

        // Insertar en DB
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO alumnos ($columns) VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $nuevoId = $pdo->lastInsertId();

        audit_log($pdo, 'ALUMNO_CREADO_DETALLADO', 'alumnos', $nuevoId, null, ['dni' => $data['dni']]);
        
        header("Location: ficha_alumno.php?id=$nuevoId&success=1");
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Listas para dropdowns
$provincias = ["Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"];

$comerciales = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();
$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC LIMIT 100")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
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
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="ficha-container">
                <div class="ficha-title">FICHA DE TRABAJADOR / ALUMNO</div>
                
                <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Cabecera: ID, Comercial y Estados -->
                    <div class="form-section" style="background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0;">
                        <div class="field-row">
                            <div class="field-group">
                                <input type="text" class="w-60" disabled placeholder="AUTO">
                            </div>
                            <div class="field-group">
                                <label>Comercial:</label>
                                <select name="comercial_id" class="w-250">
                                    <option value="">---</option>
                                    <?php foreach($comerciales as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="checkbox-group">
                                <label class="label-red">BLOQUEADO:</label>
                                <input type="checkbox" name="bloqueado">
                            </div>
                            <div class="checkbox-group">
                                <label class="label-red">RESTRINGIDO:</label>
                                <input type="checkbox" name="restringido">
                            </div>
                            <div class="checkbox-group">
                                <label class="label-red">BAJA:</label>
                                <input type="checkbox" name="baja">
                            </div>
                        </div>
                    </div>

                    <!-- Datos Personales -->
                    <div class="form-section">
                        <div class="field-row">
                            <div class="field-group"><label>Nombre:</label> <input type="text" name="nombre" class="w-150" required></div>
                            <div class="field-group"><label>Apellido 1:</label> <input type="text" name="primer_apellido" class="w-150" required></div>
                            <div class="field-group"><label>Apellido 2:</label> <input type="text" name="segundo_apellido" class="w-150"></div>
                            <div class="field-group"><label>Alias:</label> <input type="text" name="alias" class="w-150"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>NIF:</label> <input type="text" name="dni" class="w-150" required placeholder="00000000A"></div>
                            <div class="field-group"><label>Fecha nacimiento:</label> <input type="date" name="fecha_nacimiento" class="w-150"></div>
                            <div class="field-group"><label>Nº S.S.:</label> <input type="text" name="seguridad_social" class="w-150"></div>
                            <div class="field-group"><label>Profesión:</label> <input type="text" name="profesion" class="w-200"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>Sexo:</label> 
                                <select name="sexo">
                                    <option value="Mujer">Mujer</option>
                                    <option value="Hombre">Hombre</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="field-group"><label>Estudios:</label> 
                                <select name="estudios" class="w-250">
                                    <option value="">---</option>
                                    <option value="Primarios">Primarios</option>
                                    <option value="Secundarios">Secundarios</option>
                                    <option value="Grado Medio">Formación Profesional Grado Medio</option>
                                    <option value="Grado Superior">Formación Profesional Grado Superior</option>
                                    <option value="Universitarios">Universitarios</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Domicilio -->
                    <div class="form-section">
                        <div class="section-header">Dirección y Domicilio</div>
                        <div class="field-row">
                            <div class="field-group">
                                <label>Tipo de vía:</label>
                                <select name="tipo_via">
                                    <option value="Calle">Calle</option>
                                    <option value="Avenida">Avenida</option>
                                    <option value="Plaza">Plaza</option>
                                    <option value="Paseo">Paseo</option>
                                    <option value="Ctra">Carretera</option>
                                </select>
                            </div>
                            <div class="field-group"><label>Nombre de vía:</label> <input type="text" name="nombre_via" class="w-200"></div>
                            <div class="field-group">
                                <label>Tipo de num.:</label>
                                <select name="tipo_num">
                                    <option value="Numérico">Numérico</option>
                                    <option value="Kilómetro">Kilómetro</option>
                                    <option value="Sin número">Sin número</option>
                                </select>
                            </div>
                            <div class="field-group"><label>Nº domicilio:</label> <input type="text" name="num_domicilio" class="w-60"></div>
                            <div class="field-group">
                                <label>Calificador:</label>
                                <select name="calificador">
                                    <option value="">-</option>
                                    <option value="Bis">Bis</option>
                                    <option value="Duplicado">Duplicado</option>
                                </select>
                            </div>
                            <div class="field-group"><label>Bloque:</label> <input type="text" name="bloque" class="w-60"></div>
                            <div class="field-group"><label>Escalera:</label> <input type="text" name="escalera" class="w-60"></div>
                            <div class="field-group"><label>Planta:</label> <input type="text" name="planta" class="w-60"></div>
                            <div class="field-group"><label>Puerta:</label> <input type="text" name="puerta" class="w-60"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>Complemento:</label> <input type="text" name="complemento" class="w-250"></div>
                            <div class="field-group"><label>Domicilio (Línea 2):</label> <input type="text" name="domicilio_full" class="w-250" placeholder="Opcional"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>CP:</label> <input type="text" name="cp" class="w-60"></div>
                            <div class="field-group"><label>Localidad:</label> <input type="text" name="localidad" class="w-200"></div>
                            <div class="field-group"><label>Provincia:</label> 
                                <select name="provincia" class="w-200">
                                    <option value="">---</option>
                                    <?php foreach($provincias as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contacto y Horarios -->
                    <div class="form-section">
                        <div class="section-header">Contacto y Disponibilidad</div>
                        <div class="field-row">
                            <div class="field-group"><label>Teléfono:</label> <input type="text" name="telefono" class="w-150"></div>
                            <div class="field-group"><label>Móvil:</label> <input type="text" name="telefono_empresa" class="w-150"></div>
                            <div class="field-group"><label>Mañanas:</label> <input type="text" name="mananas_desde" class="w-60" placeholder="09:00"> - <input type="text" name="mananas_hasta" class="w-60" placeholder="14:00"></div>
                            <div class="field-group"><label>Tardes:</label> <input type="text" name="tardes_desde" class="w-60" placeholder="16:00"> - <input type="text" name="tardes_hasta" class="w-60" placeholder="20:00"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>E-mail:</label> <input type="email" name="email" class="w-250"></div>
                            <div class="field-group"><label>E-mail 2:</label> <input type="email" name="email_2" class="w-250"></div>
                            <div class="field-group"><label>Sólo los:</label> <input type="text" name="solo_los" class="w-200" placeholder="Ej: Lunes, Miércoles"></div>
                        </div>
                    </div>

                    <!-- Laboral y Plataforma -->
                    <div class="form-section">
                        <div class="section-header">Datos Laborales e IDs de Plataforma</div>
                        <div class="field-row">
                            <div class="field-group"><label>Última Empresa:</label> 
                                <select name="ultima_empresa_id" class="w-250">
                                    <option value="">---</option>
                                    <?php foreach($empresas as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group"><label>Centro de trabajo:</label> <input type="text" name="centro_trabajo" class="w-100"></div>
                            <div class="checkbox-group"><label>Enviar e-mails:</label> <input type="checkbox" name="enviar_emails" checked></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>Usuario Plataforma:</label> <input type="text" name="plat_usuario" class="w-150"></div>
                            <div class="field-group"><label>Clave:</label> <input type="text" name="plat_clave" class="w-100"></div>
                            <div class="field-group"><label>Id Plat 2015:</label> <input type="text" name="id_plat_2015" class="w-100"></div>
                            <div class="field-group"><label>Id Plat 2016:</label> <input type="text" name="id_plat_2016" class="w-100"></div>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="form-section">
                        <div class="section-header">Observaciones</div>
                        <textarea name="observaciones" style="width: 100%; height: 80px;"></textarea>
                    </div>

                    <!-- Entrega Material -->
                    <div class="form-section">
                        <div class="section-header">Domicilio Diferente para Entregas</div>
                        <div class="field-row">
                            <div class="field-group"><label>A la atención de:</label> <input type="text" name="entrega_atencion" class="w-250"></div>
                        </div>
                        <div class="field-row">
                            <div class="field-group"><label>Domicilio:</label> <input type="text" name="entrega_domicilio" class="w-250"></div>
                            <div class="field-group"><label>CP:</label> <input type="text" name="entrega_cp" class="w-60"></div>
                            <div class="field-group"><label>Localidad:</label> <input type="text" name="entrega_localidad" class="w-200"></div>
                            <div class="field-group"><label>Provincia:</label> 
                                <select name="entrega_provincia" class="w-200">
                                    <option value="">---</option>
                                    <?php foreach($provincias as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 20px; padding-bottom: 20px;">
                        <button type="submit" class="btn-submit">Insertar registro</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
