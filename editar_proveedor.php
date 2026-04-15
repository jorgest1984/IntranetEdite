<?php
// editar_proveedor.php - Ficha de Proveedor (edición)
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'contabilidad';
$error = '';
$success = '';

$proveedor_id = intval($_GET['id'] ?? 0);
if ($proveedor_id <= 0) {
    header("Location: facturas.php");
    exit();
}

// Cargar datos del proveedor
$proveedor = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$proveedor_id]);
    $proveedor = $stmt->fetch();
    
    if (!$proveedor) {
        $error = "Proveedor no encontrado.";
    }
} catch (Exception $e) {
    $error = "Error al cargar proveedor: " . $e->getMessage();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $proveedor) {
    try {
        $stmt = $pdo->prepare("
            UPDATE proveedores SET
                nombre = ?,
                cif = ?,
                tipo_material = ?,
                aprobado = ?,
                es_proveedor = ?,
                es_editor = ?,
                direccion = ?,
                cp = ?,
                poblacion = ?,
                provincia = ?,
                movil = ?,
                contacto = ?,
                telefono = ?,
                fax = ?,
                email = ?,
                web = ?,
                web_usuario = ?,
                web_password = ?,
                materiales = ?,
                responsable = ?,
                forma_pago = ?,
                observaciones = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['nombre'] ?? '',
            $_POST['cif'] ?? '',
            $_POST['tipo_material'] ?? null,
            isset($_POST['aprobado']) ? 1 : 0,
            isset($_POST['es_proveedor']) ? 1 : 0,
            isset($_POST['es_editor']) ? 1 : 0,
            $_POST['direccion'] ?? null,
            $_POST['cp'] ?? null,
            $_POST['poblacion'] ?? null,
            $_POST['provincia'] ?? null,
            $_POST['movil'] ?? null,
            $_POST['contacto'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['fax'] ?? null,
            $_POST['email'] ?? null,
            $_POST['web'] ?? null,
            $_POST['web_usuario'] ?? null,
            $_POST['web_password'] ?? null,
            $_POST['materiales'] ?? null,
            $_POST['responsable'] ?? null,
            $_POST['forma_pago'] ?? null,
            $_POST['observaciones'] ?? null,
            $proveedor_id
        ]);
        
        $success = "Proveedor actualizado correctamente.";
        
        // Recargar datos
        $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$proveedor_id]);
        $proveedor = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// Origen para el botón Volver
$from = $_GET['from'] ?? '';
$factura_id = $_GET['factura_id'] ?? '';
$volver_url = 'facturas.php';
if ($from === 'ficha_factura' && $factura_id) {
    $volver_url = "ficha_factura.php?id=" . intval($factura_id);
}

