<?php
// nuevo_proveedor.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])) {
    header("Location: home.php");
    exit();   
}

$active_tab = 'contabilidad';
$error = '';
$success = '';

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "INSERT INTO proveedores (cif, nombre, nombre_comercial, sector, telefono, movil, email, email_facturacion, web, estado, direccion, poblacion, provincia, cp) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['cif'],
            $_POST['nombre'],
            $_POST['nombre_comercial'] ?? null,
            $_POST['sector'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['movil'] ?? null,
            $_POST['email'] ?? null,
            $_POST['email_facturacion'] ?? null,
            $_POST['web'] ?? null,
            $_POST['estado'] ?? 'Activo',
            $_POST['direccion'] ?? null,
            $_POST['poblacion'] ?? null,
            $_POST['provincia'] ?? null,
            $_POST['cp'] ?? null
        ]);
        
        $success = "Proveedor creado correctamente.";
        if (isset($_GET['from']) && $_GET['from'] === 'factura') {
            header("Location: nueva_factura.php?msg=" . urlencode($success));
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al guardar: " . $e->getMessage();
    }
}

$sectores = ['Servicios', 'Formación', 'Informática', 'Construcción', 'Comercio', 'Hostelería', 'Otros'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Proveedor - <?= APP_NAME ?></title>
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
                <h1>Crear Nuevo Proveedor</h1>
                <p>Gestión de datos fiscales y de contacto</p>
            </div>
            <div>
                <a href="facturas.php" class="btn btn-invoice-secondary" style="text-decoration: none;">Volver</a>
            </div>
        </header>

        <section class="proveedor-container">
            <?php if ($error): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; border: 1px solid #b91c1c;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form id="formProveedor" method="POST">
                
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button type="button" class="tab-btn active" data-tab="datos-generales">Datos Generales</button>
                        <button type="button" class="tab-btn" data-tab="direccion">Dirección</button>
                        <button type="button" class="tab-btn" data-tab="otros">Otros</button>
                    </div>

                    <!-- TAB: DATOS GENERALES -->
                    <div class="tab-content active" id="datos-generales">
                        <div class="form-grid-proveedor">
                            <div class="form-group-proveedor">
                                <label>CIF / NIF</label>
                                <input type="text" name="cif" class="form-control-proveedor" required placeholder="B12345678">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Razón Social / Nombre</label>
                                <input type="text" name="nombre" class="form-control-proveedor" required>
                            </div>
                            <div class="form-group-proveedor full-width">
                                <label>Nombre Comercial</label>
                                <input type="text" name="nombre_comercial" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Actividad / Sector</label>
                                <select name="sector" class="form-control-proveedor">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($sectores as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-proveedor">
                                <label>Estado</label>
                                <select name="estado" class="form-control-proveedor">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="form-group-proveedor">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Móvil</label>
                                <input type="text" name="movil" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Email Facturación</label>
                                <input type="email" name="email_facturacion" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor full-width">
                                <label>Sitio Web</label>
                                <input type="text" name="web" class="form-control-proveedor" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <!-- TAB: DIRECCIÓN -->
                    <div class="tab-content" id="direccion">
                        <div class="form-grid-proveedor">
                            <div class="form-group-proveedor full-width">
                                <label>Dirección</label>
                                <input type="text" name="direccion" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Población</label>
                                <input type="text" name="poblacion" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Provincia</label>
                                <input type="text" name="provincia" class="form-control-proveedor">
                            </div>
                            <div class="form-group-proveedor">
                                <label>Código Postal</label>
                                <input type="text" name="cp" class="form-control-proveedor">
                            </div>
                        </div>
                    </div>

                    <!-- TAB: OTROS -->
                    <div class="tab-content" id="otros">
                        <div class="form-grid-proveedor">
                            <div class="form-group-proveedor full-width">
                                <label>Notas Adicionales</label>
                                <textarea name="notas" class="form-control-proveedor" style="height: 150px;"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-footer-proveedor">
                    <a href="facturas.php" class="btn-proveedor btn-proveedor-secondary" style="text-decoration: none;">CANCELAR</a>
                    <button type="submit" class="btn-proveedor btn-proveedor-primary">GUARDAR PROVEEDOR</button>
                </div>

            </form>
        </section>
    </main>
</div>

<script>
    // Cambio de pestañas
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            
            // Toggle Buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Toggle Contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        });
    });
</script>

</body>
</html>
