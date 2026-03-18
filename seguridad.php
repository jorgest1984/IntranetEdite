<?php
// seguridad.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Todos los empleados deben tener acceso para consulta (ISO 27001 - A.5.1)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas de Seguridad ISO 27001 - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .doc-container { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 3rem; max-width: 900px; margin: 0 auto; line-height: 1.7; color: #1e293b; }
        .doc-header { text-align: center; margin-bottom: 3rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 2rem; }
        .doc-section { margin-bottom: 2.5rem; }
        .doc-section h2 { color: var(--primary-color); font-size: 1.4rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .doc-section p { margin-bottom: 1rem; }
        .policy-list { list-style: none; padding: 0; }
        .policy-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .policy-item strong { display: block; color: var(--text-color); margin-bottom: 0.25rem; }
        
        @media print {
            .sidebar, .page-header { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            .doc-container { border: none; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Manual de Políticas de Seguridad</h1>
                <p>Criterios de cumplimiento normativo ISO 27001 / RGPD</p>
            </div>
            <button class="btn btn-primary" onclick="window.print()">Imprimir Manual</button>
        </header>

        <article class="doc-container">
            <div class="doc-header">
                <div style="font-weight: 700; font-size: 0.8rem; color: var(--primary-color); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Documentación Interna</div>
                <h1 style="font-size: 2.2rem; margin: 0;">Sistema de Gestión de Seguridad de la Información</h1>
                <p style="color: var(--text-muted);">Basado en la Norma ISO/IEC 27001:2022</p>
            </div>

            <section class="doc-section">
                <h2>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                    1. Objetivo y Alcance
                </h2>
                <p>Este manual define las políticas y procedimientos técnicos aplicados en la Intranet de <strong><?= APP_NAME ?></strong> para garantizar la triada de la seguridad: <strong>Confidencialidad, Integridad y Disponibilidad</strong> de los datos de alumnos y convocatorias.</p>
            </section>

            <section class="doc-section">
                <h2>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    2. Control de Acceso (A.9)
                </h2>
                <div class="policy-list">
                    <div class="policy-item">
                        <strong>Identificación Única:</strong> Queda prohibido el uso de cuentas compartidas. Cada coordinador o docente debe tener su propio usuario personal con contraseña robusta.
                    </div>
                    <div class="policy-item">
                        <strong>Principio de Mínimo Privilegio:</strong> Los usuarios solo tendrán acceso a los módulos estrictamente necesarios para sus funciones (Roles: Admin, Coord, Formador, Lectura).
                    </div>
                    <div class="policy-item">
                        <strong>Cierre de Sesión:</strong> Es obligatorio cerrar la sesión al abandonar el puesto de trabajo. El sistema dispone de caducidad automática por inactividad.
                    </div>
                </div>
            </section>

            <section class="doc-section">
                <h2>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    3. Cifrado y Protección (A.10)
                </h2>
                <p>Todas las comunicaciones entre el cliente y el servidor viajan bajo protocolo <strong>HTTPS (TLS 1.3)</strong>. Los datos sensibles de alumnos (DNI, Teléfono) se gestionan con controles de filtrado para evitar fugas de información.</p>
            </section>

            <section class="doc-section">
                <h2>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                    4. Registro y Auditoría (A.8.15)
                </h2>
                <p>Se registra inmutablemente toda operación de escritura: Creación de alumnos, cambios en convocatorias, exportación de informes y fallos de login. Estos logs son revisados semanalmente por el Responsable de Seguridad.</p>
            </section>

            <section class="doc-section">
                <h2>
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/></svg>
                    5. Gestión de Incidentes (A.6.8)
                </h2>
                <p>Cualquier anomalía (lentitud extrema, sospecha de acceso no autorizado, pérdida de credenciales) debe ser notificada mediante el <strong>Módulo de Incidencias</strong> interno disponible en el panel.</p>
            </section>

            <div style="margin-top: 4rem; text-align: right; font-size: 0.8rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                Aprobado por Dirección | Versión 1.2 | Fecha: <?= date('d/m/Y') ?>
            </div>
        </article>
    </main>
</div>

</body>
</html>
