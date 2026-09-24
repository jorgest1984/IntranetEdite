<?php
// scratch/deploy_ftp.php
$server = "212.227.149.63";
$user = "nintranet";
$pass = "Estacion.2025";

$conn = ftp_connect($server);
if (!$conn) {
    die("Error: No se pudo conectar al servidor FTP $server\n");
}

if (!ftp_login($conn, $user, $pass)) {
    die("Error: Autenticación FTP fallida\n");
}

ftp_pasv($conn, true);

$files = [
    "includes/auth.php",
    "includes/moodle_api.php",
    "includes/PdfGenerator.php",
    "pdf_diploma.php",
    "api_sync_moodle.php",
    "procesar_nueva_af.php",
    "cursos.php",
    "guardar_accion.php",
    "importar_matriculas_rapido.php",
    "gestion_matriculas.php",
    "relacion_alumnos.php",
    "envio_claves.php",
    "api_send_matricula_keys.php",
    "pdf_contactos_fundae.php",
    "exportar_fundae_alta_xml.php",
    "ficha_alumno.php",
    "ficha_matricula.php",
    "api_import_moodle_students.php",
    "matriculas.php",
    "editar_plan.php",
    "includes/moodle_db.php",
    "scratch/debug_tiktok_modules.php",
    "scratch/sync_tiktok_now.php",
    "scratch/migration_sedes.php",
    "scratch/check_schema.php",
    "scratch/check_centros_data.php",
    "scratch/check_matriculas_cols.php",
    "scratch/remote_check_tiktok.php",
    "scratch/register_adgd073po.php",
    "scratch/register_adgg057po.php",
    "scratch/register_adgd29_grupo2.php",
    "scratch/check_eva_patricia.php",
    "nueva_af.php",
    "ficha_accion_formativa.php",
    "guardar_grupo.php",
    "ficha_grupo_edicion.php",
    "ficha_trabajador.php",
    "scratch/check_patricia.php",
    "scratch/check_course_66.php",
    "scratch/fix_group15_course.php",
    "scratch/check_ofimatica.php",
    "scratch/fix_ofimatica_68.php",
    "usuarios.php",
    "api_relink_moodle.php",
    "acciones_formativas.php",
    "editar_af.php",
    "scratch/fix_matriculas_estado_column.php",
    "pdf_hoja_bienvenida.php",
    "pdf_recibi_material.php",
    "img/bienvenida_bg_1.png",
    "img/bienvenida_bg_2.png",
    "img/bienvenida_bg_3.png",
    "scratch/remote_pdf_debug.php",
    "scratch/remote_check_grupo.php",
    "scratch/fix_inspector_remote.php",
    "moodle_informes.php",
    "index.php",
    "home.php",
    "includes/config.php",
    "includes/sidebar.php",
    "api_send_trabajador_keys.php",
    "scratch/reset_and_send_comerciales.php",
    "scratch/verify_login_test.php",
    "scratch/check_consuelo.php"
];

foreach ($files as $f) {
    $local_file = __DIR__ . "/../" . $f;
    $remote_file = "/httpdocs/" . $f;

    if (!file_exists($local_file)) {
        echo "Archivo local no encontrado: $local_file\n";
        continue;
    }

    if (ftp_put($conn, $remote_file, $local_file, FTP_BINARY)) {
        echo "✅ Subido correctamente: $f -> $remote_file\n";
    } else {
        echo "❌ Error al subir: $f\n";
    }
}

ftp_close($conn);
echo "Envío FTP finalizado.\n";

// Limpiar OPCache invocando el endpoint de limpieza
$ch = curl_init("https://gestion.grupoefp.es/upload_and_clear.php?clean_opcache=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Resultado limpieza OPCache: " . strip_tags($res) . "\n";
