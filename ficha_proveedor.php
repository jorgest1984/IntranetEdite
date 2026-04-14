<?php
// ficha_proveedor.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'contabilidad';
$success = '';
$error = '';

// Lógica de Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert') {
    try {
        $sql = "INSERT INTO proveedores (
            nombre, cif, tipo_material, aprobado, es_proveedor, es_editor,
            direccion, cp, localidad, provincia, movil,
            contacto_nombre, telefono, fax, email, web,
            usuario_acceso, password_acceso, forma_pago, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['nombre'],
            $_POST['cif_1'] . $_POST['cif_2'] . $_POST['cif_3'],
            $_POST['tipo_material'],
            isset($_POST['aprobado']) ? 1 : 0,
            isset($_POST['es_proveedor']) ? 1 : 0,
            isset($_POST['es_editor']) ? 1 : 0,
            $_POST['direccion'],
            $_POST['cp'],
            $_POST['localidad'],
            $_POST['provincia'],
            $_POST['movil'],
            $_POST['contacto_nombre'],
            $_POST['telefono'],
            $_POST['fax'],
            $_POST['email'],
            $_POST['web'],
            $_POST['usuario_acceso'],
            $_POST['password_acceso'],
            $_POST['forma_pago'],
            $_POST['observaciones']
        ]);
        
        $success = "Proveedor insertado correctamente.";
        // Pequeño delay y redirección opcional o simplemente mensaje
    } catch (Exception $e) {
        $error = "Error al insertar: " . $e->getMessage();
    }
}

// Catálogos
$provincias = [
    "Álava", "Albacete", "Alicante", "Almería", "Asturias", "Ávila", "Badajoz", "Baleares", "Barcelona", "Burgos", "Cáceres", "Cádiz", "Cantabria", "Castellón", "Ciudad Real", "Córdoba", "Coruña (La)", "Cuenca", "Gerona", "Granada", "Guadalajara", "Guipúzcoa", "Huelva", "Huesca", "Jaén", "León", "Lérida", "Lugo", "Madrid", "Málaga", "Murcia", "Navarra", "Orense", "Palencia", "Las Palmas", "Pontevedra", "La Rioja", "Salamanca", "Santa Cruz de Tenerife", "Segovia", "Sevilla", "Soria", "Tarragona", "Teruel", "Toledo", "Valencia", "Valladolid", "Vizcaya", "Zamora", "Zaragoza", "Ceuta", "Melilla"
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Proveedor - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/proveedores.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Administración de Proveedores</h1>
                <p>Configuración detallada de ficha técnica</p>
            </div>
        </header>

        <?php if ($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #bbf7d0;">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #fecaca;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <section class="provider-card">
            <h2 class="provider-header-title">FICHA DE PROVEEDOR</h2>
            
            <form action="" method="POST" class="provider-form-grid">
                <input type="hidden" name="action" value="insert">

                <!-- Primera Fila -->
                <div class="provider-row">
                    <div class="field-group w-full">
                        <label class="field-label">Proveedor:</label>
                        <input type="text" name="nombre" class="field-input w-full" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">CIF:</label>
                        <input type="text" name="cif_1" class="field-input" style="width: 40px;" maxlength="1">
                        <input type="text" name="cif_2" class="field-input" style="width: 80px;" maxlength="8">
                        <input type="text" name="cif_3" class="field-input" style="width: 40px;" maxlength="1">
                    </div>
                </div>

                <!-- Segunda Fila -->
                <div class="provider-row">
                    <div class="field-group">
                        <label class="field-label">Tipo material:</label>
                        <select name="tipo_material" class="field-input w-md">
                            <option value="">---</option>
                            <option value="Libros">Libros</option>
                            <option value="Digital">Digital</option>
                            <option value="Servicios">Servicios</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            Aprobado: <input type="checkbox" name="aprobado">
                        </label>
                        <label class="checkbox-item">
                            Proveedor: <input type="checkbox" name="es_proveedor">
                        </label>
                        <label class="checkbox-item">
                            Editor: <input type="checkbox" name="es_editor">
                        </label>
                    </div>
                </div>

                <!-- Dirección -->
                <div class="provider-row">
                    <div class="field-group w-full">
                        <label class="field-label">Dirección:</label>
                        <input type="text" name="direccion" class="field-input w-full">
                    </div>
                </div>

                <!-- CP / Localidad / Prov / Movil -->
                <div class="provider-row">
                    <div class="field-group">
                        <label class="field-label">Código Postal:</label>
                        <input type="text" name="cp" class="field-input w-sm">
                    </div>
                    <div class="field-group w-full">
                        <label class="field-label">Localidad:</label>
                        <input type="text" name="localidad" class="field-input w-full">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Provincia:</label>
                        <select name="provincia" class="field-input w-md">
                            <option value="">---</option>
                            <?php foreach($provincias as $p): ?>
                                <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Móvil:</label>
                        <input type="text" name="movil" class="field-input w-md">
                    </div>
                </div>

                <!-- Contacto -->
                <div class="provider-row">
                    <div class="field-group w-full">
                        <label class="field-label">Contacto:</label>
                        <input type="text" name="contacto_nombre" class="field-input w-full">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Teléfono:</label>
                        <input type="text" name="telefono" class="field-input w-md">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Fax:</label>
                        <input type="text" name="fax" class="field-input w-md">
                    </div>
                    <div class="field-group">
                        <label class="field-label">E-mail:</label>
                        <input type="email" name="email" class="field-input w-lg">
                    </div>
                </div>

                <!-- Web -->
                <div class="provider-row">
                    <div class="field-group w-full">
                        <label class="field-label">Página web:</label>
                        <input type="text" name="web" class="field-input w-full">
                    </div>
                </div>

                <!-- Datos de Acceso -->
                <div class="provider-section">
                    <span class="section-title">Datos acceso</span>
                    <div class="provider-row">
                        <div class="field-group">
                            <label class="field-label">Usuario:</label>
                            <input type="text" name="usuario_acceso" class="field-input w-lg">
                        </div>
                        <div class="field-group">
                            <label class="field-label">Contraseña:</label>
                            <input type="text" name="password_acceso" class="field-input w-lg">
                        </div>
                    </div>
                </div>

                <!-- Responsable / Forma de pago -->
                <div class="provider-row" style="margin-top: 1rem;">
                    <div class="field-group">
                        <label class="field-label">Responsable:</label>
                        <select name="responsable_id" class="field-input w-lg">
                            <option value="">---</option>
                            <option value="1">Admin</option>
                        </select>
                    </div>
                    <div class="field-group w-full">
                        <label class="field-label">Forma de pago:</label>
                        <input type="text" name="forma_pago" class="field-input w-full">
                    </div>
                </div>

                <!-- Observaciones -->
                <div style="margin-top: 1rem;">
                    <label class="field-label" style="display: block; margin-bottom: 0.5rem;">Observaciones:</label>
                    <textarea name="observaciones" class="provider-textarea"></textarea>
                </div>

                <!-- Footer -->
                <div class="form-footer">
                    <a href="nueva_factura.php" class="btn-provider">Volver</a>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 40px;">Insertar registro</button>
                </div>

            </form>
        </section>
    </main>
</div>

</body>
</html>
