<?php
// logout.php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    audit_log($pdo, 'LOGOUT', 'sesion', $_SESSION['user_id'], null, ['ip' => $_SERVER['REMOTE_ADDR']]);
}

// Destruir la sesión
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: index.php?logout=1");
exit();
?>
