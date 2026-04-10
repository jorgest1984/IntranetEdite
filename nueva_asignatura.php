<?php
// nueva_asignatura.php
require_once 'includes/auth.php'; // Verifica login y permisos

$sectores = [
    'Abogados', 'Acción e Intervención Social', 'Administracion y gestion', 'Agencias de Viaje', 
    'Agricultura y otro sector ganaderia', 'Agroalimentaria', 'Alimentación', 'Alojamientos turísticos', 
    'Ambulancias', 'Arquitectura', 'Artes Gráficas', 'Artistas y Técnicos en Salas de Fiestas, Bailes y Discotecas', 
    'Asesorías', 'Asociaciones', 'Atención a personas con discapacidad', 'Atención Domiciliaria', 
    'Atención Especializada Familia', 'Automoción', 'Ayuda a domicilio', 'Banca', 'Cajas de ahorro', 
    'Centros de Asistencia Administrativa', 'Centros de día', 'Cerámicas y Artesanos', 'Chapa y pintura', 
    'Clinicas Privadas', 'Colegios/Institutos', 'Comercio', 'Construcción', 
    'Consultoría', 'Consultoría Informática', 'Contact Center', 'Coperativas', 'copisterias/fotocopias', 
    'Decoración', 'Desconocido', 'Desempleados', 'Dietetica y Nutricion', 'Diseño especializado', 
    'Economía social', 'Educación y Formación', 'Empleados Fincas Urbanas', 'Empresas de trabajo temporal', 
    'Energía y Agua', 'Enseñanza Privada', 'Entidades de Seguros', 'Estaciones de Servicio', 'Estética', 
    'Estudio de tatuajes', 'Estudios de mercado', 'Exhibición Cinematográfica', 'Farmacia', 'Fisioterapeutas', 
    'Fotografía', 'Fundaciones', 'Gestorías administrativas', 'Gimnasios', 'Guarderías', 'Hostelería', 
    'Imagen y sonido', 'Industria manufacturera', 'Industria vinícola', 'Industrias Químicas', 'Ingenierías', 
    'Inmobiliarias', 'Instalaciones Deportivas', 'Limpieza de Edificios y Locales', 'Madera y Mueble', 
    'Metal', 'Minería', 'Ocio y Tiempo Libre', 'Parques Temáticos', 'Peluquería y Estética', 'Peluquerías', 
    'Pesca', 'Pintura', 'Pompas Fúnebres', 'Prensa', 'Prensa diaria', 'Prensa no diaria', 'Producción Audiovisual', 
    'Publicidad', 'Público', 'Químicas', 'Recreativos', 'Residencias privadas', 'Sanidad', 'Seguridad Privada', 
    'Seguros', 'Serveis Financiers i Oficines', 'Servicio Doméstico', 'Servicios a la Comunidad', 
    'Servicios a las empresas', 'Servicios Auxiliares', 'Servicios de Prevención Ajenos', 'Servicios Funerarios', 
    'Servicios Sociales', 'Siderurgia', 'Suministros agrícolas', 'Talleres de restauración', 
    'Telecomunicaciones', 'Textil', 'Textil y Confección', 'Tintorerías', 'Transporte', 'Transportes', 
    'Turismo', 'Universidades', 'Vidrio y Cerámica'
];
sort($sectores);

