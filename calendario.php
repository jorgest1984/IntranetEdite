<?php
require_once 'includes/auth.php';

// ─── Auto-setup tabla de festivos ──────────────────────────────────────────────
$pdo->query("CREATE TABLE IF NOT EXISTS calendario_dias (
    fecha DATE PRIMARY KEY,
    es_vacacion TINYINT(1) DEFAULT 0,
    es_nacional TINYINT(1) DEFAULT 0,
    local_granada TINYINT(1) DEFAULT 0,
    local_almeria TINYINT(1) DEFAULT 0,
    local_valladolid TINYINT(1) DEFAULT 0,
    local_vicar TINYINT(1) DEFAULT 0,
    local_dorfland TINYINT(1) DEFAULT 0,
    local_madrid TINYINT(1) DEFAULT 0
)");

// Configuración de año
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
if ($year < 2000 || $year > 2100) $year = intval(date('Y'));

// Obtener todos los días marcados para este año
$stmt = $pdo->prepare("SELECT * FROM calendario_dias WHERE YEAR(fecha) = ?");
$stmt->execute([$year]);
$dias_marcados = [];
while ($row = $stmt->fetch()) {
    $dias_marcados[$row['fecha']] = $row;
}

// Helper para meses
$meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$dias_semana = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Laboral <?= $year ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .calendar-container { max-width: 900px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; position: relative; }
        
        /* Header año */
        .year-nav { display: flex; align-items: center; justify-content: center; gap: 1.5rem; margin-bottom: 2rem; }
        .year-nav a { text-decoration: none; color: #475569; font-size: 1.5rem; line-height: 1; padding: 0.2rem 0.5rem; border-radius: 4px; transition: background 0.2s; }
        .year-nav a:hover { background: #f1f5f9; color: #0f172a; }
        .year-nav h2 { margin: 0; font-size: 2.2rem; font-weight: 700; color: #1e293b; }

        /* Botón superior derecho */
        .btn-asignar { position: absolute; top: 2rem; right: 2rem; background: #64748b; color: #fff; border: none; padding: 0.6rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; text-decoration: none; }
        .btn-asignar:hover { background: #475569; }

        /* Grid de meses */
        .year-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        @media(max-width: 900px) { .year-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 600px) { .year-grid { grid-template-columns: 1fr; } }
        
        .month-block { text-align: center; }
        .month-title { font-weight: 600; font-size: 1rem; color: #334155; margin-bottom: 0.8rem; text-transform: lowercase; }
        
        .month-calendar { width: 100%; border-collapse: separate; border-spacing: 2px 4px; font-size: 0.75rem; }
        .month-calendar th { font-weight: 500; color: #94a3b8; padding: 0.2rem; }
        .month-calendar td { padding: 0.2rem; cursor: pointer; border-radius: 50%; aspect-ratio: 1; min-width: 24px; transition: transform 0.1s; }
        .month-calendar td:hover:not(.empty) { background: #f1f5f9; transform: scale(1.1); }
        .month-calendar td.empty { cursor: default; }

        /* Colores de días */
        .day-weekend { color: #f87171; background: #fef2f2; }
        .day-vacacion { background: #fde047; color: #854d0e; font-weight: 600; }
        .day-nacional { background: #ef4444; color: #fff; font-weight: 600; }
        .day-local { background: #f97316; color: #fff; font-weight: 600; }

        /* Leyenda */
        .legend { margin-top: 3rem; font-size: 0.8rem; color: #475569; }
        .legend h3 { font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem; font-weight: 600; }
        .legend ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem; }
        .legend li { display: flex; align-items: center; gap: 0.5rem; }
        .legend-color { width: 12px; height: 12px; border-radius: 2px; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; width: 400px; max-width: 90%; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem; border-bottom: 1px solid #e2e8f0; }
        .modal-header h3 { margin: 0; font-size: 1.25rem; color: #1e293b; }
        .modal-close { background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; line-height: 1; }
        .modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
        
        .toggle-btn { display: block; width: 100%; text-align: center; padding: 0.8rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 0.75rem; cursor: pointer; font-weight: 500; color: #334155; transition: all 0.2s; user-select: none; }
        .toggle-btn.active { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
        input[type="checkbox"].hidden-check { display: none; }
        
        .section-title { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin: 1.5rem 0 1rem; }
        
        .modal-footer { padding: 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 0.75rem; background: #f8fafc; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="padding: 1.5rem; max-width: 950px; margin: 0 auto;">
            
            <nav style="display:flex; gap:0.4rem; font-size:0.85rem; color:#64748b; margin-bottom:1.5rem; align-items:center;">
                <a href="home.php" style="color:#dc2626; text-decoration:none; font-weight:500;">Inicio</a><span>/</span>
                <a href="edite_formacion.php" style="color:#dc2626; text-decoration:none; font-weight:500;">Grupo EFP</a><span>/</span>
                <span>Calendario laboral</span>
            </nav>

            <div class="calendar-container">
                <a href="#" class="btn-asignar">Asignar vacaciones</a>

                <div class="year-nav">
                    <a href="?year=<?= $year - 1 ?>">«</a>
                    <h2><?= $year ?></h2>
                    <a href="?year=<?= $year + 1 ?>">»</a>
                </div>

                <div class="year-grid">
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        echo "<div class='month-block'>";
                        echo "<div class='month-title'>{$meses[$m]}</div>";
                        echo "<table class='month-calendar'><thead><tr>";
                        foreach ($dias_semana as $ds) echo "<th>$ds</th>";
                        echo "</tr></thead><tbody><tr>";

                        $first_day = mktime(0, 0, 0, $m, 1, $year);
                        $days_in_month = date('t', $first_day);
                        $start_day_of_week = date('N', $first_day); // 1 (Mon) - 7 (Sun)

                        // Celdas vacías iniciales
                        for ($i = 1; $i < $start_day_of_week; $i++) {
                            echo "<td class='empty'></td>";
                        }

                        $current_dow = $start_day_of_week;
                        for ($d = 1; $d <= $days_in_month; $d++) {
                            $fecha_str = sprintf("%04d-%02d-%02d", $year, $m, $d);
                            
                            $classes = [];
                            if ($current_dow == 6 || $current_dow == 7) $classes[] = 'day-weekend';
                            
                            $info = $dias_marcados[$fecha_str] ?? null;
                            if ($info) {
                                if ($info['es_vacacion']) $classes[] = 'day-vacacion';
                                elseif ($info['es_nacional']) $classes[] = 'day-nacional';
                                elseif ($info['local_granada'] || $info['local_almeria'] || $info['local_valladolid'] || $info['local_vicar'] || $info['local_dorfland'] || $info['local_madrid']) {
                                    $classes[] = 'day-local';
                                }
                            }
                            
                            $class_str = !empty($classes) ? "class='".implode(' ', $classes)."'" : "";
                            
                            // Guardamos los datos en atributos para cargar el modal
                            $data_attrs = "data-fecha='$fecha_str'";
                            if ($info) {
                                foreach(['es_vacacion', 'es_nacional', 'local_granada', 'local_almeria', 'local_valladolid', 'local_vicar', 'local_dorfland', 'local_madrid'] as $k) {
                                    if ($info[$k]) $data_attrs .= " data-$k='1'";
                                }
                            }

                            echo "<td $class_str $data_attrs onclick='openModal(this)'>$d</td>";

                            if ($current_dow == 7) {
                                echo "</tr>";
                                if ($d < $days_in_month) echo "<tr>";
                                $current_dow = 1;
                            } else {
                                $current_dow++;
                            }
                        }

                        // Completar la última semana
                        if ($current_dow > 1) {
                            for ($i = $current_dow; $i <= 7; $i++) echo "<td class='empty'></td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    }
                    ?>
                </div>

                <div class="legend">
                    <h3>Leyenda</h3>
                    <ul>
                        <li><div class="legend-color" style="background:#fde047;"></div> Vacaciones</li>
                        <li><div class="legend-color" style="background:#ef4444;"></div> Festivo nacional</li>
                        <li><div class="legend-color" style="background:#f97316;"></div> Festivo local o autonómico</li>
                        <li><div class="legend-color" style="background:#fef2f2;"></div> Fin de semana</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal-overlay" id="dayModal">
    <div class="modal">
        <form id="dayForm">
            <div class="modal-header">
                <h3 id="modalTitle">Día YYYY-MM-DD</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="fecha" id="modalFecha">
                
                <label class="toggle-btn"><input type="checkbox" name="es_vacacion" class="hidden-check"> Vacaciones</label>
                <label class="toggle-btn"><input type="checkbox" name="es_nacional" class="hidden-check"> Festivo nacional</label>
                
                <div class="section-title">FESTIVOS LOCALES EN CENTROS</div>
                
                <label class="toggle-btn"><input type="checkbox" name="local_granada" class="hidden-check"> Granada</label>
                <label class="toggle-btn"><input type="checkbox" name="local_almeria" class="hidden-check"> Almería</label>
                <label class="toggle-btn"><input type="checkbox" name="local_valladolid" class="hidden-check"> Valladolid</label>
                <label class="toggle-btn"><input type="checkbox" name="local_vicar" class="hidden-check"> Vícar</label>
                <label class="toggle-btn"><input type="checkbox" name="local_dorfland" class="hidden-check"> Dorfland</label>
                <label class="toggle-btn"><input type="checkbox" name="local_madrid" class="hidden-check"> Madrid - Francisco Silvela</label>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn" style="background:#64748b; color:#fff; border:none;" onclick="closeModal()">Cerrar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Manejo visual de los toggle buttons
document.querySelectorAll('.hidden-check').forEach(chk => {
    chk.addEventListener('change', function() {
        if (this.checked) this.closest('.toggle-btn').classList.add('active');
        else this.closest('.toggle-btn').classList.remove('active');
    });
});

function openModal(td) {
    if (td.classList.contains('empty')) return;
    
    const fecha = td.getAttribute('data-fecha');
    document.getElementById('modalTitle').innerText = `Día ${fecha}`;
    document.getElementById('modalFecha').value = fecha;
    
    // Resetear formulario
    document.getElementById('dayForm').reset();
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
    
    // Cargar datos del atributo data-* si existen
    ['es_vacacion', 'es_nacional', 'local_granada', 'local_almeria', 'local_valladolid', 'local_vicar', 'local_dorfland', 'local_madrid'].forEach(k => {
        if (td.getAttribute('data-' + k) === '1') {
            const el = document.querySelector(`input[name="${k}"]`);
            if (el) {
                el.checked = true;
                el.closest('.toggle-btn').classList.add('active');
            }
        }
    });
    
    document.getElementById('dayModal').classList.add('active');
}

function closeModal() {
    document.getElementById('dayModal').classList.remove('active');
}

// Guardar por AJAX
document.getElementById('dayForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const res = await fetch('api/calendario_save.php', { method: 'POST', body: formData });
        const result = await res.json();
        
        if (result.success) {
            window.location.reload(); // Recargar para mostrar los nuevos colores
        } else {
            alert(result.error || "Error al guardar");
        }
    } catch (err) {
        alert("Error de red al guardar");
    }
});
</script>
</body>
</html>
