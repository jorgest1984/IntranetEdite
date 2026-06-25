<?php
// ficha_empresa.php - Ficha editable de Empresa
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

$empresa_id = intval($_GET['id'] ?? 0);
if ($empresa_id <= 0) {
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

// Cargar empresa
$empresa = null;
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

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $empresa) {
    try {
        $stmt = $pdo->prepare("
            UPDATE empresas SET
                nombre             = ?,
                cif                = ?,
                email              = ?,
                telefono           = ?,
                localidad          = ?,
                cp                 = ?,
                actividad          = ?,
                provincia          = ?,
                contacto_nombre    = ?,
                contacto_telefono  = ?,
                es_vigilante       = ?,
                es_adhesion        = ?,
                es_gestora         = ?,
                es_mercadolid      = ?,
                comercial_id       = ?,
                sector             = ?,
                rlt                = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['nombre']            ?? ''),
            trim($_POST['cif']               ?? ''),
            trim($_POST['email']             ?? ''),
            trim($_POST['telefono']          ?? ''),
            trim($_POST['localidad']         ?? ''),
            trim($_POST['cp']                ?? ''),
            trim($_POST['actividad']         ?? ''),
            trim($_POST['provincia']         ?? ''),
            trim($_POST['contacto_nombre']   ?? ''),
            trim($_POST['contacto_telefono'] ?? ''),
            isset($_POST['es_vigilante'])  ? 1 : 0,
            isset($_POST['es_adhesion'])   ? 1 : 0,
            isset($_POST['es_gestora'])    ? 1 : 0,
            isset($_POST['es_mercadolid']) ? 1 : 0,
            !empty($_POST['comercial_id']) ? intval($_POST['comercial_id']) : null,
            trim($_POST['sector']            ?? ''),
            trim($_POST['rlt']               ?? ''),
            $empresa_id
        ]);

        $success = "Empresa actualizada correctamente.";

        // Recargar datos
        $stmt2 = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt2->execute([$empresa_id]);
        $empresa = $stmt2->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = "Error al guardar: " . $e->getMessage();
    }
}

