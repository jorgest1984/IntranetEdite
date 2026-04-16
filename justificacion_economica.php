<?php
// justificacion_economica.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'contabilidad';

// Fetch convocatorias for the dropdown
try {
    $stmt = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY creado_en DESC");
    $convocatorias = $stmt->fetchAll();
} catch (Exception $e) {
    $convocatorias = [];
    $error = "Error al cargar convocatorias: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justificación Económica - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/justificacion_economica.css">
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Justificación económica</h1>
                <p>Filtre por convocatoria y plan para ver la justificación detallada</p>
            </div>
            <div class="page-actions">
                <a href="contabilidad.php" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    Volver a Contabilidad
                </a>
            </div>
        </header>

        <div class="justificacion-container">
            <div class="justificacion-card">
                <form id="filterForm">
                    <div class="filter-grid">
                        <!-- Convocatoria Row -->
                        <div class="filter-label">Convocatoria</div>
                        <div class="filter-select-wrapper">
                            <select name="convocatoria_id" id="convocatoria_select" class="filter-control">
                                <option value="">Seleccione una convocatoria...</option>
                                <?php foreach ($convocatorias as $conv): ?>
                                    <option value="<?= $conv['id'] ?>">
                                        <?= htmlspecialchars($conv['nombre']) ?> (<?= htmlspecialchars($conv['codigo_expediente']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="multiple-selection-option">
                            <label for="multiple_selection">Selección múltiple</label>
                            <input type="checkbox" id="multiple_selection" name="multiple_selection">
                        </div>

                        <!-- Plan Row -->
                        <div class="filter-label">Plan</div>
                        <div class="filter-select-wrapper" style="grid-column: span 1;">
                            <select name="plan_id" id="plan_select" class="filter-control" disabled>
                                <option value="">Seleccione primero una convocatoria...</option>
                            </select>
                        </div>
                        <div></div> <!-- Empty grid spacer -->
                    </div>
                </form>
            </div>

            <!-- This section will be populated via AJAX after selection -->
            <div id="justificacion_results" class="results-section">
                <!-- Data will appear here -->
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const convocatoriaSelect = document.getElementById('convocatoria_select');
    const planSelect = document.getElementById('plan_select');
    const multipleCheckbox = document.getElementById('multiple_selection');
    const resultsSection = document.getElementById('justificacion_results');

    // Handle Multiple Selection Toggle
    multipleCheckbox.addEventListener('change', function() {
        if (this.checked) {
            convocatoriaSelect.setAttribute('multiple', 'multiple');
            convocatoriaSelect.style.height = 'auto'; // Adjust height for multi-select
            convocatoriaSelect.size = 5;
            // When multiple, plan selection might need to be multi-select too or hidden
            planSelect.disabled = true;
            planSelect.innerHTML = '<option value="">Deshabilitado en selección múltiple</option>';
        } else {
            convocatoriaSelect.removeAttribute('multiple');
            convocatoriaSelect.style.height = '';
            convocatoriaSelect.size = 1;
            planSelect.disabled = !convocatoriaSelect.value;
            if (convocatoriaSelect.value) {
                loadPlanes(convocatoriaSelect.value);
            } else {
                planSelect.innerHTML = '<option value="">Seleccione primero una convocatoria...</option>';
            }
        }
    });

    // Handle Convocatoria Change
    convocatoriaSelect.addEventListener('change', function() {
        if (multipleCheckbox.checked) return; // Logic for multiple selection handled differently

        const convocatoriaId = this.value;
        if (convocatoriaId) {
            planSelect.disabled = false;
            loadPlanes(convocatoriaId);
        } else {
            planSelect.disabled = true;
            planSelect.innerHTML = '<option value="">Seleccione primero una convocatoria...</option>';
            resultsSection.classList.remove('show');
        }
    });

    // Handle Plan Change
    planSelect.addEventListener('change', function() {
        if (this.value) {
            loadJustification(convocatoriaSelect.value, this.value);
        } else {
            resultsSection.classList.remove('show');
        }
    });

    function loadPlanes(convocatoriaId) {
        planSelect.innerHTML = '<option value="">Cargando planes...</option>';
        
        fetch(`api/get_planes.php?convocatoria_id=${convocatoriaId}`)
            .then(response => response.json())
            .then(data => {
                planSelect.innerHTML = '<option value="">Seleccione un plan...</option>';
                if (data.length > 0) {
                    data.forEach(plan => {
                        const option = document.createElement('option');
                        option.value = plan.id;
                        option.textContent = `${plan.nombre} (${plan.codigo})`;
                        planSelect.appendChild(option);
                    });
                } else {
                    planSelect.innerHTML = '<option value="">No hay planes para esta convocatoria</option>';
                }
            })
            .catch(error => {
                console.error('Error loading planes:', error);
                planSelect.innerHTML = '<option value="">Error al cargar planes</option>';
            });
    }

    function loadJustification(convocatoriaId, planId) {
        // Placeholder for loading justification data
        resultsSection.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <div class="loader-placeholder" style="margin-bottom: 1rem;">
                    <svg width="40" height="40" viewBox="0 0 50 50" style="animation: rotate 2s linear infinite;">
                        <circle cx="25" cy="25" r="20" fill="none" stroke="var(--primary-color)" stroke-width="4" stroke-dasharray="80, 200"></circle>
                    </svg>
                </div>
                <p>Cargando datos de justificación...</p>
            </div>
        `;
        resultsSection.classList.add('show');

        // Simulate data loading
        setTimeout(() => {
            resultsSection.innerHTML = `
                <h3>Datos de Justificación Económica</h3>
                <p>Plan seleccionado: <strong>${planSelect.options[planSelect.selectedIndex].text}</strong></p>
                <!-- Further tables and data would go here -->
                <div style="margin-top:1.5rem; padding:1.5rem; border:1.5px dashed #cbd5e1; border-radius:6px; color:#64748b; text-align:center;">
                    Desglose detallado de gastos e ingresos pendiente de cargar.
                </div>
            `;
        }, 800);
    }
});
</script>

<style>
@keyframes rotate {
    100% { transform: rotate(360deg); }
}
</style>

</body>
</html>
