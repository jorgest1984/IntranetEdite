<?php
// importar_alumnos.php - Importación masiva de Alumnos / Trabajadores desde CSV / Excel
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_TUTOR, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';
$simulacion = false;
$datos_preview = [];
$errores_validacion = [];
$total_filas = 0;
$insertados = 0;
$actualizados = 0;
$duplicados = 0;

// Descargar plantilla CSV de muestra
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_importar_alumnos.csv');
    $output = fopen('php://output', 'w');
    // BOM UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'Nombre',
        'Primer Apellido',
        'Segundo Apellido',
        'DNI / NIE',
        'Email Personal',
        'Teléfono',
        'Empresa / Centro de Trabajo',
        'Localidad',
        'Provincia',
        'CP',
        'Colectivo (Régimen General, Autónomo, Desempleado)',
        'Puesto de Trabajo'
    ], ';');
    
    // Ejemplo
    fputcsv($output, [
        'María',
        'García',
        'López',
        '12345678Z',
        'maria.garcia@email.com',
        '655112233',
        'EMPRESA EJEMPLO S.L.',
        'Valladolid',
        'Valladolid',
        '47001',
        'Régimen General',
        'Administrativo'
    ], ';');
    
    fclose($output);
    exit();
}

// Cargar comerciales
$comerciales = $pdo->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE (r.nombre LIKE '%Comercial%' OR r.nombre LIKE '%Jefe%') AND u.activo = 1 ORDER BY u.nombre ASC")->fetchAll();

