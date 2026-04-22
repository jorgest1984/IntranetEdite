<?php
// includes/sidebar.php
if (!isset($_SESSION['user_id'])) exit();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<script>
    // Aplicar el ancho de la sidebar antes de que se renderice el resto
    (function() {
        const savedWidth = localStorage.getItem('sidebarWidth');
        if (savedWidth && window.innerWidth > 1024) {
            document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
        }
    })();
</script>
<!-- Mobile Toggle Button -->
<button class="menu-toggle" onclick="toggleSidebar()" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" width="24" height="24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="img/logo_efp.png" alt="Grupo EFP Logo">
        </div>
        <div class="sidebar-title">
            Grupo EFP
            <span>Gestión Académica</span>
        </div>
        <button class="mobile-close" onclick="toggleSidebar()" aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="home.php" class="<?= $current_page == 'home.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                Inicio / Dashboard
            </a>
        </li>
        <li>
            <a href="buscador_global.php" class="<?= ($current_page == 'buscador_global.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                Buscador Global
            </a>
        </li>

        <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])): ?>
        <li class="menu-divider">Área Académica</li>
        <li>
            <a href="formacion.php" class="<?= $current_page == 'formacion.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
                Formación
            </a>
        </li>
        <li>
            <a href="convocatorias.php" class="<?= $current_page == 'convocatorias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                Convocatorias (SEPE)
            </a>
        </li>
        <li>
            <a href="inscripciones.php" class="<?= $current_page == 'inscripciones.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"/></svg>
                Inscripciones
            </a>
        </li>
        <li>
            <a href="tutorias.php" class="<?= $current_page == 'tutorias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/></svg>
                Tutorías
            </a>
        </li>
        <li>
            <a href="alumnos.php" class="<?= $current_page == 'alumnos.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Alumnos
            </a>
        </li>
        <li>
            <a href="cursos.php" class="<?= $current_page == 'cursos.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM5 15h14v2H5zM5 11h9v2H5zm0-4h14v2H5z"/></svg>
                Cursos Moodle
            </a>
        </li>
        <li>
            <a href="informes.php" class="<?= $current_page == 'informes.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                Informes SEPE/FUNDAE
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO])): ?>
        <li class="menu-divider">Área Económica</li>
        <li>
            <a href="contabilidad.php" class="<?= $current_page == 'contabilidad.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-6 2h4v4h-4V5zm-2 4H7V5h4v4zm-4 2h4v4H7v-4zm6 0h4v4h-4v-4zm-6 6h4v4H7v-4zm6 0h4v4h-4v-4z"/></svg>
                Contabilidad
            </a>
        </li>
        <li>
            <a href="facturas.php" class="<?= $current_page == 'facturas.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                Facturas
            </a>
        </li>
        <li>
            <a href="proveedores.php" class="<?= $current_page == 'proveedores.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M7 18h2V6H7v12zm4 4h2V6h-2v16zm-8-8h2V6H3v8zM15 6v16h2V6h-2zm4 16h2V6h-2v16zM1 2v2h22V2H1z"/></svg>
                Proveedores
            </a>
        </li>
        <?php endif; ?>

        <li class="menu-divider">Seguridad e ISO</li>
        <li>
            <a href="incidencias.php" class="<?= $current_page == 'incidencias.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm1 14h-2v-2h2v2zm0-4h-2V8h2v4z"/></svg>
                Incidencias
            </a>
        </li>
        <li>
            <a href="seguridad.php" class="<?= $current_page == 'seguridad.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                Manual de Seguridad
            </a>
        </li>
        
        <?php if (has_permission([ROLE_ADMIN])): ?>
        <li class="menu-divider">Mantenimiento</li>
        <li>
            <a href="usuarios.php" class="<?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Usuarios y Roles
            </a>
        </li>
        <li>
            <a href="auditoria.php" class="<?= $current_page == 'auditoria.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                Auditoría ISO
            </a>
        </li>
        <li>
            <a href="configuracion.php" class="<?= $current_page == 'configuracion.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.43-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
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

    <!-- Tirador para redimensionar -->
    <div class="sidebar-resizer" id="sidebarResizer"></div>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// Lógica de redimensionamiento
(function() {
    const resizer = document.getElementById('sidebarResizer');
    const sidebar = document.getElementById('sidebar');
    let isResizing = false;

    if (!resizer) return;

    resizer.addEventListener('mousedown', (e) => {
        if (window.innerWidth <= 1024) return; // No redimensionar en móvil/tablet
        
        isResizing = true;
        document.body.classList.add('is-resizing');
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', stopResizing);
    });

    function handleMouseMove(e) {
        if (!isResizing || window.innerWidth <= 1024) return;
        
        let newWidth = e.clientX;
        
        // Límites
        if (newWidth < 200) newWidth = 200;
        if (newWidth > 600) newWidth = 600;
        
        document.documentElement.style.setProperty('--sidebar-width', newWidth + 'px');
    }

    function stopResizing() {
        if (!isResizing) return;
        isResizing = false;
        document.body.classList.remove('is-resizing');
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', stopResizing);
        
        // Guardar preferencia
        const finalWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width'));
        localStorage.setItem('sidebarWidth', finalWidth);
    }
})();
</script>
