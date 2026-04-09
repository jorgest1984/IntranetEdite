<?php
// includes/fp_sidebar.php
?>
<aside class="fp-sidebar">
    <ul class="fp-menu">
        <li>
            <a href="formacion_profesional.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'formacion_profesional.php') ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                Títulos formativos
            </a>
            <ul class="fp-submenu">
                <li>
                    <a href="formacion_profesional.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                        Listado de títulos formativos
                    </a>
                </li>
                <li>
                    <a href="nuevo_titulo_formativo.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nuevo título formativo
                    </a>
                </li>
            </ul>
        </li>
        <li>
            <a href="asignaturas.php" <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['asignaturas.php', 'nueva_asignatura.php'])) ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                Asignaturas / acciones "abuela"
            </a>
            <ul class="fp-submenu">
                <li>
                    <a href="asignaturas.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                        Listado de asignaturas
                    </a>
                </li>
                <li>
                    <a href="nueva_asignatura.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nueva asignatura
                    </a>
                </li>
            </ul>
        </li>
        <li>
            <a href="acciones_madre.php" <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['acciones_madre.php', 'nueva_accion_madre.php', 'editar_accion_madre.php'])) ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                Contenidos acciones / acciones "madre"
            </a>
            <ul class="fp-submenu">
                <li>
                    <a href="acciones_madre.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                        Listado de contenidos acciones
                    </a>
                </li>
                <li>
                    <a href="nueva_accion_madre.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nuevo contenido
                    </a>
                </li>
            </ul>
        </li>
        <li>
            <a href="acciones_hija.php" <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['acciones_hija.php', 'ficha_accion_formativa.php'])) ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Acc. Formativas / Acc. "hija"
            </a>
            <ul class="fp-submenu">
                <li>
                    <a href="acciones_hija.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                        Listado de acciones formativas
                    </a>
                </li>
                <li>
                    <a href="ficha_accion_formativa.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nueva acción formativa
                    </a>
                </li>
            </ul>
        </li>
        <li>
            <a href="grupos.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'grupos.php') ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Grupos
            </a>
        </li>
        <li>
            <a href="inscripciones.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'inscripciones.php') ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                Inscripciones
            </a>
        </li>
        <li>
            <a href="buscar_alumnos.php" <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['buscar_alumnos.php', 'alumnos.php'])) ? 'class="active"' : ''; ?>>
                <svg class="fp-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Alumnos
            </a>
            <ul class="fp-submenu">
                <li>
                    <a href="buscar_alumnos.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        Buscar Alumno
                    </a>
                </li>
                <li>
                    <a href="alumnos.php">
                        <svg class="submenu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Nuevo Alumno
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</aside>
