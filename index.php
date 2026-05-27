<?php
// index.php
require_once 'includes/config.php';

// ===========================================================
// MIGRACIÓN: tabla login_attempts con timestamp Unix (INT)
// Completely timezone-immune: solo compara números enteros
// ===========================================================
try {
    // Comprobar si la tabla ya tiene la columna correcta (attempt_unix INT).
    // NOTA: rowCount() en SHOW COLUMNS no es fiable con PDO/MySQL.
    // En su lugar, hacemos un SELECT directo con LIMIT 0 (no trae filas,
    // solo valida que la columna y la tabla existen).
    try {
        $pdo->query("SELECT attempt_unix FROM `login_attempts` LIMIT 0");
        // Si llega aquí: tabla y columna correctas → no hay nada que migrar
    } catch (PDOException $e_col) {
        // La tabla no existe O tiene el esquema antiguo → recrear limpia
        $pdo->query("DROP TABLE IF EXISTS `login_attempts`");
        $pdo->query("CREATE TABLE `login_attempts` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip_address`    VARCHAR(45)  NOT NULL,
            `username`      VARCHAR(150) NOT NULL,
            `attempt_unix`  INT UNSIGNED NOT NULL COMMENT 'PHP time() — sin zonas horarias',
            `is_successful` TINYINT(1)   NOT NULL DEFAULT 0,
            KEY `idx_lock` (`ip_address`, `username`, `attempt_unix`, `is_successful`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Asegurar que audit_log acepta usuario_id NULL
    try { $pdo->query("ALTER TABLE `audit_log` MODIFY COLUMN `usuario_id` INT(11) NULL"); } catch (PDOException $e2) {}

} catch (PDOException $e) {
    // Silencioso en producción
}

$error = '';

// Constantes de bloqueo
define('MAX_INTENTOS',      3);
define('VENTANA_SEGUNDOS',  900); // 15 minutos

// Si ya está logueado, redirigir al home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    usleep(250000); // 0.25s anti-timing attack

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Por favor, introduce usuario y contraseña.";
    } else {
        // Validar token CSRF
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            $error = "Error de seguridad (CSRF). Por favor, refresque la página e inténtelo de nuevo.";
        } else {
            try {
                // Obtener IP real (compatible con balanceadores / Plesk)
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip_address = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
                } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip_address = $_SERVER['HTTP_CLIENT_IP'];
                }

                // ============================================================
                // BLOQUEO POR UNIX TIMESTAMP — solo comparación de enteros,
                // completamente inmune a zonas horarias de MySQL/Plesk
                // ============================================================
                $ahora      = time();                         // entero PHP
                $desde      = $ahora - VENTANA_SEGUNDOS;     // entero PHP (15 min atrás)

                // Contar fallos de esta IP+usuario en los últimos 15 minutos
                $st_count = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts
                    WHERE ip_address   = ?
                      AND username     = ?
                      AND is_successful = 0
                      AND attempt_unix  > ?
                ");
                $st_count->execute([$ip_address, strtolower($username), $desde]);
                $fallos = (int) $st_count->fetchColumn();

                if ($fallos >= MAX_INTENTOS) {
                    // Ya bloqueado — calcular tiempo restante
                    $st_first = $pdo->prepare("
                        SELECT MIN(attempt_unix) FROM login_attempts
                        WHERE ip_address    = ?
                          AND username      = ?
                          AND is_successful = 0
                          AND attempt_unix  > ?
                    ");
                    $st_first->execute([$ip_address, strtolower($username), $desde]);
                    $primer_fallo  = (int) $st_first->fetchColumn();
                    $desbloqueo    = $primer_fallo + VENTANA_SEGUNDOS;
                    $segundos_left = max(0, $desbloqueo - $ahora);
                    $minutos_left  = (int) ceil($segundos_left / 60);
                    $error = "Acceso bloqueado por seguridad. Inténtelo de nuevo en {$minutos_left} minuto" . ($minutos_left != 1 ? "s" : "") . ".";

                } else {
                    // No bloqueado — verificar credenciales
                    $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id WHERE u.username = ? AND u.activo = 1");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        // ✅ LOGIN CORRECTO
                        // Eliminar intentos fallidos previos (resetear contador)
                        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = ? AND is_successful = 0")
                            ->execute([$ip_address, strtolower($username)]);

                        session_regenerate_id(true); // Prevenir session fixation

                        $_SESSION['user_id']        = $user['id'];
                        $_SESSION['username']        = $user['username'];
                        $apellidos = $user['apellidos'] ?? (($user['primer_apellido'] ?? '') . ' ' . ($user['segundo_apellido'] ?? ''));
                        $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . trim($apellidos);
                        $_SESSION['rol_id']          = $user['rol_id'];
                        $_SESSION['rol_nombre']      = $user['rol_nombre'];

                        $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$user['id']]);

                        // Auditoría — login exitoso
                        try {
                            $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_unix, is_successful) VALUES (?, ?, ?, 1)")
                                ->execute([$ip_address, strtolower($username), $ahora]);
                        } catch (PDOException $e) {}
                        audit_log($pdo, 'LOGIN_SUCCESS', 'sesion', $user['id'], null, ['ip' => $ip_address]);

                        header("Location: home.php");
                        exit();

                    } else {
                        // ❌ LOGIN INCORRECTO — registrar intento con Unix timestamp
                        $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_unix, is_successful) VALUES (?, ?, ?, 0)")
                            ->execute([$ip_address, strtolower($username), $ahora]);

                        // Calcular intentos restantes (fallos ANTES de este + 1 = este)
                        $restantes = MAX_INTENTOS - ($fallos + 1);

                        if ($restantes <= 0) {
                            $error = "Acceso bloqueado por seguridad. Inténtelo de nuevo en 15 minutos.";
                        } else {
                            $error = "Usuario o contraseña incorrectos. Le queda" . ($restantes > 1 ? "n " : " ") . $restantes . " intento" . ($restantes > 1 ? "s" : "") . ".";
                        }

                        // Auditoría
                        if ($user) {
                            audit_log($pdo, 'LOGIN_FAILED', 'sesion', $user['id'], null, ['ip' => $ip_address, 'username' => $username], $user['id']);
                        } else {
                            audit_log($pdo, 'LOGIN_FAILED_UNKNOWN', 'sesion', null, null, ['ip' => $ip_address, 'username' => $username], null);
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Error del sistema. Por favor, contacte con el administrador.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            margin: 0;
            overflow: hidden;
        }
        
        /* Animación de fondo sutil red/white */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            background-image: radial-gradient(#3b82f6 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            opacity: 0.05;
        }
        .shape1 {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, rgba(59,130,246,0) 70%);
            top: -150px;
            right: -150px;
            animation: float 20s infinite ease-in-out alternate;
        }
        .shape2 {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.05) 0%, rgba(59,130,246,0) 70%);
            bottom: -100px;
            left: -150px;
            animation: float 15s infinite ease-in-out alternate-reverse;
        }
        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-30px, 30px); }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.1);
            backdrop-filter: blur(10px);
            animation: fadeInBody 0.6s ease-out forwards;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 1rem;
            color: var(--text-color);
        }
        
        .login-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .login-logo {
            width: 200px;
            height: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #1e293b;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            background-color: #ffffff;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(to right, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.5);
            background: linear-gradient(to right, #2563eb, #1d4ed8);
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border-left: 4px solid;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-left-color: #ef4444;
        }
        
        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border-left-color: #3b82f6;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            gap: 0.5rem;
        }
        
        .security-badge svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <div class="bg-shapes">
        <div class="shape1"></div>
        <div class="shape2"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <img src="img/logo_efp.png" alt="Grupo EFP Logo">
            </div>
            <h1>Acceso al Sistema</h1>
            <p>Grupo EFP - Gestión Académica</p>
        </div>
        
        <?php if (!empty($error)) { ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php } ?>
        
        <?php if (isset($_GET['timeout'])) { ?>
            <div class="alert alert-info">
                La sesión ha expirado por inactividad. Por favor, vuelva a identificarse.
            </div>
        <?php } ?>
        
        <?php if (isset($_GET['logout'])) { ?>
            <div class="alert alert-info">
                Sesión cerrada correctamente.
            </div>
        <?php } ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Introduzca su usuario" required autofocus autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Introduzca su contraseña" required autocomplete="current-password">
            </div>
            
            <button type="submit" name="login" class="btn-login">Iniciar Sesión</button>
        </form>
        
        <div class="security-badge">
            <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
            <span>Acceso Seguro y Auditado</span>
        </div>
    </div>
</body>
</html>
