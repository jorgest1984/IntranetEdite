<?php
// includes/sidebar.php
if (!isset($_SESSION['user_id'])) exit();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile Toggle Button -->
<button class="menu-toggle" onclick="toggleSidebar()" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" width="24" height="24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">EF</div>
        <div class="sidebar-title">
            EDITE Formación
            <span>Panel de Gestión</span>
        </div>
        <button class="mobile-close" onclick="toggleSidebar()" aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="home.php" class="<?= $current_page == 'home.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                Inicio / Dashboard
            </a>
        </li>
        <li>
            <a href="formacion.php" class="<?= $current_page == 'formacion.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                Formación
            </a>
        </li>
        <li>
            <a href="formacion_profesional.php" class="<?= $current_page == 'formacion_profesional.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                Formación Profesional
            </a>
        </li>
        <li>
            <a href="convocatorias.php" class="<?= $current_page == 'convocatorias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                Convocatorias (SEPE)
            </a>
</li>
        <li>
            <a href="acciones_formativas.php" class="<?= $current_page == 'acciones_formativas.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                Acciones Formativas
            </a>
</li>
        <li>
            <a href="inscripciones.php" class="<?= $current_page == 'inscripciones.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm4.59-12.42L10 14.17l-2.59-2.58L6 13l4 4 8-8z"/></svg>
                Inscripciones
            </a>
</li>
        <li>
            <a href="tutorias.php" class="<?= $current_page == 'tutorias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V8L12 3zm6.82 5.09L12 11.89 5.18 8.09 12 4.38l6.82 3.71zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                Tutorías
            </a>
</li>
        <li>
            <a href="grupos.php" class="<?= $current_page == 'grupos.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Grupos
            </a>
</li>
        <li>
            <a href="comerciales.php" class="<?= $current_page == 'comerciales.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Comerciales
            </a>
</li>
        <li>
            <a href="alumnos.php" class="<?= $current_page == 'alumnos.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Alumnos
            </a>
</li>
        <li>
            <a href="empresas.php" class="<?= $current_page == 'empresas.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-8h8v8zm-2-6h-4v4h4v-4z"/></svg>
                Empresas / Centros
            </a>
</li>
        <li>
            <a href="cursos.php" class="<?= $current_page == 'cursos.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                Cursos Moodle
            </a>
</li>
        <li>
            <a href="asistencia.php" class="<?= $current_page == 'asistencia.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                Asistencia
            </a>
</li>
        <li>
            <a href="informes.php" class="<?= $current_page == 'informes.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                Informes SEPE/FUNDAE
            </a>
</li>
        <li>
            <a href="incidencias.php" class="<?= $current_page == 'incidencias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/></svg>
                Incidencias Seguridad
            </a>
</li>
        <li>
            <a href="seguridad.php" class="<?= $current_page == 'seguridad.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                Manual de Seguridad ISO
            </a>
</li>
        
        <?php if (has_permission([ROLE_ADMIN])): ?>
        <li>
            <a href="usuarios.php" class="<?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Usuarios y Roles
            </a>
</li>
        <li>
            <a href="auditoria.php" class="<?= $current_page == 'auditoria.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                Auditoría ISO 27001
            </a>
</li>
        <li>
            <a href="configuracion.php" class="<?= $current_page == 'configuracion.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.43-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
                Configuración
            </a>
</li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                <?= substr($_SESSION['nombre_completo'], 0, 1) ?>
            </div>
            <div class="user-details">
                <div class="user-name" title="<?= htmlspecialchars($_SESSION['nombre_completo']) ?>">
                    <?= htmlspecialchars($_SESSION['nombre_completo']) ?>
                </div>
                <div class="user-role"><?= htmlspecialchars($_SESSION['rol_nombre']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            Cerrar Sesión
        </a>
    </div>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}
</script>
