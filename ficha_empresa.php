<?php
// ficha_empresa.php - Ficha editable de Empresa (edición y alta nueva)
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$is_new = isset($_GET['new']);

$empresa_id = intval($_GET['id'] ?? 0);

// En modo nuevo, no redirigimos aunque id == 0
if (!$is_new && $empresa_id <= 0) {
    header("Location: buscar_empresas.php");
    exit();
}

// Cargar comerciales para el select
$comerciales = [];
try {
    $stmtCom = $pdo->query("SELECT u.id, u.nombre, u.apellidos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre LIKE '%Comercial%' AND u.activo = 1 ORDER BY u.nombre ASC");
    $comerciales = $stmtCom->fetchAll();
} catch (Exception $e) {}

// Provincias
$provincias = [
    'A Coruña','Álava','Albacete','Alicante','Almería','Asturias','Ávila','Badajoz',
    'Islas Baleares','Barcelona','Burgos','Cáceres','Cádiz','Cantabria','Castellón',
    'Ciudad Real','Córdoba','Cuenca','Girona','Granada','Guadalajara','Gipuzkoa',
    'Huelva','Huesca','Jaén','La Rioja','Las Palmas','León','Lleida','Lugo','Madrid',
    'Málaga','Murcia','Navarra','Ourense','Palencia','Pontevedra','Salamanca',
    'Santa Cruz de Tenerife','Segovia','Sevilla','Soria','Tarragona','Teruel',
    'Toledo','Valencia','Valladolid','Vizcaya','Zamora','Zaragoza','Ceuta','Melilla'
];

// Empresa: vacía para alta nueva, cargada para edición
$empresa = $is_new ? [] : null;

if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$empresa) {
            $error = "Empresa no encontrada.";
        }
    } catch (Exception $e) {
        $error = "Error al cargar la empresa: " . $e->getMessage();
    }
}