// Procesar importación o simulación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $simulacion = isset($_POST['simular']);
    $comercial_id_asignado = !empty($_POST['comercial_id']) ? intval($_POST['comercial_id']) : null;
    $file = $_FILES['archivo_csv'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo. Código: " . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            $error = "Por favor suba un archivo en formato CSV o TXT delimitado por comas, punto y coma o tabulaciones.";
        } else {
            $raw = file_get_contents($file['tmp_name']);
            $primera_linea = strtok($raw, "\n");
            
            // Detectar delimitador
            $delimitador = ';';
            $count_semicolon = substr_count($primera_linea, ';');
            $count_comma = substr_count($primera_linea, ',');
            $count_tab = substr_count($primera_linea, "\t");
            
            if ($count_tab > $count_semicolon && $count_tab > $count_comma) {
                $delimitador = "\t";
            } elseif ($count_comma > $count_semicolon) {
                $delimitador = ',';
            }
            
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle) {
                // Saltar BOM UTF-8
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $num_linea = 0;
                while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                    $num_linea++;
                    // Omitir cabecera
                    if ($num_linea === 1 && (strripos($data[0] ?? '', 'Nombre') !== false || strripos($data[3] ?? '', 'DNI') !== false)) {
                        continue;
                    }
                    
                    if (empty(array_filter($data))) continue; // Línea vacía
                    
                    $total_filas++;
                    $nombre = trim($data[0] ?? '');
                    $primer_apellido = trim($data[1] ?? '');
                    $segundo_apellido = trim($data[2] ?? '');
                    $dni = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $data[3] ?? '')));
                    $email = trim($data[4] ?? '');
                    $telefono = trim($data[5] ?? '');
                    $empresa_nombre = trim($data[6] ?? '');
                    $localidad = trim($data[7] ?? '');
                    $provincia = trim($data[8] ?? '');
                    $cp = trim($data[9] ?? '');
                    $colectivo = trim($data[10] ?? 'Régimen General');
                    $puesto = trim($data[11] ?? '');

                    if (empty($nombre)) {
                        $errores_validacion[] = "Línea $num_linea: El nombre es obligatorio.";
                        continue;
                    }

                    $apellidos_combined = trim("$primer_apellido $segundo_apellido");
                    if (empty($apellidos_combined)) {
                        $apellidos_combined = '—';
                    }

                    $dni_val = !empty($dni) ? $dni : null;
                    $email_val = !empty($email) ? $email : null;

                    // Comprobar si ya existe alumno por DNI o Email
                    $existe = false;
                    if (!empty($dni_val)) {
                        $stCh = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? LIMIT 1");
                        $stCh->execute([$dni_val]);
                        if ($stCh->fetch()) $existe = true;
                    }
                    if (!$existe && !empty($email_val)) {
                        $stChE = $pdo->prepare("SELECT id FROM alumnos WHERE email = ? LIMIT 1");
                        $stChE->execute([$email_val]);
                        if ($stChE->fetch()) $existe = true;
                    }

                    if ($existe) {
                        $duplicados++;
                    }

                    $datos_preview[] = [
                        'linea' => $num_linea,
                        'nombre' => $nombre,
                        'primer_apellido' => $primer_apellido,
                        'segundo_apellido' => $segundo_apellido,
                        'dni' => $dni,
                        'email' => $email,
                        'telefono' => $telefono,
                        'empresa' => $empresa_nombre,
                        'localidad' => $localidad,
                        'provincia' => $provincia,
                        'colectivo' => $colectivo,
                        'duplicado' => $existe
                    ];

                    if (!$simulacion) {
                        try {
                            if ($existe) {
                                // Actualizar si existe
                                $stUp = $pdo->prepare("UPDATE alumnos SET nombre=?, apellidos=?, primer_apellido=?, segundo_apellido=?, email=?, telefono=?, centro_trabajo=?, localidad=?, provincia=?, cp=?, colectivo=?, puesto_trabajo=?, comercial_id=COALESCE(?, comercial_id) WHERE dni=? OR email=?");
                                $stUp->execute([$nombre, $apellidos_combined, $primer_apellido, $segundo_apellido, $email_val, $telefono, $empresa_nombre, $localidad, $provincia, $cp, $colectivo, $puesto, $comercial_id_asignado, $dni_val, $email_val]);
                                $actualizados++;
                            } else {
                                // Insertar nuevo
                                $stIns = $pdo->prepare("INSERT INTO alumnos (nombre, apellidos, primer_apellido, segundo_apellido, dni, email, telefono, centro_trabajo, localidad, provincia, cp, colectivo, puesto_trabajo, comercial_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stIns->execute([$nombre, $apellidos_combined, $primer_apellido, $segundo_apellido, $dni_val, $email_val, $telefono, $empresa_nombre, $localidad, $provincia, $cp, $colectivo, $puesto, $comercial_id_asignado]);
                                $insertados++;
                            }
                        } catch (PDOException $e) {
                            $errores_validacion[] = "Línea $num_linea Error BD: " . $e->getMessage();
                        }
                    }
                }
                fclose($handle);

                if ($simulacion) {
                    $success = "Simulación completada. Se leyeron $total_filas alumnos/trabajadores ($duplicados existentes). Ningún dato ha sido modificado aún.";
                } else {
                    $success = "Importación finalizada con éxito. Alumnos insertados: $insertados | Actualizados: $actualizados | Total procesados: $total_filas.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Alumnos / Trabajadores - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
        .card-import { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .drag-area { border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2.5rem; text-align: center; background: #f1f5f9; cursor: pointer; transition: all 0.2s; }
        .drag-area:hover { border-color: #0284c7; background: #f0f9ff; }
        .btn-action { background: #0284c7; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-action:hover { background: #0369a1; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
        .table-preview { width: 100%; border-collapse: collapse; margin-top: 1.5rem; font-size: 0.85rem; }
        .table-preview th, .table-preview td { padding: 10px 12px; border: 1px solid #e2e8f0; text-align: left; }
        .table-preview th { background: #f8fafc; font-weight: 700; color: #0f172a; }
        .badge-dup { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .badge-ok { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="app-container" style="display:flex; min-height:100vh;">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content" style="flex:1; padding: 2rem;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
            <div>
                <a href="buscar_alumnos.php" style="text-decoration:none; color:#0284c7; font-weight:600; font-size:0.85rem;">← Volver a Alumnos</a>
                <h1 style="margin-top:0.5rem; color:#0f172a; font-size:1.75rem; font-weight:800;">Importación Masiva de Alumnos / Trabajadores</h1>
                <p style="color:#64748b; margin:0; font-size:0.9rem;">Importe alumnos y participantes desde su intranet anterior vía CSV / Excel.</p>
            </div>
            <a href="importar_alumnos.php?download_template=1" class="btn-action btn-secondary">
                📥 Descargar Plantilla CSV Ejemplo
            </a>
        </div>

        <?php if ($success): ?>
            <div style="background:#dcfce7; color:#166534; padding:1rem; border-radius:8px; border:1px solid #bbf7d0; margin-bottom:1.5rem; font-weight:600;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:8px; border:1px solid #fecaca; margin-bottom:1.5rem; font-weight:600;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card-import">
            <form method="POST" enctype="multipart/form-data">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="font-weight:700; color:#334155; display:block; margin-bottom:0.5rem;">Asignar Comercial a los Alumnos Importados:</label>
                        <select name="comercial_id" style="width:100%; padding:0.6rem; border:1px solid #cbd5e1; border-radius:6px; background:#fff;">
                            <option value="">--- Sin comercial asignado / Mantener existente ---</option>
                            <?php foreach ($comerciales as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display:flex; align-items:center; gap: 10px; margin-top:1.8rem;">
                        <label style="font-weight:700; color:#334155; cursor:pointer; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="simular" value="1" <?= $simulacion ? 'checked' : '' ?> style="width:18px; height:18px;">
                            Simular importación (Modo prueba sin guardar cambios en base de datos)
                        </label>
                    </div>
                </div>

                <div class="drag-area" onclick="document.getElementById('archivo_csv').click();">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#0284c7" stroke-width="2" style="margin:0 auto 1rem auto; display:block;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                    </svg>
                    <h3 style="margin:0; font-size:1.1rem; color:#0f172a;">Haga clic o arrastre aquí su archivo CSV / Excel (.csv, .txt)</h3>
                    <p style="color:#64748b; font-size:0.85rem; margin-top:0.4rem;">Soporta formatos delimitados por punto y coma (;), coma (,) o tabulador.</p>
                    <input type="file" id="archivo_csv" name="archivo_csv" accept=".csv,.txt" style="display:none;" onchange="this.form.submit();">
                </div>

                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="submit" class="btn-action">
                        🚀 Procesar e Importar Alumnos
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($datos_preview)): ?>
            <div class="card-import">
                <h3 style="margin-top:0; color:#0f172a; font-size:1.2rem; font-weight:800;">Vista Previa de Registros Procesados (<?= count($datos_preview) ?>)</h3>
                
                <table class="table-preview">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Estado</th>
                            <th>Nombre Completo</th>
                            <th>DNI</th>
                            <th>Empresa / Centro</th>
                            <th>Localidad</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Colectivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_preview as $row): ?>
                            <tr>
                                <td><?= $row['linea'] ?></td>
                                <td>
                                    <?php if ($row['duplicado']): ?>
                                        <span class="badge-dup">Existente / Actualizar</span>
                                    <?php else: ?>
                                        <span class="badge-ok">Nuevo</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['nombre'] . ' ' . $row['primer_apellido'] . ' ' . $row['segundo_apellido']) ?></strong></td>
                                <td><code><?= htmlspecialchars($row['dni'] ?: '—') ?></code></td>
                                <td><?= htmlspecialchars($row['empresa'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['localidad'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['telefono'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['email'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['colectivo'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
