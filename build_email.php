<?php
$file = 'tutorias.php';
$content = file_get_contents($file);

// Cambiar titulo y current page
$content = str_replace("\$current_page = 'tutorias.php';", "\$current_page = 'email_masivo.php';", $content);
$content = str_replace("<title>Tutorías - <?= APP_NAME ?></title>", "<title>Email Masivo - <?= APP_NAME ?></title>", $content);

// Eliminar la action bar
$action_bar = <<<'HTML'
            <!-- ACTION BAR -->
            <div class="action-bar">
                <button type="button" class="btn-action">Calcular llamadas</button>
                <button type="button" class="btn-action">E-mails masivos</button>
                <button type="button" class="btn-action">Inicio curso ()</button>
                <button type="button" class="btn-action">Mitad de curso ()</button>
                <button type="button" class="btn-action">7 días fin ()</button>
                <button type="button" class="btn-action">Documentación ()</button>
                <button type="button" class="btn-action">Subir evals</button>
                <button type="button" class="btn-action">Imprimir evals</button>
                <button type="button" class="btn-action">Llamadas seguimiento</button>
                <button type="button" class="btn-action">Calendario de tutorias</button>
            </div>
HTML;
$content = str_replace($action_bar, "", $content);

// Reemplazar Búsqueda (Buscador -> Resultados)
$start = strpos($content, "<!-- BUSCADOR -->");
$end = strpos($content, "<!-- RESULTADOS -->");
$before = substr($content, 0, $start);
$after = substr($content, $end);

$new_search = <<<'HTML'
<!-- BUSCADOR -->
            <div class="search-card">
                <div class="card-header-custom" style="background:#f8fafc;">
                    <h2 style="color:var(--title-red); margin:0;">EMAIL MASIVO - TUTORÍAS</h2>
                </div>
                <form class="search-form" method="POST" enctype="multipart/form-data">
                    
                    <!-- Fila 1 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Nº Acción:</label><input type="text" name="n_accion" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Número grupo:</label><input type="text" name="n_grupo" class="form-control" style="width: 80px;">
                        </div>
                        <div class="form-group">
                            <label>Convocatoria:</label>
                            <select name="convocatoria" class="form-control" style="width: 150px;">
                                <option value="">Todas</option>
                                <?php foreach($convocatorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plan:</label>
                            <select name="plan" class="form-control" style="width: 250px;"><option value="">Todos los planes</option></select>
                        </div>
                        <div class="form-group">
                            <label>Modalidad:</label>
                            <select name="modalidad" class="form-control" style="width: 120px;">
                                <option value="">---</option>
                                <option value="Teleformación">Teleformación</option>
                                <option value="Distancia">Distancia</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Presencial">Presencial</option>
                                <option value="Semipresencial">Semipresencial</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Inicio desde:</label><input type="date" name="inicio_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="inicio_hasta" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Fin desde:</label><input type="date" name="fin_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="fin_hasta" class="form-control">
                        </div>
                        <div class="form-group" style="margin-left: 10px;">
                            <label>Empresa:</label>
                            <input type="text" name="empresa" class="form-control" list="empresas_list" placeholder="..." style="width: 150px;">
                            <datalist id="empresas_list">
                                <?php foreach($centros_db as $c): ?><option value="<?= htmlspecialchars($c['nombre']) ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Estado:</label>
                            <select name="estado" class="form-control" style="width: 150px;">
                                <option value="">---</option>
                                <option value="Admitido">Admitido</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Comercial:</label>
                            <select name="comercial" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($comerciales as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Claves enviadas:</label>
                            <select name="claves" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Conectados:</label>
                            <select name="conectados" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Realizaron encuesta:</label>
                            <select name="encuesta" class="form-control"><option value="">---</option><option value="S">Sí</option><option value="N">No</option></select>
                        </div>
                        <div class="form-group">
                            <label>Tutor:</label>
                            <select name="tutor" class="form-control" style="width: 180px;">
                                <option value="">---</option>
                                <?php foreach ($tutores as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre'] . ' ' . $t['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr style="margin: 10px 0; border: 0; border-top: 1px solid #e2e8f0;">

                    <!-- Fila 4 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label style="color: #ca8a04;">
                                <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align: middle;"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg> 
                                E-mail remitente:</label>
                            <input type="email" name="remitente" class="form-control" value="elena.adame@editeformacion.com" style="width: 250px;">
                        </div>
                        <div class="form-group" style="flex: 1; margin-left: 15px;">
                            <label>Asunto:</label>
                            <input type="text" name="asunto" class="form-control" style="width: 100%; min-width: 300px;">
                        </div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="search-row">
                        <div class="form-group">
                            <label>Fecha envío diploma desde:</label><input type="date" name="diploma_desde" class="form-control">
                            <label>hasta:</label><input type="date" name="diploma_hasta" class="form-control">
                        </div>
                    </div>

                    <!-- Fila 6 (Archivos) -->
                    <div class="search-row" style="margin-top: 10px; gap: 15px;">
                        <input type="file" name="adjunto1" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto2" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                        <input type="file" name="adjunto3" style="font-size: 0.8rem; border: 1px solid #cbd5e1; padding: 2px;">
                    </div>

                    <!-- Fila 7 (Mensaje WYSIWYG) -->
                    <div style="margin-top: 15px;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--label-blue); display: block; margin-bottom: 5px;">Mensaje:</label>
                        <textarea id="mensaje_email" name="mensaje_email" style="width: 100%; height: 250px;"></textarea>
                    </div>

                    <!-- Botones -->
                    <div style="text-align: center; margin-top: 15px; display: flex; justify-content: center; gap: 15px;">
                        <button type="button" class="btn-buscar">Buscar</button>
                        <button type="submit" class="btn-buscar" style="color: var(--label-blue); border-color: var(--border-gray);">Enviar e-mail a seleccionados</button>
                    </div>
                </form>
            </div>

            <!-- RESULTADOS -->
HTML;

$content = $before . $new_search . substr($after, 18);

// Añadir el script de TinyMCE antes del cierre de body
$tinymce = <<<'HTML'
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#mensaje_email',
        menubar: false,
        plugins: 'lists link image table code',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code',
        branding: false,
        language: 'es'
      });
    </script>
</body>
HTML;
$content = str_replace("</body>", $tinymce, $content);

file_put_contents('email_masivo.php', $content);
echo "Done.";
