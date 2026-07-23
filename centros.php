<?php
// centros.php
require_once 'includes/auth.php';

// Solo admin
if (!has_permission([ROLE_ADMIN])) {
    header("Location: index.php");
    exit();
}

$page_title = "Gestión de Centros/Sedes";

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

// Obtener todos los centros con cuenta de usuarios
$stmt = $pdo->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM usuarios u WHERE u.centro_id = c.id) as num_usuarios 
                    FROM centros c ORDER BY c.nombre ASC");
$centros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
<style>
    .centro-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .centro-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
        overflow: hidden;
    }

    .centro-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border-color: #3b82f6;
    }

    .centro-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 5px;
        background: linear-gradient(90deg, #1e3a8a, #3b82f6);
    }

    .centro-tag {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #3b82f6;
        background: #eff6ff;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 12px;
    }

    .centro-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #1e3a8a;
        margin-bottom: 15px;
        line-height: 1.3;
    }
    
    .centro-details {
        font-size: 0.8rem;
        color: #475569;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .centro-details svg {
        width: 14px;
        height: 14px;
        vertical-align: middle;
        margin-right: 5px;
        color: #94a3b8;
    }

    .centro-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #f1f5f9;
    }

    .stat-item { text-align: center; }
    .stat-value { display: block; font-size: 1.2rem; font-weight: 800; color: #1e3a8a; }
    .stat-label { font-size: 0.65rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; }

    .btn-add-centro {
        background: #1e3a8a;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        text-decoration: none;
        display: inline-block;
    }
    .btn-add-centro:hover {
        background: #172554;
        color: white;
    }
</style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content" style="flex: 1; overflow-y: auto; padding: 2rem;">
        <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1 class="page-title" style="margin: 0; color: #1e3a8a; font-size: 1.8rem; font-weight: 800;">Sedes y Centros Físicos</h1>
                <p class="page-description" style="margin: 5px 0 0 0; color: #64748b;">Gestiona las diferentes delegaciones y lugares de impartición.</p>
            </div>
            <a href="nuevo_centro.php" class="btn-add-centro">+ Añadir Centro</a>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert-success" style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="centro-grid">
            <?php if (empty($centros)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🏢</div>
                    <h3 style="color: #1e3a8a; margin-bottom: 10px;">No se encontraron centros</h3>
                    <p style="color: #64748b;">Aún no hay centros o sedes registradas en el sistema.</p>
                    <a href="nuevo_centro.php" class="btn-add-centro" style="margin-top: 15px;">Crear primer centro</a>
                </div>
            <?php endif; ?>
            <?php foreach($centros as $c): ?>
            <div class="centro-card">
                <?php if (!empty($c['provincia'])): ?>
                <span class="centro-tag"><?= htmlspecialchars($c['provincia']) ?></span>
                <?php endif; ?>
                <h3 class="centro-title"><?= htmlspecialchars($c['nombre']) ?></h3>
                
                <div class="centro-details">
                    <?php if (!empty($c['direccion'])): ?>
                    <div style="margin-bottom: 4px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <?= htmlspecialchars($c['direccion']) ?><?= !empty($c['cp']) ? ' (' . htmlspecialchars($c['cp']) . ')' : '' ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['telefono'])): ?>
                    <div style="margin-bottom: 4px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        <?= htmlspecialchars($c['telefono']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['email_contacto'])): ?>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <?= htmlspecialchars($c['email_contacto']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="centro-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $c['num_usuarios'] ?></span>
                        <span class="stat-label">Usuarios</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="editar_centro.php?id=<?= $c['id'] ?>" class="btn-icon" style="color: #64748b;" title="Editar Centro">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </a>
                        <a href="?delete=<?= $c['id'] ?>" 
                           onclick="return confirm('¿Estás seguro de que deseas eliminar este centro? Esta acción no se puede deshacer.');" 
                           class="btn-icon" style="color: #ef4444;" title="Borrar Centro">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
