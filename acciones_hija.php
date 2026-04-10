<?php
// acciones_hija.php
require_once 'includes/auth.php';

// Datos de ejemplo para el buscador (en una app real vendrían de DB)
$convocatorias = [
    ['id' => 1, 'nombre' => 'CONTRATOS PROGRAMA 2024'],
    ['id' => 2, 'nombre' => 'FORMACIÓN OCUPADOS 2023'],
    ['id' => 3, 'nombre' => 'PLANES SECTORIALES']
];

$planes = [
    ['id' => 1, 'nombre' => 'COMPETENCIAS DIGITALES'],
    ['id' => 2, 'nombre' => 'SISTEMAS DE SEGURIDAD'],
    ['id' => 3, 'nombre' => 'IDDI - INNOVACIÓN']
];

$sectores = [
    'Automoción', 'Educación', 'Sanidad', 'Turismo', 'Hostelería', 'Informática'
];

$proveedores = ['Edite', 'Appforbrands', 'SIPE'];

$modalidades = ['Teleformación', 'Presencial', 'Mixta', 'Aula Virtual'];
$estados = ['Activo', 'Inactivo', 'En curso', 'Finalizado'];

// Datos de ejemplo para la tabla
$acciones_hija = [
    [
        'id' => 1,
        'num_acc' => '001',
        'titulo' => 'ACREDITACIÓN DOCENTE PARA TELEFORMACIÓN',
        'abrev' => 'ADT',
        'modalidad' => 'Teleformación',
        'duracion' => 60,
        'plan' => 'COMPETENCIAS DIGITALES',
        'partic' => 25,
        'mostrar' => 'Sí',
        'estado' => 'Activo',
        'tutor1' => 'Juan Pérez',
        'tutor2' => '-',
        'win' => '8.0.0',
        'mac' => '2.1.0',
        'proveedor' => 'Edite',
        'precio_venta' => '450.00€',
        'u_inicio' => '15/03/2024'
    ],
    [
        'id' => 2,
        'num_acc' => '005',
        'titulo' => 'GESTIÓN DE FLOTAS Y LOGÍSTICA',
        'abrev' => 'GFL',
        'modalidad' => 'Mixta',
        'duracion' => 45,
        'plan' => 'SISTEMAS DE SEGURIDAD',
        'partic' => 15,
        'mostrar' => 'Sí',
        'estado' => 'En curso',
        'tutor1' => 'Ana Belén',
        'tutor2' => 'Carlos Ruíz',
        'win' => '1.2.5',
        'mac' => '1.0.0',
        'proveedor' => 'Appforbrands',
        'precio_venta' => '325.00€',
        'u_inicio' => '20/03/2024'
    ]
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acciones Formativas (Hija) - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Estilos específicos adaptados del modelo Madre */
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
        }
        .form-grid-fp {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            align-items: flex-end;
        }
        .form-group-fp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-label-fp {
            font-weight: 700;
            font-size: 0.75rem;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .form-control-fp {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            background: #f8fafc;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
        }
        .form-control-fp:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }
        .btn-fp-action {
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            justify-content: center;
        }
        .btn-fp-action.primary { background: #334155; color: white; }
        .btn-fp-action.primary:hover { background: #1e293b; }
        .btn-fp-action.pdf { background: var(--primary-color); color: white; }
        .btn-fp-action.pdf:hover { background: var(--primary-hover); }
        .btn-fp-action.pdf svg { width: 14px; height: 14px; }

        .search-actions-fp {
            grid-column: span 12;
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        /* Tabla Estilo Madre Premium */
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
        .table-hija {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .table-hija th {
            background: #1e293b;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-right: 1px solid rgba(255,255,255,0.1);
            white-space: nowrap;
        }
        .table-hija td {
            padding: 10px 8px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle;
        }
        .table-hija tr:hover td { background: #f8fafc; }
        
        .tag-estado {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        .tag-activo { background: #dcfce7; color: #166534; }
        .tag-inactivo { background: #fee2e2; color: #991b1b; }
        .tag-curso { background: #dbeafe; color: #1e40af; }
        
        .row-actions { display: flex; gap: 5px; }
        .action-btn {
            background: none;
            border: 1px solid transparent;
            padding: 4px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.15s;
            color: #64748b;
        }
        .action-btn:hover { border-color: #e2e8f0; background: #f1f5f9; color: var(--primary-color); }

        /* Modal Deletión base */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000;
            align-items: center; justify-content: center; backdrop-filter: blur(3px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white; border-radius: 10px; padding: 25px; max-width: 400px; width: 90%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); text-align: center;
        }
        .modal-actions { display: flex; gap: 10px; justify-content: center; margin-top: 20px; }
        .btn-modal { padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; border: 1px solid #cbd5e1; }
        .btn-modal.confirm { background: var(--primary-color); color: white; border: none; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="fp-layout">
            <?php include 'includes/fp_sidebar.php'; ?>
            
            <section class="fp-content-main">
                <header class="page-header">
                    <div class="page-title" style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:20px;">
                        <div>
                            <h1 style="color:var(--primary-color); font-weight:800; text-transform:uppercase; font-size:1.2rem; margin:0;">Acciones Formativas (&ldquo;Hija&rdquo;)</h1>
                            <p style="color:#64748b; font-size:0.85rem; margin:5px 0 0;">Gestión técnica de acciones formativas finales y grupos vinculados</p>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <a href="ficha_accion_formativa.php" class="btn-fp-action primary" style="background:#059669;">+ Nueva Acción</a>
                            <a href="formacion_profesional.php" class="btn-fp-action primary">Volver</a>
                        </div>
                    </div>
                </header>

                <!-- Buscador -->
                <section class="search-container-fp">
                    <div class="search-header-fp">Acciones Formativas Finales — Campos de Búsqueda</div>
                    <form class="search-form-fp" method="GET">
                        <div class="form-grid-fp">
                            <div class="form-group-fp" style="grid-column: span 8;">
                                <label class="form-label-fp">Nombre / Título:</label>
                                <input type="text" class="form-control-fp" name="nombre" placeholder="Buscar por título...">
                            </div>
                            <div class="form-group-fp" style="grid-column: span 4;">
                                <label class="form-label-fp">Nº Acción:</label>
                                <input type="text" class="form-control-fp" name="num_accion" placeholder="Ej: 001">
                            </div>

                            <div class="form-group-fp" style="grid-column: span 4;">
                                <label class="form-label-fp">Convocatoria:</label>
                                <select class="form-control-fp" name="convocatoria">
                                    <option value="">Todas</option>
                                    <?php foreach ($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-fp" style="grid-column: span 4;">
                                <label class="form-label-fp">Plan:</label>
                                <select class="form-control-fp" name="plan">
                                    <option value="">Todos</option>
                                    <?php foreach ($planes as $p): ?><option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-fp" style="grid-column: span 4;">
                                <label class="form-label-fp">Sector:</label>
                                <select class="form-control-fp" name="sector">
                                    <option value="">Todos</option>
                                    <?php foreach ($sectores as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group-fp" style="grid-column: span 3;">
                                <label class="form-label-fp">Modalidad:</label>
                                <select class="form-control-fp" name="modalidad">
                                    <option value="">Todas</option>
                                    <?php foreach ($modalidades as $m): ?><option><?= $m ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-fp" style="grid-column: span 3;">
                                <label class="form-label-fp">Proveedor:</label>
                                <select class="form-control-fp" name="proveedor">
                                    <option value="">Todos</option>
                                    <?php foreach ($proveedores as $pr): ?><option><?= $pr ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-fp" style="grid-column: span 3;">
                                <label class="form-label-fp">Estado:</label>
                                <select class="form-control-fp" name="estado">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $e): ?><option><?= $e ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-fp" style="grid-column: span 3;">
                                <label class="form-label-fp">Reserva:</label>
                                <select class="form-control-fp" name="reserva">
                                    <option value="">Cualquiera</option>
                                    <option value="S">Sí</option>
                                    <option value="N">No</option>
                                </select>
                            </div>

                            <div class="search-actions-fp">
                                <button type="submit" class="btn-fp-action primary" style="width:120px;">Buscar</button>
                                <button type="button" class="btn-fp-action pdf">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM14 12h1V8h-1v4zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                                    Contenidos
                                </button>
                                <button type="button" class="btn-fp-action pdf">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                                    Imprimir
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Resultados -->
                <section class="results-wrapper">
                    <div class="results-bar">
                        <span class="results-title">Resultados de Acciones Formativas</span>
                        <span class="results-count"><?= count($acciones_hija) ?> registros cargados</span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="table-hija">
                            <thead>
                                <tr>
                                    <th>Nº Acc</th>
                                    <th>Título / Nombre</th>
                                    <th>Abrev.</th>
                                    <th>Mod.</th>
                                    <th>Dur.</th>
                                    <th>Plan</th>
                                    <th>Part.</th>
                                    <th>Web</th>
                                    <th>Estado</th>
                                    <th>Tutor 1</th>
                                    <th>Tutor 2</th>
                                    <th>W</th>
                                    <th>M</th>
                                    <th>Prov.</th>
                                    <th>Precio</th>
                                    <th style="border-right:none;">Acc.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($acciones_hija as $h): ?>
                                <tr>
                                    <td><strong><?= $h['num_acc'] ?></strong></td>
                                    <td style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($h['titulo']) ?></td>
                                    <td><?= $h['abrev'] ?></td>
                                    <td><span style="font-size:0.65rem; color:#64748b;"><?= $h['modalidad'] ?></span></td>
                                    <td style="font-weight:700;"><?= $h['duracion'] ?>h</td>
                                    <td style="font-size:0.65rem; color:#475569; max-width:120px;"><?= $h['plan'] ?></td>
                                    <td style="text-align:center; font-weight:700;"><?= $h['partic'] ?></td>
                                    <td style="text-align:center;"><?= $h['mostrar'] ?></td>
                                    <td>
                                        <span class="tag-estado <?= $h['estado'] === 'Activo' ? 'tag-activo' : ($h['estado'] === 'En curso' ? 'tag-curso' : 'tag-inactivo') ?>">
                                            <?= $h['estado'] ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.65rem;"><?= $h['tutor1'] ?></td>
                                    <td style="font-size:0.65rem;"><?= $h['tutor2'] ?></td>
                                    <td><?= $h['win'] ?></td>
                                    <td><?= $h['mac'] ?></td>
                                    <td><?= $h['proveedor'] ?></td>
                                    <td style="font-weight:700; color:#059669;"><?= $h['precio_venta'] ?></td>
                                    <td style="border-right:none;">
                                        <div class="row-actions">
                                            <a href="ficha_accion_formativa.php?id=<?= $h['id'] ?>" class="action-btn" title="Editar">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <button class="action-btn" title="Eliminar" onclick="confirmarBorrado('<?= $h['num_acc'] ?>')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </main>
</div>

<!-- Modal Borrado -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3 style="margin-top:0;">¿Eliminar acción?</h3>
        <p style="color:#64748b; font-size:0.85rem;">Esta acción eliminará permanentemente la acción formativa y sus datos asociados.</p>
        <div class="modal-actions">
            <button class="btn-modal" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-modal confirm" onclick="ejecutarBorrado()">Sí, eliminar</button>
        </div>
    </div>
</div>

<script>
    let _idToDelete = null;
    function confirmarBorrado(id) {
        _idToDelete = id;
        document.getElementById('deleteModal').classList.add('active');
    }
    function cerrarModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    function ejecutarBorrado() {
        alert('Acción ' + _idToDelete + ' eliminada (Simulación)');
        cerrarModal();
    }
</script>

</body>
</html>
