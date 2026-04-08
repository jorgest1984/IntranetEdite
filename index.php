<?php
// index.php
require_once 'includes/config.php';

$error = '';

// Si ya está logueado, redirigir al home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    
    // Evitar ataques de fuerza bruta simples: pequeño retardo
    usleep(500000); // 0.5 segundos
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Por favor, introduce usuario y contraseña.";
    } else {
        try {
            // Buscar usuario en DB
            $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id WHERE u.username = ? AND u.activo = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login correcto
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Si la tabla de usuarios aún usa el campo 'apellidos'
                $apellidos = $user['apellidos'] ?? (($user['primer_apellido'] ?? '') . ' ' . ($user['segundo_apellido'] ?? ''));
                $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . trim($apellidos);
                $_SESSION['rol_id'] = $user['rol_id'];
                $_SESSION['rol_nombre'] = $user['rol_nombre'];
                
                // Actualizar último acceso
                $updateSt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                $updateSt->execute([$user['id']]);
                
                // Registrar log de auditoría (ISO 27001)
                audit_log($pdo, 'LOGIN_SUCCESS', 'sesion', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR']]);
                
                header("Location: home.php");
                exit();
                
            } else {
                $error = "Usuario o contraseña incorrectos.";
                // Registrar intento fallido
                if ($user) {
                    audit_log($pdo, 'LOGIN_FAILED', 'sesion', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'], 'username' => $username]);
                } else {
                    // Log fail para usuario inexistente
                    $stmt_log = $pdo->prepare("INSERT INTO audit_log (usuario_id, accion, entidad, ip_address) VALUES (0, 'LOGIN_FAILED_UNKNOWN', 'sesion', ?)");
                    $stmt_log->execute([$_SERVER['REMOTE_ADDR']]);
                }
            }
        } catch (PDOException $e) {
            $error = "Error del sistema. Por favor, contacte con el administrador.";
            // $error = $e->getMessage(); // Solo para debug
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
            background-image: radial-gradient(#dc2626 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            opacity: 0.05;
        }
        .shape1 {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(220,38,38,0.08) 0%, rgba(220,38,38,0) 70%);
            top: -150px;
            right: -150px;
            animation: float 20s infinite ease-in-out alternate;
        }
        .shape2 {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(220,38,38,0.05) 0%, rgba(220,38,38,0) 70%);
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
            box-shadow: 0 25px 50px -12px rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.1);
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
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
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
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
            background-color: #ffffff;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(to right, #dc2626, #b91c1c);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.4);
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.5);
            background: linear-gradient(to right, #b91c1c, #991b1b);
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
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-info">
                La sesión ha expirado por inactividad. Por favor, vuelva a identificarse.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['logout'])): ?>
            <div class="alert alert-info">
                Sesión cerrada correctamente.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
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
