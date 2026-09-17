<?php
/**
 * moodle_descarga_pdf.php
 * 
 * Este archivo DEBE SER SUBIDO a la carpeta principal de Moodle (p.ej. public_html/aulavirtual/ o similar).
 * Permite a los alumnos logueados descargar sus documentos directamente desde la Intranet.
 * 
 * USO EN MOODLE:
 * 1. Sube este archivo a la raíz de tu Moodle (donde está config.php de Moodle).
 * 2. En el curso, crea una actividad de tipo "URL".
 * 3. Pon la URL: https://aulavirtual.grupoefp.es/moodle_descarga_pdf.php?tipo=recibi
 *    o para bienvenida: https://aulavirtual.grupoefp.es/moodle_descarga_pdf.php?tipo=bienvenida
 * 4. En "Apariencia" -> Selecciona "En ventana emergente" (opcional pero recomendado).
 */

require_once('config.php'); // Carga la configuración y funciones base de Moodle

// Requerir que el usuario esté logueado
require_login();

// Obtener parámetros
// $course->id ya está disponible si lo pasamos por URL o usamos la función require_login($course) 
// pero en recursos URL globales, usamos el course actual del navegador si es posible.
// Si no se pasa el course, intentamos obtenerlo de la URL referer o forzamos pasarlo.
// Moodle permite pasar variables en el módulo URL, pero para hacerlo a prueba de fallos:
// Pasaremos ?courseid=X explícitamente en el enlace o intentaremos detectarlo.

$tipo = required_param('tipo', PARAM_ALPHA); // 'recibi' o 'bienvenida'
$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    $courseid = optional_param('id', 0, PARAM_INT);
}

// Si no viene courseid por URL, intentamos sacarlo de la sesión, DB de Moodle o Referer
if (!$courseid) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        // Si proviene de un módulo de recurso URL en Moodle (/mod/url/view.php?id=XXX)
        if (strpos($referer, '/mod/') !== false && preg_match('/id=(\d+)/', $referer, $matches)) {
            $cmid = (int)$matches[1];
            if (isset($DB)) {
                $cm = $DB->get_record('course_modules', array('id' => $cmid), 'course');
                if ($cm && !empty($cm->course)) {
                    $courseid = (int)$cm->course;
                }
            }
        }
        
        if (!$courseid && preg_match('/id=(\d+)/', $referer, $matches)) {
            $courseid = (int)$matches[1];
        }
    }
    
    if (!$courseid) {
        global $COURSE;
        if (isset($COURSE) && !empty($COURSE->id)) {
            $courseid = (int)$COURSE->id;
        }
    }
}

// Si $courseid es la portada de Moodle (1) o 0, lo fijamos a 0 para que la Intranet busque por las matrículas del alumno
if ($courseid <= 1) {
    $courseid = 0;
}

// Configuración de la Intranet
// Ajustar si el dominio de la intranet es diferente
$intranet_url = 'https://gestion.grupoefp.es/api_moodle_pdf.php'; 
$secret = "EfpMoodleSecret2026!#";

global $USER;
$moodle_user_id = $USER->id;
$ts = time();

// Generar firma HMAC
$token = hash_hmac('sha256', $courseid . '|' . $moodle_user_id . '|' . $tipo . '|' . $ts, $secret);

// Construir la URL final de la Intranet
$final_url = $intranet_url . "?courseid=" . $courseid . "&userid=" . $moodle_user_id . "&tipo=" . $tipo . "&ts=" . $ts . "&token=" . $token;

// Redirigir al usuario a la intranet para descargar/generar el PDF
redirect($final_url);
