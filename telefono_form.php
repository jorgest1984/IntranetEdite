<?php
require_once 'includes/auth.php';

$id      = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = $id > 0;
$success = '';
$error   = '';

// Cargar usuarios para el selector
$usuarios_list = $pdo->query("SELECT id, CONCAT(nombre,' ',COALESCE(apellidos,'')) AS nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

// ─── Guardar ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero       = trim($_POST['numero'] ?? '');
    $extension    = trim($_POST['extension'] ?? '');
    $imei         = trim($_POST['imei'] ?? '');
    $iccid        = trim($_POST['iccid'] ?? '');
    $modelo       = trim($_POST['modelo'] ?? '');
    $operador     = trim($_POST['operador'] ?? '');
    $pin          = trim($_POST['pin'] ?? '');
    $puk          = trim($_POST['puk'] ?? '');
    $estado       = $_POST['estado'] ?? 'Disponible';
    $sede         = trim($_POST['sede'] ?? '');
    $observaciones= trim($_POST['observaciones'] ?? '');

    if ($is_edit) {
        $stmt = $pdo->prepare("UPDATE telefonos SET numero=?,extension=?,imei=?,iccid=?,modelo=?,operador=?,pin=?,puk=?,estado=?,sede=?,observaciones=? WHERE id=?");
        $stmt->execute([$numero,$extension,$imei,$iccid,$modelo,$operador,$pin,$puk,$estado,$sede,$observaciones,$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO telefonos (numero,extension,imei,iccid,modelo,operador,pin,puk,estado,sede,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$numero,$extension,$imei,$iccid,$modelo,$operador,$pin,$puk,$estado,$sede,$observaciones]);
        $id = $pdo->lastInsertId();
        $is_edit = true;
    }

    // Asignaciones: borrar las existentes y reinsertar
    $pdo->prepare("DELETE FROM telefono_asignaciones WHERE telefono_id = ?")->execute([$id]);

    $usuarios_asig = $_POST['asig_usuario'] ?? [];
    $desdes        = $_POST['asig_desde']   ?? [];
    $hastas        = $_POST['asig_hasta']   ?? [];

    foreach ($usuarios_asig as $i => $uid) {
        if (empty($uid)) continue;
        // Buscar nombre del usuario
        $stmt_u = $pdo->prepare("SELECT CONCAT(nombre,' ',COALESCE(apellidos,'')) AS nombre_completo FROM usuarios WHERE id=?");
        $stmt_u->execute([$uid]);
        $u_row = $stmt_u->fetch();
        $nombre_u = $u_row['nombre_completo'] ?? '';

        $desde = !empty($desdes[$i]) ? $desdes[$i] : null;
        $hasta = !empty($hastas[$i]) ? $hastas[$i] : null;

        $stmt2 = $pdo->prepare("INSERT INTO telefono_asignaciones (telefono_id, usuario_id, nombre_usuario, desde, hasta) VALUES (?,?,?,?,?)");
        $stmt2->execute([$id, $uid, $nombre_u, $desde, $hasta]);
    }

    $success = $is_edit ? 'Teléfono actualizado correctamente.' : 'Teléfono creado correctamente.';
    header("Location: telefonos.php?ok=1");
    exit;
}

// ─── Cargar datos si edición ──────────────────────────────────────────────────
$telefono    = [];
$asignaciones = [];
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM telefonos WHERE id = ?");
    $stmt->execute([$id]);
    $telefono = $stmt->fetch();
    if (!$telefono) { header("Location: telefonos.php"); exit; }

    $stmt2 = $pdo->prepare("SELECT * FROM telefono_asignaciones WHERE telefono_id = ? ORDER BY desde DESC");
    $stmt2->execute([$id]);
    $asignaciones = $stmt2->fetchAll();
}
$t = $telefono;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Editar' : 'Nuevo' ?> Teléfono - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .form-card { max-width: 700px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .form-row { display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .form-row label { font-size: 0.88rem; font-weight: 600; color: #475569; text-align: right; }
        .form-row input, .form-row select, .form-row textarea {
            padding: 0.55rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 6px;
            font-family: inherit; font-size: 0.9rem; background: #f8fafc; transition: border-color 0.2s; width: 100%; box-sizing: border-box;
        }
        .form-row input:focus, .form-row select:focus, .form-row textarea:focus {
            outline: none; border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,0.12); background: #fff;
        }
        .form-row textarea { height: 90px; resize: vertical; }

        /* Asignaciones */
        .asig-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; background: #fafafa; }
        .asig-row { display: flex; gap: 0.6rem; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; }
        .asig-row select { flex: 2; min-width: 180px; padding: 0.5rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; }
        .asig-row input[type="date"] { flex: 1; min-width: 120px; padding: 0.5rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; }
        .asig-row label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
        .btn-del-asig { background: #dc2626; color: #fff; border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-size: 1.1rem; line-height: 1; }
        .btn-add-asig { background: #059669; color: #fff; border: none; border-radius: 6px; padding: 0.45rem 1rem; cursor: pointer; font-size: 0.85rem; font-weight: 600; }

        .breadcrumb { display:flex; gap:0.4rem; font-size:0.83rem; color:#64748b; margin-bottom:1.25rem; align-items:center; }
        .breadcrumb a { color:#dc2626; text-decoration:none; font-weight:500; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb span { color:#94a3b8; }
        .code-field { background: #f1f5f9; font-family: monospace; color: #475569; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="max-width:720px; padding:1.5rem 0;">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="home.php">Inicio</a><span>/</span>
                <a href="edite_formacion.php">Edite Formación</a><span>/</span>
                <a href="telefonos.php">Teléfonos</a><span>/</span>
                <span><?= $is_edit ? 'Editar #'.$id : 'Nuevo teléfono' ?></span>
            </nav>

            <div class="form-card">
                <h2 style="margin:0 0 1.5rem; font-size:1.15rem; color:#dc2626;"><?= $is_edit ? 'Editar Teléfono' : 'Alta de Nuevo Teléfono' ?></h2>

                <form method="POST">
                    <?php if ($is_edit): ?>
                    <div class="form-row">
                        <label>Código</label>
                        <input type="text" value="<?= $id ?>" class="code-field" readonly style="width:80px;">
                    </div>
                    <?php endif; ?>

                    <div class="form-row">
                        <label for="numero">Número</label>
                        <input type="text" id="numero" name="numero" value="<?= htmlspecialchars($t['numero'] ?? '') ?>" placeholder="626996324">
                    </div>
                    <div class="form-row">
                        <label for="extension">Extensión</label>
                        <input type="text" id="extension" name="extension" value="<?= htmlspecialchars($t['extension'] ?? '') ?>" placeholder="8000">
                    </div>
                    <div class="form-row">
                        <label for="imei">IMEI</label>
                        <input type="text" id="imei" name="imei" value="<?= htmlspecialchars($t['imei'] ?? '') ?>" placeholder="356476068222845">
                    </div>
                    <div class="form-row">
                        <label for="iccid">ICCID</label>
                        <input type="text" id="iccid" name="iccid" value="<?= htmlspecialchars($t['iccid'] ?? '') ?>" placeholder="8934014072007372282">
                    </div>
                    <div class="form-row">
                        <label for="modelo">Modelo</label>
                        <input type="text" id="modelo" name="modelo" value="<?= htmlspecialchars($t['modelo'] ?? '') ?>" placeholder="Xiaomi Redmi 7A Matt">
                    </div>
                    <div class="form-row">
                        <label for="operador">Operador</label>
                        <input type="text" id="operador" name="operador" value="<?= htmlspecialchars($t['operador'] ?? '') ?>" placeholder="Orange">
                    </div>
                    <div class="form-row">
                        <label for="pin">PIN</label>
                        <input type="text" id="pin" name="pin" value="<?= htmlspecialchars($t['pin'] ?? '') ?>" placeholder="3208">
                    </div>
                    <div class="form-row">
                        <label for="puk">PUK</label>
                        <input type="text" id="puk" name="puk" value="<?= htmlspecialchars($t['puk'] ?? '') ?>" placeholder="53382914">
                    </div>
                    <div class="form-row">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="En uso"     <?= ($t['estado'] ?? '') === 'En uso'      ? 'selected' : '' ?>>En uso</option>
                            <option value="Disponible" <?= ($t['estado'] ?? 'Disponible') === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="Baja"       <?= ($t['estado'] ?? '') === 'Baja'        ? 'selected' : '' ?>>Baja</option>
                        </select>
                    </div>

                    <!-- Asignaciones -->
                    <div class="form-row" style="align-items:flex-start;">
                        <label style="padding-top:0.5rem;">Usuario(s)<br>asignado(s)</label>
                        <div>
                            <div class="asig-box">
                                <!-- Cabecera -->
                                <div style="display:flex;gap:0.6rem;margin-bottom:0.4rem;padding:0 0 0.3rem;border-bottom:1px solid #e2e8f0;">
                                    <div style="flex:2;font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">USUARIO</div>
                                    <div style="flex:1;font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">DESDE</div>
                                    <div style="flex:1;font-size:0.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">HASTA</div>
                                    <div style="width:30px;"></div>
                                </div>
                                <div id="asig-list">
                                    <?php
                                    $asig_to_render = !empty($asignaciones) ? $asignaciones : [['usuario_id'=>'','desde'=>'','hasta'=>'']];
                                    foreach ($asig_to_render as $a):
                                    ?>
                                    <div class="asig-row">
                                        <select name="asig_usuario[]" style="flex:2; min-width:180px;">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ($usuarios_list as $ul): ?>
                                            <option value="<?= $ul['id'] ?>" <?= ($a['usuario_id'] ?? '') == $ul['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ul['nombre_completo']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="date" name="asig_desde[]" value="<?= htmlspecialchars($a['desde'] ?? '') ?>">
                                        <input type="date" name="asig_hasta[]" value="<?= htmlspecialchars($a['hasta'] ?? '') ?>">
                                        <button type="button" class="btn-del-asig" onclick="this.closest('.asig-row').remove()">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add-asig" onclick="addAsig()">+ Nueva asignación</button>
                            </div>
                        </div>
                    </div>

                    <!-- Sede -->
                    <div class="form-row">
                        <label for="sede">Sede</label>
                        <select id="sede" name="sede">
                            <option value="">— Selecciona —</option>
                            <?php foreach (['Almería','Centralita','Granada','Madrid - Francisco Silvela','Valladolid'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($t['sede'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                            <?php
                            // Añadir sede actual si no está en la lista
                            if (!empty($t['sede']) && !in_array($t['sede'], ['Almería','Centralita','Granada','Madrid - Francisco Silvela','Valladolid'])):
                            ?>
                            <option value="<?= htmlspecialchars($t['sede']) ?>" selected><?= htmlspecialchars($t['sede']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Observaciones -->
                    <div class="form-row" style="align-items:flex-start;">
                        <label for="observaciones" style="padding-top:0.5rem;">Observaciones</label>
                        <textarea id="observaciones" name="observaciones"><?= htmlspecialchars($t['observaciones'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex; gap:0.75rem; margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f1f5f9;">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <a href="telefonos.php" class="btn" style="background:#f1f5f9;color:#475569;border:none;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
// Template de nueva fila de asignación
const usuariosOpts = <?= json_encode(array_map(fn($u) => ['id'=>$u['id'],'nombre'=>$u['nombre_completo']], $usuarios_list)) ?>;

function addAsig() {
    const list = document.getElementById('asig-list');
    let opts = '<option value="">— Sin asignar —</option>';
    usuariosOpts.forEach(u => { opts += `<option value="${u.id}">${u.nombre}</option>`; });
    const row = document.createElement('div');
    row.className = 'asig-row';
    row.innerHTML = `
        <select name="asig_usuario[]" style="flex:2; min-width:180px;">${opts}</select>
        <input type="date" name="asig_desde[]">
        <input type="date" name="asig_hasta[]">
        <button type="button" class="btn-del-asig" onclick="this.closest('.asig-row').remove()">×</button>`;
    list.appendChild(row);
}
</script>
</body>
</html>
