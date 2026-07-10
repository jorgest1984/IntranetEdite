<?php
// scratch/test_notifications_sending.php
// Script de prueba para enviar las 4 plantillas de correo a jorge@estaciondiseno.es

require_once __DIR__ . '/../includes/smtp_mailer.php';

$test_email = 'jorge@estaciondiseno.es';
$curso_titulo = 'CURSO DE PRUEBA: Ley de Igualdad y Violencia de Género';
$fecha_25 = date('d/m/Y', strtotime('+1 day'));
$fecha_fin = date('d/m/Y', strtotime('+3 days'));

echo "=== INICIANDO ENVÍO DE CORREOS DE PRUEBA ===\n";
echo "Destinatario: $test_email\n\n";

// 1. Alerta del 25% (Día de antes)
$subject1 = "⚠️ IMPORTANTE: Acceso pendiente a tu curso en el Aula Virtual (Prueba)";
$body1 = "Hola Jorge,\n\n"
      . "Te recordamos que tu curso '$curso_titulo' se encuentra en marcha. Aún no registramos tu primer acceso a nuestra Aula Virtual.\n\n"
      . "Es obligatorio que accedas como tarde mañana ($fecha_25), ya que si no registras tu primer acceso antes de esa fecha límite, la plataforma te dará de baja de forma automática y perderás tu plaza en el curso.\n\n"
      . "Por favor, accede cuanto antes haciendo clic aquí:\nhttps://aulavirtual.grupoefp.es/\n\n"
      . "Tus credenciales de acceso son:\n"
      . "- Usuario: 12345678X\n"
      . "- Contraseña: Edite12345678X!\n\n"
      . "Un saludo,\n"
      . "Equipo de Formación Grupo EFP.";

echo "Enviando plantilla 1 (Alerta 25%)... ";
if (send_smtp_email($test_email, $subject1, $body1)) {
    echo "¡Enviado con éxito!\n";
} else {
    echo "FALLÓ.\n";
}

// 2. Baja automática (Día del 25%)
$subject2 = "Baja automática del curso por falta de acceso (Prueba)";
$body2 = "Hola Jorge,\n\n"
      . "Lamentamos informarte que, debido a que no has accedido al Aula Virtual antes de la fecha límite del 25% del curso ($fecha_25), se ha procedido a darte de baja del curso '$curso_titulo' de manera automática.\n\n"
      . "Si consideras que se trata de un error o deseas consultar opciones de reincorporación, por favor contacta con nosotros respondiendo a este email.\n\n"
      . "Un saludo,\n"
      . "Equipo de Formación Grupo EFP.";

echo "Enviando plantilla 2 (Baja 25%)... ";
if (send_smtp_email($test_email, $subject2, $body2)) {
    echo "¡Enviado con éxito!\n";
} else {
    echo "FALLÓ.\n";
}

// 3. Mensaje del 50%
$subject3 = "📈 Progreso del curso: ¡Has alcanzado el 50%! (Prueba)";
$body3 = "Hola Jorge,\n\n"
      . "Te informamos que hoy es la fecha del 50% (mitad de curso) de tu curso '$curso_titulo'.\n\n"
      . "Te animamos a seguir ingresando regularmente al Aula Virtual y completando tus evaluaciones y actividades pendientes para asegurar el aprovechamiento del curso.\n\n"
      . "Puedes acceder al Aula Virtual aquí:\nhttps://aulavirtual.grupoefp.es/\n\n"
      . "Un saludo,\n"
      . "Equipo de Formación Grupo EFP.";

echo "Enviando plantilla 3 (Hito 50%)... ";
if (send_smtp_email($test_email, $subject3, $body3)) {
    echo "¡Enviado con éxito!\n";
} else {
    echo "FALLÓ.\n";
}

// 4. Mensaje de Fin (3 días antes)
$subject4 = "⏳ Faltan 3 días para finalizar tu curso (Prueba)";
$body4 = "Hola Jorge,\n\n"
      . "Te recordamos que tu curso '$curso_titulo' finalizará en 3 días (el $fecha_fin).\n\n"
      . "Por favor, asegúrate de haber realizado todas las evaluaciones, exámenes y cuestionarios de calidad en el Aula Virtual antes del cierre del curso.\n\n"
      . "Acceso al Aula Virtual:\nhttps://aulavirtual.grupoefp.es/\n\n"
      . "Un saludo,\n"
      . "Equipo de Formación Grupo EFP.";

echo "Enviando plantilla 4 (Aviso 3 días fin)... ";
if (send_smtp_email($test_email, $subject4, $body4)) {
    echo "¡Enviado con éxito!\n";
} else {
    echo "FALLÓ.\n";
}

echo "\n=== PRUEBA FINALIZADA ===\n";
