<?php
require_once 'includes/auth.php';

// Configuración de la red Wi-Fi
$ssid = 'MARS_DIGITAL';
$password = 'BSgAutNYXwjx';
$banda = '2.4G';
$qr_data = urlencode("WIFI:S:{$ssid};T:WPA;P:{$password};;");
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={$qr_data}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redes Wi-Fi - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/home.css">
    <style>
        .breadcrumb { display:flex; gap:0.4rem; font-size:0.85rem; color:#64748b; margin-bottom:1.5rem; align-items:center; background: #f8fafc; padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid #e2e8f0;}
        .breadcrumb a { color:#dc2626; text-decoration:none; font-weight:500; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb span { color:#94a3b8; }

        .wifi-card { background: #fff; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; max-width: 800px; margin: 0; }
        .wifi-title { font-size: 1.8rem; color: #1e293b; margin-top: 0; margin-bottom: 2rem; font-weight: 600; }
        
        .wifi-details { margin-bottom: 2.5rem; font-size: 0.95rem; color: #334155; line-height: 1.8; }
        .wifi-details strong { font-weight: 700; color: #0f172a; }
        
        .qr-section { text-align: center; margin-bottom: 3rem; }
        .qr-text { margin-bottom: 1.5rem; color: #475569; font-size: 0.95rem; }
        .qr-image { border: 10px solid #fff; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 8px; display: inline-block; }
        .qr-image img { display: block; width: 250px; height: 250px; }
        
        .scanner-info { font-size: 0.85rem; color: #64748b; }
        .scanner-info a { color: #2563eb; font-weight: 600; text-decoration: none; }
        .scanner-info a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Información de Redes</h1>
            </div>
            <div class="page-actions">
                <a href="edite_formacion.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Volver
                </a>
            </div>
        </header>

        <div class="main-content-inner" style="max-width:850px; padding: 1.5rem;">
            
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="home.php">Inicio</a><span>/</span>
                <a href="edite_formacion.php">Grupo EFP</a><span>/</span>
                <span>Redes Wi-Fi</span>
            </nav>

            <!-- Wifi Card -->
            <div class="wifi-card">
                <h2 class="wifi-title">Redes Wi-Fi</h2>
                
                <div class="wifi-details">
                    <p>SSID: <strong><?= htmlspecialchars($ssid) ?></strong></p>
                    <p>Contraseña: <strong><?= htmlspecialchars($password) ?></strong></p>
                    <p>Banda: <strong><?= htmlspecialchars($banda) ?></strong></p>
                </div>
                
                <div class="qr-section">
                    <p class="qr-text">Escanea el siguiente código QR para conectar directamente desde tu móvil:</p>
                    <div class="qr-image">
                        <img src="<?= htmlspecialchars($qr_url) ?>" alt="QR Code Wi-Fi">
                    </div>
                </div>
                
                <p class="scanner-info">
                    Si no dispones de una aplicación para escanear códigos QR puedes instalar 
                    <a href="https://play.google.com/store/apps/details?id=com.google.zxing.client.android" target="_blank">Barcode Scanner</a>.
                </p>
            </div>
            
        </div>
    </main>
</div>
</body>
</html>
