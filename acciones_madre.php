<?php
// acciones_madre.php
require_once 'includes/auth.php';

$sectores = [
    'Abogados', 'Acción e Intervención Social', 'Administracion y gestion', 'Agencias de Viaje',
    'Alimentación', 'Ambulancias', 'Arquitectura', 'Artes Gráficas', 'Asesorías',
    'Asociaciones', 'Atención a personas con discapacidad', 'Atención Domiciliaria',
    'Automoción', 'Banca', 'Centros de día', 'Comercio', 'Construcción',
    'Consultoría', 'Contact Center', 'Educación y Formación', 'Farmacia',
    'Gimnasios', 'Hostelería', 'Imagen y sonido', 'Industria manufacturera',
    'Informática', 'Inmobiliarias', 'Madera y Mueble', 'Metal',
    'Peluquería y Estética', 'Producción Audiovisual', 'Publicidad', 'Sanidad',
    'Seguridad Privada', 'Seguros', 'Servicios Sociales', 'Telecomunicaciones',
    'Transporte', 'Turismo', 'Universidades'
];
sort($sectores);

// Datos de ejemplo
$acciones_madre = [
    [
        'id' => 1,
        'asignatura' => 'Mantenimiento mecánico preventivo del vehículo',
        'cod_asignatura' => 'MMPV',
        'contenido' => 'Sistemas de seguridad y confortabilidad',
        'codigo' => 'M-001',
        'abreviatura' => 'SSC',
        'horas' => 45,
        'sector' => 'Automoción',
        'estado' => 'Activo'
    ],
    [
        'id' => 2,
        'asignatura' => 'Didáctica de la educación infantil',
        'cod_asignatura' => 'DEI',
        'contenido' => 'Planificación de la intervención educativa',
        'codigo' => 'M-002',
        'abreviatura' => 'PIE',
        'horas' => 60,
        'sector' => 'Educación y Formación',
        'estado' => 'Activo'
    ],
    [
        'id' => 3,
        'asignatura' => 'Técnicas de conducción de bicicletas',
        'cod_asignatura' => 'TCB',
        'contenido' => 'Mecánica básica de la bicicleta',
        'codigo' => 'M-003',
        'abreviatura' => 'MBB',
        'horas' => 30,
        'sector' => 'Turismo',
        'estado' => 'Activo'
    ],
    [
        'id' => 4,
        'asignatura' => 'Mantenimiento mecánico preventivo del vehículo',
        'cod_asignatura' => 'MMPV',
        'contenido' => 'Diagnóstico y revisión de frenos',
        'codigo' => 'M-004',
        'abreviatura' => 'DRF',
        'horas' => 25,
        'sector' => 'Automoción',
        'estado' => 'Inactivo'
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contenidos Acciones (Madre) - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Búsqueda */
        .search-container-fp {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .search-header-fp {
            background: #1e293b;
            color: white;
            padding: 10px 20px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .search-form-fp {
            padding: 20px 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .form-group-fp {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 180px;
        }
        .form-label-fp {
            font-weight: 700;
            font-size: 0.75rem;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .form-control-fp {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            font-family: inherit;
        }
        .form-control-fp:focus {
            outline: none;
            border-color: #475569;
            background: white;
        }
        .search-actions-fp {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }
        .btn-search-fp {
            background: #334155;
            color: white;
            border: none;
            padding: 8px 25px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-search-fp:hover { background: #1e293b; }
        .btn-reset-fp {
            background: #e2e8f0;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 18px;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        /* Tabla Resultados */
        .results-wrapper {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .results-bar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .results-title {
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #1e293b;
            letter-spacing: 0.5px;
        }
        .results-count {
            font-size: 0.75rem;
            color: #64748b;
        }
        .table-madre {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .table-madre th {
            background: #1e293b;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .table-madre td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle;
        }
        .table-madre tr:hover td { background: #f8fafc; }
        .tag-estado {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-activo { background: #dcfce7; color: #166534; }
        .tag-inactivo { background: #fee2e2; color: #991b1b; }
        .row-actions { display: flex; gap: 5px; }
        .action-btn {
            background: none;
            border: 1px solid transparent;
            padding: 4px 6px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.15s;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .action-btn:hover { border-color: #e2e8f0; background: #f1f5f9; }
        .action-btn.edit { color: #b45309; }
        .action-btn.delete { color: #dc2626; }

        /* Modal confirmación borrado */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }
        .modal-overlay.active { display: flex; animation: fadeInBody 0.2s; }
        .modal-box {
            background: white;
            border-radius: 10px;
            padding: 28px 32px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            text-align: center;
        }
        .modal-icon {
            width: 52px; height: 52px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .modal-icon svg { color: #dc2626; }
        .modal-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .modal-desc { font-size: 0.85rem; color: #64748b; margin-bottom: 22px; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-modal-cancel {
            padding: 8px 24px; border: 1px solid #cbd5e1; border-radius: 6px;
            background: white; color: #64748b; font-weight: 600; cursor: pointer; font-size: 0.85rem;
        }
        .btn-modal-delete {
            padding: 8px 24px; border: none; border-radius: 6px;
            background: #dc2626; color: white; font-weight: 700; cursor: pointer; font-size: 0.85rem;
            transition: background 0.2s;
        }
        .btn-modal-delete:hover { background: #b91c1c; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title" style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:20px;">
                <div>
                    <h1>Contenidos de Acciones (&ldquo;Madre&rdquo;)</h1>
                    <p>Módulos y contenidos vinculados a asignaturas de nivel abuela</p>
                </div>
                <a href="formacion_profesional.php" class="btn-fp" style="display:flex; align-items:center; gap:8px; text-decoration:none; background:#1e293b; color:white; border-radius:0; padding:6px 14px; font-weight:700; font-size:0.75rem; border:1px solid #0f172a; white-space:nowrap;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER
                </a>
            </div>
        </header>

        <!-- Buscador -->
        <section class="search-container-fp">
            <div class="search-header-fp">Contenidos de Asignaturas — Campos de Búsqueda</div>
            <form class="search-form-fp" method="GET">
                <div class="form-group-fp">
                    <label class="form-label-fp">Asignatura (Abuela):</label>
                    <select class="form-control-fp" name="asignatura">
                        <option value="">Todas...</option>
                        <option>Mantenimiento mecánico preventivo del vehículo</option>
                        <option>Didáctica de la educación infantil</option>
                        <option>Técnicas de conducción de bicicletas</option>
                    </select>
                </div>
                <div class="form-group-fp">
                    <label class="form-label-fp">Nombre del Contenido:</label>
                    <input type="text" class="form-control-fp" name="contenido" placeholder="Buscar...">
                </div>
                <div class="form-group-fp">
                    <label class="form-label-fp">Código:</label>
                    <input type="text" class="form-control-fp" name="codigo" placeholder="M-001...">
                </div>
                <div class="form-group-fp">
                    <label class="form-label-fp">Sector:</label>
                    <select class="form-control-fp" name="sector">
                        <option value="">Todos...</option>
                        <?php foreach ($sectores as $s): ?>
                            <option><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-fp">
                    <label class="form-label-fp">Estado:</label>
                    <select class="form-control-fp" name="estado">
                        <option value="">Todos</option>
                        <option>Activo</option>
                        <option>Inactivo</option>
                    </select>
                </div>
                <div class="search-actions-fp">
                    <button type="submit" class="btn-search-fp">Buscar</button>
                    <button type="reset" class="btn-reset-fp">Limpiar</button>
                </div>
            </form>
        </section>

        <!-- Resultados -->
        <section class="results-wrapper">
            <div class="results-bar">
                <span class="results-title">Resultado de la Búsqueda</span>
                <div style="display:flex; align-items:center; gap:15px;">
                    <span class="results-count"><?= count($acciones_madre) ?> registros encontrados</span>
                    <a href="nueva_accion_madre.php" class="btn" style="background:#1e293b; color:white; padding:5px 16px; font-size:0.75rem;">
                        + Nuevo Contenido
                    </a>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="table-madre">
                    <thead>
                        <tr>
                            <th style="width:5%;">Cód.</th>
                            <th style="width:27%;">Asignatura (Abuela)</th>
                            <th style="width:27%;">Contenido (Madre)</th>
                            <th style="width:8%;">Abrev.</th>
                            <th style="width:8%;">Horas</th>
                            <th style="width:15%;">Sector</th>
                            <th style="width:5%;">Estado</th>
                            <th style="width:5%; border-right:none;">Acc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acciones_madre as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['codigo']) ?></strong></td>
                            <td style="color:#64748b; font-size:0.75rem;">
                                <span style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($m['cod_asignatura']) ?></span>
                                — <?= htmlspecialchars($m['asignatura']) ?>
                            </td>
                            <td style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($m['contenido']) ?></td>
                            <td><?= htmlspecialchars($m['abreviatura']) ?></td>
                            <td style="font-weight:700;"><?= htmlspecialchars($m['horas']) ?>h</td>
                            <td style="color:#475569;"><?= htmlspecialchars($m['sector']) ?></td>
                            <td>
                                <span class="tag-estado <?= $m['estado'] === 'Activo' ? 'tag-activo' : 'tag-inactivo' ?>">
                                    <?= htmlspecialchars($m['estado']) ?>
                                </span>
                            </td>
                            <td style="border-right:none;">
                                <div class="row-actions">
                                    <a href="editar_accion_madre.php?id=<?= $m['id'] ?>" class="action-btn edit" title="Editar">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </a>
                                    <button class="action-btn delete" title="Eliminar"
                                        onclick="confirmarBorrado(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['contenido'])) ?>', this)">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Modal confirmación borrado -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        </div>
        <div class="modal-title">¿Eliminar este contenido?</div>
        <div class="modal-desc" id="deleteModalDesc">Esta acción no se puede deshacer.</div>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-modal-delete" id="btnConfirmDelete" onclick="ejecutarBorrado()">Sí, eliminar</button>
        </div>
    </div>
</div>

<script>
let _rowToDelete = null;

function confirmarBorrado(id, nombre, btn) {
    _rowToDelete = btn.closest('tr');
    document.getElementById('deleteModalDesc').textContent =
        '"' + nombre + '" será eliminado permanentemente.';
    document.getElementById('deleteModal').classList.add('active');
}

function cerrarModal() {
    document.getElementById('deleteModal').classList.remove('active');
    _rowToDelete = null;
}

function ejecutarBorrado() {
    if (!_rowToDelete) return;
    _rowToDelete.style.transition = 'all 0.35s ease';
    _rowToDelete.style.opacity = '0';
    _rowToDelete.style.transform = 'translateX(30px)';
    setTimeout(() => {
        _rowToDelete.remove();
        cerrarModal();
        // Actualizar contador
        const tbody = document.querySelector('.table-madre tbody');
        const rows = tbody ? tbody.querySelectorAll('tr').length : 0;
        const counter = document.querySelector('.results-count');
        if (counter) counter.textContent = rows + ' registros encontrados';
    }, 350);
}

// Cerrar al hacer clic fuera del modal
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
</script>

</body>
</html>