// Datos comunes del formulario
$campos = [
    'nombre'                       => trim($_POST['nombre']                        ?? ''),
    'cif'                          => trim($_POST['cif']                           ?? ''),
    'email'                        => trim($_POST['email']                         ?? ''),
    'telefono'                     => trim($_POST['telefono']                      ?? ''),
    'localidad'                    => trim($_POST['localidad']                     ?? ''),
    'cp'                           => trim($_POST['cp']                            ?? ''),
    'actividad'                    => trim($_POST['actividad']                     ?? ''),
    'provincia'                    => trim($_POST['provincia']                     ?? ''),
    'contacto_nombre'              => trim($_POST['contacto_nombre']               ?? ''),
    'contacto_telefono'            => trim($_POST['contacto_telefono']            ?? ''),
    'es_vigilante'                 => isset($_POST['es_vigilante'])              ? 1 : 0,
    'es_adhesion'                  => isset($_POST['es_adhesion'])               ? 1 : 0,
    'es_gestora'                   => isset($_POST['es_gestora'])                ? 1 : 0,
    'es_mercadolid'                => isset($_POST['es_mercadolid'])             ? 1 : 0,
    'es_promax'                    => isset($_POST['es_promax'])                 ? 1 : 0,
    'no_llamar'                    => isset($_POST['no_llamar'])                 ? 1 : 0,
    'en_reserva'                   => isset($_POST['en_reserva'])                ? 1 : 0,
    'bloqueado'                    => isset($_POST['bloqueado'])                 ? 1 : 0,
    'comercial_id'                 => !empty($_POST['comercial_id'])              ? intval($_POST['comercial_id']) : null,
    'sector'                       => trim($_POST['sector']                        ?? ''),
    'rlt'                          => trim($_POST['rlt']                           ?? ''),
    'redes_total_participantes'    => intval($_POST['redes_total_participantes']   ?? 0),
    'redes_colectivos_prioritarios'=> intval($_POST['redes_colectivos_prioritarios']?? 0),
    'redes_tiempo_parcial'         => intval($_POST['redes_tiempo_parcial']        ?? 0),
    'redes_temporal'               => intval($_POST['redes_temporal']            ?? 0),
    'redes_mujeres'                => intval($_POST['redes_mujeres']             ?? 0),
    'redes_mayores_45'             => intval($_POST['redes_mayores_45']          ?? 0),
    'rep_legal_nombre'             => trim($_POST['rep_legal_nombre']              ?? ''),
    'rep_legal_apellidos'          => trim($_POST['rep_legal_apellidos']           ?? ''),
    'rep_legal_sexo'               => trim($_POST['rep_legal_sexo']                ?? ''),
    'rep_legal_nif'                => trim($_POST['rep_legal_nif']                 ?? ''),
    'rep_legal_cargo'              => trim($_POST['rep_legal_cargo']               ?? ''),
    'rep_legal_email'              => trim($_POST['rep_legal_email']               ?? '')
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($campos['nombre'])) {
        $error = "La razón social es obligatoria.";
    } else {
        try {
            if ($is_new) {
                // INSERT nueva empresa
                $cols_keys = array_keys($campos);
                $placeholders = implode(',', array_fill(0, count($cols_keys), '?'));
                $cols_str = implode(',', $cols_keys);
                
                $stmt = $pdo->prepare("INSERT INTO empresas ($cols_str) VALUES ($placeholders)");
                $stmt->execute(array_values($campos));
                $nuevo_id = $pdo->lastInsertId();
                header("Location: ficha_empresa.php?id=" . $nuevo_id . "&saved=1");
                exit();
            } else {
                // UPDATE empresa existente
                $set_clause = implode('=?, ', array_keys($campos)) . '=?';
                $stmt = $pdo->prepare("UPDATE empresas SET $set_clause WHERE id=?");
                $stmt->execute([...array_values($campos), $empresa_id]);
                $success = "Empresa actualizada correctamente.";
                
                // Recargar datos
                $stmt2 = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
                $stmt2->execute([$empresa_id]);
                $empresa = $stmt2->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Mensaje de empresa recién creada
if (isset($_GET['saved']) && $empresa) {
    $success = "Empresa creada correctamente.";
}

// URL de retorno
$from = $_GET['from'] ?? 'buscar_empresas';
$volver_url = ($from === 'comerciales') ? 'comerciales.php' : 'buscar_empresas.php?buscar=1';
$current_page = 'buscar_empresas.php';
$page_mode_label = $is_new ? 'NUEVA EMPRESA' : 'FICHA DE EMPRESA';
$page_title = $is_new ? 'Nueva Empresa' : 'Ficha Empresa';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== FICHA EMPRESA PREMIUM STYLES ===== */
        .header-premium {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
        }

        .fe-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            max-width: 980px;
            margin: 0 auto 2rem;
            box-shadow: var(--glass-shadow);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            overflow: hidden;
        }

        .fe-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 4px 15px rgba(0, 108, 228, 0.15);
        }

        .fe-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom.span-12 { grid-column: span 12; }
        .form-group-custom.span-8 { grid-column: span 8; }
        .form-group-custom.span-7 { grid-column: span 7; }
        .form-group-custom.span-6 { grid-column: span 6; }
        .form-group-custom.span-5 { grid-column: span 5; }
        .form-group-custom.span-4 { grid-column: span 4; }
        .form-group-custom.span-3 { grid-column: span 3; }
        .form-group-custom.span-2 { grid-column: span 2; }

        .form-group-custom label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .section-header-custom {
            grid-column: span 12;
            font-weight: 800;
            color: var(--primary-color);
            text-transform: uppercase;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            border-left: 4px solid var(--primary-color);
            padding-left: 10px;
            letter-spacing: 0.5px;
        }

        /* Checkbox groups */
        .checkbox-group-custom {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            height: 100%;
            box-sizing: border-box;
        }
        .dark-theme .checkbox-group-custom {
            background: rgba(0, 0, 0, 0.2);
        }

        .checkbox-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-color);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        /* Footer buttons container */
        .fe-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: rgba(0, 108, 228, 0.03);
            border-top: 1px solid var(--border-color);
        }

        /* Tables */
        .table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .table-premium th {
            text-align: left;
            background: rgba(0, 108, 228, 0.04);
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border-color);
            color: var(--primary-color);
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-premium td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        .table-premium tr:last-child td {
            border-bottom: none;
        }
        .table-premium tr:hover td {
            background-color: rgba(0, 108, 228, 0.01);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        @media print {
            .sidebar, .header-premium, .fe-footer .btn-glass { display: none !important; }
            .fe-card { border: none; box-shadow: none; max-width: 100%; margin: 0; }
            .fe-body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="app-container" style="display: flex; min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto; padding: 2rem;">

        <!-- HEADER PREMIUM -->
        <div class="header-premium" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="<?= htmlspecialchars($volver_url) ?>" style="text-decoration:none; color: var(--primary-color); font-weight:700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver al listado
                </a>
                <h1 style="margin-top: 0.5rem; margin-bottom: 0.25rem; font-size: 1.75rem; font-weight: 800; color: var(--text-color);"><?= $is_new ? 'Nueva Empresa' : htmlspecialchars($empresa['nombre']) ?></h1>
                <?php if (!$is_new && $empresa): ?>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">
                        CIF: <strong style="color: var(--text-color);"><?= htmlspecialchars($empresa['cif'] ?? '—') ?></strong> | ID: <strong style="color: var(--text-color);">#<?= $empresa_id ?></strong> | Registrada el <strong style="color: var(--text-color);"><?= date('d/m/Y', strtotime($empresa['creado_en'] ?? 'now')) ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($is_new || $empresa): ?>
        <div class="fe-card">
            <div class="fe-header"><?= $page_mode_label ?></div>

            <form method="POST" id="formEmpresa" style="margin:0;">
                <div class="fe-body">
                    <div class="form-grid">
                        
                        <!-- IDENTIFICACIÓN -->
                        <div class="section-header-custom">Identificación</div>

                        <!-- Razón Social + CIF -->
                        <div class="form-group-custom span-8">
                            <label>(*) Razón Social / Nombre:</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($empresa['nombre'] ?? '') ?>" required>
                        </div>
                        <div class="form-group-custom span-4">
                            <label>CIF:</label>
                            <input type="text" name="cif" value="<?= htmlspecialchars($empresa['cif'] ?? '') ?>">
                        </div>

                        <!-- Comercial + Atributos -->
                        <div class="form-group-custom span-4">
                            <label>Comercial Asignado:</label>
                            <select name="comercial_id">
                                <option value="">--- Sin asignar ---</option>
                                <?php foreach($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= ($empresa['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom span-8">
                            <label>Atributos / Estado:</label>
                            <div class="checkbox-group-custom">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="es_adhesion" value="1" <?= !empty($empresa['es_adhesion']) ? 'checked' : '' ?>>
                                    Es Adhesión
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="es_gestora" value="1" <?= !empty($empresa['es_gestora']) ? 'checked' : '' ?>>
                                    Es Gestora
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="es_mercadolid" value="1" <?= !empty($empresa['es_mercadolid']) ? 'checked' : '' ?>>
                                    Es Mercadolid
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="es_vigilante" value="1" <?= !empty($empresa['es_vigilante']) ? 'checked' : '' ?>>
                                    Vigilante
                                </label>
                            </div>
                        </div>

                        <!-- Actividad + Sector -->
                        <div class="form-group-custom span-6">
                            <label>Actividad:</label>
                            <input type="text" name="actividad" value="<?= htmlspecialchars($empresa['actividad'] ?? '') ?>">
                        </div>
                        <div class="form-group-custom span-6">
                            <label>Sector:</label>
                            <input type="text" name="sector" value="<?= htmlspecialchars($empresa['sector'] ?? '') ?>">
                        </div>

                        <!-- UBICACIÓN -->
                        <div class="section-header-custom">Ubicación</div>

                        <!-- Localidad + CP + Provincia -->
                        <div class="form-group-custom span-5">
                            <label>Localidad:</label>
                            <input type="text" name="localidad" value="<?= htmlspecialchars($empresa['localidad'] ?? '') ?>">
                        </div>
                        <div class="form-group-custom span-3">
                            <label>Código Postal (CP):</label>
                            <input type="text" name="cp" value="<?= htmlspecialchars($empresa['cp'] ?? '') ?>">
                        </div>
                        <div class="form-group-custom span-4">
                            <label>(*) Provincia:</label>
                            <select name="provincia">
                                <option value="">--- Seleccione ---</option>
                                <?php foreach($provincias as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>"
                                        <?= ($empresa['provincia'] ?? '') === $prov ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- CONTACTO -->
                        <div class="section-header-custom">Contacto</div>

                        <!-- Teléfono + Email -->
                        <div class="form-group-custom span-4">
                            <label>Teléfono:</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>">
                        </div>
                        <div class="form-group-custom span-8">
                            <label>E-mail:</label>
                            <div style="display: flex; gap: 8px; align-items: center; width: 100%;">
                                <input type="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" style="flex: 1;">
                                <?php if (!empty($empresa['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>"
                                   class="btn"
                                   style="background: #fef08a; border: 1px solid #facc15; padding: 0.6rem; border-radius: 8px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; box-sizing: border-box;"
                                   title="Enviar email">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="#854d0e">
                                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Persona de contacto + Teléfono contacto -->
                        <div class="form-group-custom span-7">
                            <label>Persona de Contacto:</label>
                            <input type="text" name="contacto_nombre" value="<?= htmlspecialchars($empresa['contacto_nombre'] ?? '') ?>">
                        </div>
                        <div class="form-group-custom span-5">
                            <label>Teléfono de Contacto:</label>
                            <input type="text" name="contacto_telefono" value="<?= htmlspecialchars($empresa['contacto_telefono'] ?? '') ?>">
                        </div>

                        <!-- RLT -->
                        <div class="form-group-custom span-12">
                            <label>RLT (Representante Legal de los Trabajadores):</label>
                            <input type="text" name="rlt" value="<?= htmlspecialchars($empresa['rlt'] ?? '') ?>" placeholder="Ej. Sí, constituido / No dispone...">
                        </div>

                    </div>
                </div>

                <!-- FOOTER -->
                <div class="fe-footer">
                    <a href="<?= htmlspecialchars($volver_url) ?>" class="btn btn-glass">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Volver
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align: middle;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Guardar Registro
                    </button>
                </div>

            </form>
        </div>

        <!-- Llamadas recientes de esta empresa -->
        <?php if (!empty($llamadas_empresa)): ?>
        <div class="fe-card" style="margin-top: 2rem;">
            <div class="fe-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                Últimas llamadas registradas (<?= count($llamadas_empresa) ?>)
            </div>
            <div style="padding: 1.5rem; overflow-x: auto;">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Resultado</th>
                            <th>Comercial</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($llamadas_empresa as $ll): ?>
                        <tr>
                            <td style="font-weight: 600; white-space: nowrap;"><?= htmlspecialchars($ll['fecha'] ?? '') ?></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($ll['hora'] ?? '') ?></td>
                            <td>
                                <span style="background: rgba(0, 108, 228, 0.08); color: var(--primary-color); padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(0, 108, 228, 0.15);">
                                    <?= htmlspecialchars($ll['resultado'] ?? '—') ?>
                                </span>
                            </td>
                            <td style="font-weight: 500;">
                                <?= htmlspecialchars(($ll['comercial_nombre'] ?? '') . ' ' . ($ll['comercial_apellidos'] ?? '')) ?>
                            </td>
                            <td style="color: var(--text-muted); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($ll['observaciones'] ?? '') ?>">
                                <?= htmlspecialchars($ll['observaciones'] ?? '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-error"><?= htmlspecialchars($error ?: 'Empresa no encontrada.') ?></div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
