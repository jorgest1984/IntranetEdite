<?php
// generar_certificado.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) die("ID de profesor no proporcionado.");

// Cargar datos del alumno/profesor
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->execute([$id]);
$alumno = $stmt->fetch();

if (!$alumno) die("Profesor no encontrado.");

// Cargar detalles de profesorado
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE alumno_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

// Cargar historial de asistencia/clases dadas (Muestra de ejemplo)
$stAsis = $pdo->prepare("
    SELECT a.*, c.nombre as convocatoria_nombre 
    FROM asistencia a
    JOIN convocatorias c ON a.convocatoria_id = c.id
    WHERE a.alumno_id = ? AND a.estado = 'Presente'
    ORDER BY a.fecha DESC
");
$stAsis->execute([$id]);
$clases = $stAsis->fetchAll();

$total_horas = 0;
foreach($clases as $c) $total_horas += $c['horas'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <title>Certificado - <?= htmlspecialchars($alumno['nombre']) ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1f2937; line-height: 1.6; padding: 40px; }
        .certificate-border {
            border: 15px solid #dc2626;
            padding: 50px;
            position: relative;
            background: white;
            min-height: 800px;
        }
        .header { text-align: center; margin-bottom: 50px; }
        .header h1 { color: #dc2626; font-size: 3rem; margin-bottom: 10px; text-transform: uppercase; }
        .content { text-align: center; margin-top: 40px; }
        .content p { font-size: 1.2rem; }
        .content .name { font-size: 2.5rem; font-weight: 700; color: #111827; margin: 20px 0; }
        .details-table { width: 100%; margin-top: 40px; border-collapse: collapse; }
        .details-table th, .details-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .footer { margin-top: 80px; display: flex; justify-content: space-between; }
        .signature { border-top: 1px solid #374151; width: 250px; text-align: center; padding-top: 10px; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .certificate-border { border-width: 10px; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Imprimir / Guardar como PDF</button>
</div>

<div class="certificate-border">
    <div class="header">
        <h1>Certificado de Formación</h1>
        <p>Acreditación de Actividad Docente</p>
    </div>

    <div class="content">
        <p>Se certifica que:</p>
        <div class="name"><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?></div>
        <p>Con DNI/NIE: <strong><?= htmlspecialchars($alumno['dni']) ?></strong></p>
        
        <p style="margin-top: 30px;">Ha impartido formación en los siguientes módulos y convocatorias:</p>
        
        <table class="details-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Convocatoria / Curso</th>
                    <th>Estado</th>
                    <th>Horas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($clases as $cl): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($cl['fecha'])) ?></td>
                        <td><?= htmlspecialchars($cl['convocatoria_nombre']) ?></td>
                        <td><?= $cl['estado'] ?></td>
                        <td><?= $cl['horas'] ?>h</td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($clases)): ?>
                    <tr><td colspan="4" style="text-align: center; padding: 20px;">No se registran horas impartidas en el sistema local.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: 700;">
                    <td colspan="3" style="text-align: right;">TOTAL HORAS ACREDITADAS:</td>
                    <td><?= $total_horas ?>h</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <div>
            <p>Fecha de emisión: <?= date('d/m/Y') ?></p>
            <p>Sello de la Empresa de Formación</p>
        </div>
        <div class="signature">
            <p>Firma del Responsable</p>
            <p>Director de Formación</p>
        </div>
    </div>
</div>

</body>
</html>
