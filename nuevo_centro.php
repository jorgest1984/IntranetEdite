<?php
// nuevo_centro.php
require_once 'includes/auth.php';

// Solo admin
if (!has_permission([ROLE_ADMIN])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $direccion = trim($_POST['direccion']);
    $provincia = trim($_POST['provincia']);
    $cp = trim($_POST['cp']);
    $telefono = trim($_POST['telefono']);
    $email_contacto = trim($_POST['email_contacto']);

    if (empty($nombre)) {
        $error = "El nombre del centro es obligatorio.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO centros (nombre, direccion, provincia, cp, telefono, email_contacto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $direccion, $provincia, $cp, $telefono, $email_contacto]);
            
            header("Location: centros.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error al guardar el centro: " . $e->getMessage();
        }
    }
}

$page_title = "Añadir Nuevo Centro";
require_once 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <a href="centros.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver a Centros
                </a>
                <h1 class="page-title">Añadir Nuevo Centro</h1>
            </div>
        </header>

        <div class="card form-card fade-in">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Nombre del Centro *</label>
                        <input type="text" name="nombre" class="form-input" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" placeholder="Ej. Sede Central Madrid">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Dirección Completa</label>
                        <input type="text" name="direccion" class="form-input" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>" placeholder="Calle, número, piso...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-input" value="<?= htmlspecialchars($_POST['provincia'] ?? '') ?>" placeholder="Ej. Madrid">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código Postal</label>
                        <input type="text" name="cp" class="form-input" value="<?= htmlspecialchars($_POST['cp'] ?? '') ?>" placeholder="Ej. 28001">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Teléfono de Contacto</label>
                        <input type="tel" name="telefono" class="form-input" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" placeholder="Ej. 910000000">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email de Contacto</label>
                        <input type="email" name="email_contacto" class="form-input" value="<?= htmlspecialchars($_POST['email_contacto'] ?? '') ?>" placeholder="sede@ejemplo.com">
                    </div>
                </div>

                <div class="form-actions mt-6">
                    <a href="centros.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar Centro</button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