$familias = [
    'Certificado de Profesionalidad', 'Familia- Actividades Físicas y Deportivas',
    'Familia- Administración y Gestión', 'Familia- Agraria', 'Familia- Artes graficas',
    'Familia- Comercio y Marketing', 'Familia- Edificación y Obra Civil',
    'Familia- Energía y Agua', 'Familia- Hostelería y Turismo', 'Familia- Imagen Personal',
    'Familia- Imagen y Sonido', 'Familia- Industria alimentaria',
    'Familia- Informática y Comunicaciones', 'Familia- Seguridad y Medioambiente',
    'Familia: Sevicios socioculturales y a la comunidad', 'Oferta 1.Appforbrands',
    'Oferta 2.Appforbrands', 'Oferta 3. Hosteleria y Restauracion',
    'Prevención de Riesgos Laborales', 'SAP', 'Seguridad Privada', 'Transversal'
];
sort($familias);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Asignatura - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .ficha-container {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .ficha-header {
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .ficha-body {
            padding: 25px;
        }

        /* Secciones de la Ficha */
        .ficha-section {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
        }

        .form-group-ficha {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .label-ficha {
            font-weight: 700;
            font-size: 0.8rem;
            color: #1e3a8a; /* Azul oscuro para los labels principales */
        }

        .input-ficha {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
            width: 100%;
            background: #f8fafc;
            outline: none;
        }

        .input-ficha:focus {
            border-color: #0ea5e9;
            background: white;
        }

        /* Tabla de Horas */
        .horas-container {
            margin: 20px 0;
        }

        .horas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .horas-table th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
            color: #1e3a8a;
            font-weight: 700;
        }

        .horas-table td {
            border: 1px solid #cbd5e1;
            padding: 0;
        }

        .input-hora {
            width: 100%;
            height: 100%;
            display: block;
            border: none;
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            outline: none;
            background: #fff;
            box-sizing: border-box;
            transition: background 0.2s;
        }

        .input-hora:focus {
            background: #fef2f2;
        }

        /* Estilo para las flechas integradas */
        .input-hora::-webkit-inner-spin-button,
        .input-hora::-webkit-outer-spin-button {
            opacity: 1;
            cursor: pointer;
            height: 24px;
        }

        /* Áreas temáticas */
        .area-wrapper {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .btn-dots {
            padding: 8px 10px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
        }

        /* Editores */
        .editor-label {
            color: #c2410c; /* Naranja/Rojo para los labels de los editores */
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 10px;
            display: block;
            text-decoration: underline;
        }

        .editor-mockup {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .editor-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .tool-btn {
            background: none;
            border: 1px solid transparent;
            padding: 4px;
            border-radius: 3px;
            cursor: pointer;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
        }

        .tool-btn:hover { background: #e2e8f0; border-color: #cbd5e1; }

        .tool-btn svg { width: 14px; height: 14px; }

        .editor-area {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: none;
            resize: vertical;
            font-family: inherit;
            font-size: 0.85rem;
            outline: none;
        }

        /* Footer */
        .ficha-footer {
            padding: 15px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-insertar {
            background: #1e293b;
            color: white;
            padding: 10px 30px;
            border-radius: 6px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .btn-insertar:hover {
            background: #0f172a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-cancelar-ficha {
            padding: 10px 20px;
            background: white;
            border: 1px solid #cbd5e1;
            color: #64748b;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .btn-cancelar-ficha:hover {
            background: #f1f5f9;
        }

        /* Tabla de Sectores Adicionales */
        .sectores-table-container {
            margin-top: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .sectores-table-header {
            background: #1e293b;
            padding: 10px;
            text-align: center;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }

        .sectores-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sectores-table th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 10px;
            font-size: 0.75rem;
            color: #1e3a8a;
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sectores-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 10px;
            text-align: center;
            font-size: 0.8rem;
            color: #334155;
        }

        .btn-add-sector-tab {
            padding: 5px 15px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e3a8a;
            transition: all 0.2s;
        }

        .btn-add-sector-tab:hover {
            background: #f1f5f9;
            border-color: #1e3a8a;
        }

    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title" style="display: flex; align-items: center; gap: 20px; justify-content: space-between; width: 100%;">
                <div>
                    <h1>Ficha de Acción (Abuela)</h1>
                    <p>Creación de contenido para títulos formativos</p>
                </div>
                <a href="asignaturas.php" class="btn-fp" style="display: flex; align-items: center; gap: 8px; text-decoration: none; background: var(--primary-color); color: white; border-radius: 0; padding: 6px 12px; font-weight: 700; font-size: 0.75rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2); transition: all 0.2s; border: 1px solid #b91c1c;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    VOLVER AL LISTADO
                </a>
            </div>
        </header>

        <form action="asignaturas.php" method="POST" class="ficha-container">
            <div class="ficha-header">Ficha de Acción de Título Formativo</div>
            
            <div class="ficha-body">
                <!-- Fila 1 -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 6;">
                        <label class="label-ficha">Curso al que pertenece:</label>
                        <select class="input-ficha">
                            <option value="">Seleccione el curso...</option>
                            <option>Técnico en Emergencias Sanitarias</option>
                            <option>Técnico Superior en Educación Infantil</option>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 4;">
                        <label class="label-ficha">Denominación:</label>
                        <input type="text" class="input-ficha">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Modalidad:</label>
                        <select class="input-ficha">
                            <option>Presencial</option>
                            <option>Distancia</option>
                            <option>Teleformación</option>
                            <option>Mixta</option>
                        </select>
                    </div>
                </div>

                <!-- Fila 2 -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código Intranet:</label>
                        <input type="text" class="input-ficha">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Código externo:</label>
                        <input type="text" class="input-ficha">
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 3;">
                        <label class="label-ficha">Nivel formación:</label>
                        <select class="input-ficha">
                            <option>Básico</option>
                            <option>Intermedio</option>
                            <option>Avanzado</option>
                        </select>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 2;">
                        <label class="label-ficha">Abreviatura:</label>
                        <input type="text" class="input-ficha">
                    </div>
                </div>

                <!-- Horas -->
                <div class="horas-container">
                    <label class="label-ficha" style="margin-bottom: 10px; display: block;">Nº de horas de la Acción Formativa:</label>
                    <table class="horas-table">
                        <thead>
                            <tr>
                                <th>Presencial</th>
                                <th>Distancia</th>
                                <th>Teleformación</th>
                                <th>Mixta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="number" class="input-hora" placeholder="0"></td>
                                <td><input type="number" class="input-hora" placeholder="0"></td>
                                <td><input type="number" class="input-hora" placeholder="0"></td>
                                <td><input type="number" class="input-hora" placeholder="0"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Áreas temáticas -->
                <div class="ficha-section">
                    <div class="form-group-ficha" style="grid-column: span 4;">
                        <label class="label-ficha">Área temática:</label>
                        <div class="area-wrapper">
                            <select class="input-ficha">
                                <option value="">Seleccione área...</option>
                                <?php foreach ($sectores as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-dots">...</button>
                        </div>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 4;">
                        <label class="label-ficha">Segunda área temática:</label>
                        <div class="area-wrapper">
                            <select class="input-ficha">
                                <option value="">Seleccione área...</option>
                                <?php foreach ($sectores as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-dots">...</button>
                        </div>
                    </div>
                    <div class="form-group-ficha" style="grid-column: span 4;">
                        <label class="label-ficha">Familia profesional:</label>
                        <select class="input-ficha">
                            <option value="">Seleccione familia...</option>
                            <?php foreach ($familias as $f): ?>
                                <option value="<?= $f ?>"><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Tabla de Sectores -->
                <div class="sectores-table-container">
                    <div class="sectores-table-header">SECTORES ADICIONALES</div>
                    <table class="sectores-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">SECTOR</th>
                                <th style="width: 30%;">SOLICITANTE</th>
                                <th style="width: 30%;">CONVOCATORIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" style="padding: 15px;">
                                    <button type="button" class="btn-add-sector-tab">+ Añadir nuevo sector</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Editores -->
                <div class="editor-section">
                    <label class="editor-label">Contenidos:</label>
                    <div class="editor-mockup">
                        <div class="editor-toolbar">
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                            <button type="button" class="tool-btn">Párrafo</button>
                            <div style="width: 1px; height: 18px; background: #cbd5e1; margin: 0 5px;"></div>
                            <button type="button" class="tool-btn">B</button>
                            <button type="button" class="tool-btn">I</button>
                            <button type="button" class="tool-btn">X²</button>
                            <button type="button" class="tool-btn">X₂</button>
                            <div style="width: 1px; height: 18px; background: #cbd5e1; margin: 0 5px;"></div>
                            <button type="button" class="tool-btn">""</button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></button>
                        </div>
                        <textarea class="editor-area" placeholder="Escriba los contenidos aquí..."></textarea>
                    </div>

                    <label class="editor-label">Objetivos:</label>
                    <div class="editor-mockup">
                        <div class="editor-toolbar">
                            <button type="button" class="tool-btn">B</button>
                            <button type="button" class="tool-btn">I</button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
                        </div>
                        <textarea class="editor-area" placeholder="Escriba los objetivos aquí..."></textarea>
                    </div>

                    <label class="editor-label">Formación requerida del alumno:</label>
                    <div class="editor-mockup">
                        <div class="editor-toolbar">
                            <button type="button" class="tool-btn">B</button>
                            <button type="button" class="tool-btn">I</button>
                            <button type="button" class="tool-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
                        </div>
                        <textarea class="editor-area" placeholder="Escriba los requisitos aquí..."></textarea>
                    </div>
                </div>
            </div>

            <div class="ficha-footer">
                <a href="asignaturas.php" class="btn-cancelar-ficha">Cancelar</a>
                <button type="submit" class="btn-insertar">Insertar Registro</button>
            </div>
        </form>
    </main>
</div>

</body>
</html>
