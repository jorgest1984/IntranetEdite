<?php
// importar_empresas.php - Importación masiva de empresas desde CSV / Excel
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_JEFE_COMERCIAL, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';
$simulacion = false;
$datos_preview = [];
$errores_validacion = [];
$total_filas = 0;
$insertadas = 0;
$actualizadas = 0;
$duplicadas = 0;

// Descargar plantilla CSV de muestra
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_importar_empresas.csv');
    $output = fopen('php://output', 'w');
    // Escribir BOM UTF-8 para compatibilidad Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'Nombre / Razón Social',
        'CIF',
        'Email',
        'Teléfono',
        'Localidad',
        'Provincia',
        'CP',
        'Actividad',
        'Sector',
        'Persona Contacto',
        'Teléfono Contacto',
        'Es Promax (SI/NO)',
        'Es Gestoría (SI/NO)'
    ], ';');
    
    // Fila de ejemplo
    fputcsv($output, [
        'EMPRESA EJEMPLO S.L.',
        'B12345678',
        'contacto@empresaejemplo.com',
        '983001122',
        'Valladolid',
        'Valladolid',
        '47001',
        'Formación y Servicios',
        'Servicios',
        'Juan Pérez',
        '600112233',
        'NO',
        'NO'
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
                    if ($num_linea === 1 && (strripos($data[0] ?? '', 'Nombre') !== false || strripos($data[0] ?? '', 'Razón') !== false)) {
                        continue;
                    }
                    
                    if (empty(array_filter($data))) continue; // Línea vacía
                    
                    $total_filas++;
                    $nombre = trim($data[0] ?? '');
                    $cif = strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $data[1] ?? '')));
                    $email = trim($data[2] ?? '');
                    $telefono = trim($data[3] ?? '');
                    $localidad = trim($data[4] ?? '');
                    $provincia = trim($data[5] ?? '');
                    $cp = trim($data[6] ?? '');
                    $actividad = trim($data[7] ?? '');
                    $sector = trim($data[8] ?? '');
                    $contacto_nombre = trim($data[9] ?? '');
                    $contacto_telefono = trim($data[10] ?? '');
                    $es_promax = (strtoupper(trim($data[11] ?? '')) === 'SI' || trim($data[11] ?? '') === '1') ? 1 : 0;
                    $es_gestora = (strtoupper(trim($data[12] ?? '')) === 'SI' || trim($data[12] ?? '') === '1') ? 1 : 0;

                    if (empty($nombre)) {
                        $errores_validacion[] = "Línea $num_linea: El nombre / razón social es obligatorio.";
                        continue;
                    }

                    // Comprobar si ya existe por CIF o Nombre
                    $existe = false;
                    if (!empty($cif)) {
                        $stCh = $pdo->prepare("SELECT id FROM empresas WHERE cif = ? AND cif <> '' LIMIT 1");
                        $stCh->execute([$cif]);
                        if ($stCh->fetch()) $existe = true;
                    }
                    if (!$existe) {
                        $stChN = $pdo->prepare("SELECT id FROM empresas WHERE nombre = ? LIMIT 1");
                        $stChN->execute([$nombre]);
                        if ($stChN->fetch()) $existe = true;
                    }

                    if ($existe) {
                        $duplicadas++;
                    }

                    $datos_preview[] = [
                        'linea' => $num_linea,
                        'nombre' => $nombre,
                        'cif' => $cif,
                        'email' => $email,
                        'telefono' => $telefono,
                        'localidad' => $localidad,
                        'provincia' => $provincia,
                        'cp' => $cp,
                        'actividad' => $actividad,
                        'sector' => $sector,
                        'contacto_nombre' => $contacto_nombre,
                        'contacto_telefono' => $contacto_telefono,
                        'es_promax' => $es_promax,
                        'es_gestora' => $es_gestora,
                        'duplicado' => $existe
                    ];

                    if (!$simulacion) {
                        try {
                            if ($existe) {
                                // Actualizar si existe
                                $stUp = $pdo->prepare("UPDATE empresas SET email=?, telefono=?, localidad=?, provincia=?, cp=?, actividad=?, sector=?, contacto_nombre=?, contacto_telefono=?, es_promax=?, es_gestora=?, comercial_id=COALESCE(?, comercial_id) WHERE cif=? OR nombre=?");
                                $stUp->execute([$email, $telefono, $localidad, $provincia, $cp, $actividad, $sector, $contacto_nombre, $contacto_telefono, $es_promax, $es_gestora, $comercial_id_asignado, $cif, $nombre]);
                                $actualizadas++;
                            } else {
                                // Insertar nuevo
                                $stIns = $pdo->prepare("INSERT INTO empresas (nombre, cif, email, telefono, localidad, provincia, cp, actividad, sector, contacto_nombre, contacto_telefono, es_promax, es_gestora, comercial_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stIns->execute([$nombre, $cif, $email, $telefono, $localidad, $provincia, $cp, $actividad, $sector, $contacto_nombre, $contacto_telefono, $es_promax, $es_gestora, $comercial_id_asignado]);
                                $insertadas++;
                            }
                        } catch (PDOException $e) {
                            $errores_validacion[] = "Línea $num_linea Error BD: " . $e->getMessage();
                        }
                    }
                }
                fclose($handle);

                if ($simulacion) {
                    $success = "Simulación completada. Se leyeron $total_filas registros ($duplicadas existentes/duplicados). Ningún dato ha sido modificado todavía.";
                } else {
                    $success = "Importación finalizada con éxito. Registros insertados: $insertadas | Actualizados: $actualizadas | Filas procesadas: $total_filas.";
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
    <title>Importar Empresas - <?= APP_NAME ?></title>
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
                <a href="buscar_empresas.php" style="text-decoration:none; color:#0284c7; font-weight:600; font-size:0.85rem;">← Volver a Empresas</a>
                <h1 style="margin-top:0.5rem; color:#0f172a; font-size:1.75rem; font-weight:800;">Importación Masiva de Empresas</h1>
                <p style="color:#64748b; margin:0; font-size:0.9rem;">Importe el listado de empresas desde su intranet anterior vía archivo CSV o Excel.</p>
            </div>
            <a href="importar_empresas.php?download_template=1" class="btn-action btn-secondary">
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
                        <label style="font-weight:700; color:#334155; display:block; margin-bottom:0.5rem;">Asignar Comercial a las Empresas Importadas:</label>
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
                        🚀 Procesar e Importar Empresas
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
                            <th>Empresa / Razón Social</th>
                            <th>CIF</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Contacto</th>
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
                                <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                                <td><code><?= htmlspecialchars($row['cif'] ?: '—') ?></code></td>
                                <td><?= htmlspecialchars($row['localidad'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['provincia'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['telefono'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['email'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['contacto_nombre'] ?: '—') ?></td>
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
