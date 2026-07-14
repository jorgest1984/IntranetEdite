<?php
// editar_unidades.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$accion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$accion_id) {
    die("Se requiere el ID de la Acción Formativa.");
}

// Create table if not exists (quick migration)
$pdo->exec("
CREATE TABLE IF NOT EXISTS unidades_didacticas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accion_id INT NOT NULL,
    numero_unidad INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    horas DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (accion_id) REFERENCES acciones_formativas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_unit'])) {
        $stmt = $pdo->prepare("INSERT INTO unidades_didacticas (accion_id, numero_unidad, titulo, horas) VALUES (?, ?, ?, ?)");
        $stmt->execute([$accion_id, $_POST['numero_unidad'], $_POST['titulo'], $_POST['horas']]);
        header("Location: editar_unidades.php?id=" . $accion_id);
        exit();
    } elseif (isset($_POST['delete_unit'])) {
        $stmt = $pdo->prepare("DELETE FROM unidades_didacticas WHERE id = ? AND accion_id = ?");
        $stmt->execute([$_POST['unit_id'], $accion_id]);
        header("Location: editar_unidades.php?id=" . $accion_id);
        exit();
    }
}

// Fetch accion info
$stmt = $pdo->prepare("SELECT num_accion, horas_teoricas, horas_practicas, duracion FROM acciones_formativas WHERE id = ?");
$stmt->execute([$accion_id]);
$accion = $stmt->fetch();

if (!$accion) {
    die("Acción Formativa no encontrada.");
}

// Fetch existing units
$stmt = $pdo->prepare("SELECT * FROM unidades_didacticas WHERE accion_id = ? ORDER BY numero_unidad ASC");
$stmt->execute([$accion_id]);
$unidades = $stmt->fetchAll();

$total_horas = array_sum(array_column($unidades, 'horas'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Unidades Didácticas</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Unidades Didácticas - Acción <?= htmlspecialchars($accion['num_accion']) ?></h1>
            </div>
            <div class="page-actions">
                <a href="ficha_accion_formativa.php?id=<?= $accion_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Ficha</a>
            </div>
        </header>

        <div class="card">
            <h2>Gestionar Unidades</h2>
            <p>Añade aquí las unidades didácticas. Las horas totales deben coincidir con la duración de la acción formativa (<?= $accion['duracion'] ?> h).</p>
            <p><strong>Total Horas Asignadas:</strong> <span style="color: <?= $total_horas == $accion['duracion'] ? 'green' : 'red' ?>; font-weight: bold;"><?= $total_horas ?> / <?= $accion['duracion'] ?></span> h</p>

            <table class="data-table" style="margin-top: 1rem;">
                <thead>
                    <tr>
                        <th>Nº Unidad</th>
                        <th>Título</th>
                        <th>Horas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unidades as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['numero_unidad']) ?></td>
                        <td><?= htmlspecialchars($u['titulo']) ?></td>
                        <td><?= htmlspecialchars($u['horas']) ?> h</td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Seguro que quieres borrar esta unidad?');">
                                <input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
                                <button type="submit" name="delete_unit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin-top: 2rem;">Añadir Nueva Unidad</h3>
            <form method="post" class="standard-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número de Unidad</label>
                        <input type="number" name="numero_unidad" required class="form-control" value="<?= count($unidades) + 1 ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Título de la Unidad</label>
                        <input type="text" name="titulo" required class="form-control" placeholder="Ej. Unidad 1">
                    </div>
                    <div class="form-group">
                        <label>Horas</label>
                        <input type="number" step="0.5" name="horas" required class="form-control" value="10">
                    </div>
                </div>
                <button type="submit" name="add_unit" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-plus"></i> Añadir Unidad</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
