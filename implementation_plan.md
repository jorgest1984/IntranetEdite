# Acta de Evaluación Final de Grupo (PDF)

## 🎯 Objetivo
Crear una nueva tarjeta en la sección de "Documentación y Anexos" para generar el "Acta de Evaluación Final de Grupo" en PDF. Este documento oficial debe ajustarse exactamente al formato proporcionado en la imagen de referencia y debe incluir únicamente a los alumnos que hayan finalizado todas las evaluaciones en Moodle.

## 📋 Contexto y Requisitos
El acta es un documento de cierre donde el formador y el centro certifican las calificaciones finales de los alumnos aptos.

**Requisitos específicos:**
- **Filtrado de alumnos:** Solo deben aparecer alumnos donde las tres evaluaciones estén completadas (`moodle_e1_completed = 1`, `moodle_e2_completed = 1`, `moodle_e3_completed = 1`) y que estén activos en el grupo.
- **Formato Visual:**
  - Cabecera con los logos oficiales (Fundae, Ministerio, SEPE, Edite, etc.).
  - Título centrado: "ACTA DE EVALUACIÓN FINAL DE GRUPO".
  - Metadatos: Expediente, Nº Acción, Grupo, Curso y Nº horas.
  - **Tabla estructurada:**
    - ALUMNOS (Apellidos, Nombre).
    - CONTROLES (Subcolumnas: E1, E2, E3, % CONTROLES).
    - CALIFICACIÓN (Subcolumnas: FINAL, y etiqueta "APTO").
  - **Pie de firma:**
    - Izquierda: "El centro:" con el sello escaneado (`firma_mars.png`).
    - Derecha: "El formador:" con el Nombre y NIF del tutor.

## 🛠️ Detalles de Implementación Técnica

### 1. Interfaz de Usuario (Frontend)
- Modificar `documentacion.php` para añadir una nueva tarjeta `Acta de Evaluación Final`.
- Reutilizar la ventana modal de selección existente para pasar la `accion_id` y `grupo_id`.
- Botón de descarga que abrirá el generador PDF en una nueva pestaña.

### 2. Generador PDF (Backend)
- Crear el script `pdf_acta_evaluacion.php` utilizando la librería nativa **FPDF** (igual que el informe de evaluaciones), ya que el diseño es una tabla sencilla y muy cuadriculada, que FPDF maneja perfectamente.
- **Lógica SQL:** 
  - Consultar `acciones_formativas`, `planes`, `convocatorias` y `grupos` para obtener la duración en horas y los metadatos.
  - Obtener el nombre y NIF del formador/tutor desde la tabla `usuarios` enlazado mediante `grupos.tutor_id`.
  - Consultar los alumnos de `matriculas` aplicando el filtro estricto de finalización (`e1_completed = 1 AND e2_completed = 1 AND e3_completed = 1`).
- **Dibujado del PDF:**
  - `Image()` para posicionar la cabecera de logos.
  - Funciones `Cell()` de FPDF configuradas para dibujar los bordes anidados de la tabla de controles y calificaciones, recreando con exactitud matemática el pantallazo.
  - Inserción de la imagen de firma al final del documento con coordenadas X,Y específicas.

## ⚠️ User Review Required
> [!IMPORTANT]
> **Preguntas de diseño para ti:**
> 1. En la columna "% CONTROLES", ¿se mostrará siempre `100.00%` ya que el filtro obliga a que tengan todo completo, o existe algún cálculo específico que deba aplicarse basándose en las notas?
> 2. En la firma del formador (derecha), extraeré el nombre y DNI del usuario asociado como tutor del grupo. ¿Es correcto?
>
> Revisa el plan y confírmame para empezar.
