<?php
// includes/fp_sidebar.php
$current_fp_page = basename($_SERVER['PHP_SELF']);
?>
<script>
    // Aplicar el ancho de la sidebar y el tema antes de que se renderice el resto para evitar destellos
    (function() {
        const savedWidth = localStorage.getItem('sidebarWidth');
        if (savedWidth && window.innerWidth > 1024) {
            document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
        }
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-theme');
        } else {
            document.documentElement.classList.remove('dark-theme');
        }
    })();
</script>
<style>
    .fp-sidebar {
        width: var(--sidebar-width) !important;
        background: var(--sidebar-bg);
        border-right: 1px solid var(--sidebar-border);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: var(--glass-shadow);
        transition: transform 0.3s ease, width 0.05s linear, background-color 0.4s ease, border-color 0.4s ease;
        display: flex;
        flex-direction: column;
    }
    .fp-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .fp-menu li a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--sidebar-text);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
        gap: 12px;
    }
    .fp-menu li a:hover, .fp-menu li a.active {
        background: var(--input-focus-bg);
        color: var(--primary-color);
    }
    .fp-menu li a.active {
        border-right: 3px solid var(--primary-color);
    }
    .fp-menu-icon {
        width: 18px;
        height: 18px;
        opacity: 0.7;
    }
    .fp-submenu {
        list-style: none;
        padding-left: 45px;
        background: rgba(0, 0, 0, 0.04);
        padding-bottom: 10px;
    }
    .dark-theme .fp-submenu {
        background: rgba(255, 255, 255, 0.03);
    }
    .fp-submenu li a {
        padding: 8px 10px;
        font-size: 0.8rem;
    }
    .menu-divider {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        padding: 20px 20px 10px 20px;
        letter-spacing: 0.5px;
    }
</style>
<!-- Mobile Toggle Button -->
<button class="menu-toggle" onclick="toggleFpSidebar()" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" width="24" height="24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
</button>