// Cargar lista de usuarios para el selector de Responsable
$usuarios_list = [];
try {
    $usuarios_list = $pdo->query("SELECT id, nombre, apellidos FROM usuarios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}

$provincias = [
    'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila', 'Badajoz', 'Barcelona',
    'Burgos', 'Cáceres', 'Cádiz', 'Cantabria', 'Castellón', 'Ciudad Real', 'Córdoba', 'Cuenca',
    'Gerona', 'Granada', 'Guadalajara', 'Guipúzcoa', 'Huelva', 'Huesca', 'Islas Baleares',
    'Jaén', 'La Coruña', 'La Rioja', 'Las Palmas', 'León', 'Lérida', 'Lugo', 'Madrid', 'Málaga',
    'Murcia', 'Navarra', 'Orense', 'Palencia', 'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife',
    'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Teruel', 'Toledo', 'Valencia', 'Valladolid',
    'Vizcaya', 'Zamora', 'Zaragoza', 'Ceuta', 'Melilla'
];

$tipos_material = ['Material didáctico', 'Material de oficina', 'Equipamiento informático', 'Servicios externos', 'Imprenta', 'Limpieza', 'Mantenimiento', 'Otros'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Proveedor #<?= $proveedor_id ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/facturas.css">
    <style>
        /* ===== FICHA PROVEEDOR STYLES ===== */
        .ficha-proveedor-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0;
            max-width: 960px;
            margin: 0 auto 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .ficha-proveedor-header {
            background: #c0392b;
            color: white;
            text-align: center;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .ficha-proveedor-body {
            padding: 0;
        }

        .fp-row {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            min-height: 38px;
        }

        .fp-row:last-child {
            border-bottom: none;
        }

        .fp-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #c0392b;
            padding: 4px 10px;
            white-space: nowrap;
            min-width: 110px;
        }

        .fp-input {
            flex: 1;
            border: none;
            border-left: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 0.85rem;
            color: #1e293b;
            background: transparent;
            height: 36px;
            font-family: 'Inter', sans-serif;
        }

        .fp-input:focus {
            outline: none;
            background: #fef9c3;
        }

        .fp-input-group {
            display: flex;
            flex: 1;
            align-items: center;
        }

        .fp-input-group .fp-label {
            min-width: auto;
        }

        .fp-select {
            flex: 1;
            border: none;
            border-left: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 0.85rem;
            color: #1e293b;
            background: transparent;
            height: 36px;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
        }

        .fp-select:focus {
            outline: none;
            background: #fef9c3;
        }

        .fp-checkbox-group {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 0 10px;
            flex: 1;
        }

        .fp-checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .fp-section-label {
            background: #fef2f2;
            border-bottom: 2px solid #c0392b;
            padding: 5px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #c0392b;
        }

        .fp-textarea {
            width: 100%;
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            font-size: 0.85rem;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
            min-height: 100px;
            resize: vertical;
            background: transparent;
        }

        .fp-textarea:focus {
            outline: none;
            background: #fef9c3;
        }

        .fp-textarea-container {
            padding: 0;
        }

        .fp-footer {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .fp-btn {
            padding: 0.5rem 2rem;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #94a3b8;
            border-radius: 2px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .fp-btn-save {
            background: #f8fafc;
            color: #334155;
        }

        .fp-btn-save:hover {
            background: #e2e8f0;
        }

        .fp-btn-back {
            background: #f8fafc;
            color: #334155;
        }

        .fp-btn-back:hover {
            background: #e2e8f0;
        }

        .fp-btn-assign {
            float: right;
            margin: 4px 10px;
            background: transparent;
            border: none;
            color: #475569;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px;
        }

        .fp-btn-assign:hover {
            color: #c0392b;
        }

        .fp-email-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: #fbbf24;
            border-radius: 50%;
            margin-left: 6px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .fp-email-icon svg {
            width: 14px;
            height: 14px;
            fill: #92400e;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Ficha de Proveedor</h1>
                <p>Modificar datos del proveedor #<?= $proveedor_id ?></p>
            </div>
            <div>
                <a href="<?= htmlspecialchars($volver_url) ?>" class="btn btn-invoice-secondary" style="text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver
                </a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="ficha-alert ficha-alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="ficha-alert ficha-alert-success">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($proveedor): ?>
        <div class="ficha-proveedor-card">
            <div class="ficha-proveedor-header">FICHA DE PROVEEDOR</div>

            <form id="formEditarProveedor" method="POST">
                <div class="ficha-proveedor-body">

                    <!-- Proveedor + CIF -->
                    <div class="fp-row">
                        <span class="fp-label">Proveedor:</span>
                        <input type="text" name="nombre" class="fp-input" style="flex: 2;" 
                               value="<?= htmlspecialchars($proveedor['nombre'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">CIF:</span>
                        <input type="text" name="cif" class="fp-input" style="flex: 0.8;" 
                               value="<?= htmlspecialchars($proveedor['cif'] ?? '') ?>">
                    </div>

                    <!-- Tipo material + Checkboxes -->
                    <div class="fp-row">
                        <span class="fp-label">Tipo material:</span>
                        <select name="tipo_material" class="fp-select" style="flex: 0.8;">
                            <option value="">-- Seleccione --</option>
                            <?php foreach($tipos_material as $tm): ?>
                                <option value="<?= $tm ?>" <?= ($proveedor['tipo_material'] ?? '') === $tm ? 'selected' : '' ?>>
                                    <?= $tm ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="fp-checkbox-group">
                            <label>
                                <strong>Aprobado:</strong>
                                <input type="checkbox" name="aprobado" value="1" <?= !empty($proveedor['aprobado']) ? 'checked' : '' ?>>
                            </label>
                            <label>
                                <strong>Proveedor:</strong>
                                <input type="checkbox" name="es_proveedor" value="1" <?= !empty($proveedor['es_proveedor']) ? 'checked' : '' ?>>
                            </label>
                            <label>
                                <strong>Editor:</strong>
                                <input type="checkbox" name="es_editor" value="1" <?= !empty($proveedor['es_editor']) ? 'checked' : '' ?>>
                            </label>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="fp-row">
                        <span class="fp-label">Dirección:</span>
                        <input type="text" name="direccion" class="fp-input" 
                               value="<?= htmlspecialchars($proveedor['direccion'] ?? '') ?>">
                    </div>

                    <!-- CP + Localidad + Provincia + Móvil -->
                    <div class="fp-row">
                        <span class="fp-label">Código Postal:</span>
                        <input type="text" name="cp" class="fp-input" style="flex: 0.5;" 
                               value="<?= htmlspecialchars($proveedor['cp'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">Localidad:</span>
                        <input type="text" name="poblacion" class="fp-input" style="flex: 1;" 
                               value="<?= htmlspecialchars($proveedor['poblacion'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">Provincia:</span>
                        <select name="provincia" class="fp-select" style="flex: 0.8;">
                            <option value="">-- Seleccione --</option>
                            <?php foreach($provincias as $prov): ?>
                                <option value="<?= $prov ?>" <?= ($proveedor['provincia'] ?? '') === $prov ? 'selected' : '' ?>>
                                    <?= $prov ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="fp-label" style="min-width: auto;">Móvil:</span>
                        <input type="text" name="movil" class="fp-input" style="flex: 0.6;" 
                               value="<?= htmlspecialchars($proveedor['movil'] ?? '') ?>">
                    </div>

                    <!-- Contacto + Teléfono + Fax + Email -->
                    <div class="fp-row">
                        <span class="fp-label">Contacto:</span>
                        <input type="text" name="contacto" class="fp-input" style="flex: 1;" 
                               value="<?= htmlspecialchars($proveedor['contacto'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">Teléfono:</span>
                        <input type="text" name="telefono" class="fp-input" style="flex: 0.6;" 
                               value="<?= htmlspecialchars($proveedor['telefono'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">Fax:</span>
                        <input type="text" name="fax" class="fp-input" style="flex: 0.5;" 
                               value="<?= htmlspecialchars($proveedor['fax'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">E-mail:</span>
                        <input type="text" name="email" class="fp-input" style="flex: 1;" 
                               value="<?= htmlspecialchars($proveedor['email'] ?? '') ?>">
                        <a href="mailto:<?= htmlspecialchars($proveedor['email'] ?? '') ?>" class="fp-email-icon" title="Enviar email">
                            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        </a>
                    </div>

                    <!-- Página web -->
                    <div class="fp-row">
                        <span class="fp-label">Página web:</span>
                        <input type="text" name="web" class="fp-input" 
                               value="<?= htmlspecialchars($proveedor['web'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <!-- Datos acceso -->
                    <div class="fp-section-label">Datos acceso</div>
                    <div class="fp-row">
                        <span class="fp-label" style="padding-left: 30px;">Usuario:</span>
                        <input type="text" name="web_usuario" class="fp-input" style="flex: 0.6;" 
                               value="<?= htmlspecialchars($proveedor['web_usuario'] ?? '') ?>">
                        <span class="fp-label" style="min-width: auto;">Contraseña:</span>
                        <input type="text" name="web_password" class="fp-input" style="flex: 0.6;" 
                               value="<?= htmlspecialchars($proveedor['web_password'] ?? '') ?>">
                        <div style="flex: 1;"></div>
                    </div>

                    <!-- Materiales -->
                    <div class="fp-section-label">Materiales que actualmente pedimos a este proveedor:</div>
                    <div class="fp-textarea-container">
                        <textarea name="materiales" class="fp-textarea" style="border: none; border-bottom: 1px solid #e2e8f0;"><?= htmlspecialchars($proveedor['materiales'] ?? '') ?></textarea>
                        <button type="button" class="fp-btn-assign">Asignar mas materiales a proveedor</button>
                    </div>

                    <!-- Responsable + Forma de pago -->
                    <div class="fp-row" style="clear: both;">
                        <span class="fp-label">Responsable:</span>
                        <select name="responsable" class="fp-select" style="flex: 0.6;">
                            <option value="">-- Seleccione --</option>
                            <?php foreach($usuarios_list as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($proveedor['responsable'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="fp-label" style="min-width: auto;">Forma de pago:</span>
                        <input type="text" name="forma_pago" class="fp-input" style="flex: 1.5;" 
                               value="<?= htmlspecialchars($proveedor['forma_pago'] ?? '') ?>">
                    </div>

                    <!-- Observaciones -->
                    <div class="fp-section-label">Observaciones:</div>
                    <div class="fp-textarea-container">
                        <textarea name="observaciones" class="fp-textarea" style="border: none;"><?= htmlspecialchars($proveedor['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="fp-footer">
                    <a href="<?= htmlspecialchars($volver_url) ?>" class="fp-btn fp-btn-back" style="text-decoration: none;">Volver</a>
                    <button type="submit" class="fp-btn fp-btn-save">Guardar registro</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>
