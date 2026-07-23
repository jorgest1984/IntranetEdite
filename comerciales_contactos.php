<?php
// comerciales_contactos.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';
$comercial_id = $_SESSION['user_id'];

// CREAR TABLAS SI NO EXISTEN
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS comerciales_contactos_empresa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercial_id INT NOT NULL,
        empresa VARCHAR(255) NOT NULL,
        actividad VARCHAR(255),
        sector VARCHAR(255),
        baja TINYINT(1) DEFAULT 0,
        no_valido TINYINT(1) DEFAULT 0,
        motivo_no_valido VARCHAR(255),
        interesado_nuevas TINYINT(1) DEFAULT 0,
        domicilio VARCHAR(255),
        cp VARCHAR(20),
        localidad VARCHAR(255),
        provincia VARCHAR(255),
        nif VARCHAR(50),
        telefono VARCHAR(50),
        movil_1 VARCHAR(50),
        movil_2 VARCHAR(50),
        movil_3 VARCHAR(50),
        web VARCHAR(255),
        email_1 VARCHAR(255),
        email_2 VARCHAR(255),
        email_3 VARCHAR(255),
        email_4 VARCHAR(255),
        toma_contacto_1 TEXT,
        toma_contacto_2 TEXT,
        toma_contacto_3 TEXT,
        observacion_1 TEXT,
        observacion_2 TEXT,
        observacion_3 TEXT,
        tema_interes VARCHAR(255),
        es_promax TINYINT(1) DEFAULT 0,
        persona_contacto VARCHAR(255),
        tiene_certificados TINYINT(1) DEFAULT 0,
        num_certificados INT DEFAULT 0,
        notas TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comerciales_contactos_trabajador (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comercial_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(150),
        nif VARCHAR(50),
        empresa_id INT DEFAULT NULL,
        empresa_nombre VARCHAR(255),
        puesto VARCHAR(255),
        telefono VARCHAR(50),
        movil VARCHAR(50),
        email VARCHAR(255),
        domicilio VARCHAR(255),
        cp VARCHAR(20),
        localidad VARCHAR(255),
        provincia VARCHAR(255),
        notas TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
    // Ignorar si falla por permisos
}

// PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tipo_contacto']) && $_POST['tipo_contacto'] === 'empresa') {
        try {
            $stmt = $pdo->prepare("INSERT INTO comerciales_contactos_empresa (
                comercial_id, empresa, actividad, sector, baja, no_valido, motivo_no_valido,
                interesado_nuevas, domicilio, cp, localidad, provincia, telefono, movil_1,
                email_1, tema_interes, es_promax, persona_contacto, tiene_certificados,
                num_certificados, notas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $comercial_id,
                $_POST['empresa'] ?? '',
                $_POST['actividad'] ?? '',
                $_POST['sector'] ?? '',
                isset($_POST['baja']) ? 1 : 0,
                isset($_POST['no_valido']) ? 1 : 0,
                $_POST['motivo_no_valido'] ?? '',
                isset($_POST['interesado_nuevas']) ? 1 : 0,
                $_POST['domicilio'] ?? '',
                $_POST['cp'] ?? '',
                $_POST['localidad'] ?? '',
                $_POST['provincia'] ?? '',
                $_POST['telefono1'] ?? '',
                $_POST['movil'] ?? '',
                $_POST['email'] ?? '',
                $_POST['tema_interes'] ?? '',
                isset($_POST['es_promax']) ? 1 : 0,
                $_POST['persona_contacto'] ?? '',
                isset($_POST['tiene_certificados']) ? 1 : 0,
                (int)($_POST['num_certificados'] ?? 0),
                $_POST['notas'] ?? ''
            ]);
            $success = "Contacto de Empresa insertado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al insertar la empresa: " . $e->getMessage();
        }
    } elseif (isset($_POST['tipo_contacto']) && $_POST['tipo_contacto'] === 'trabajador') {
        try {
            $stmt = $pdo->prepare("INSERT INTO comerciales_contactos_trabajador (
                comercial_id, nombre, apellidos, nif, empresa_nombre, puesto,
                telefono, movil, email, domicilio, cp, localidad, provincia, notas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $comercial_id,
                $_POST['nombre'] ?? '',
                $_POST['apellidos'] ?? '',
                $_POST['nif'] ?? '',
                $_POST['empresa_nombre'] ?? '',
                $_POST['puesto'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['movil'] ?? '',
                $_POST['email'] ?? '',
                $_POST['domicilio'] ?? '',
                $_POST['cp'] ?? '',
                $_POST['localidad'] ?? '',
                $_POST['provincia'] ?? '',
                $_POST['notas'] ?? ''
            ]);
            $success = "Contacto de Trabajador insertado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al insertar el trabajador: " . $e->getMessage();
        }
    }
}