<aside class="sidebar fp-sidebar" id="fpSidebar">
    <button class="mobile-close" onclick="toggleFpSidebar()" aria-label="Cerrar menú">
        <svg viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    </button>

    <div class="fp-sidebar-header" style="padding: 20px; text-align: center; border-bottom: 1px solid var(--sidebar-border); margin-bottom: 10px;">
        <a href="home.php" style="display: block;">
            <img src="img/logo_efp.png" alt="Logo EFP" style="max-width: 80%; height: auto;">
        </a>
        <div class="sidebar-title" style="font-size: 0.8rem; font-weight: 800; color: var(--primary-color); margin-top: 10px; text-transform: uppercase; letter-spacing: 1px;">
            Formación Profesional
        </div>
    </div>
    <ul class="fp-menu">
        <li>
            <a href="planes.php" class="<?= $current_fp_page == 'planes.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                Planes
            </a>
        </li>

        <li>
            <a href="convocatorias.php" class="<?= $current_fp_page == 'convocatorias.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Convocatoria
            </a>
        </li>
        <li class="has-submenu">
            <a href="acciones_formativas.php" class="<?= in_array($current_fp_page, ['acciones_formativas.php', 'gestor_contenidos.php', 'buscar_aaff.php']) ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                Acc. Formativas <span style="color:red; font-weight:bold;">•</span>
            </a>
            <ul class="fp-submenu">
                <li><a href="gestor_contenidos.php">Listado contenidos</a></li>
                <li><a href="buscar_aaff.php">Buscar AAFF <span style="color:red; font-weight:bold;">•</span></a></li>
                <li><a href="aula_virtual_control.php">Aula Virtual</a></li>
                <li><a href="control_cursos_plataforma.php">Control cursos plataforma</a></li>
                <li><a href="nueva_af.php">Nueva A.F.</a></li>
                <li><a href="resumen_aaff.php">Resumen AA.FF.</a></li>
                <li><a href="objetivos_aaff.php">Objetivos AA.FF.</a></li>
                <li><a href="objetivos_comercial.php">Objetivos/comercial</a></li>
                <li><a href="revision_objetivos.php">Revisión objetivos</a></li>
                <li><a href="cuadro_resumen.php">Cuadro resumen</a></li>
                <li><a href="informe_ugt.php">Informe UGT</a></li>
                <li><a href="acciones_peligro.php">Acciones en "peligro"</a></li>
            </ul>
        </li>
        <li>
            <a href="grupos.php" class="<?= in_array($current_fp_page, ['grupos.php', 'nuevo_grupo.php']) ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Grupos <span style="color:red; font-weight:bold;">•</span>
            </a>
            <ul class="fp-submenu">
                <li><a href="grupos.php" class="<?= $current_fp_page == 'grupos.php' ? 'active' : '' ?>">Buscar grupo <span style="color:red; font-weight:bold;">•</span></a></li>
                <li><a href="control_doc.php">Control doc.</a></li>
                <li><a href="nuevo_grupo.php">Nuevo Grupo</a></li>
                <li><a href="informe_cursos.php">Informe Cursos</a></li>
                <li><a href="informe_cursos_profes.php">Informe Cursos con profes</a></li>
                <li><a href="informe_plazos.php">Informe Plazos Comunicaciones</a></li>
                <li><a href="comunicaciones.php">Comunicaciones</a></li>
                <li><a href="calendario_ocupacion.php">Calendario de ocupación de tutores</a></li>
                <li><a href="programar.php">Programar</a></li>
                <li><a href="programar_v2.php">Programar v.2</a></li>
                <li><a href="alertas_s20.php">Alertas S20</a></li>
                <li><a href="grupos_previstos.php">Grupos Previstos IDFO</a></li>
            </ul>
        </li>
        <li>
            <a href="inscripciones.php" class="<?= in_array($current_fp_page, ['inscripciones.php', 'tutorias.php', 'informe_certificacion.php']) ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                Matrículas <span style="color:red; font-weight:bold;">•</span>
            </a>
            <ul class="fp-submenu">
                <li><a href="llamadas_admision.php">Llamadas Admisión</a></li>
                <li><a href="tutorias.php" class="<?= $current_fp_page == 'tutorias.php' ? 'active' : '' ?>">Tutorías <span style="color:red; font-weight:bold;">•</span></a></li>
                <li><a href="inscripciones.php" class="<?= $current_fp_page == 'inscripciones.php' ? 'active' : '' ?>">Buscar Matrícula</a></li>
                <li><a href="informe_estados_ins.php">Informe estados matrículas</a></li>
                <li><a href="informe_certificacion.php" class="<?= $current_fp_page == 'informe_certificacion.php' ? 'active' : '' ?>">Informe matrículas certifican <span style="color:red; font-weight:bold;">•</span></a></li>
                <li><a href="informe_captacion.php">Informe de captación por acción</a></li>
                <li><a href="informe_ins_alumno.php">Informe de matrículas por alumno</a></li>
                <li><a href="tiempos_conexion.php">Tiempos conexión</a></li>
                <li><a href="datos_tutores.php">Datos tutores --></a></li>
                <li><a href="comunicaciones_ins.php">Comunicaciones</a></li>
                <li><a href="control_diplomas.php">Control diplomas</a></li>
                <li><a href="recuento_inscripciones.php">Recuento matrículas</a></li>
                <li><a href="produc_ciales.php">Produc. ciales.</a></li>
                <li><a href="liquid_ciales.php">Liquid. ciales.</a></li>
            </ul>
        </li>
        <li>
            <a href="alumnos.php" class="<?= $current_fp_page == 'alumnos.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Alumnos
            </a>
        </li>
        <li>
            <a href="buscar_empresas.php" class="<?= $current_fp_page == 'buscar_empresas.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4"></path><path d="M5 21V10.85"></path><path d="M19 21V10.85"></path><path d="M9 21V14"></path><path d="M15 21V14"></path></svg>
                Empresas
            </a>
        </li>
        <li>
            <a href="buscar_alumnos.php" class="<?= $current_fp_page == 'buscar_alumnos.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Trabajadores
            </a>
        </li>
        <li>
            <a href="documentacion.php" class="<?= $current_fp_page == 'documentacion.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Documentación
            </a>
        </li>
        <li>
            <a href="envios_doc.php" class="<?= $current_fp_page == 'envios_doc.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Envíos doc.
            </a>
        </li>
        <li>
            <a href="documentos.php" class="<?= $current_fp_page == 'documentos.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                Documentos
            </a>
        </li>
        <li class="has-submenu">
            <a href="javascript:void(0)" class="<?= in_array($current_fp_page, ['encuestas.php', 'auditoria_tutores.php', 'no_conformidades.php']) ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Calidad
            </a>
            <ul class="fp-submenu">
                <li><a href="encuestas.php">Encuestas de satisfacción</a></li>
                <li><a href="auditoria_tutores.php">Auditoría de tutores</a></li>
                <li><a href="no_conformidades.php">No Conformidades</a></li>
                <li><a href="evaluacion_formadores.php">Evaluación formadores</a></li>
            </ul>
        </li>
        <li>
            <a href="doc_costes.php" class="<?= $current_fp_page == 'doc_costes.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                Doc. Costes
            </a>
        </li>
        <li>
            <a href="imputacion_costes.php" class="<?= $current_fp_page == 'imputacion_costes.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20v-6M6 20V10M18 20V4"></path></svg>
                Imputación Costes
            </a>
        </li>
        <li>
            <a href="tareas.php" class="<?= $current_fp_page == 'tareas.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                Tareas
            </a>
        </li>
        <?php if (has_permission([ROLE_ADMIN])) { ?>
        <li class="menu-divider">Mantenimiento</li>
        <li>
            <a href="usuarios.php" class="<?= $current_fp_page == 'usuarios.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Usuarios y Roles
            </a>
        </li>
        <li>
            <a href="auditoria.php" class="<?= $current_fp_page == 'auditoria.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Auditoría ISO
            </a>
        </li>
        <li>
            <a href="changelog.php" class="<?= $current_fp_page == 'changelog.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Changelog ISO 27001
            </a>
        </li>
        <li>
            <a href="papelera.php" class="<?= $current_fp_page == 'papelera.php' ? 'active' : '' ?>">
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Papelera de Reciclaje
            </a>
        </li>
        <?php } ?>
    </ul>
    
    <!-- Tirador para redimensionar -->
    <div class="sidebar-resizer" id="fpSidebarResizer"></div>
