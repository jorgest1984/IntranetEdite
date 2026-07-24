<?php
// importar_patricia_directo.php - Script rápido para importar los 442 alumnos de Patricia Vaquero
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_JEFE_COMERCIAL, ROLE_COMERCIAL])) {
    header("Location: home.php");
    exit();
}

$csv_path = __DIR__ . '/alumnos_patricia_vaquero.csv';
$error = '';
$success = '';
$procesados = 0;
$insertados = 0;
$actualizados = 0;

// Buscar ID de Patricia Vaquero
$stPat = $pdo->query("SELECT id, nombre, apellidos FROM usuarios WHERE (nombre LIKE '%Patricia%' AND apellidos LIKE '%Vaquero%') OR nombre LIKE '%Patricia%' LIMIT 1");
$patricia = $stPat->fetch(PDO::FETCH_ASSOC);
$comercial_id = $patricia['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_importacion'])) {
    if (!file_exists($csv_path)) {
        $error = "El archivo CSV de la lista de Patricia Vaquero no existe en la raíz.";
    } else {
        $handle = fopen($csv_path, 'r');
        if ($handle) {
            // Saltar BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);
            
            $num_linea = 0;
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $num_linea++;
                if ($num_linea === 1) continue; // Cabecera
                if (empty(array_filter($data))) continue;
                
                $procesados++;
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
                $colectivo = trim($data[10] ?? 'Desempleado');
                $puesto = trim($data[11] ?? '');

                $apellidos_combined = trim("$primer_apellido $segundo_apellido");
                if (empty($apellidos_combined)) {
                    $apellidos_combined = '—';
                }

                $dni_val = !empty($dni) ? $dni : null;
                $email_val = !empty($email) ? $email : null;

                // Comprobar existencia
                $existe_id = null;
                if (!empty($dni_val)) {
                    $stEx = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? LIMIT 1");
                    $stEx->execute([$dni_val]);
                    $existe_id = $stEx->fetchColumn();
                }
                if (!$existe_id && !empty($email_val)) {
                    $stExE = $pdo->prepare("SELECT id FROM alumnos WHERE email = ? LIMIT 1");
                    $stExE->execute([$email_val]);
                    $existe_id = $stExE->fetchColumn();
                }

                try {
                    if ($existe_id) {
                        $stUp = $pdo->prepare("UPDATE alumnos SET nombre=?, apellidos=?, primer_apellido=?, segundo_apellido=?, email=?, centro_trabajo=?, provincia=?, colectivo=?, comercial_id=COALESCE(?, comercial_id) WHERE id=?");
                        $stUp->execute([$nombre, $apellidos_combined, $primer_apellido, $segundo_apellido, $email_val, $empresa_nombre, $provincia, $colectivo, $comercial_id, $existe_id]);
                        $actualizados++;
                    } else {
                        $stIns = $pdo->prepare("INSERT INTO alumnos (nombre, apellidos, primer_apellido, segundo_apellido, dni, email, telefono, centro_trabajo, localidad, provincia, cp, colectivo, puesto_trabajo, comercial_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stIns->execute([$nombre, $apellidos_combined, $primer_apellido, $segundo_apellido, $dni_val, $email_val, $telefono, $empresa_nombre, $localidad, $provincia, $cp, $colectivo, $puesto, $comercial_id]);
                        $insertados++;
                    }
                } catch (PDOException $e) {
                    $error_msg = "Línea $num_linea (" . htmlspecialchars($nombre . ' ' . $apellidos_combined) . "): " . $e->getMessage();
                    $errores_detalle[] = $error_msg;
                }
            }
            fclose($handle);
            $err_summary = !empty($errores_detalle) ? "<br><br><strong>Avisos/Errores en filas (" . count($errores_detalle) . "):</strong><br>" . implode("<br>", array_slice($errores_detalle, 0, 10)) : "";
            $success = "¡Importación masiva de Patricia Vaquero completada! Registros procesados: $procesados (Nuevos: $insertados, Actualizados: $actualizados) asignados a Patricia Vaquero (ID #$comercial_id).$err_summary";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importación Lista Patricia Vaquero - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
<div class="app-container" style="display:flex; min-height:100vh;">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content" style="flex:1; padding: 2rem;">
        
        <h1 style="color:#0f172a; font-weight:800;">Importar Lista de Alumnos de Patricia Vaquero</h1>
        <p style="color:#64748b;">Se han detectado <strong>442 alumnos</strong> preparados para importar y asignar automáticamente a Patricia Vaquero.</p>

        <?php if ($patricia): ?>
            <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:1rem; border-radius:8px; margin-bottom:1.5rem; color:#0369a1; font-weight:600;">
                👤 Comercial Detectado: <strong><?= htmlspecialchars($patricia['nombre'] . ' ' . $patricia['apellidos']) ?></strong> (ID #<?= $patricia['id'] ?>)
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background:#dcfce7; color:#166534; padding:1rem; border-radius:8px; border:1px solid #bbf7d0; margin-bottom:1.5rem; font-weight:700;">
                <?= htmlspecialchars($success) ?>
            </div>
            <a href="buscar_alumnos.php" class="btn" style="background:#0284c7; color:white; padding:0.8rem 1.5rem; border-radius:6px; font-weight:700; text-decoration:none;">Ver Alumnos en el Sistema →</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:8px; border:1px solid #fecaca; margin-bottom:1.5rem; font-weight:600;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="ejecutar_importacion" value="1">
                <button type="submit" class="btn" style="background:#16a34a; color:white; padding:1rem 2rem; font-size:1.1rem; border-radius:8px; font-weight:800; border:none; cursor:pointer;">
                    ⚡ Importar los 442 Alumnos de Patricia Vaquero Ahora
                </button>
            </form>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
