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

            <!-- Informes Excel Section -->
            <section class="reports-section">
                <h2 class="section-title">Informes Excel</h2>
                <div class="info-alert">
                    Selecciona una convocatoria para que se puedan generar los archivos Excel correspondientes.
                </div>

                <div class="reports-grid">
                    <!-- Card 1 -->
                    <a href="#" class="report-card" data-report="imparticion">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                        </div>
                        <div class="report-card-title">Informe impartición</div>
                    </a>
                    <!-- Card 2 -->
                    <a href="#" class="report-card" data-report="imparticion_detallado">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                        </div>
                        <div class="report-card-title">Informe impartición detallado</div>
                    </a>
                    <!-- Card 3 -->
                    <a href="#" class="report-card" data-report="horas_imputadas">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                        </div>
                        <div class="report-card-title">Informe de horas imputadas</div>
                    </a>
                    <!-- Card 4 -->
                    <a href="#" class="report-card" data-report="facturas_plan">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2z"/></svg>
                        </div>
                        <div class="report-card-title">Informe facturas por plan</div>
                    </a>
                    <!-- Card 5 -->
                    <a href="#" class="report-card" data-report="facturas_grupo">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2z"/></svg>
                        </div>
                        <div class="report-card-title">Informe facturas por grupo</div>
                    </a>
                    <!-- Card 6 -->
                    <a href="#" class="report-card" data-report="costes_personal">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2z" opacity=".2"/></svg>
                        </div>
                        <div class="report-card-title">Informe de costes de personal por plan</div>
                    </a>
                    <!-- Card 7 -->
                    <a href="#" class="report-card" data-report="resumen_justificacion">
                        <div class="report-card-icon">
                            <svg viewBox="0 0 24 24"><path d="M15 18.5c-2.5 0-4.7-1.4-5.8-3.5H13v-2H8.3c-.2-.6-.3-1.2-.3-2s.1-1.4.3-2H13V7H9.2C10.3 4.9 12.5 3.5 15 3.5c1.4 0 2.7.4 3.7 1.1L20 3.3C18.6 2.5 16.9 2 15 2c-3.6 0-6.7 2.2-8.1 5.3L4.6 6.1 3.2 7.5 5.8 10.1C5.4 11.3 5.2 12.6 5.2 14c0 1.4.2 2.7.6 3.9l-2.6 2.6 1.4 1.4L6 19.3c1.4 3.1 4.5 5.3 8.1 5.3 1.9 0 3.6-.5 5-1.3l-1.3-1.3c-1 0.7-2.3 1.1-3.7 1.1z"/></svg>
                        </div>
                        <div class="report-card-title">Informe resumen de justificación</div>
                    </a>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const convocatoriaSelect = document.getElementById('convocatoria_select');
    const planSelect = document.getElementById('plan_select');
    const multipleCheckbox = document.getElementById('multiple_selection');
    const resultsSection = document.getElementById('justificacion_results');
    const reportCards = document.querySelectorAll('.report-card');

    function updateReportCardsState() {
        const hasSelection = convocatoriaSelect.value !== "";
        reportCards.forEach(card => {
            if (hasSelection) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    }

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
        updateReportCardsState();
    });

    // Handle Convocatoria Change
    convocatoriaSelect.addEventListener('change', function() {
        updateReportCardsState();
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

    // Handle Report Card Clicks
    reportCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            if (!this.classList.contains('active')) return;
            
            const reportType = this.getAttribute('data-report');
            const convocatoriaId = convocatoriaSelect.value;
            const planId = planSelect.value;
            
            // Redirect to report generation script (placeholder for now)
            alert(`Generando reporte: ${reportType}\nConvocatoria: ${convocatoriaId}\nPlan: ${planId || 'Múltiples/Todos'}`);
            // window.location.href = `generar_reporte.php?tipo=${reportType}&convocatoria_id=${convocatoriaId}&plan_id=${planId}`;
        });
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
