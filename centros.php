<?php
// centros.php
require_once 'includes/auth.php';

// Solo admin
if (!has_permission([ROLE_ADMIN])) {
    header("Location: index.php");
    exit();
}

$page_title = "Gestión de Centros/Sedes";
require_once 'includes/header.php';

// Eliminar centro si se solicita
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM centros WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Centro eliminado correctamente.";
    } catch (PDOException $e) {
        $error = "Error al eliminar el centro. Asegúrese de que no tiene usuarios ni grupos asociados. Error: " . $e->getMessage();
    }
}

// Obtener todos los centros
$stmt = $pdo->query("SELECT * FROM centros ORDER BY nombre ASC");
$centros = $stmt->fetchAll();
?>

<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1 class="page-title">Sedes y Centros Físicos</h1>
                <p class="page-description">Gestiona las diferentes delegaciones y lugares de impartición.</p>
            </div>
            <div class="header-actions">
                <a href="nuevo_centro.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Añadir Centro
                </a>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card fade-in">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre del Centro</th>
                            <th>Provincia</th>
                            <th>Teléfono</th>
                            <th>Email de Contacto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($centros as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($c['provincia'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['telefono'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['email_contacto'] ?? '') ?></td>
                                <td class="actions-cell">
                                    <a href="editar_centro.php?id=<?= $c['id'] ?>" class="btn-icon text-primary" title="Editar">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    </a>
                                    <a href="centros.php?delete=<?= $c['id'] ?>" class="btn-icon text-danger" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar este centro? Solo será posible si no tiene grupos ni usuarios vinculados.');">
                                        <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($centros)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No hay centros registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
