<?php
$moodle_course_id = 43; // ID from user's URL
$moodle_user_id = 3;   // ID from user's URL

$ts = time();
$tipo = 'recibi';
$secret = "EfpMoodleSecret2026!#";
$token = hash_hmac('sha256', $moodle_course_id . '|' . $moodle_user_id . '|' . $tipo . '|' . $ts, $secret);

$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$url = "https://gestion.grupoefp.es/api_moodle_pdf.php?courseid={$moodle_course_id}&userid={$moodle_user_id}&tipo={$tipo}&ts={$ts}&token={$token}";
echo "Fetching: $url\n";
echo file_get_contents($url, false, $ctx);
