<?php
// generar_certificado.php - Versión Oficial según imagen
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) die("ID de trabajador no proporcionado.");

// Cargar datos del trabajador (usuarios)
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$trabajador = $stmt->fetch();

if (!$trabajador) die("Trabajador no encontrado.");

// Cargar detalles de profesorado
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE usuario_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

// Cargar acciones formativas (Tutorías)
$stTut = $pdo->prepare("SELECT * FROM prof_tutorias WHERE usuario_id = ? ORDER BY anio DESC");
$stTut->execute([$id]);
$tutorias = $stTut->fetchAll();

// Configuración del Administrador (según imagen)
$admin_nombre = "Enrique Cirera Salas";
$admin_dni = "27528441V";
$empresa = "MARSDIGITAL S.L.";
$cif = "B18579953";
$domicilio = "Calle Benjamín Franklin, nº 1, 18100 Armilla - Granada";

// Fecha actual en español
$meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
$fecha_hoy = date('j') . " de " . $meses[date('n')-1] . " de " . date('Y');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado - <?= htmlspecialchars($trabajador['nombre']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', Arial, sans-serif; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f5f5f5; }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
        }
        
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .logo { width: 220px; }
        .web-link { color: #0077b6; text-decoration: none; font-weight: 700; font-size: 0.9rem; }
        
        .banner {
            background: #1e70b3;
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem;
            letter-spacing: 2px;
            margin-bottom: 50px;
        }
        
        .content { font-size: 1.1rem; text-align: justify; padding: 0 10px; }
        .content p { margin-bottom: 25px; }
        
        .acciones-list { margin: 20px 0 30px 40px; }
        .acciones-list li { margin-bottom: 8px; font-weight: 600; }

        .signature-section {
            margin-top: 80px;
            text-align: center;
        }
        .seal-placeholder {
            width: 150px;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        .signature-name { font-weight: 700; font-size: 1.1rem; }
        
        .footer-legal {
            position: absolute;
            bottom: 20mm;
            left: 20mm;
            right: 20mm;
            font-size: 0.7rem;
            color: #4a4a4a;
            text-align: justify;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        @media print {
            body { background: white; }
            .page { margin: 0; box-shadow: none; width: 100%; height: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 100;">
    <button onclick="window.print()" style="padding: 12px 24px; background: #1e70b3; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">Imprimir Certificado</button>
</div>

<div class="page">
    <div class="header">
        <img src="/img/logo_efp.png" alt="Logo" class="logo">
        <a href="https://www.escueladeformacionprofesional.es" class="web-link">www.escueladeformacionprofesional.es</a>
    </div>

    <div class="banner">CERTIFICADO</div>

    <div class="content">
        <p>
            D. <strong><?= $admin_nombre ?></strong>, con DNI <strong><?= $admin_dni ?></strong>, en calidad de Administrador de la empresa 
            <strong><?= $empresa ?></strong>, con CIF <strong><?= $cif ?></strong>, con domicilio en <strong><?= $domicilio ?></strong>, dedicada a la formación continua de trabajadores y desempleados,
        </p>

        <p>
            Certifica que D. <strong><?= htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellidos']) ?></strong>, 
            con DNI <strong><?= htmlspecialchars($prof['dni'] ?? '__________') ?></strong> ha impartido las siguientes acciones formativas:
        </p>

        <ul class="acciones-list">
            <?php foreach($tutorias as $tut): ?>
                <li>- <?= htmlspecialchars($tut['curso']) ?> (Año <?= htmlspecialchars($tut['anio']) ?>)</li>
            <?php endforeach; ?>
            <?php if(empty($tutorias)): ?>
                <li>- No se registran acciones formativas en el historial.</li>
            <?php endif; ?>
        </ul>

        <p style="margin-top: 50px;">
            Y para que así conste y surta los efectos oportunos donde convenga, se expide este CERTIFICADO en Granada, a <?= $fecha_hoy ?>.
        </p>
    </div>

    <div class="signature-section">
        <!-- Imagen de firma si existiera, si no usamos espaciado -->
        <div style="height: 100px;">
             <!-- <img src="/img/firma_sello.png" class="seal-placeholder"> -->
        </div>
        <div class="signature-name">Fdo.: <?= $admin_nombre ?></div>
    </div>

    <div class="footer-legal">
        Clausula de protección de datos:<br>
        A los efectos de lo dispuesto en la Ley Orgánica 15/1999 de 13 de diciembre de Protección de Datos de Carácter Personal y demás normativa de desarrollo, se informa al interesado de que los datos de carácter personal que voluntariamente facilita, se incorporarán a un fichero automatizado propiedad y responsabilidad de MarsDigital S.L. Dichos datos serán utilizados para la gestión y ejecución del Plan Formativo correspondiente, tanto por parte de la Entidad Organizadora, como por los Organismos Públicos competentes o cualesquiera otras personas relacionadas con dicho Plan. Le informamos de que puede ejercer su derecho de acceso, rectificación, oposición y cancelación de los datos personales obrantes en dicho fichero, ante el domicilio social sito en Calle Benjamín Franklin, 1 - 18100 Armilla (Granada).
    </div>
</div>

</body>
</html>
