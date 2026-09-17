<?php
// usuarios.php - Gestión de Usuarios con Modal Premium
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Auto-crear columnas DNI y moodle_user_id si no existen
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN dni VARCHAR(20) DEFAULT NULL AFTER apellidos");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN moodle_user_id INT DEFAULT NULL");
} catch (PDOException $e) {}

$is_admin = has_permission([ROLE_ADMIN]);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

$success = '';
$error = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Error de seguridad (CSRF). Por favor, refresque la página e inténtelo de nuevo.";
    } else {
        // Crear Usuario
        if ($_POST['action'] == 'create') {
            if (!$is_admin) {
                $error = "Acceso denegado. Solo los administradores pueden crear usuarios.";
            } else {
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $nombre = trim($_POST['nombre']);
                $apellidos = trim($_POST['apellidos']);
                $dni = trim($_POST['dni']);
                $email = trim($_POST['email']);
                $rol_id = intval($_POST['rol_id']);
                
                if (empty($username) || empty($password) || empty($nombre) || empty($email)) {
                    $error = "Faltan campos obligatorios.";
                } else {
                    // Validar complejidad de contraseña (mínimo 8 caracteres, mayúscula, minúscula, número)
                    $complexity = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
                    if (!preg_match($complexity, $password)) {
                        $error = "La contraseña debe tener al menos 8 caracteres e incluir al menos una letra mayúscula, una letra minúscula y un número.";
                    } else {
                        try {
                            $password_hash = password_hash($password, PASSWORD_BCRYPT);
                            $centro_id = !empty($_POST['centro_id']) ? intval($_POST['centro_id']) : null;
                            
                            $stmt = $pdo->prepare("INSERT INTO usuarios (username, password_hash, nombre, apellidos, dni, email, rol_id, centro_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$username, $password_hash, $nombre, $apellidos, $dni, $email, $rol_id, $centro_id]);
                            
                            audit_log($pdo, 'USUARIO_CREADO', 'usuarios', $pdo->lastInsertId(), null, ['username' => $username, 'rol' => $rol_id]);
                            $success = "Usuario '$username' creado correctamente.";
                        } catch (PDOException $e) {
                            $error = "Error: El nombre de usuario o email ya existe.";
                        }
                    }
                }
            }
        }
    
    // Cambiar Estado (Activo/Inactivo)
    if ($_POST['action'] == 'toggle_status') {
        if (!$is_admin) {
            $error = "Acceso denegado. Solo los administradores pueden cambiar el estado de usuarios.";
        } else {
            $id = intval($_POST['user_id']);
            $status = intval($_POST['status']);
            
            // Evitar desactivarse a sí mismo
            if ($id == $_SESSION['user_id']) {
                $error = "No puedes desactivar tu propia cuenta de administrador.";
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                audit_log($pdo, 'USUARIO_STATUS_TOGGLE', 'usuarios', $id, null, ['nuevo_estado' => $status]);
                $success = "Estado de usuario actualizado.";
            }
        }
    }
    
    // Eliminar Usuario
    if ($_POST['action'] == 'delete') {
        if (!$is_admin) {
            $error = "Acceso denegado. Solo los administradores pueden eliminar usuarios.";
        } else {
            $id = intval($_POST['user_id']);
            
            // Evitar borrarse a sí mismo
            if ($id == $_SESSION['user_id']) {
                $error = "No puedes borrar tu propia cuenta de administrador.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Obtener datos del usuario antes de borrar
                    $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmtUser->execute([$id]);
                    $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

                    if (!$user_data) {
                        throw new Exception("El usuario no existe.");
                    }

                    // Archivar en la papelera
                    require_once 'includes/Papelera.php';
                    $datos = ['usuarios' => $user_data];
                    $titulo_papelera = $user_data['nombre'] . ' ' . $user_data['apellidos'] . ' (' . $user_data['username'] . ')';
                    Papelera::archivar($pdo, 'usuarios', $id, $titulo_papelera, $datos);

                    // Intentar eliminación física en BD
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $pdo->commit();
                    
                    audit_log($pdo, 'USUARIO_ELIMINADO', 'usuarios', $id, null, ['id_eliminado' => $id]);
                    $success = "Usuario enviado a la papelera correctamente.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (method_exists($e, 'getCode') && $e->getCode() == '23000') {
                        $error = "No se puede eliminar este usuario porque tiene registros de actividad asociados. En su lugar, puedes suspender su cuenta.";
                    } elseif (isset($e->errorInfo) && $e->errorInfo[0] == '23000') {
                        $error = "No se puede eliminar este usuario porque tiene registros de actividad asociados. En su lugar, puedes suspender su cuenta.";
                    } else {
                        $error = "Error al eliminar el usuario: " . $e->getMessage();
                    }
                }
            }
        }
    }
        
        // Alta o Sincronización en Moodle
        if ($_POST['action'] == 'sync_moodle') {
            if (!$is_admin) {
                $error = "Acceso denegado. Solo los administradores pueden sincronizar usuarios con Moodle.";
            } else {
                $id = intval($_POST['user_id']);
                $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmtUser->execute([$id]);
                $user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user_data) {
                    require_once 'includes/moodle_api.php';
                    try {
                        $moodle = new MoodleAPI($pdo);
                        if ($moodle->isConfigured()) {
                            $existingUser = null;
                            try {
                                $checkByEmail = $moodle->getUsersByField('email', [$user_data['email']]);
                                if (!empty($checkByEmail['users'])) {
                                    $existingUser = $checkByEmail['users'][0];
                                } else {
                                    $checkByUsername = $moodle->getUsersByField('username', [strtolower($user_data['username'])]);
                                    if (!empty($checkByUsername['users'])) {
                                        $existingUser = $checkByUsername['users'][0];
                                    }
                                }
                            } catch (Exception $ex) {}

                            if ($existingUser) {
                                $mUserId = $existingUser['id'];
                                $pdo->prepare("UPDATE usuarios SET moodle_user_id = ? WHERE id = ?")->execute([$mUserId, $id]);
                                $moodle->syncUserPictureFromMoodle($id, $mUserId);
                                audit_log($pdo, 'USUARIO_MOODLE_ALTA', 'usuarios', $id, null, ['moodle_user_id' => $mUserId, 'modo' => 'vinculado_existente']);
                                $success = "El usuario '{$user_data['username']}' ya estaba dado de alta en Moodle (ID #{$mUserId}, Email: {$user_data['email']}) y se ha sincronizado correctamente con la intranet.";
                            } else {
                                $newUsers = $moodle->createUser(
                                    strtolower($user_data['username']),
                                    'MoodleTemp123!',
                                    $user_data['nombre'],
                                    $user_data['apellidos'],
                                    $user_data['email']
                                );
                                
                                if (!empty($newUsers) && isset($newUsers[0]['id'])) {
                                    $mUserId = $newUsers[0]['id'];
                                    $pdo->prepare("UPDATE usuarios SET moodle_user_id = ? WHERE id = ?")->execute([$mUserId, $id]);
                                    $moodle->syncUserPictureFromMoodle($id, $mUserId);
                                    audit_log($pdo, 'USUARIO_MOODLE_ALTA', 'usuarios', $id, null, ['moodle_user_id' => $mUserId, 'modo' => 'creado_nuevo']);
                                    $success = "El usuario '{$user_data['username']}' ha sido dado de alta correctamente en Moodle (ID #{$mUserId}, Contraseña temporal: MoodleTemp123!).";
                                } else {
                                    $error = "No se ha podido crear el usuario en Moodle (Respuesta inesperada).";
                                }
                            }
                        } else {
                            $error = "Moodle no está configurado correctamente en el sistema.";
                        }
                    } catch (Exception $e) {
                        $error = "Error al comunicar con Moodle: " . $e->getMessage();
                    }
                } else {
                    $error = "Usuario no encontrado.";
                }
            }
        }

        // Sincronizar Todos los Usuarios con Moodle
        if ($_POST['action'] == 'sync_all_moodle') {
            if (!$is_admin) {
                $error = "Acceso denegado. Solo los administradores pueden ejecutar la sincronización masiva.";
            } else {
                require_once 'includes/moodle_api.php';
                try {
                    $moodle = new MoodleAPI($pdo);
                    if ($moodle->isConfigured()) {
                        $stmtAll = $pdo->query("SELECT * FROM usuarios WHERE activo = 1");
                        $allUsers = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
                        $linked_count = 0;

                        foreach ($allUsers as $uData) {
                            $existingUser = null;
                            try {
                                $checkByEmail = $moodle->getUsersByField('email', [$uData['email']]);
                                if (!empty($checkByEmail['users'])) {
                                    $existingUser = $checkByEmail['users'][0];
                                } else {
                                    $checkByUsername = $moodle->getUsersByField('username', [strtolower($uData['username'])]);
                                    if (!empty($checkByUsername['users'])) {
                                        $existingUser = $checkByUsername['users'][0];
                                    }
                                }
                            } catch (Exception $ex) {}

                            if ($existingUser) {
                                $mUserId = $existingUser['id'];
                                $pdo->prepare("UPDATE usuarios SET moodle_user_id = ? WHERE id = ?")->execute([$mUserId, $uData['id']]);
                                $moodle->syncUserPictureFromMoodle($uData['id'], $mUserId);
                                audit_log($pdo, 'USUARIO_MOODLE_ALTA', 'usuarios', $uData['id'], null, ['moodle_user_id' => $mUserId, 'modo' => 'vinculado_existente']);
                                $linked_count++;
                            }
                        }

                        $success = "Sincronización masiva con Moodle completada. Se han detectado y vinculado $linked_count usuario(s) existentes en Moodle.";
                    } else {
                        $error = "Moodle no está configurado correctamente en el sistema.";
                    }
                } catch (Exception $e) {
                    $error = "Error al sincronizar con Moodle: " . $e->getMessage();
                }
            }
        }

        // Suplantar Usuario (Impersonate)
        if ($_POST['action'] == 'impersonate') {
            if (!$is_admin) {
                $error = "Acceso denegado. Solo los administradores pueden suplantar usuarios.";
            } else {
                $id = intval($_POST['user_id']);
                if ($id != $_SESSION['user_id']) {
                    $_SESSION['impersonator_id'] = $_SESSION['user_id'];
                    $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ? AND u.activo = 1");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nombre_completo'] = trim($user['nombre'] . ' ' . $user['apellidos']);
                        $_SESSION['rol_id'] = $user['rol_id'];
                        $_SESSION['rol_nombre'] = $user['rol_nombre'];
                        $_SESSION['centro_id'] = $user['centro_id'] ?? null;
                        
                        audit_log($pdo, 'USUARIO_IMPERSONATE', 'usuarios', $id, null, ['impersonator' => $_SESSION['impersonator_id']]);
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "No se puede entrar a la cuenta de este usuario (no existe o está inactiva).";
                        unset($_SESSION['impersonator_id']);
                    }
                } else {
                    $error = "Ya estás usando esta cuenta.";
                }
            }
        }

        // Modificar Email de Usuario
        if ($_POST['action'] == 'update_email') {
            $user_id = intval($_POST['user_id']);
            if (!$is_admin && $user_id !== $current_user_id) {
                $error = "Solo puedes modificar tus propios datos personales.";
            } else {
                $new_email = trim($_POST['email']);
                if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Por favor, introduce una dirección de e-mail válida.";
                } else {
                    $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                    $stmtUser->execute([$user_id]);
                    $uTarget = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$uTarget) {
                        $error = "Usuario no encontrado.";
                    } else {
                        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                        $stmtCheck->execute([$new_email, $user_id]);
                        if ($stmtCheck->fetch()) {
                            $error = "El e-mail '$new_email' ya está registrado por otro usuario.";
                        } else {
                            $old_email = $uTarget['email'];
                            $stmtUpd = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                            $stmtUpd->execute([$new_email, $user_id]);
                            
                            $moodle_info = '';
                            if (!empty($uTarget['moodle_user_id'])) {
                                try {
                                    require_once 'includes/moodle_api.php';
                                    $moodle = new MoodleAPI($pdo);
                                    if ($moodle->isConfigured()) {
                                        $moodle->updateUser($uTarget['moodle_user_id'], ['email' => $new_email]);
                                        $moodle_info = " y se ha sincronizado la modificación con Moodle (ID #{$uTarget['moodle_user_id']})";
                                    }
                                } catch (Exception $mEx) {
                                    $moodle_info = " (Atención: no se pudo actualizar en Moodle: " . $mEx->getMessage() . ")";
                                }
                            }
                            
                            audit_log($pdo, 'USUARIO_EMAIL_CAMBIADO', 'usuarios', $user_id, null, ['old_email' => $old_email, 'new_email' => $new_email]);
                            $success = "El e-mail del usuario '{$uTarget['username']}' se ha actualizado de '$old_email' a '$new_email'$moodle_info.";
                        }
                    }
                }
            }
        }

        // Modificar Datos Personales Completo (Perfil)
        if ($_POST['action'] == 'update_profile') {
            $target_user_id = intval($_POST['user_id'] ?? 0);
            if (!$is_admin && $target_user_id !== $current_user_id) {
                $error = "Acceso denegado. Solo puedes modificar tus propios datos personales.";
            } else {
                $nombre = trim($_POST['nombre'] ?? '');
                $apellidos = trim($_POST['apellidos'] ?? '');
                $dni = trim($_POST['dni'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $new_password = $_POST['password'] ?? '';

                if (empty($nombre) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Por favor, introduce un nombre y un e-mail válidos.";
                } else {
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$email, $target_user_id]);
                    if ($stmtCheck->fetch()) {
                        $error = "El e-mail '$email' ya está registrado por otro usuario.";
                    } else {
                        $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                        $stmtUser->execute([$target_user_id]);
                        $uTarget = $stmtUser->fetch(PDO::FETCH_ASSOC);

                        if (!$uTarget) {
                            $error = "Usuario no encontrado.";
                        } else {
                            $pass_updated = false;
                            $pass_hash = $uTarget['password_hash'];
                            if (!empty($new_password)) {
                                $complexity = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
                                if (!preg_match($complexity, $new_password)) {
                                    $error = "La contraseña debe tener al menos 8 caracteres e incluir al menos una letra mayúscula, una letra minúscula y un número.";
                                } else {
                                    $pass_hash = password_hash($new_password, PASSWORD_BCRYPT);
                                    $pass_updated = true;
                                }
                            }

                            if (empty($error)) {
                                if ($is_admin && isset($_POST['rol_id'])) {
                                    $rol_id = intval($_POST['rol_id']);
                                    $centro_id = !empty($_POST['centro_id']) ? intval($_POST['centro_id']) : null;
                                    $stmtUpd = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, dni = ?, email = ?, password_hash = ?, rol_id = ?, centro_id = ? WHERE id = ?");
                                    $stmtUpd->execute([$nombre, $apellidos, $dni, $email, $pass_hash, $rol_id, $centro_id, $target_user_id]);
                                } else {
                                    $stmtUpd = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, dni = ?, email = ?, password_hash = ? WHERE id = ?");
                                    $stmtUpd->execute([$nombre, $apellidos, $dni, $email, $pass_hash, $target_user_id]);
                                }

                                if ($target_user_id === $current_user_id) {
                                    $_SESSION['nombre_completo'] = trim($nombre . ' ' . $apellidos);
                                    if (isset($_SESSION['email'])) $_SESSION['email'] = $email;
                                }

                                if (!empty($uTarget['moodle_user_id'])) {
                                    try {
                                        require_once 'includes/moodle_api.php';
                                        $moodle = new MoodleAPI($pdo);
                                        if ($moodle->isConfigured()) {
                                            $mData = ['email' => $email, 'firstname' => $nombre, 'lastname' => $apellidos];
                                            if ($pass_updated) {
                                                $mData['password'] = $new_password;
                                            }
                                            $moodle->updateUser($uTarget['moodle_user_id'], $mData);
                                        }
                                    } catch (Exception $mEx) {}
                                }

                                audit_log($pdo, 'USUARIO_DATOS_ACTUALIZADOS', 'usuarios', $target_user_id, null, ['user_id' => $target_user_id]);
                                $success = "Datos personales actualizados correctamente.";
                            }
                        }
                    }
                }
            }
        }

    }
}

