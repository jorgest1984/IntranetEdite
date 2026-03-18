<?php
require_once 'includes/auth.php';

$enviado = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asunto      = trim($_POST['asunto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (empty($asunto) || empty($descripcion)) {
        $error = 'Por favor, rellena el asunto y la descripción.';
    } else {
        // Enviar via relay en el servidor externo
        $bridge_url   = 'https://gestion.grupoefp.es/send_mail.php';
        $bridge_token = getenv('BRIDGE_TOKEN') ?: 'dbbea329538b1694971d7ee66cc3e4673';

        $ch = curl_init($bridge_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'token'       => $bridge_token,
            'asunto'      => $asunto,
            'descripcion' => $descripcion,
            'usuario'     => $_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Desconocido',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $error = 'Error de conexión al enviar el correo. Inténtalo de nuevo.';
        } else {
            $result = json_decode($response, true);
            if (!empty($result['success'])) {
                $enviado = true;
            } else {
                $error = $result['error'] ?? 'No se pudo enviar la sugerencia.';
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
    <title>Buzón de sugerencias - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css">
    <style>
        .sugerencias-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .sugerencias-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        .sugerencias-card p.intro {
            color: #555;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1.4rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #333;
            font-size: 0.9rem;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            color: #333;
            background: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
            background: #fff;
        }
        .form-group textarea {
            height: 180px;
            resize: vertical;
        }
        .btn-submit {
            background: linear-gradient(to right, #dc2626, #b91c1c);
            color: #fff;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(220,38,38,0.3);
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(220,38,38,0.4);
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #15803d;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Buzón de sugerencias</h1>
                <p>Tu opinión nos ayuda a mejorar</p>
            </div>
            <div class="page-actions">
                <a href="edite_formacion.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver
                </a>
            </div>
        </header>

        <div class="main-content-inner" style="max-width:700px; margin: 0 auto; padding: 1.5rem;">
            <div class="sugerencias-card">
                <p class="intro">Desde este formulario puedes enviarnos tus sugerencias sobre el funcionamiento de la intranet o cualquier propuesta de mejora.</p>

                <?php if ($enviado): ?>
                    <div class="alert-success">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        ¡Sugerencia enviada correctamente! Muchas gracias por tu aportación.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!$enviado): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="asunto">Asunto</label>
                        <input type="text" id="asunto" name="asunto" placeholder="Escribe el asunto de tu sugerencia" required value="<?= htmlspecialchars($_POST['asunto'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Explica tu sugerencia con el máximo detalle posible..." required><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        Enviar sugerencia
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
