<?php
// generar_certificado.php - Versión Pulida y Ajustada
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN])) {
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

// Configuración del Administrador
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body { 
            font-family: 'Inter', Arial, sans-serif; 
            color: #1a1a1a; 
            line-height: 1.7; 
            margin: 0; 
            padding: 0; 
            background: #f0f2f5; 
            -webkit-print-color-adjust: exact;
        }
        
        .page {
            width: 210mm;
            height: 296mm; /* Ajuste para evitar salto de página extra */
            padding: 25mm 25mm 20mm 25mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            position: relative;
        }
        
        /* Cabecera */
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-end; 
            margin-bottom: 50px; 
        }
        .logo { width: 200px; height: auto; }
        .web-link { 
            color: #0077b6; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 0.85rem; 
            margin-bottom: 5px;
        }
        
        /* Banner Azul */
        .banner {
            background: #1e70b3;
            color: white;
            text-align: center;
            padding: 14px;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.3rem;
            letter-spacing: 3px;
            margin-bottom: 60px;
            text-transform: uppercase;
        }
        
        /* Contenido Principal */
        .content { 
            flex-grow: 1; /* Empuja la firma hacia abajo si hay poco texto */
            text-align: justify;
            font-size: 1.05rem;
        }
        .content p { margin-bottom: 30px; }
        .content strong { color: #000; }
        
        .acciones-container {
            margin: 20px 0 40px 30px;
        }
        .acciones-list { 
            list-style: none; 
            padding: 0; 
        }
        .acciones-list li { 
            margin-bottom: 10px; 
            font-weight: 600;
            position: relative;
            padding-left: 20px;
        }
        .acciones-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #1e70b3;
        }

        /* Sección Firma */
        .signature-section {
            margin-top: 40px;
            margin-bottom: 100px; /* Espacio para que no toque el footer */
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .signature-space {
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .signature-name { 
            font-weight: 700; 
            font-size: 1.1rem;
            border-top: 1px solid #eee;
            padding-top: 10px;
            min-width: 300px;
        }
        
        /* Footer Legal */
        .footer-legal {
            font-size: 0.65rem;
            color: #666;
            text-align: justify;
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
            line-height: 1.4;
        }

        /* Utilidades */
        .no-print {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
        }
        .btn-print {
            padding: 10px 25px;
            background: #1e70b3;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        .btn-print:hover { background: #155a92; }

        @media print {
            body { background: white; }
            .page { 
                margin: 0; 
                box-shadow: none; 
                height: 297mm;
                width: 210mm;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">Imprimir / Guardar PDF</button>
    <button onclick="window.close()" class="btn-print" style="background: #64748b;">Cerrar</button>
</div>

<div class="page">
    <div class="header">
        <img src="/img/logo_efp.png" alt="Logo Escuela de Formación Profesional" class="logo">
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

        <div class="acciones-container">
            <ul class="acciones-list">
                <?php foreach($tutorias as $tut): ?>
                    <li><?= htmlspecialchars($tut['curso']) ?> (Año <?= htmlspecialchars($tut['anio']) ?>)</li>
                <?php endforeach; ?>
                <?php if(empty($tutorias)): ?>
                    <li style="font-weight: 400; color: #666;">No se registran acciones formativas en el historial académico.</li>
                <?php endif; ?>
            </ul>
        </div>

        <p>
            Y para que así conste y surta los efectos oportunos donde convenga, se expide este CERTIFICADO en Granada, a <?= $fecha_hoy ?>.
        </p>
    </div>

    <div class="signature-section">
        <div class="signature-space">
             <!-- Espacio para sello y firma física -->
        </div>
        <div class="signature-name">Fdo.: <?= $admin_nombre ?></div>
    </div>

    <div class="footer-legal">
        <strong>Cláusula de protección de datos:</strong><br>
        A los efectos de lo dispuesto en la Ley Orgánica 15/1999 de 13 de diciembre de Protección de Datos de Carácter Personal y demás normativa de desarrollo, se informa al interesado de que los datos de carácter personal que voluntariamente facilita, se incorporarán a un fichero automatizado propiedad y responsabilidad de MarsDigital S.L. Dichos datos serán utilizados para la gestión y ejecución del Plan Formativo correspondiente, tanto por parte de la Entidad Organizadora, como por los Organismos Públicos competentes o cualesquiera otras personas relacionadas con dicho Plan. Le informamos de que puede ejercer su derecho de acceso, rectificación, oposición y cancelación de los datos personales obrantes en dicho fichero, ante el domicilio social sito en Calle Benjamín Franklin, 1 - 18100 Armilla (Granada).
    </div>
</div>

</body>
</html>