$provincias = [
    'A Coruña', 'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila', 'Badajoz', 'Islas Baleares', 'Barcelona', 'Burgos', 'Cáceres', 'Cádiz', 'Cantabria', 'Castellón', 'Ciudad Real', 'Córdoba', 'Cuenca', 'Girona', 'Granada', 'Guadalajara', 'Gipuzkoa', 'Huelva', 'Huesca', 'Jaén', 'La Rioja', 'Las Palmas', 'León', 'Lleida', 'Lugo', 'Madrid', 'Málaga', 'Murcia', 'Navarra', 'Ourense', 'Palencia', 'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife', 'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Teruel', 'Toledo', 'Valencia', 'Valladolid', 'Vizcaya', 'Zamora', 'Zaragoza', 'Ceuta', 'Melilla'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 1rem 0.75rem; }
        .btn-volver {
            padding: 6px 20px;
            font-size: 0.85rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
            color: #475569;
            font-weight: 500;
        }
        .btn-volver:hover { background: #e2e8f0; }
        
        /* Tabs styling */
        .tabs { display: flex; margin-top: 20px; margin-bottom: 0; border-bottom: 2px solid #cbd5e1; }
        .tab { padding: 10px 25px; cursor: pointer; background: #e2e8f0; border-top-left-radius: 6px; border-top-right-radius: 6px; margin-right: 5px; font-weight: 600; color: #475569; border: 1px solid #cbd5e1; border-bottom: none; }
        .tab.active { background: #fff; color: #b91c1c; border-bottom: 2px solid #fff; margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        :root {
            --title-red: #b91c1c;
            --label-blue: #000080;
            --border-gray: #cbd5e1;
            --bg-gray: #f1f5f9;
            --input-bg: #e2e8f0;
        }
        .form-section {
            background: #fff;
            border: 1px solid var(--border-gray);
            border-top: none;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            width: 100%;
            box-sizing: border-box;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
        }
        .section-header {
            text-align: center;
            border-bottom: 1px solid var(--border-gray);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
        }
        .section-header h2 {
            color: var(--title-red);
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            align-items: center;
        }
        .form-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .label {
            color: var(--label-blue);
            font-weight: 700;
            font-size: 0.8rem;
            white-space: nowrap;
            display: inline-block;
            flex-shrink: 0;
        }
        .form-control {
            border: 1px solid #94a3b8;
            background: var(--input-bg);
            padding: 4px 8px;
            border-radius: 2px;
            font-size: 0.85rem;
            width: 100%;
            font-family: inherit;
            color: var(--label-blue);
            height: 28px;
            box-sizing: border-box;
        }
        .form-control:focus {
            background: #fff;
            outline: none;
            border-color: #64748b;
        }
        .checkbox-group { display: flex; align-items: center; gap: 5px; }
        .btn-submit {
            background: #f1f5f9;
            border: 1px solid #94a3b8;
            color: var(--label-blue);
            font-weight: 500;
            padding: 6px 20px;
            font-size: 0.95rem;
            cursor: pointer;
            border-radius: 4px;
        }
        .btn-submit:hover { background: #e2e8f0; }
        .alert-success { background: #dcfce7; color: #166534; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 0;">
                <a href="comerciales.php" class="btn-volver">← Volver a Gestión Comercial</a>
            </div>

            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" onclick="switchTab('empresa')">Contacto Empresa</div>
                <div class="tab" onclick="switchTab('trabajador')">Contacto Trabajador</div>
            </div>

            <!-- TAB EMPRESA -->
            <div id="tab-empresa" class="tab-content active">
                <form method="POST" style="width: 100%;">
                    <input type="hidden" name="tipo_contacto" value="empresa">
                    <div class="form-section">
                        <div class="section-header"><h2>FICHA DE CONTACTOS EMPRESA</h2></div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <span class="label">Empresa:</span>
                                <input type="text" class="form-control" name="empresa" required>
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <span class="label">Actividad:</span>
                                <input type="text" class="form-control" name="actividad">
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <span class="label">Sector:</span>
                                <input type="text" class="form-control" name="sector">
                            </div>
                        </div>

                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group" style="flex: 3;">
                                <span class="label" style="width: 100px;">Domicilio:</span>
                                <input type="text" class="form-control" name="domicilio">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">CP:</span>
                                <input type="text" class="form-control" name="cp">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <span class="label" style="width: 100px;">Localidad:</span>
                                <input type="text" class="form-control" name="localidad">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <span class="label">Provincia:</span>
                                <select class="form-control" name="provincia">
                                    <option value=""></option>
                                    <?php foreach($provincias as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <span class="label" style="width: 100px;">Teléfono 1:</span>
                                <input type="text" class="form-control" name="telefono1">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">Móvil:</span>
                                <input type="text" class="form-control" name="movil">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <span class="label">E-mail:</span>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>

                        <div class="form-row" style="align-items: flex-start; margin-top: 15px;">
                            <span class="label" style="width: 100px; margin-top: 5px;">Notas:</span>
                            <textarea class="form-control" name="notas" style="height: 80px; resize: vertical;"></textarea>
                        </div>

                        <div style="text-align: center; margin-top: 25px;">
                            <button type="submit" class="btn-submit">Insertar Contacto Empresa</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB TRABAJADOR -->
            <div id="tab-trabajador" class="tab-content">
                <form method="POST" style="width: 100%;">
                    <input type="hidden" name="tipo_contacto" value="trabajador">
                    <div class="form-section">
                        <div class="section-header"><h2>FICHA DE CONTACTOS TRABAJADOR</h2></div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <span class="label" style="width: 100px;">Nombre:</span>
                                <input type="text" class="form-control" name="nombre" required>
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <span class="label">Apellidos:</span>
                                <input type="text" class="form-control" name="apellidos">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">NIF/NIE:</span>
                                <input type="text" class="form-control" name="nif">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <span class="label" style="width: 100px;">Empresa:</span>
                                <input type="text" class="form-control" name="empresa_nombre" placeholder="Empresa en la que trabaja actualmente">
                            </div>
                            <div class="form-group" style="flex: 1.5;">
                                <span class="label">Puesto:</span>
                                <input type="text" class="form-control" name="puesto">
                            </div>
                        </div>

                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group" style="flex: 3;">
                                <span class="label" style="width: 100px;">Domicilio:</span>
                                <input type="text" class="form-control" name="domicilio">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">CP:</span>
                                <input type="text" class="form-control" name="cp">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <span class="label" style="width: 100px;">Localidad:</span>
                                <input type="text" class="form-control" name="localidad">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <span class="label">Provincia:</span>
                                <select class="form-control" name="provincia">
                                    <option value=""></option>
                                    <?php foreach($provincias as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <span class="label" style="width: 100px;">Teléfono:</span>
                                <input type="text" class="form-control" name="telefono">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <span class="label">Móvil:</span>
                                <input type="text" class="form-control" name="movil">
                            </div>
                            <div class="form-group" style="flex: 2;">
                                <span class="label">E-mail:</span>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>

                        <div class="form-row" style="align-items: flex-start; margin-top: 15px;">
                            <span class="label" style="width: 100px; margin-top: 5px;">Notas:</span>
                            <textarea class="form-control" name="notas" style="height: 80px; resize: vertical;"></textarea>
                        </div>

                        <div style="text-align: center; margin-top: 25px;">
                            <button type="submit" class="btn-submit">Insertar Contacto Trabajador</button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if(tabId === 'empresa') {
                document.querySelector('.tab:nth-child(1)').classList.add('active');
                document.getElementById('tab-empresa').classList.add('active');
            } else {
                document.querySelector('.tab:nth-child(2)').classList.add('active');
                document.getElementById('tab-trabajador').classList.add('active');
            }
        }
    </script>
</body>
</html>
