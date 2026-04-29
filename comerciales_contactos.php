<?php
// comerciales_contactos.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header("Location: dashboard.php");
    exit();
}

$provincias = [
    'A Coruña', 'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila', 'Badajoz', 'Islas Baleares', 'Barcelona', 'Burgos', 'Cáceres', 'Cádiz', 'Cantabria', 'Castellón', 'Ciudad Real', 'Córdoba', 'Cuenca', 'Girona', 'Granada', 'Guadalajara', 'Gipuzkoa', 'Huelva', 'Huesca', 'Jaén', 'La Rioja', 'Las Palmas', 'León', 'Lleida', 'Lugo', 'Madrid', 'Málaga', 'Murcia', 'Navarra', 'Ourense', 'Palencia', 'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife', 'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Teruel', 'Toledo', 'Valencia', 'Valladolid', 'Vizcaya', 'Zamora', 'Zaragoza', 'Ceuta', 'Melilla'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .main-content { padding: 1.5rem; }
        .btn-volver {
            padding: 6px 20px;
            font-size: 0.85rem;
            cursor: pointer;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
            color: #475569;
            font-weight: 500;
        }
        .btn-volver:hover { background: #e2e8f0; }
    </style>
        <style>
            :root {
                --title-red: #b91c1c;
                --label-blue: #000080;
                --border-gray: #cbd5e1;
                --bg-gray: #f1f5f9;
                --input-bg: #e2e8f0;
            }
            .form-section {
                background: #fff;
                border: 1px solid var(--border-gray);
                border-radius: 4px;
                padding: 1.5rem;
                margin-top: 15px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .section-header {
                text-align: center;
                border-bottom: 1px solid var(--border-gray);
                margin-bottom: 1.5rem;
                padding-bottom: 0.5rem;
            }
            .section-header h2 {
                color: var(--title-red);
                font-size: 1.1rem;
                font-weight: 800;
                margin: 0;
                text-transform: uppercase;
            }
            .form-row {
                display: flex;
                gap: 15px;
                margin-bottom: 10px;
                align-items: center;
            }
            .form-group {
                display: flex;
                align-items: center;
                gap: 8px;
                flex: 1;
            }
            .label {
                color: var(--label-blue);
                font-weight: 700;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            .form-control {
                border: 1px solid #94a3b8;
                background: var(--input-bg);
                padding: 4px 8px;
                border-radius: 2px;
                font-size: 0.85rem;
                width: 100%;
                font-family: inherit;
                color: var(--label-blue);
                height: 28px;
                box-sizing: border-box;
            }
            .form-control:focus {
                background: #fff;
                outline: none;
                border-color: #64748b;
            }
            select.form-control {
                padding: 2px 5px;
            }
            .checkbox-group {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .btn-submit {
                background: #f1f5f9;
                border: 1px solid #94a3b8;
                color: var(--label-blue);
                font-weight: 500;
                padding: 4px 20px;
                font-size: 0.85rem;
                cursor: pointer;
                border-radius: 2px;
            }
            .btn-submit:hover { background: #e2e8f0; }

            /* Tables */
            .table-custom {
                width: 100%;
                border-collapse: collapse;
                margin-top: 5px;
            }
            .table-custom th {
                border: 1px solid var(--border-gray);
                padding: 4px 8px;
                text-align: left;
                color: var(--label-blue);
                font-weight: 800;
                font-size: 0.9rem;
            }
            .table-custom td {
                border: 1px solid var(--border-gray);
                padding: 0;
                height: 24px;
            }
            .table-custom input {
                width: 100%;
                height: 100%;
                border: none;
                background: transparent;
                padding: 0 8px;
                font-family: inherit;
                font-size: 0.85rem;
                color: var(--label-blue);
                box-sizing: border-box;
            }
            .table-custom input:focus {
                background: #fff;
                outline: none;
            }
        </style>

        <main class="main-content" style="flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 0;">
                <a href="comerciales.php" class="btn-volver">← Volver a Gestión Comercial</a>
            </div>

            <form method="POST">
                <div class="form-section">
                    <div class="section-header">
                        <h2>FICHA DE CONTACTOS EMPRESA</h2>
                    </div>

                    <!-- Fila 1 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <span class="label">Empresa:</span>
                            <input type="text" class="form-control" name="empresa">
                        </div>
                        <div class="form-group" style="flex: 1.5;">
                            <span class="label">Actividad:</span>
                            <input type="text" class="form-control" name="actividad">
                        </div>
                        <div class="form-group" style="flex: 1.5;">
                            <span class="label">Sector:</span>
                            <select class="form-control" name="sector">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="checkbox-group" style="margin-left: 10px;">
                            <span class="label" style="font-weight: 800;">BAJA</span>
                            <input type="checkbox" name="baja">
                        </div>
                    </div>

                    <!-- Fila 2 (No válido) -->
                    <div style="text-align: center; margin: 15px 0 5px 0;">
                        <span class="label">Si el contacto deja de ser válido, por favor marca la siguiente casilla y el motivo...</span>
                    </div>
                    <div class="form-row" style="justify-content: center; border-bottom: 1px solid var(--border-gray); padding-bottom: 15px;">
                        <div class="checkbox-group" style="margin-right: 20px;">
                            <span class="label">Contacto no válido:</span>
                            <input type="checkbox" name="no_valido">
                        </div>
                        <div class="form-group" style="flex: none; width: 250px;">
                            <span class="label">Elige motivo:</span>
                            <select class="form-control" name="motivo_no_valido">
                                <option value=""></option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 3 (Interesado) -->
                    <div class="form-row" style="justify-content: center; margin-top: 15px; border-bottom: 1px solid var(--border-gray); padding-bottom: 15px;">
                        <div class="checkbox-group">
                            <span class="label">Interesado nuevas convocatorias:</span>
                            <input type="checkbox" name="interesado_nuevas">
                        </div>
                    </div>

                    <!-- Fila 4 -->
                    <div class="form-row" style="margin-top: 15px;">
                        <div class="form-group" style="flex: 3;">
                            <span class="label" style="width: 120px; text-align: right;">Domicilio:</span>
                            <input type="text" class="form-control" name="domicilio">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <span class="label">CP:</span>
                            <input type="text" class="form-control" name="cp">
                        </div>
                    </div>

                    <!-- Fila 5 -->
                    <div class="form-row">
                        <div class="form-group">
                            <span class="label" style="width: 120px; text-align: right;">Localidad:</span>
                            <input type="text" class="form-control" name="localidad">
                        </div>
                        <div class="form-group">
                            <span class="label">Provincia:</span>
                            <select class="form-control" name="provincia">
                                <option value=""></option>
                                <?php foreach($provincias as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <span class="label">Teléfono 1:</span>
                            <input type="text" class="form-control" name="telefono1">
                        </div>
                        <div class="form-group">
                            <span class="label">Teléfono 2:</span>
                            <input type="text" class="form-control" name="telefono2">
                        </div>
                        <div class="form-group">
                            <span class="label">Fax:</span>
                            <input type="text" class="form-control" name="fax">
                        </div>
                    </div>

                    <!-- Fila 6 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <span class="label" style="width: 120px; text-align: right;">Móvil:</span>
                            <input type="text" class="form-control" name="movil">
                        </div>
                        <div class="form-group" style="flex: 2;">
                            <span class="label">E-mail:</span>
                            <div style="display: flex; width: 100%; position: relative; align-items: center;">
                                <input type="email" class="form-control" name="email" style="padding-right: 25px;">
                                <div style="position: absolute; right: 4px; display: flex; align-items: center; justify-content: center; width: 16px; height: 16px; background: #eab308; border-radius: 50%;">
                                    <svg viewBox="0 0 24 24" width="10" height="10" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10h5v-2h-5c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8c0 1.5-.5 2.5-1.5 2.5S18 13.5 18 12V6h-2v1.5c-1.1-1.3-2.8-2-4.5-1.5-2.2.6-3.8 2.6-4 4.9C7.2 14.5 10.1 17 13.5 17c1.4 0 2.7-.6 3.5-1.5.6.8 1.6 1.5 2.5 1.5 2.2 0 4-1.8 4-4 0-5.5-4.5-10-10-10zm0 11c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3z"/></svg>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1.5;">
                            <span class="label">Comercial:</span>
                            <select class="form-control" name="comercial">
                                <option value="Elena Adame Carbonell">Elena Adame Carbonell</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 7 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <span class="label" style="width: 120px; text-align: right;">Tema de interés:</span>
                            <input type="text" class="form-control" name="tema_interes">
                        </div>
                        <div class="checkbox-group" style="flex: 1;">
                            <span class="label">Es de PROMAX:</span>
                            <input type="checkbox" name="es_promax">
                        </div>
                    </div>

                    <!-- Fila 8 -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <span class="label" style="width: 120px; text-align: right;">Persona de contacto:</span>
                            <input type="text" class="form-control" name="persona_contacto">
                        </div>
                        <div class="checkbox-group" style="flex: 0.5;">
                            <span class="label">Tiene Certificados</span>
                            <input type="checkbox" name="tiene_certificados">
                        </div>
                        <div class="form-group" style="flex: 0.5;">
                            <span class="label">Nº Certificados</span>
                            <input type="number" class="form-control" name="num_certificados" value="0" style="width: 50px;">
                        </div>
                    </div>

                    <!-- Fila 9 (Notas) -->
                    <div class="form-row" style="align-items: flex-start; margin-top: 15px;">
                        <span class="label" style="width: 120px; text-align: right; margin-top: 5px;">Notas:</span>
                        <textarea class="form-control" name="notas" style="height: 100px; resize: vertical;"></textarea>
                    </div>

                    <!-- Botón Insertar -->
                    <div style="text-align: center; margin-top: 15px;">
                        <button type="submit" class="btn-submit">Insertar registro</button>
                    </div>

                    <!-- GESTORIAS -->
                    <div class="section-header" style="margin-top: 30px; border: none; margin-bottom: 5px;">
                        <h2>GESTORIAS</h2>
                    </div>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Razón Social</th>
                                <th style="width: 30%;">Fecha desde</th>
                                <th style="width: 30%;">Fecha hasta</th>
                                <th style="width: 30px; border: 1px solid var(--border-gray);"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="gestoria_razon[]"></td>
                                <td><input type="text" name="gestoria_desde[]"></td>
                                <td><input type="text" name="gestoria_hasta[]"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- CERTIFICADOS DE PROFESIONALIDAD -->
                    <div class="section-header" style="margin-top: 30px; border: none; margin-bottom: 5px;">
                        <h2>CERTIFICADOS DE PROFESIONALIDAD</h2>
                    </div>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Título</th>
                                <th style="width: 20%;">Código</th>
                                <th style="width: 40%;">Familia Profesional</th>
                                <th style="width: 30px; border: 1px solid var(--border-gray);"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" name="cert_titulo[]"></td>
                                <td><input type="text" name="cert_codigo[]"></td>
                                <td><input type="text" name="cert_familia[]"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </form>
        </main>
    </div>
</body>
</html>