// URL de retorno
$from = $_GET['from'] ?? 'buscar_empresas';
$volver_url = ($from === 'comerciales') ? 'comerciales.php' : 'buscar_empresas.php?buscar=1';
$current_page = 'buscar_empresas.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Empresa - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== FICHA EMPRESA STYLES ===== */
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }

        .ficha-empresa-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0;
            max-width: 980px;
            margin: 0 auto 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .ficha-empresa-header {
            background: #000080;
            color: white;
            text-align: center;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .fe-row {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            min-height: 36px;
            flex-wrap: wrap;
        }
        .fe-row:last-child { border-bottom: none; }

        .fe-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #000080;
            padding: 4px 10px;
            white-space: nowrap;
            min-width: 90px;
            flex-shrink: 0;
        }

        .fe-input {
            flex: 1;
            border: none;
            border-left: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 0.83rem;
            color: #1e293b;
            background: transparent;
            height: 34px;
            font-family: 'Inter', sans-serif;
            min-width: 60px;
        }
        .fe-input:focus {
            outline: none;
            background: #fef9c3;
        }

        .fe-select {
            flex: 1;
            border: none;
            border-left: 1px solid #e2e8f0;
            padding: 4px 8px;
            font-size: 0.83rem;
            color: #1e293b;
            background: transparent;
            height: 34px;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            min-width: 80px;
        }
        .fe-select:focus { outline: none; background: #fef9c3; }

        .fe-checkbox-row {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            padding: 6px 10px;
            flex-wrap: wrap;
        }
        .fe-checkbox-row label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            white-space: nowrap;
        }
        .fe-checkbox-row input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .fe-section-label {
            background: #eff6ff;
            border-bottom: 1px solid #bfdbfe;
            border-top: 1px solid #bfdbfe;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 800;
            color: #000080;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .fe-footer {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-top: 2px solid #e2e8f0;
        }

        .fe-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 28px;
            font-size: 0.82rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .fe-btn-save {
            background: #000080;
            color: #fff;
            border: none;
        }
        .fe-btn-save:hover { background: #00007a; }
        .fe-btn-save:active { transform: scale(0.98); }

        .fe-btn-back {
            background: #fff;
            color: #475569;
            border: 1.5px solid #cbd5e1;
        }
        .fe-btn-back:hover { background: #f8fafc; border-color: #94a3b8; }

        .alert-success {
            background: #dcfce7; border: 1px solid #86efac; color: #166534;
            padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem;
            font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
            padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem;
            font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;
        }

        .empresa-meta {
            font-size: 0.7rem; color: #94a3b8; padding: 4px 12px;
            border-bottom: 1px solid #f1f5f9; background: #fafafa;
        }

        @media print {
            .sidebar, .page-header, .fe-footer .fe-btn-back { display: none !important; }
        }
    </style>
</head>
<body>
<div class="app-container" style="display: flex; min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto; padding: 1.25rem;">

        <div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <a href="<?= htmlspecialchars($volver_url) ?>" class="fe-btn fe-btn-back" style="font-size: 0.78rem; padding: 6px 18px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Volver al listado
            </a>
            <?php if ($empresa): ?>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                    ID #<?= $empresa_id ?> — Registrada: <?= date('d/m/Y', strtotime($empresa['creado_en'] ?? 'now')) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert-success">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($empresa): ?>
        <div class="ficha-empresa-card">
            <div class="ficha-empresa-header">FICHA DE EMPRESA</div>

            <form method="POST" id="formEmpresa">

                <!-- IDENTIFICACIÓN -->
                <div class="fe-section-label">Identificación</div>

                <!-- Razón Social + CIF -->
                <div class="fe-row">
                    <span class="fe-label">(*) Razón Social:</span>
                    <input type="text" name="nombre" class="fe-input" style="flex: 3;"
                           value="<?= htmlspecialchars($empresa['nombre'] ?? '') ?>" required>
                    <span class="fe-label" style="min-width: auto;">CIF:</span>
                    <input type="text" name="cif" class="fe-input" style="flex: 1;"
                           value="<?= htmlspecialchars($empresa['cif'] ?? '') ?>">
                </div>

                <!-- Comercial -->
                <div class="fe-row">
                    <span class="fe-label">Comercial:</span>
                    <select name="comercial_id" class="fe-select" style="flex: 2;">
                        <option value="">--- Sin asignar ---</option>
                        <?php foreach($comerciales as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= ($empresa['comercial_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="fe-checkbox-row" style="flex: 3;">
                        <label>
                            <input type="checkbox" name="es_adhesion" value="1" <?= !empty($empresa['es_adhesion']) ? 'checked' : '' ?>>
                            Es Adhesión
                        </label>
                        <label>
                            <input type="checkbox" name="es_gestora" value="1" <?= !empty($empresa['es_gestora']) ? 'checked' : '' ?>>
                            Es Gestora
                        </label>
                        <label>
                            <input type="checkbox" name="es_mercadolid" value="1" <?= !empty($empresa['es_mercadolid']) ? 'checked' : '' ?>>
                            Es Mercadolid
                        </label>
                        <label>
                            <input type="checkbox" name="es_vigilante" value="1" <?= !empty($empresa['es_vigilante']) ? 'checked' : '' ?>>
                            Vigilante
                        </label>
                    </div>
                </div>

                <!-- Actividad + Sector -->
                <div class="fe-row">
                    <span class="fe-label">Actividad:</span>
                    <input type="text" name="actividad" class="fe-input" style="flex: 2;"
                           value="<?= htmlspecialchars($empresa['actividad'] ?? '') ?>">
                    <span class="fe-label" style="min-width: auto;">Sector:</span>
                    <input type="text" name="sector" class="fe-input" style="flex: 2;"
                           value="<?= htmlspecialchars($empresa['sector'] ?? '') ?>">
                </div>

                <!-- UBICACIÓN -->
                <div class="fe-section-label">Ubicación</div>

                <!-- Localidad + CP + Provincia -->
                <div class="fe-row">
                    <span class="fe-label">Localidad:</span>
                    <input type="text" name="localidad" class="fe-input" style="flex: 2;"
                           value="<?= htmlspecialchars($empresa['localidad'] ?? '') ?>">
                    <span class="fe-label" style="min-width: auto;">CP:</span>
                    <input type="text" name="cp" class="fe-input" style="flex: 0.6; max-width: 90px;"
                           value="<?= htmlspecialchars($empresa['cp'] ?? '') ?>">
                    <span class="fe-label" style="min-width: auto;">(*) Provincia:</span>
                    <select name="provincia" class="fe-select" style="flex: 1.5;">
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
                <div class="fe-section-label">Contacto</div>

                <!-- Teléfono + Email -->
                <div class="fe-row">
                    <span class="fe-label">Teléfono:</span>
                    <input type="text" name="telefono" class="fe-input" style="flex: 1;"
                           value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>">
                    <span class="fe-label" style="min-width: auto;">E-mail:</span>
                    <input type="email" name="email" class="fe-input" style="flex: 2;"
                           value="<?= htmlspecialchars($empresa['email'] ?? '') ?>">
                    <?php if (!empty($empresa['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>"
                       style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;background:#fbbf24;border-radius:50%;margin:0 8px;flex-shrink:0;"
                       title="Enviar email">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="#92400e">
                            <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Persona de contacto + Teléfono contacto -->
                <div class="fe-row">
                    <span class="fe-label">Persona contacto:</span>
                    <input type="text" name="contacto_nombre" class="fe-input" style="flex: 2;"
                           value="<?= htmlspecialchars($empresa['contacto_nombre'] ?? '') ?>">
                    <span class="fe-label" style="min-width: auto;">Teléfono contacto:</span>
                    <input type="text" name="contacto_telefono" class="fe-input" style="flex: 1;"
                           value="<?= htmlspecialchars($empresa['contacto_telefono'] ?? '') ?>">
                </div>

                <!-- RLT -->
                <div class="fe-row">
                    <span class="fe-label">RLT:</span>
                    <input type="text" name="rlt" class="fe-input"
                           value="<?= htmlspecialchars($empresa['rlt'] ?? '') ?>"
                           placeholder="Representante Legal de los Trabajadores">
                </div>

                <!-- FOOTER -->
                <div class="fe-footer">
                    <a href="<?= htmlspecialchars($volver_url) ?>" class="fe-btn fe-btn-back">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Volver
                    </a>
                    <button type="submit" class="fe-btn fe-btn-save">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Guardar registro
                    </button>
                </div>

            </form>
        </div>

        <!-- Llamadas recientes de esta empresa -->
        <?php
        $llamadas_empresa = [];
        try {
            $stmtL = $pdo->prepare("
                SELECT ts.fecha, ts.hora, ts.resultado, ts.observaciones,
                       u.nombre as comercial_nombre, u.apellidos as comercial_apellidos
                FROM tutorias_seguimiento ts
                LEFT JOIN usuarios u ON ts.usuario_id = u.id
                WHERE ts.empresa_id = ?
                ORDER BY ts.fecha DESC, ts.hora DESC
                LIMIT 10
            ");
            $stmtL->execute([$empresa_id]);
            $llamadas_empresa = $stmtL->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        ?>
        <?php if (!empty($llamadas_empresa)): ?>
        <div style="max-width: 980px; margin: 0 auto 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
            <div style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 0.6rem 1rem; font-size: 0.8rem; font-weight: 800; color: #000080; text-transform: uppercase; letter-spacing: 0.5px;">
                Últimas llamadas registradas (<?= count($llamadas_empresa) ?>)
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.78rem;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 6px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #000080; font-weight: 700;">Fecha</th>
                        <th style="padding: 6px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #000080; font-weight: 700;">Hora</th>
                        <th style="padding: 6px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #000080; font-weight: 700;">Resultado</th>
                        <th style="padding: 6px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #000080; font-weight: 700;">Comercial</th>
                        <th style="padding: 6px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #000080; font-weight: 700;">Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($llamadas_empresa as $ll): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 5px 10px;"><?= htmlspecialchars($ll['fecha'] ?? '') ?></td>
                        <td style="padding: 5px 10px;"><?= htmlspecialchars($ll['hora'] ?? '') ?></td>
                        <td style="padding: 5px 10px;">
                            <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 600;">
                                <?= htmlspecialchars($ll['resultado'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="padding: 5px 10px; color: #475569;">
                            <?= htmlspecialchars(($ll['comercial_nombre'] ?? '') . ' ' . ($ll['comercial_apellidos'] ?? '')) ?>
                        </td>
                        <td style="padding: 5px 10px; color: #64748b; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($ll['observaciones'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php else: ?>
            <div class="alert-error"><?= htmlspecialchars($error ?: 'Empresa no encontrada.') ?></div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
