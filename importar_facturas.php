<?php
// importar_facturas.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();
}

$active_tab = 'contabilidad';
$error = '';
$success = '';
$simulacion = false;
$datos_preview = [];
$errores_validacion = [];
$total_filas = 0;

// Procesar importación o simulación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $simulacion = isset($_POST['simular']);
    $rango = trim($_POST['rango'] ?? '');
    $file = $_FILES['archivo_excel'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo. Código: " . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $error = "Formato de archivo no soportado. Use CSV, XLS o XLSX.";
        } else {
            $contenido = [];
            
            if ($ext === 'csv') {
                // Auto-detectar delimitador: leer primera línea y contar separadores
                $raw = file_get_contents($file['tmp_name']);
                $primera_linea = strtok($raw, "\n");
                $delimitador = ';'; // Por defecto
                
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
                    // Detectar BOM UTF-8 y saltarlo
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }
                    
                    while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
                        $contenido[] = $data;
                    }
                    fclose($handle);
                }
            } elseif ($ext === 'xls') {
                // Intentar leer como XML Spreadsheet 2003 (formato de nuestra plantilla)
                $xml_content = file_get_contents($file['tmp_name']);
                if (strpos($xml_content, 'urn:schemas-microsoft-com:office:spreadsheet') !== false) {
                    // Es un XML Spreadsheet
                    $xml_content = preg_replace('/xmlns(:[a-z]+)?="[^"]*"/', '', $xml_content);
                    $xml = @simplexml_load_string($xml_content);
                    if ($xml) {
                        foreach ($xml->Worksheet->Table->Row as $row) {
                            $fila_data = [];
                            foreach ($row->Cell as $cell) {
                                $fila_data[] = (string)($cell->Data ?? '');
                            }
                            $contenido[] = $fila_data;
                        }
                    } else {
                        $error = "No se pudo leer el archivo XLS. Asegúrese de que es un formato válido o conviértalo a CSV.";
                    }
                } else {
                    $error = "Formato XLS binario no soportado. Guarde como CSV (separado por punto y coma) o use la plantilla descargable.";
                }
            } else {
                // XLSX
                $error = "Para archivos XLSX, guarde como CSV (separado por punto y coma) desde Excel. O use la plantilla descargable en formato XLS.";
            }
            
            if (empty($error) && !empty($contenido)) {
                // Nombres de columnas esperadas (13 columnas: A a M)
                $columnas = ['CIF', 'num_factura', 'importe', 'importe_imputable', 'fecha_emision', 'fecha_pago', 'referencia', 'expediente', 'num_accion', 'num_grupo', 'concepto', 'unidades', 'tipo_imputacion'];
                $num_columnas = count($columnas);
                
                // Parsear rango - por defecto A2 hasta última fila
                $inicio_fila = 2;
                $fin_fila = count($contenido);
                $inicio_col = 0; // A = 0
                $fin_col = $num_columnas - 1; // M = 12
                
                if (!empty($rango)) {
                    // Parsear rango tipo A2:M57, B3:N100, etc.
                    if (preg_match('/^([A-Z]{1,2})(\d+):([A-Z]{1,2})(\d+)$/i', $rango, $matches)) {
                        // Convertir letra(s) a índice: A=0, B=1, ..., Z=25, AA=26, etc.
                        $col_str_ini = strtoupper($matches[1]);
                        $col_str_fin = strtoupper($matches[3]);
                        
                        $inicio_col = 0;
                        for ($c = 0; $c < strlen($col_str_ini); $c++) {
                            $inicio_col = $inicio_col * 26 + (ord($col_str_ini[$c]) - ord('A') + 1);
                        }
                        $inicio_col--; // 0-indexed
                        
                        $fin_col = 0;
                        for ($c = 0; $c < strlen($col_str_fin); $c++) {
                            $fin_col = $fin_col * 26 + (ord($col_str_fin[$c]) - ord('A') + 1);
                        }
                        $fin_col--; // 0-indexed
                        
                        $inicio_fila = intval($matches[2]);
                        $fin_fila = intval($matches[4]);
                    } else {
                        $error = "Formato de rango no válido. Use el formato: A2:M57";
                    }
                }
                
                if (empty($error)) {
                    // Extraer datos del rango (filas y columnas son 1-indexed en el rango, 0-indexed en el array)
                    for ($i = $inicio_fila - 1; $i <= $fin_fila - 1 && $i < count($contenido); $i++) {
                        if (!isset($contenido[$i])) continue;
                        
                        $fila = [];
                        $fila_vacia = true;
                        
                        for ($col_idx = 0; $col_idx < $num_columnas; $col_idx++) {
                            $j = $inicio_col + $col_idx; // Posición real en la fila CSV
                            $col_name = $columnas[$col_idx];
                            $valor = trim($contenido[$i][$j] ?? '');
                            $fila[$col_name] = $valor;
                            if ($valor !== '') $fila_vacia = false;
                        }
                        
                        if (!$fila_vacia) {
                            // Validar fila
                            $errores_fila = [];
                            $num_fila_excel = $i + 1; // Número de fila en el Excel
                            
                            if (empty($fila['CIF'])) {
                                $errores_fila[] = "CIF vacío";
                            }
                            if (empty($fila['num_factura'])) {
                                $errores_fila[] = "Nº factura vacío";
                            }
                            if (!empty($fila['importe']) && !is_numeric(str_replace(',', '.', $fila['importe']))) {
                                $errores_fila[] = "Importe no válido";
                            }
                            if (!empty($fila['importe_imputable']) && !is_numeric(str_replace(',', '.', $fila['importe_imputable']))) {
                                $errores_fila[] = "Importe imputable no válido";
                            }
                            if (!empty($fila['fecha_emision']) && !preg_match('#^\d{1,2}/\d{1,2}/\d{4}$|^\d{4}-\d{2}-\d{2}$#', $fila['fecha_emision'])) {
                                $errores_fila[] = "Fecha emisión formato inválido (use DD/MM/AAAA)";
                            }
                            if (!empty($fila['fecha_pago']) && !preg_match('#^\d{1,2}/\d{1,2}/\d{4}$|^\d{4}-\d{2}-\d{2}$#', $fila['fecha_pago'])) {
                                $errores_fila[] = "Fecha pago formato inválido (use DD/MM/AAAA)";
                            }
                            
                            $fila['_fila_num'] = $num_fila_excel;
                            $fila['_errores'] = $errores_fila;
                            $datos_preview[] = $fila;
                            
                            if (!empty($errores_fila)) {
                                $errores_validacion[] = "Fila $num_fila_excel: " . implode(', ', $errores_fila);
                            }
                        }
                    }
                    
                    $total_filas = count($datos_preview);
                    
                    if ($total_filas === 0 && empty($error)) {
                        $error = "No se encontraron datos en el rango especificado. Verifique que el rango sea correcto.";
                    }
                    
                    // Si NO es simulación y no hay errores, insertar en BD
                    if (!$simulacion && empty($errores_validacion) && $total_filas > 0) {
                        try {
                            $pdo->beginTransaction();
                            
                            $insertados = 0;
                            foreach ($datos_preview as $fila) {
                                $importe = floatval(str_replace(',', '.', $fila['importe'] ?? '0'));
                                $importe_imputable = floatval(str_replace(',', '.', $fila['importe_imputable'] ?? '0'));
                                
                                // Buscar proveedor por CIF
                                $stmtProv = $pdo->prepare("SELECT id, nombre FROM proveedores WHERE cif = ? LIMIT 1");
                                $stmtProv->execute([$fila['CIF']]);
                                $prov = $stmtProv->fetch();
                                
                                $razon_social = $prov ? $prov['nombre'] : '';
                                $emisor_id = $prov ? $prov['id'] : null;
                                
                                // Convertir fecha DD/MM/YYYY → YYYY-MM-DD
                                $fecha_emision = null;
                                if (!empty($fila['fecha_emision'])) {
                                    $fe = $fila['fecha_emision'];
                                    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $fe, $m)) {
                                        $fecha_emision = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                                    } else {
                                        $fecha_emision = $fe;
                                    }
                                }
                                
                                $fecha_pago = null;
                                if (!empty($fila['fecha_pago'])) {
                                    $fp = $fila['fecha_pago'];
                                    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $fp, $m)) {
                                        $fecha_pago = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
                                    } else {
                                        $fecha_pago = $fp;
                                    }
                                }
                                
                                $stmtIns = $pdo->prepare("
                                    INSERT INTO facturas (cif, numero_factura, total, fecha_emision, fecha_pago, referencia, razon_social, tipo_emisor, emisor_id)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Proveedor', ?)
                                ");
                                $stmtIns->execute([
                                    $fila['CIF'],
                                    $fila['num_factura'],
                                    $importe,
                                    $fecha_emision,
                                    $fecha_pago,
                                    $fila['referencia'] ?? null,
                                    $razon_social,
                                    $emisor_id
                                ]);
                                $insertados++;
                            }
                            
                            $pdo->commit();
                            $success = "Se han importado correctamente <strong>$insertados</strong> facturas.";
                            $datos_preview = [];
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $error = "Error durante la importación: " . $e->getMessage();
                        }
                    } elseif (!$simulacion && !empty($errores_validacion)) {
                        $error = "No se ha realizado la importación. Se encontraron " . count($errores_validacion) . " error(es) de validación. Corrija los datos y vuelva a intentarlo.";
                    } elseif ($simulacion && $total_filas > 0) {
                        $success = "Simulación completada. Se han detectado <strong>$total_filas</strong> filas válidas" . 
                                   (!empty($errores_validacion) ? " y <strong>" . count($errores_validacion) . "</strong> error(es)." : ". Listo para importar.");
                    }
                }
            } elseif (empty($error)) {
                $error = "El archivo no contiene datos o no se pudo leer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Facturas - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/facturas.css">
    <style>
        .import-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 2rem 2.5rem;
            max-width: 960px;
            margin: 0 auto 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .import-top-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.5rem;
        }

        .btn-download-template {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.5rem;
            background: #006ce4;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-download-template:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 108, 228, 0.3);
        }

        .btn-download-template svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .import-form-row {
            display: grid;
            grid-template-columns: 160px 1fr;
            align-items: center;
            margin-bottom: 1.25rem;
            gap: 1.5rem;
        }

        .import-form-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #334155;
            text-align: right;
        }

        .import-form-field {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .import-input-file {
            font-size: 0.85rem;
            color: #475569;
        }

        .import-input-text {
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 140px;
            font-family: 'Inter', monospace;
            color: #334155;
        }

        .import-input-text:focus {
            outline: none;
            border-color: #006ce4;
            box-shadow: 0 0 0 2px rgba(0, 108, 228, 0.1);
        }

        .import-hint {
            font-size: 0.78rem;
            color: #64748b;
            line-height: 1.5;
            max-width: 450px;
        }

        .import-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
        }

        .btn-import {
            padding: 0.6rem 2rem;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-import-primary {
            background: #006ce4;
            color: white;
        }

        .btn-import-primary:hover {
            background: #0056b3;
        }

        .btn-import-secondary {
            background: #64748b;
            color: white;
        }

        .btn-import-secondary:hover {
            background: #475569;
        }

        /* Preview Table */
        .preview-section {
            margin-top: 2rem;
        }

        .preview-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .preview-badge-sim {
            background: #fef3c7;
            color: #92400e;
        }

        .preview-badge-ok {
            background: #dcfce7;
            color: #15803d;
        }

        .preview-badge-err {
            background: #fee2e2;
            color: #b91c1c;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.72rem;
        }

        .preview-table th {
            background: #1e293b;
            color: white;
            padding: 8px 8px;
            font-weight: 600;
            white-space: nowrap;
            text-align: left;
            border-right: 1px solid rgba(255,255,255,0.1);
        }

        .preview-table th:last-child {
            border-right: none;
        }

        .preview-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            white-space: nowrap;
        }

        .preview-table tr:hover td {
            background: #f8fafc;
        }

        .preview-table tr.row-error td {
            background: #fef2f2;
        }

        .preview-error-cell {
            color: #b91c1c;
            font-weight: 600;
            font-size: 0.7rem;
        }

        .errores-list {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 1rem 1.5rem;
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #b91c1c;
        }

        .errores-list ul {
            margin: 0.5rem 0 0 1rem;
            padding: 0;
        }

        .errores-list li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Importar Facturas</h1>
                <p>Carga masiva de facturas desde archivo Excel/CSV</p>
            </div>
            <div>
                <a href="facturas.php" class="btn btn-invoice-secondary" style="text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver a Facturas
                </a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="ficha-alert ficha-alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="ficha-alert ficha-alert-success">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="import-card">
            <!-- Botón Descargar Plantilla -->
            <div class="import-top-actions">
                <a href="descargar_plantilla_facturas.php" class="btn-download-template">
                    Descargar plantilla
                    <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data" id="formImportar">
                <!-- Archivo Excel -->
                <div class="import-form-row">
                    <label class="import-form-label">Archivo Excel</label>
                    <div class="import-form-field">
                        <input type="file" name="archivo_excel" accept=".csv,.xls,.xlsx" class="import-input-file" required>
                    </div>
                </div>

                <!-- Rango a importar -->
                <div class="import-form-row">
                    <label class="import-form-label">Rango a importar</label>
                    <div class="import-form-field">
                        <input type="text" name="rango" class="import-input-text" placeholder="A2:M57" value="<?= htmlspecialchars($_POST['rango'] ?? '') ?>">
                        <span class="import-hint">
                            Indicar el rango donde se encuentran los datos, sin incluir filas de encabezados ni de totales.<br>
                            Si no se indica, se importará desde la celda A2 hasta la última celda escrita.
                        </span>
                    </div>
                </div>

                <!-- Botones -->
                <div class="import-actions">
                    <button type="submit" name="importar" class="btn-import btn-import-primary">Importar</button>
                    <button type="submit" name="simular" class="btn-import btn-import-secondary">Simular importación</button>
                </div>
            </form>
        </div>

        <?php if (!empty($datos_preview)): ?>
            <div class="import-card preview-section">
                <div class="preview-title">
                    <?php if ($simulacion): ?>
                        <span class="preview-badge preview-badge-sim">SIMULACIÓN</span>
                    <?php endif; ?>
                    Vista previa de datos (<?= $total_filas ?> filas)
                    <?php if (empty($errores_validacion)): ?>
                        <span class="preview-badge preview-badge-ok">SIN ERRORES</span>
                    <?php else: ?>
                        <span class="preview-badge preview-badge-err"><?= count($errores_validacion) ?> ERROR(ES)</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($errores_validacion)): ?>
                    <div class="errores-list">
                        <strong>Errores de validación:</strong>
                        <ul>
                            <?php foreach ($errores_validacion as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div style="overflow-x: auto; margin-top: 1rem;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Fila</th>
                                <th>CIF</th>
                                <th>Nº Factura</th>
                                <th>Importe</th>
                                <th>Imp. Imputable</th>
                                <th>F. Emisión</th>
                                <th>F. Pago</th>
                                <th>Referencia</th>
                                <th>Expediente</th>
                                <th>Nº Acción</th>
                                <th>Nº Grupo</th>
                                <th>Concepto</th>
                                <th>Unidades</th>
                                <th>Tipo Imput.</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_preview as $fila): ?>
                                <tr class="<?= !empty($fila['_errores']) ? 'row-error' : '' ?>">
                                    <td><strong><?= $fila['_fila_num'] ?></strong></td>
                                    <td><?= htmlspecialchars($fila['CIF'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['num_factura'] ?? '') ?></td>
                                    <td style="text-align: right;"><?= htmlspecialchars($fila['importe'] ?? '') ?></td>
                                    <td style="text-align: right;"><?= htmlspecialchars($fila['importe_imputable'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['fecha_emision'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['fecha_pago'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['referencia'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['expediente'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['num_accion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['num_grupo'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['concepto'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['unidades'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($fila['tipo_imputacion'] ?? '') ?></td>
                                    <td class="<?= !empty($fila['_errores']) ? 'preview-error-cell' : '' ?>">
                                        <?= !empty($fila['_errores']) ? implode(', ', $fila['_errores']) : '✓' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 1rem; margin-bottom: 2rem;">
            <a href="facturas.php" class="btn btn-invoice-secondary" style="text-decoration: none;">
                Volver a Facturas
            </a>
        </div>

    </main>
</div>

</body>
</html>