</aside>

<!-- Botón Flotante de Selector de Tema (Accesible en cualquier dispositivo) -->
<button id="floatingThemeToggleBtn" class="floating-theme-btn" aria-label="Cambiar tema">
    <svg id="floatThemeSun" class="theme-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
    <svg id="floatThemeMoon" class="theme-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
</button>

<script>
// Lógica de cambio de tema en sidebar de FP (Botón Flotante)
(function() {
    const btn = document.getElementById('floatingThemeToggleBtn');
    const sunIcon = document.getElementById('floatThemeSun');
    const moonIcon = document.getElementById('floatThemeMoon');

    if (!btn) return;

    function updateToggleUI(theme) {
        if (theme === 'dark') {
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
        } else {
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
        }
    }

    const currentTheme = document.documentElement.classList.contains('dark-theme') ? 'dark' : 'light';
    updateToggleUI(currentTheme);

    btn.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark-theme');
        const theme = isDark ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
        updateToggleUI(theme);
    });
})();
</script>

<!-- Overlay for Mobile Sidebar -->
<div id="mobile-overlay" class="mobile-overlay" onclick="toggleFpSidebar()"></div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Wrap all tables in .table-responsive to enable horizontal scrolling on mobile
    document.querySelectorAll("table").forEach(function(table) {
        if (!table.parentElement.classList.contains("table-responsive") && 
            !table.closest(".table-responsive") &&
            !table.classList.contains("no-responsive")) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
});

function toggleFpSidebar() {
    const sidebar = document.getElementById('fpSidebar');
    const overlay = document.getElementById('mobile-overlay');
    sidebar.classList.toggle('active');
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

// Lógica de redimensionamiento
(function() {
    const resizer = document.getElementById('fpSidebarResizer');
    const sidebar = document.getElementById('fpSidebar');
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
