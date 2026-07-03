<?php
$file = 'ficha_alumno.php';
$content = file_get_contents($file);

$start_tag = '<!-- TAB: Inscripciones -->';
$end_tag = '<!-- TAB: Documentación -->';

$start_pos = strpos($content, $start_tag);
$end_pos = strpos($content, $end_tag);

if ($start_pos !== false && $end_pos !== false) {
    $new_content = '<!-- TAB: Inscripciones -->
            <div id="tab-inscripciones" style="<?= $active_tab == \'inscripciones\' ? \'\' : \'display:none;\' ?>">
                
                <div style="text-align: center; margin-bottom: 10px;">
                    <button style="font-size: 0.7rem; padding: 2px 5px; border: 1px solid #999; background: #eee; cursor: pointer;">Guardar registro</button>
                    <h2 style="color: #b91c1c; font-size: 1rem; font-weight: bold; margin: 10px 0;">CURSOS CONTRATOS-PROGRAMA</h2>
                    <div style="color: #1e3a8a; font-size: 0.8rem; font-weight: bold;">
                        Se muestran cursos de todas las convocatorias. <a href="#" style="color: #1e3a8a; text-decoration: underline;">VER SÓLO CURSOS DE CONVOCATORIA ACTUAL</a>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; text-align: left; background: #fff; min-width: 1000px; font-family: Arial, sans-serif;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Empresa</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Plan</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Nº Acción</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Nº Grupo</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Modalidad</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Horas</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Curso</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Tutor</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Situación</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Inicio</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0; color: #1e3a8a; font-weight: bold;">Fin</th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0;"></th>
                                <th style="border: 1px solid #999; padding: 4px; background: #f0f0f0;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matriculas as $mat): ?>
                                <tr>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'empresa_nombre\'] ?? \'DESEMPLEADO\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'plan_nombre\'] ?? \'Formacion 2025\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'af_abreviatura\'] ?? \'2PRL\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'numero_grupo\'] ?? \'1\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'modalidad_real\'] ?? \'T\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'horas\'] ?? \'30\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'curso_nombre\'] ?? $mat[\'convocatoria_nombre\'] ?? \'\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars(trim(($mat[\'tutor_nombre\'] ?? \'\') . \' \' . ($mat[\'tutor_apellidos\'] ?? \'\'))) ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= htmlspecialchars($mat[\'estado\'] ?? \'Finalizado\') ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= !empty($mat[\'grupo_inicio\']) && $mat[\'grupo_inicio\'] != \'0000-00-00\' ? date(\'d/m/Y\', strtotime($mat[\'grupo_inicio\'])) : \'\' ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; color: #1e3a8a;"><?= !empty($mat[\'grupo_fin\']) && $mat[\'grupo_fin\'] != \'0000-00-00\' ? date(\'d/m/Y\', strtotime($mat[\'grupo_fin\'])) : \'\' ?></td>
                                    <td style="border: 1px solid #999; padding: 4px; text-align: center;">
                                        <a href="#" style="text-decoration: none;">📝</a>
                                    </td>
                                    <td style="border: 1px solid #999; padding: 4px; text-align: center;">
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm(\'¿Estás seguro de que deseas eliminar esta inscripción?\');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">
                                            <input type="hidden" name="action" value="delete_inscripcion">
                                            <input type="hidden" name="matricula_id" value="<?= $mat[\'id\'] ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; color: #b91c1c; font-weight: bold; padding: 0;">❌</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Añadir nueva inscripcion row -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; text-align: center;">
                                    <a href="#" onclick="document.getElementById(\'form-nueva-inscripcion\').style.display=\'block\'; return false;" style="color: #1e3a8a; text-decoration: none;">Nueva inscripción</a>
                                </td>
                            </tr>
                            
                            <!-- Bonificados Header -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; background: #f0f0f0; text-align: center; color: #b91c1c; font-weight: bold;">CURSOS BONIFICADOS</td>
                            </tr>
                            <!-- Bonificados Empty row -->
                            <tr>
                                <td colspan="13" style="border: 1px solid #999; padding: 4px; text-align: center; color: #1e3a8a; font-weight: bold;">No hay inscripciones bonificadas</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Formulario Añadir Inscripcion (Oculto inicialmente) -->
                <div id="form-nueva-inscripcion" style="display: none; margin-top: 20px; border: 1px solid #999; padding: 15px; background: #f8fafc;">
                    <h3 style="margin-top: 0; color: #1e3a8a; font-family: Arial, sans-serif; font-size: 1rem;">Añadir Inscripción</h3>
                    <form method="POST" style="font-family: Arial, sans-serif;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">
                        <input type="hidden" name="action" value="add_inscripcion">
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Convocatoria / Curso *</label>
                            <select name="convocatoria_id" required style="width: 100%; max-width: 400px; padding: 0.4rem; border: 1px solid #999;">
                                <option value="">-- Seleccionar Convocatoria --</option>
                                <?php foreach ($convocatorias as $c): ?>
                                    <option value="<?= $c[\'id\'] ?>">
                                        <?= htmlspecialchars(($c[\'codigo_expediente\'] ? \'[\'.$c[\'codigo_expediente\'].\'] \' : \'\') . $c[\'nombre\']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Estado *</label>
                            <select name="estado" required style="width: 100%; max-width: 200px; padding: 0.4rem; border: 1px solid #999;">
                                <option value="Inscrito" selected>Inscrito</option>
                                <option value="Activo">Activo</option>
                                <option value="Finalizada">Finalizada</option>
                                <option value="Baja">Baja</option>
                                <option value="Cancelada">Cancelada</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; color: #1e3a8a; margin-bottom: 0.4rem;">Fecha de Matrícula</label>
                            <input type="date" name="fecha_matricula" value="<?= date(\'Y-m-d\') ?>" style="width: 100%; max-width: 150px; padding: 0.4rem; border: 1px solid #999;">
                        </div>
                        
                        <button type="submit" style="padding: 5px 15px; border: 1px solid #999; background: #eee; cursor: pointer; color: #1e3a8a; font-weight: bold;">
                            Registrar Inscripción
                        </button>
                        <button type="button" onclick="document.getElementById(\'form-nueva-inscripcion\').style.display=\'none\';" style="padding: 5px 15px; border: 1px solid #999; background: #eee; cursor: pointer; color: #b91c1c; font-weight: bold; margin-left: 10px;">
                            Cancelar
                        </button>
                    </form>
                </div>
            </div>
            
            ';

    $final_content = substr($content, 0, $start_pos) . $new_content . substr($content, $end_pos);
    file_put_contents($file, $final_content);
    echo "Success!";
} else {
    echo "Tags not found.";
}