// Listado de usuarios
if ($is_admin) {
    $stmt = $pdo->query("SELECT u.*, r.nombre as rol_nombre, c.nombre as centro_nombre 
                         FROM usuarios u 
                         JOIN roles r ON u.rol_id = r.id 
                         LEFT JOIN centros c ON u.centro_id = c.id
                         ORDER BY u.activo DESC, u.username ASC");
    $usuarios = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre, c.nombre as centro_nombre 
                          FROM usuarios u 
                          JOIN roles r ON u.rol_id = r.id 
                          LEFT JOIN centros c ON u.centro_id = c.id
                          WHERE u.id = ?");
    $stmt->execute([$current_user_id]);
    $usuarios = $stmt->fetchAll();
}

// Listado de roles para el combo (excluyendo Solo Lectura)
$roles = $pdo->query("SELECT * FROM roles WHERE id != " . ROLE_LECTURA . " ORDER BY id ASC")->fetchAll();

// Listado de centros para el combo
$centros_list = $pdo->query("SELECT id, nombre FROM centros ORDER BY nombre ASC")->fetchAll();

// Moodle Sync Data
$syncedUserIds = [];
try {
    $stmtLog = $pdo->query("SELECT DISTINCT entidad_id FROM audit_log WHERE accion = 'USUARIO_MOODLE_ALTA'");
    while ($row = $stmtLog->fetch()) {
        if (!empty($row['entidad_id'])) {
            $syncedUserIds[] = $row['entidad_id'];
        }
    }
} catch (Exception $e) {
    // Silently ignore if audit_log table query fails
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            /* Palette tailored to Intranet EFP */
            --primary-rose: #e11d48;
            --primary-rose-hover: #be123c;
            --admin-gradient: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
            --adm-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --tutor-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
            --com-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --lec-gradient: linear-gradient(135deg, #6b7280 0%, #374151 100%);
            
            --title-blue: #1e3a8a;
            --label-blue: #1e40af;
            --border-gray: #e2e8f0;
            --bg-gray: #f8fafc;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-premium: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Dashboard Stats Custom Style overrides */
        .stats-grid {
            margin-bottom: 2.5rem;
        }

        .stat-card-premium {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: background 0.3s;
        }

        .stat-card-premium.total::before { background: #3b82f6; }
        .stat-card-premium.active::before { background: #10b981; }
        .stat-card-premium.suspended::before { background: #f59e0b; }
        .stat-card-premium.admin::before { background: #f43f5e; }

        .stat-card-premium:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: #cbd5e1;
        }

        .stat-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-wrapper.blue { background: #eff6ff; color: #2563eb; }
        .stat-icon-wrapper.green { background: #ecfdf5; color: #059669; }
        .stat-icon-wrapper.amber { background: #fffbeb; color: #d97706; }
        .stat-icon-wrapper.rose { background: #fff1f2; color: #e11d48; }

        /* Search Section */
        .search-filter-card {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: border-color 0.2s;
        }

        .search-filter-card:focus-within {
            border-color: #93c5fd;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.05);
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: #94a3b8;
            pointer-events: none;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 46px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            background-color: #f8fafc;
            color: var(--text-color);
            transition: all 0.2s;
        }

        .search-input-wrapper input:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            outline: none;
        }

        /* List Section Redesign */
        .list-section-premium {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-gray);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-top: 1rem;
        }

        .section-header-premium {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header-premium h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--title-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Premium Table and Rows */
        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.925rem;
        }

        .premium-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.75px;
            border-bottom: 1px solid var(--border-gray);
        }

        .premium-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: background-color 0.2s;
        }

        .premium-table tr {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .premium-table tr:hover td {
            background-color: #eff6ff;
        }

        /* User identity & dynamic avatar cell */
        .identity-flex {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-gradient {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.05rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .avatar-admin { background: var(--admin-gradient); box-shadow: 0 4px 8px rgba(244, 63, 94, 0.25); }
        .avatar-adm { background: var(--adm-gradient); box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25); }
        .avatar-tutor { background: var(--tutor-gradient); box-shadow: 0 4px 8px rgba(16, 185, 129, 0.25); }
        .avatar-com { background: var(--com-gradient); box-shadow: 0 4px 8px rgba(245, 158, 11, 0.25); }
        .avatar-lec { background: var(--lec-gradient); box-shadow: 0 4px 8px rgba(107, 114, 128, 0.25); }

        .user-info-text {
            display: flex;
            flex-direction: column;
        }

        .user-info-text .username {
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.975rem;
        }

        .user-info-text .email {
            font-size: 0.775rem;
            color: var(--text-muted);
            margin-top: 1px;
        }

        /* Badges */
        .badge-premium-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.725rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
        }

        .badge-admin { background: #ffe4e6; color: #be123c; border: 1px solid #fecdd3; }
        .badge-adm { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-jefe { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
        .badge-tutor { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-comercial { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-default { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Pulsing Status Dot & Badge */
        .status-badge-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .status-dot-pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            position: relative;
        }

        .status-dot-pulse.active {
            background: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            animation: pulseEmerald 2s infinite;
        }

        .status-dot-pulse.inactive {
            background: #94a3b8;
            box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.1);
        }

        @keyframes pulseEmerald {
            0% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0); }
        }

        /* Action Buttons */
        .btn-action-premium {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.775rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .btn-action-premium.profile {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .btn-action-premium.profile:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.15);
        }

        .btn-action-premium.suspend {
            background: #fff5f5;
            color: #e11d48;
            border-color: #fecdd3;
        }

        .btn-action-premium.suspend:hover {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(225, 29, 72, 0.15);
        }

        .btn-action-premium.activate {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }

        .btn-action-premium.activate:hover {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(22, 163, 74, 0.15);
        }

        .btn-action-premium.delete-btn {
            background: #fff1f2;
            color: #e11d48;
            border-color: #fecdd3;
        }

        .btn-action-premium.delete-btn:hover {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(225, 29, 72, 0.15);
        }

        /* Premium Modal Design */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(12px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
            transition: opacity 0.3s ease;
        }

        .modal-container {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: 20px;
            box-shadow: var(--shadow-premium);
            overflow: hidden;
            border: 1px solid var(--border-gray);
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-overlay.open .modal-container {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            padding: 24px 28px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--title-blue);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-close {
            background: #e2e8f0;
            border: none;
            color: #475569;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #cbd5e1;
            color: #0f172a;
        }

        .modal-body {
            padding: 28px;
            overflow-y: auto;
        }

        .premium-field {
            margin-bottom: 1.25rem;
        }

        .premium-field label {
            display: block;
            font-weight: 700;
            color: var(--label-blue);
            font-size: 0.725rem;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.75px;
        }

        .premium-field input, .premium-field select {
            width: 100%;
            padding: 11px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.925rem;
            transition: all 0.2s;
            box-sizing: border-box;
            background-color: #ffffff;
            color: var(--text-color);
        }

        .premium-field input:focus, .premium-field select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn-create-premium {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            width: 100%;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            letter-spacing: 0.75px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(30, 64, 175, 0.15);
            margin-top: 0.5rem;
        }

        .btn-create-premium:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(30, 64, 175, 0.25);
        }

        /* Custom Alert styling */
        .premium-alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            border-left: 6px solid transparent;
        }
        .premium-alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; border: 1px solid #a7f3d0; }
        .premium-alert-error { background: #fff1f2; color: #991b1b; border-left-color: #ef4444; border: 1px solid #fecdd3; }
        .premium-alert svg { flex-shrink: 0; }

        /* ==========================================================================
           RESPONSIVE MOBILE STYLES
           ========================================================================== */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1rem !important;
                margin-bottom: 1.5rem !important;
            }
        }

        @media (max-width: 768px) {
            .app-container {
                max-width: 100vw !important;
                overflow-x: hidden !important;
            }

            .main-content {
                padding: 1rem 0.75rem !important;
                max-width: 100vw !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }

            .page-header {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 1rem !important;
                margin-bottom: 1.25rem !important;
            }

            .page-header .page-title h1 {
                font-size: 1.4rem !important;
                margin-bottom: 0.25rem !important;
            }

            .page-header .page-title p {
                font-size: 0.8rem !important;
                color: #64748b !important;
            }

            .page-header button.btn-primary {
                width: 100% !important;
                justify-content: center !important;
                padding: 12px 16px !important;
                font-size: 0.9rem !important;
                border-radius: 10px !important;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.65rem !important;
                margin-bottom: 1.25rem !important;
            }

            .stat-card-premium {
                padding: 0.9rem !important;
                gap: 0.75rem !important;
                border-radius: 12px !important;
            }

            .stat-icon-wrapper {
                width: 38px !important;
                height: 38px !important;
                border-radius: 10px !important;
            }

            .stat-icon-wrapper svg {
                width: 20px !important;
                height: 20px !important;
            }

            .stat-info .stat-value {
                font-size: 1.35rem !important;
            }

            .stat-info .stat-label {
                font-size: 0.72rem !important;
            }

            .search-filter-card {
                padding: 0.75rem 1rem !important;
                margin-bottom: 1.25rem !important;
                border-radius: 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .search-input-wrapper input {
                font-size: 0.85rem !important;
                padding: 10px 14px 10px 40px !important;
            }

            .search-icon {
                left: 12px !important;
                width: 18px !important;
                height: 18px !important;
            }

            .list-section-premium {
                border-radius: 12px !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                margin-top: 0.5rem !important;
            }

            .section-header-premium {
                background: #ffffff !important;
                border: 1px solid var(--border-gray) !important;
                border-radius: 12px !important;
                padding: 12px 16px !important;
                margin-bottom: 10px !important;
            }

            .section-header-premium h2 {
                font-size: 0.95rem !important;
            }

            /* Convert Table Rows into Mobile Responsive Cards */
            .premium-table {
                display: block !important;
                width: 100% !important;
            }

            .premium-table thead {
                display: none !important;
            }

            .premium-table tbody {
                display: flex !important;
                flex-direction: column !important;
                gap: 12px !important;
                width: 100% !important;
            }

            .premium-table tr.user-row-item {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
                background: #ffffff !important;
                border: 1px solid var(--border-gray) !important;
                border-radius: 14px !important;
                padding: 14px 16px !important;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
            }

            .premium-table tr.user-row-item:hover td {
                background: transparent !important;
            }

            .premium-table tr.user-row-item td {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 2px 0 !important;
                border: none !important;
                background: transparent !important;
                width: 100% !important;
                font-size: 0.85rem !important;
                box-sizing: border-box !important;
            }

            /* Cell 1: Identity & Avatar */
            .premium-table tr.user-row-item td:nth-child(1) {
                border-bottom: 1px solid #f1f5f9 !important;
                padding-bottom: 10px !important;
                margin-bottom: 4px !important;
                justify-content: flex-start !important;
            }

            .identity-flex {
                width: 100% !important;
                gap: 0.75rem !important;
            }

            .user-avatar-gradient {
                width: 42px !important;
                height: 42px !important;
                font-size: 0.95rem !important;
            }

            .user-info-text .username {
                font-size: 0.95rem !important;
                color: #1e3a8a !important;
            }

            .user-info-text .email {
                font-size: 0.75rem !important;
                word-break: break-all !important;
            }

            /* Cell 2: Name */
            .premium-table tr.user-row-item td:nth-child(2)::before {
                content: 'Nombre:';
                font-weight: 700;
                color: #64748b;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Cell 3: Role */
            .premium-table tr.user-row-item td:nth-child(3)::before {
                content: 'Rol:';
                font-weight: 700;
                color: #64748b;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Cell 4: Centro */
            .premium-table tr.user-row-item td:nth-child(4)::before {
                content: 'Sede:';
                font-weight: 700;
                color: #64748b;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Cell 5: Estado */
            .premium-table tr.user-row-item td:nth-child(5)::before {
                content: 'Estado:';
                font-weight: 700;
                color: #64748b;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Cell 6: Acciones */
            .premium-table tr.user-row-item td:nth-child(6) {
                border-top: 1px solid #f1f5f9 !important;
                padding-top: 10px !important;
                margin-top: 6px !important;
                display: block !important;
                width: 100% !important;
            }

            .premium-table tr.user-row-item td:nth-child(6) > div {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 6px !important;
                justify-content: flex-start !important;
                width: 100% !important;
            }

            .btn-action-premium {
                padding: 7px 11px !important;
                font-size: 0.72rem !important;
                flex-grow: 1 !important;
                justify-content: center !important;
                border-radius: 6px !important;
            }

            /* Modal Responsive */
            .modal-overlay {
                padding: 10px !important;
            }

            .modal-container {
                width: 100% !important;
                max-width: 100% !important;
                max-height: 94vh !important;
                border-radius: 14px !important;
            }

            .modal-header {
                padding: 16px 20px !important;
            }

            .modal-header h2 {
                font-size: 1.05rem !important;
            }

            .modal-body {
                padding: 16px 20px !important;
            }

            .premium-field {
                margin-bottom: 1rem !important;
            }

            .premium-field input, .premium-field select {
                padding: 9px 12px !important;
                font-size: 0.88rem !important;
            }

            .btn-create-premium {
                padding: 12px !important;
                font-size: 0.88rem !important;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 0.5rem !important;
            }
            .stat-card-premium {
                padding: 0.75rem 0.6rem !important;
                gap: 0.5rem !important;
            }
            .stat-icon-wrapper {
                width: 32px !important;
                height: 32px !important;
            }
            .stat-info .stat-value {
                font-size: 1.2rem !important;
            }
            .stat-info .stat-label {
                font-size: 0.65rem !important;
            }
            .btn-action-premium {
                font-size: 0.68rem !important;
                padding: 6px 8px !important;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1><?= $is_admin ? 'Usuarios y Permisos' : 'Mis Datos Personales' ?></h1>
                <p><?= $is_admin ? 'Administración del acceso y control de seguridad corporativo (ISO 27001)' : 'Gestión de tu perfil y credenciales de acceso a la Intranet' ?></p>
            </div>
            <?php if ($is_admin): ?>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Escanear y vincular todos los usuarios de la intranet que ya existen en Moodle?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="sync_all_moodle">
                    <button type="submit" class="btn" style="border-radius: 10px; padding: 11px 18px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        Sincronizar Todo con Moodle
                    </button>
                </form>
                <button class="btn btn-primary" onclick="openModal()" style="border-radius: 10px; padding: 11px 20px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nuevo Usuario
                </button>
            </div>
            <?php endif; ?>
        </header>

        <?php if ($success): ?>
            <div class="premium-alert premium-alert-success">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="premium-alert premium-alert-error">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$is_admin): ?>
            <?php $my_u = $usuarios[0] ?? null; ?>
            <?php if ($my_u): ?>
            <section style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-md); max-width: 800px; margin: 0 auto;">
                <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                    <div class="user-avatar-gradient avatar-adm" style="width: 64px; height: 64px; font-size: 1.5rem; border-radius: 16px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($my_u['foto']) && file_exists(__DIR__ . '/' . $my_u['foto'])): ?>
                            <img src="<?= htmlspecialchars($my_u['foto']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($my_u['nombre'], 0, 1) . substr($my_u['apellidos'] ?: $my_u['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 style="margin: 0 0 0.25rem 0; color: #1e3a8a; font-size: 1.35rem; font-weight: 700;">
                            <?= htmlspecialchars($my_u['nombre'] . ' ' . $my_u['apellidos']) ?>
                        </h2>
                        <p style="margin: 0; color: #64748b; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span>Usuario: <strong><?= htmlspecialchars($my_u['username']) ?></strong></span>
                            <span>•</span>
                            <span>Rol: <span class="badge-premium-pill badge-adm"><?= htmlspecialchars($my_u['rol_nombre']) ?></span></span>
                            <?php if (!empty($my_u['centro_nombre'])): ?>
                                <span>•</span>
                                <span>Sede: <strong><?= htmlspecialchars($my_u['centro_nombre']) ?></strong></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="user_id" value="<?= $my_u['id'] ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="premium-field">
                            <label>Nombre</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($my_u['nombre']) ?>" required>
                        </div>
                        <div class="premium-field">
                            <label>Apellidos</label>
                            <input type="text" name="apellidos" value="<?= htmlspecialchars($my_u['apellidos']) ?>">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div class="premium-field">
                            <label>DNI / NIF</label>
                            <input type="text" name="dni" value="<?= htmlspecialchars($my_u['dni'] ?? '') ?>" placeholder="12345678Z">
                        </div>
                        <div class="premium-field">
                            <label>E-mail Corporativo</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($my_u['email']) ?>" required>
                        </div>
                    </div>

                    <div class="premium-field" style="margin-top: 0.5rem;">
                        <label>Cambiar Contraseña (dejar en blanco para mantener la actual)</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="password" name="password" id="profile_password" placeholder="Nueva contraseña de acceso" style="flex: 1;">
                            <button type="button" class="btn-outline" onclick="toggleProfilePassword()" style="padding: 0.65rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer;" title="Mostrar/Ocultar contraseña">👁️</button>
                            <button type="button" class="btn-outline" onclick="generateProfilePassword()" style="padding: 0.65rem 1rem; border-radius: 8px; border: 1px solid #2563eb; background: #2563eb; color: white; font-weight: 600; cursor: pointer;" title="Generar contraseña segura">🔑 Generar</button>
                        </div>
                        <small style="color: #64748b; font-size: 0.775rem; margin-top: 4px; display: block;">Mínimo 12 caracteres con mayúsculas, minúsculas, números y caracteres especiales (@, $, !, %, *, ?, &, #).</small>
                    </div>

                    <div style="margin-top: 2rem; text-align: right;">
                        <button type="submit" class="btn-create-premium" style="width: auto; padding: 12px 28px; display: inline-flex; align-items: center; gap: 8px; border-radius: 10px;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Guardar Mis Datos Personales
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>
        <?php else: ?>

        <!-- CALCULATE DASHBOARD COUNTS -->
        <?php
        $total_users = count($usuarios);
        $active_users = 0;
        $suspended_users = 0;
        $admin_users = 0;
        foreach ($usuarios as $u) {
            if ($u['activo']) {
                $active_users++;
            } else {
                $suspended_users++;
            }
            if ($u['rol_id'] == ROLE_ADMIN) {
                $admin_users++;
            }
        }
        ?>

        <!-- DASHBOARD STATS SECTION -->
        <section class="stats-grid">
            <div class="stat-card-premium total">
                <div class="stat-icon-wrapper blue">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-label">Personal Total</div>
                </div>
            </div>
            
            <div class="stat-card-premium active">
                <div class="stat-icon-wrapper green">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $active_users ?></div>
                    <div class="stat-label">Habilitados</div>
                </div>
            </div>

            <div class="stat-card-premium suspended">
                <div class="stat-icon-wrapper amber">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $suspended_users ?></div>
                    <div class="stat-label">Suspendidos</div>
                </div>
            </div>

            <div class="stat-card-premium admin">
                <div class="stat-icon-wrapper rose">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $admin_users ?></div>
                    <div class="stat-label">Administradores</div>
                </div>
            </div>
        </section>

        <!-- INTERACTIVE LIVE FILTER SEARCH BAR -->
        <section class="search-filter-card">
            <div class="search-input-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="userSearchInput" placeholder="Buscar personal por nombre, usuario, email o rol en tiempo real..." onkeyup="filterUsers()">
            </div>
        </section>

        <!-- TABLE SECTION -->
        <section class="list-section-premium">
            <div class="section-header-premium">
                <h2>Listado de Personal Registrado</h2>
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); background: #f1f5f9; padding: 6px 14px; border-radius: 8px;" id="userCounter">
                    <?= $total_users ?> usuarios
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="premium-table" id="usersTable">
                    <thead>
                    <tr>
                        <th>Identidad Acceso</th>
                        <th>Nombre y Apellidos</th>
                        <th>Nivel de Acceso</th>
                        <th>Sede / Centro</th>
                        <th>Estado Actual</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <?php
                    // Dynamic class selection for background gradients based on role
                    $avatar_class = 'avatar-lec';
                    $badge_class = 'badge-default';
                    switch ($u['rol_id']) {
                        case ROLE_ADMIN:
                            $avatar_class = 'avatar-admin';
                            $badge_class = 'badge-admin';
                            break;
                        case ROLE_COORD:
                            $avatar_class = 'avatar-adm';
                            $badge_class = 'badge-adm';
                            break;
                        case ROLE_ADMINISTRATIVO:
                            $avatar_class = 'avatar-adm';
                            $badge_class = 'badge-adm';
                            break;
                        case ROLE_TUTOR:
                            $avatar_class = 'avatar-tutor';
                            $badge_class = 'badge-tutor';
                            break;
                        case ROLE_JEFE_COMERCIAL:
                            $avatar_class = 'avatar-com';
                            $badge_class = 'badge-jefe'; // Purple distinct badge
                            break;
                        case ROLE_COMERCIAL:
                            $avatar_class = 'avatar-com';
                            $badge_class = 'badge-comercial';
                            break;
                    }
                    
                    // Initials for avatar
                    $iniciales = strtoupper(substr($u['nombre'], 0, 1) . substr($u['apellidos'] ?: $u['username'], 0, 1));
                    ?>
                    <tr class="user-row-item">
                        <td>
                            <div class="identity-flex">
                                <div class="user-avatar-gradient <?= $avatar_class ?>" style="overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($u['foto']) && file_exists(__DIR__ . '/' . $u['foto'])): ?>
                                        <img src="<?= htmlspecialchars($u['foto']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?= $iniciales ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-info-text">
                                    <span class="username"><?= htmlspecialchars($u['username']) ?></span>
                                    <span class="email" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <?= htmlspecialchars($u['email']) ?>
                                        <button type="button" onclick="openEditEmailModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>')" style="background: none; border: none; padding: 2px 4px; cursor: pointer; color: #64748b; border-radius: 4px;" title="Editar Email de <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>">
                                            <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                        <td>
                            <span class="badge-premium-pill <?= $badge_class ?>">
                                <?= htmlspecialchars($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['centro_nombre'] ?? 'Global / Sin Asignar') ?>
                        </td>
                        <td>
                            <div class="status-badge-premium">
                                <span class="status-dot-pulse <?= $u['activo'] ? 'active' : 'inactive' ?>"></span>
                                <span style="color: <?= $u['activo'] ? '#059669' : '#64748b' ?>;">
                                    <?= $u['activo'] ? 'Habilitado' : 'Suspendido' ?>
                                </span>
                            </div>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                <a href="ficha_trabajador.php?id=<?= $u['id'] ?>" class="btn-action-premium profile">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    Perfil
                                </a>
                                <button type="button" class="btn-action-premium" style="background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;" onclick="openSendUserKeysModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>')" title="Enviar o restablecer claves de acceso por email">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                                    Enviar Claves
                                </button>
                                <?php if ($u['activo']): ?>
                                    <?php if (in_array($u['id'], $syncedUserIds) || !empty($u['moodle_user_id'])): ?>
                                        <button type="button" class="btn-action-premium" style="background: #ecfdf5; color: #059669; border-color: #a7f3d0; cursor: default;" title="Usuario dado de alta o sincronizado con Moodle (ID #<?= htmlspecialchars($u['moodle_user_id'] ?? '') ?>)">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                            Sincronizado Moodle
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Sincronizar este usuario con Moodle? Se comprobará si ya existe en Moodle para vincularlo o darlo de alta.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                            <input type="hidden" name="action" value="sync_moodle">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-action-premium" style="background: #fff8e1; color: #d97706; border-color: #fde68a;" title="Sincronizar o dar de alta en Moodle">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2.12-1.15V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
                                                Sincronizar Moodle
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que desea cambiar el estado de este usuario?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['activo'] ? '0' : '1' ?>">
                                    
                                    <?php if ($u['activo']): ?>
                                        <button type="submit" class="btn-action-premium suspend">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
                                            Suspender
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn-action-premium activate">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                            Activar
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que deseas entrar en la cuenta de este usuario?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="impersonate">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-action-premium" style="background: #fdf4ff; color: #a21caf; border-color: #f5d0fe;">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                            Entrar como
                                        </button>
                                    </form>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('¿Seguro que desea eliminar de forma permanente a este usuario? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-action-premium delete-btn">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                            Borrar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            
            <div id="noResults" style="display: none; padding: 3rem; text-align: center; color: #64748b; font-weight: 500;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" style="margin: 0 auto 15px auto; color: #cbd5e1;"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                No se encontraron usuarios que coincidan con la búsqueda.
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL ALTA USUARIO -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Alta de Nuevo Usuario</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="premium-field">
                    <label>Nombre de Usuario (Log-in)</label>
                    <input type="text" name="username" placeholder="p.ej. j.garcia" required>
                </div>
                
                <div class="premium-field">
                    <label>Contraseña Provisional</label>
                    <div style="display: flex; gap: 10px; align-items: center; position: relative;">
                        <input type="password" name="password" id="user_password" placeholder="Mínimo 8 caracteres" style="flex: 1; padding: 0.75rem;" required>
                        <button type="button" class="btn-outline" onclick="togglePasswordVisibility()" style="padding: 0.6rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; background: white; border: 1px solid #cbd5e1; color: #475569;" title="Mostrar/Ocultar contraseña">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                        <button type="button" class="btn-outline" onclick="generateSecurePassword()" style="padding: 0.6rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; background: #2563eb; border: 1px solid #2563eb; color: white;" title="Generar contraseña segura">
                            🔑 Generar
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="premium-field">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="premium-field">
                        <label>Apellidos</label>
                        <input type="text" name="apellidos">
                    </div>
                </div>
                
                <div class="premium-field">
                    <label>DNI / NIF</label>
                    <input type="text" name="dni" placeholder="12345678Z">
                </div>
                
                <div class="premium-field">
                    <label>E-mail Corporativo</label>
                    <input type="email" name="email" placeholder="usuario@grupoefp.es" required>
                </div>
                
                <div class="premium-field">
                    <label>Rol y Atribuciones de Acceso</label>
                    <select name="rol_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="premium-field">
                    <label>Sede / Centro (Opcional)</label>
                    <select name="centro_id">
                        <option value="">-- Todas (Global / Administrador) --</option>
                        <?php foreach ($centros_list as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-create-premium">Generar Credenciales de Acceso</button>
                <p style="font-size: 0.725rem; color: var(--text-muted); text-align: center; margin-top: 15px; font-weight: 500; line-height: 1.4;">
                    🔒 ISO 27001: El usuario será notificado tras la creación exitosa siguiendo los protocolos de seguridad de la información corporativa.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    // Live client-side user search and filtering
    function filterUsers() {
        const query = document.getElementById('userSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#usersTable tbody tr.user-row-item');
        const noResults = document.getElementById('noResults');
        const table = document.querySelector('.premium-table');
        const counter = document.getElementById('userCounter');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update matches counter dynamically
        counter.textContent = `${visibleCount} de ${rows.length} usuarios`;

        if (visibleCount === 0) {
            table.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            table.style.display = 'table';
            noResults.style.display = 'none';
        }
    }

    // Modal Operations with smooth backdrop scaling transitions
    function openModal() {
        const overlay = document.getElementById('modalOverlay');
        overlay.style.display = 'flex';
        // Force reflow
        overlay.offsetHeight;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden'; 
    }

    function closeModal() {
        const overlay = document.getElementById('modalOverlay');
        overlay.classList.remove('open');
        setTimeout(() => {
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }, 300);
    }

    // Close when clicking outside modal box
    window.onclick = function(event) {
        const overlay = document.getElementById('modalOverlay');
        if (event.target == overlay) {
            closeModal();
        }
    }

    // Mostrar/Ocultar contraseña
    function togglePasswordVisibility() {
        const input = document.getElementById('user_password');
        const icon = document.getElementById('togglePasswordIcon');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    // Generar contraseña segura y válida según requisitos de complejidad (mínimo 12 caracteres, especiales: @$!%*?&#)
    function generateSecurePassword() {
        const uppers = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lowers = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const specials = "@$!%*?&#";
        const all = uppers + lowers + numbers + specials;
        
        let password = "";
        password += uppers.charAt(Math.floor(Math.random() * uppers.length));
        password += lowers.charAt(Math.floor(Math.random() * lowers.length));
        password += numbers.charAt(Math.floor(Math.random() * numbers.length));
        password += specials.charAt(Math.floor(Math.random() * specials.length));
        
        // Generar el resto de caracteres (12 en total)
        for (let i = 0; i < 8; i++) {
            password += all.charAt(Math.floor(Math.random() * all.length));
        }
        
        // Mezclar los caracteres
        password = password.split('').sort(() => 0.5 - Math.random()).join('');
        
        const input = document.getElementById('user_password');
        input.value = password;
        
        // Mostrar la contraseña en texto plano para que el usuario la vea y la copie
        input.type = 'text';
        const icon = document.getElementById('togglePasswordIcon');
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
</script>

<!-- MODAL EDITAR EMAIL -->
<div class="modal-overlay" id="modalEditEmailOverlay">
    <div class="modal-container" style="max-width: 480px;">
        <div class="modal-header" style="background: var(--admin-gradient); color: white; border-radius: 12px 12px 0 0; padding: 1.25rem 1.5rem;">
            <h2 style="color: white; font-size: 1.15rem; margin: 0;">Modificar E-mail de Usuario</h2>
            <button class="modal-close" onclick="closeEditEmailModal()" style="color: white; opacity: 0.8;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="update_email">
                <input type="hidden" name="user_id" id="editEmailUserId">
                
                <p style="color: #475569; font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.4;">
                    Estás cambiando el e-mail del usuario <strong id="editEmailUsername" style="color: #1e293b;"></strong>.<br>
                    <small style="color: #64748b;">Si el usuario está sincronizado con Moodle, la dirección se actualizará en la plataforma también.</small>
                </p>

                <div class="premium-field" style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 600; color: #1e3a8a; margin-bottom: 0.5rem; font-size: 0.88rem;">Nuevo E-mail</label>
                    <input type="email" name="email" id="editEmailInput" placeholder="correo@ejemplo.com" required style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 0.95rem; box-sizing: border-box;">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeEditEmailModal()" class="btn-action-premium" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-action-premium" style="background: var(--admin-gradient); color: white; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">Guardar E-mail</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditEmailModal(userId, username, email) {
        document.getElementById('editEmailUserId').value = userId;
        document.getElementById('editEmailUsername').textContent = username;
        document.getElementById('editEmailInput').value = email;
        document.getElementById('modalEditEmailOverlay').classList.add('open');
    }

    function closeEditEmailModal() {
        document.getElementById('modalEditEmailOverlay').classList.remove('open');
    }

    function toggleProfilePassword() {
        const input = document.getElementById('profile_password');
        if (input) {
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    }

    function generateProfilePassword() {
        const uppers = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lowers = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const specials = "@$!%*?&#";
        const all = uppers + lowers + numbers + specials;
        
        let password = "";
        password += uppers.charAt(Math.floor(Math.random() * uppers.length));
        password += lowers.charAt(Math.floor(Math.random() * lowers.length));
        password += numbers.charAt(Math.floor(Math.random() * numbers.length));
        password += specials.charAt(Math.floor(Math.random() * specials.length));
        
        for (let i = 0; i < 8; i++) {
            password += all.charAt(Math.floor(Math.random() * all.length));
        }
        
        password = password.split('').sort(() => 0.5 - Math.random()).join('');
        
        const input = document.getElementById('profile_password');
        if (input) {
            input.value = password;
            input.type = 'text';
        }
    }

    // Modal Enviar Claves de Usuario
    let currentSendKeysUser = { id: 0, username: '', nombre: '', email: '' };

    function openSendUserKeysModal(id, username, nombre, email) {
        currentSendKeysUser = { id, username, nombre, email };
        document.getElementById('sendUserKeysId').value = id;
        document.getElementById('sendUserKeysEmail').value = email;
        document.getElementById('sendUserKeysUsername').value = username;
        document.getElementById('sendUserKeysError').style.display = 'none';

        generateSendUserKeysPassword();

        const overlay = document.getElementById('modalSendUserKeysOverlay');
        overlay.style.display = 'flex';
        overlay.offsetHeight;
        overlay.style.opacity = '1';
    }

    function closeSendUserKeysModal() {
        const overlay = document.getElementById('modalSendUserKeysOverlay');
        overlay.style.opacity = '0';
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 250);
    }

    function generateSendUserKeysPassword() {
        const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
        let randomPart = "";
        for (let i = 0; i < 6; i++) {
            randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const pass = "Efp" + (Math.floor(100 + Math.random() * 900)) + randomPart + "!";
        document.getElementById('sendUserKeysPassword').value = pass;
        updateSendUserKeysEmailBody();
    }

    function updateSendUserKeysEmailBody() {
        const passVal = document.getElementById('sendUserKeysPassword').value.trim();
        const passwordText = passVal ? passVal : '[Pendiente de indicar]';
        const bodyText = `Hola ${currentSendKeysUser.nombre || currentSendKeysUser.username},

Te facilitamos tus credenciales para acceder a la Intranet de Grupo EFP:

Dirección de acceso: https://gestion.grupoefp.es/
Usuario: ${currentSendKeysUser.username}  (o con su email: ${currentSendKeysUser.email})
Contraseña: ${passwordText}

Por favor, guarde estos datos en un lugar seguro. Puede acceder tanto con su nombre de usuario como con su dirección de correo electrónico.

Un saludo,
El equipo de administración.`;

        document.getElementById('sendUserKeysBody').value = bodyText;
    }

    function submitSendUserKeys(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSendUserKeysSubmit');
        const errDiv = document.getElementById('sendUserKeysError');

        btn.disabled = true;
        btn.innerHTML = '⏳ Enviando...';
        errDiv.style.display = 'none';

        const formData = new FormData(document.getElementById('formSendUserKeys'));

        fetch('api_send_trabajador_keys.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('¡Claves actualizadas y enviadas correctamente por correo electrónico!');
                closeSendUserKeysModal();
            } else {
                errDiv.textContent = data.error || 'Ocurrió un error al enviar.';
                errDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '🚀 Enviar Claves por Correo';
            }
        })
        .catch(error => {
            errDiv.textContent = 'Error de conexión con el servidor.';
            errDiv.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '🚀 Enviar Claves por Correo';
        });
    }
</script>

<!-- MODAL ENVIAR CLAVES USUARIO -->
<div class="modal-overlay" id="modalSendUserKeysOverlay" style="display: none; opacity: 0; transition: opacity 0.25s ease; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal-container" style="background: white; border-radius: 16px; width: 100%; max-width: 620px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <h2 style="margin: 0; font-size: 1.2rem; color: #1e3a8a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                🔑 Enviar Claves de Acceso por Correo
            </h2>
            <button type="button" onclick="closeSendUserKeysModal()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">✕</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div id="sendUserKeysError" style="display: none; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;"></div>

            <form id="formSendUserKeys" onsubmit="submitSendUserKeys(event)">
                <input type="hidden" name="trabajador_id" id="sendUserKeysId">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 4px; font-size: 0.85rem;">Destinatario (Email)</label>
                        <input type="email" name="email" id="sendUserKeysEmail" required style="width: 100%; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 4px; font-size: 0.85rem;">Usuario (Log-in)</label>
                        <input type="text" id="sendUserKeysUsername" disabled style="width: 100%; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; background: #f1f5f9; color: #64748b; box-sizing: border-box;">
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 700; color: #1e3a8a; margin-bottom: 4px; font-size: 0.85rem;">Contraseña de Acceso (se guardará en BD y se enviará)</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" name="password" id="sendUserKeysPassword" required style="flex: 1; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 0.95rem; font-weight: 600; color: #1e293b; box-sizing: border-box;" oninput="updateSendUserKeysEmailBody()">
                        <button type="button" onclick="generateSendUserKeysPassword()" style="padding: 8px 14px; border-radius: 6px; background: #2563eb; color: white; border: 1px solid #2563eb; cursor: pointer; font-weight: 700; font-size: 0.85rem; white-space: nowrap;">
                            🔑 Generar
                        </button>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 4px; font-size: 0.85rem;">Asunto</label>
                    <input type="text" name="subject" value="Claves de acceso a la Intranet - Grupo EFP" required style="width: 100%; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 4px; font-size: 0.85rem;">Contenido del Correo</label>
                    <textarea name="body" id="sendUserKeysBody" rows="7" required style="width: 100%; border: 1px solid #cbd5e1; padding: 10px 12px; border-radius: 6px; font-size: 0.875rem; font-family: monospace; resize: vertical; box-sizing: border-box;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: 1rem;">
                    <button type="button" onclick="closeSendUserKeysModal()" style="padding: 8px 16px; border-radius: 6px; background: white; border: 1px solid #cbd5e1; color: #475569; cursor: pointer; font-weight: 600;">Cancelar</button>
                    <button type="submit" id="btnSendUserKeysSubmit" style="padding: 8px 24px; border-radius: 6px; background: #0284c7; border: 1px solid #0284c7; color: white; cursor: pointer; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                        🚀 Enviar Claves por Correo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
