<?php
// includes/moodle_api.php

class MoodleAPI {
    private $url;
    private $token;
    
    public function __construct($pdo) {
        // Cargar configuración de DB
        $stmt = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('moodle_url', 'moodle_token')");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['clave']] = $row['valor'];
        }
        
        $moodleUrl = $config['moodle_url'] ?? '';
        $moodleToken = $config['moodle_token'] ?? '';
        
        // Sobrescribir dinámicamente según el entorno para evitar errores de copia de Base de Datos
        if (defined('MOODLE_URL_OVERRIDE') && MOODLE_URL_OVERRIDE !== '') {
            $moodleUrl = MOODLE_URL_OVERRIDE;
        }
        if (defined('MOODLE_TOKEN_OVERRIDE') && MOODLE_TOKEN_OVERRIDE !== '') {
            $moodleToken = MOODLE_TOKEN_OVERRIDE;
        }
        
        // Formato URL correcto para REST
        $cleanUrl = rtrim($moodleUrl, '/');
        // Eliminar si el usuario pegó la URL de la página de tokens por error
        $cleanUrl = str_replace('/admin/webservice/tokens.php', '', $cleanUrl);
        
        $this->url = $cleanUrl !== '' ? $cleanUrl . '/webservice/rest/server.php' : '';
        $this->token = $moodleToken;
    }
    
    public function isConfigured() {
        return !empty($this->url) && !empty($this->token) && strpos($this->url, 'http') === 0;
    }
    
    private function call($functionName, $params = []) {
        if (!$this->isConfigured()) {
            throw new Exception("Moodle no está configurado (URL o Token faltante).");
        }
        
        $serverurl = $this->url . '?wstoken=' . $this->token . '&wsfunction=' . $functionName . '&moodlewsrestformat=json';
        
        $ch = curl_init($serverurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // En producción se debe verificar el SSL, desactivamos para entorno de pruebas/locales a veces problemáticos
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Seguir redirecciones (ej: http -> https)
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Error de conexión cURL: " . $error . " (URL: " . $serverurl . ")");
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $extra = "";
            if (strpos($response, '<title>Redireccionar</title>') !== false || strpos($response, 'Redirect') !== false) {
                $extra = " <strong>Sugerencia: Revisa si la URL de Moodle en Configuración es correcta y usa https://.</strong>";
            }
            throw new Exception("Error al decodificar respuesta JSON de Moodle en la URL: " . $serverurl . ". Posible página de redirección HTML recibida." . $extra);
        }
        
        if (isset($result['exception'])) {
            $msg = $result['message'] ?? $result['exception'];
            if (!empty($result['debuginfo'])) {
                $msg .= " (Detalles: " . $result['debuginfo'] . ")";
            }
            throw new Exception("Moodle API Error (" . $functionName . "): " . $msg);
        }
        
        if ($result === null) {
            if (trim($response) === 'null') {
                return true; // Es una respuesta exitosa tipo void (sin retorno) de Moodle
            }
            throw new Exception("Moodle devolvió una respuesta vacía o nula.");
        }
        
        return $result;
    }
    
    // ==========================================
    // FUNCIONES PRINCIPALES
    // ==========================================
    
    /**
     * Obtener información del sitio y funciones permitidas para el token
     */
    public function getSiteInfo() {
        return $this->call('core_webservice_get_site_info');
    }
    
    /**
     * Obtener lista de todos los cursos
     */
    public function getCourses() {
        // En Moodle, pasando parámetros vacíos suele retornar todos si eres admin
        return $this->call('core_course_get_courses', []);
    }

    /**
     * Verificar si un curso existe por su ID de Moodle (comprueba BD de Moodle primero)
     */
    public function courseExists($courseId) {
        require_once __DIR__ . '/moodle_db.php';
        $moodleDb = new MoodleDB();
        if ($moodleDb->isConnected()) {
            try {
                $mpdo = $moodleDb->getPDO();
                $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                $stmt = $mpdo->prepare("SELECT id FROM {$prefix}course WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$courseId]);
                return (bool)$stmt->fetchColumn();
            } catch (Exception $e) {
                // Fallback
            }
        }

        try {
            $res = $this->call('core_course_get_courses_by_field', ['field' => 'id', 'value' => (int)$courseId]);
            return !empty($res['courses']);
        } catch (Exception $e) {
            // Si la API falla pero no pudimos conectar a la BD, asumimos true para no borrarlo localmente
            return true;
        }
    }

    /**
     * Obtener todos los usuarios (requiere permiso de admin en el token)
     */
    public function getAllUsers() {
        // Intentar buscar por email que contenga @ (todos suelen tenerlo)
        return $this->call('core_user_get_users', ['criteria' => [['key' => 'email', 'value' => '%@%']]]);
    }
    
    /**
     * Obtener usuarios por campo (ej. 'email')
     */
    public function getUsersByField($field, $values) {
        // 1. Intentamos con core_user_get_users_by_field (método oficial y recomendado para búsquedas exactas)
        // ya que suele estar expuesto con menores restricciones de seguridad que core_user_get_users.
        try {
            $params = [
                'field' => $field,
                'values' => $values
            ];
            $res = $this->call('core_user_get_users_by_field', $params);
            
            // core_user_get_users_by_field devuelve un array plano de usuarios.
            // Para mantener total compatibilidad con el código actual que espera ['users' => [...]]:
            if (is_array($res)) {
                return ['users' => $res];
            }
        } catch (Exception $e) {
            // Si la función core_user_get_users_by_field no está habilitada o da un error de acceso/parámetro,
            // hacemos fallback al método genérico core_user_get_users.
            // Ignoramos errores de control de acceso o de parámetro inválido para el fallback, pero arrojamos otros.
            if (strpos($e->getMessage(), 'control de acceso') === false && 
                strpos($e->getMessage(), 'access') === false && 
                strpos($e->getMessage(), 'invalidparameter') === false &&
                strpos($e->getMessage(), 'invalidrecord') === false) {
                throw $e;
            }
        }

        // 2. Fallback al método original core_user_get_users
        $params = [
            'criteria' => [
                [
                    'key' => $field,
                    'value' => $values[0] // ws structure requires this format
                ]
            ]
        ];
        return $this->call('core_user_get_users', $params);
    }
    
    /**
     * Crear un usuario nuevo
     */
    public function createUser($username, $password, $firstname, $lastname, $email) {
        $params = [
            'users' => [
                [
                    'username' => strtolower($username),
                    'password' => $password,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => strtolower($email),
                    'auth' => 'manual',
                    'lang' => 'es'
                ]
            ]
        ];
        // Retorna array con info de los usuarios creados [{id, username}]
        return $this->call('core_user_create_users', $params);
    }

    /**
     * Sube un archivo local al área de draft de Moodle y retorna el itemid de draft generado
     */
    public function uploadFile($localFilePath) {
        if (!$this->isConfigured()) {
            throw new Exception("Moodle no está configurado (URL o Token faltante).");
        }
        if (!file_exists($localFilePath)) {
            throw new Exception("El archivo local no existe: " . $localFilePath);
        }

        // Obtener URL de webservice/upload.php a partir de la URL de webservice/rest/server.php
        $uploadUrl = str_replace('/webservice/rest/server.php', '/webservice/upload.php', $this->url);
        
        $ch = curl_init($uploadUrl);
        
        $cFile = new CurlFile($localFilePath, mime_content_type($localFilePath), basename($localFilePath));
        
        $postData = [
            'token' => $this->token,
            'filearea' => 'draft',
            'itemid' => 0,
            'file' => $cFile
        ];
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Error cURL al subir archivo a Moodle: " . $error);
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar respuesta de subida de Moodle: " . $response);
        }
        
        if (is_array($result) && isset($result[0]['itemid'])) {
            return (int)$result[0]['itemid'];
        }
        
        if (isset($result['exception'])) {
            throw new Exception("Excepción al subir archivo a Moodle: " . ($result['message'] ?? $result['exception']));
        }
        
        throw new Exception("Respuesta inesperada al subir archivo a Moodle: " . $response);
    }

    /**
     * Sincroniza la foto de un usuario con la de Moodle a partir de una ruta local
     */
    public function updateUserPicture($moodleUserId, $localFilePath) {
        $itemId = $this->uploadFile($localFilePath);
        if ($itemId > 0) {
            return $this->updateUser($moodleUserId, ['userpicture' => $itemId]);
        }
        return false;
    }

    /**
     * Actualizar un usuario existente
     */
    public function updateUser($moodleUserId, $data) {
        $user = ['id' => $moodleUserId];
        if (isset($data['firstname'])) $user['firstname'] = $data['firstname'];
        if (isset($data['lastname'])) $user['lastname'] = $data['lastname'];
        if (isset($data['email'])) $user['email'] = strtolower($data['email']);
        if (isset($data['password'])) $user['password'] = $data['password'];
        if (isset($data['userpicture'])) $user['userpicture'] = $data['userpicture'];

        $params = ['users' => [$user]];
        return $this->call('core_user_update_users', $params);
    }
    
    /**
     * Crear un grupo en un curso
     */
    public function createGroup($courseId, $groupName, $description = '') {
        // 1. Intentar buscar en la base de datos de Moodle primero
        require_once __DIR__ . '/moodle_db.php';
        $moodleDb = new MoodleDB();
        if ($moodleDb->isConnected()) {
            try {
                $mpdo = $moodleDb->getPDO();
                $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                $stmt = $mpdo->prepare("SELECT id FROM {$prefix}groups WHERE courseid = ? AND name = ? LIMIT 1");
                $stmt->execute([(int)$courseId, $groupName]);
                $row = $stmt->fetch();
                if ($row) {
                    return [
                        [
                            'id' => (int)$row['id'],
                            'courseid' => (int)$courseId,
                            'name' => $groupName
                        ]
                    ];
                }
            } catch (Exception $e) {
                // Silencioso, continuamos
            }
        }

        // 2. Intentar buscar mediante la API de Moodle si no se encontró en DB
        try {
            $groups = $this->call('core_group_get_course_groups', ['courseid' => (int)$courseId]);
            if (is_array($groups)) {
                foreach ($groups as $group) {
                    if (isset($group['name']) && $group['name'] === $groupName) {
                        return [
                            [
                                'id' => (int)$group['id'],
                                'courseid' => (int)$courseId,
                                'name' => $groupName
                            ]
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Silencioso, continuamos
        }

        // 3. Crear el grupo si no existía
        try {
            $params = [
                'groups' => [
                    [
                        'courseid' => $courseId,
                        'name' => $groupName,
                        'description' => $description
                    ]
                ]
            ];
            return $this->call('core_group_create_groups', $params);
        } catch (Exception $e) {
            // 4. Si da error de "ya existe", intentar buscar una última vez en la base de datos o por API (por si acaso)
            if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'existe') !== false) {
                // Intentar por DB de nuevo
                if ($moodleDb->isConnected()) {
                    try {
                        $mpdo = $moodleDb->getPDO();
                        $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                        $stmt = $mpdo->prepare("SELECT id FROM {$prefix}groups WHERE courseid = ? AND name = ? LIMIT 1");
                        $stmt->execute([(int)$courseId, $groupName]);
                        $row = $stmt->fetch();
                        if ($row) {
                            return [
                                [
                                    'id' => (int)$row['id'],
                                    'courseid' => (int)$courseId,
                                    'name' => $groupName
                                ]
                            ];
                        }
                    } catch (Exception $dbEx) {
                    }
                }
                
                // O intentar por API de nuevo
                try {
                    $groups = $this->call('core_group_get_course_groups', ['courseid' => (int)$courseId]);
                    if (is_array($groups)) {
                        foreach ($groups as $group) {
                            if (isset($group['name']) && $group['name'] === $groupName) {
                                return [
                                    [
                                        'id' => (int)$group['id'],
                                        'courseid' => (int)$courseId,
                                        'name' => $groupName
                                    ]
                                ];
                            }
                        }
                    }
                } catch (Exception $apiEx) {
                }
            }
            // Si todo falló, relanzar la excepción original
            throw $e;
        }
    }

    /**
     * Verificar si un grupo existe en Moodle por su ID
     */
    public function groupExists($groupId) {
        // 1. Intentar por DB
        require_once __DIR__ . '/moodle_db.php';
        $moodleDb = new MoodleDB();
        if ($moodleDb->isConnected()) {
            try {
                $mpdo = $moodleDb->getPDO();
                $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                $stmt = $mpdo->prepare("SELECT id FROM {$prefix}groups WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$groupId]);
                $row = $stmt->fetch();
                if ($row) {
                    return true;
                }
            } catch (Exception $e) {
                // Silencioso
            }
        }

        // 2. Fallback a API
        try {
            $res = $this->call('core_group_get_groups', ['groupids' => [(int)$groupId]]);
            return is_array($res) && !empty($res);
        } catch (Exception $e) {
            return false;
        }
    }


    /**
     * Crear una categoría de curso en Moodle
     */
    public function createCategory($name, $parentId = 0, $description = '') {
        $params = [
            'categories' => [
                [
                    'name' => $name,
                    'parent' => $parentId,
                    'description' => $description
                ]
            ]
        ];
        return $this->call('core_course_create_categories', $params);
    }

    /**
     * Obtener o crear una categoría en Moodle por su nombre
     */
    public function getOrCreateCategory($name, $parentId = 0) {
        try {
            // Buscar si ya existe la categoría por nombre
            $categories = $this->call('core_course_get_categories', [
                'criteria' => [
                    ['key' => 'name', 'value' => $name]
                ]
            ]);
            if (is_array($categories) && !empty($categories)) {
                // Devolver el ID de la primera coincidencia
                return (int)$categories[0]['id'];
            }
        } catch (Exception $e) {
            // Si la búsqueda da error o no está habilitada, procedemos a crearla
        }
        
        // Si no existe, crear la categoría
        $result = $this->createCategory($name, $parentId);
        if (isset($result[0]['id'])) {
            return (int)$result[0]['id'];
        }
        
        throw new Exception("No se pudo obtener ni crear la categoría '$name' en Moodle.");
    }

    /**
     * Crear un curso nuevo en Moodle
     */
    public function createCourse($fullname, $shortname, $categoryId = 1, $summary = '') {
        $params = [
            'courses' => [
                [
                    'fullname' => $fullname,
                    'shortname' => $shortname,
                    'categoryid' => $categoryId,
                    'summary' => $summary,
                    'format' => 'topics'
                ]
            ]
        ];
        return $this->call('core_course_create_courses', $params);
    }
    
    /**
     * Matricular usuario en un curso (manual enrol)
     */
    public function enrolUser($userId, $courseId, $roleId = 5) { // 5 suele ser student
        // 1. Intentar por base de datos primero (para evitar bloqueos de token)
        require_once __DIR__ . '/moodle_db.php';
        $moodleDb = new MoodleDB();
        if ($moodleDb->isConnected()) {
            try {
                $mpdo = $moodleDb->getPDO();
                $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                
                // Buscar el ID del método de matriculación manual para este curso
                $stmtEnrol = $mpdo->prepare("SELECT id FROM {$prefix}enrol WHERE courseid = ? AND enrol = 'manual' LIMIT 1");
                $stmtEnrol->execute([(int)$courseId]);
                $enrolRow = $stmtEnrol->fetch();
                
                if ($enrolRow) {
                    $enrolId = (int)$enrolRow['id'];
                    $now = time();
                    
                    // Comprobar si ya está matriculado para evitar duplicados
                    $stmtCheck = $mpdo->prepare("SELECT id FROM {$prefix}user_enrolments WHERE userid = ? AND enrolid = ?");
                    $stmtCheck->execute([(int)$userId, $enrolId]);
                    
                    if (!$stmtCheck->fetch()) {
                        // Insertar la matriculación
                        $stmtEnrolUser = $mpdo->prepare("
                            INSERT INTO {$prefix}user_enrolments (status, enrolid, userid, timestart, timeend, modifierid, timecreated, timemodified)
                            VALUES (0, ?, ?, ?, 0, 2, ?, ?)
                        ");
                        $stmtEnrolUser->execute([$enrolId, (int)$userId, $now, $now, $now]);
                    }
                    
                    // Asignar rol de estudiante en el contexto del curso si no lo tiene (contextlevel = 50)
                    $stmtContext = $mpdo->prepare("SELECT id FROM {$prefix}context WHERE contextlevel = 50 AND instanceid = ? LIMIT 1");
                    $stmtContext->execute([(int)$courseId]);
                    $contextRow = $stmtContext->fetch();
                    if ($contextRow) {
                        $contextId = (int)$contextRow['id'];
                        $stmtRoleCheck = $mpdo->prepare("SELECT id FROM {$prefix}role_assignments WHERE roleid = ? AND userid = ? AND contextid = ?");
                        $stmtRoleCheck->execute([(int)$roleId, (int)$userId, $contextId]);
                        if (!$stmtRoleCheck->fetch()) {
                            $stmtAssign = $mpdo->prepare("
                                INSERT INTO {$prefix}role_assignments (roleid, contextid, userid, timemodified, modifierid, component, itemid, sortorder)
                                VALUES (?, ?, ?, ?, 2, '', 0, 0)
                            ");
                            $stmtAssign->execute([(int)$roleId, $contextId, (int)$userId, $now]);
                        }
                    }
                    return ['status' => true];
                }
            } catch (Exception $dbEx) {
                // Si falla, dejamos pasar al fallback de la API
            }
        }

        // 2. Fallback a la API de Moodle
        $params = [
            'enrolments' => [
                [
                    'roleid' => $roleId,
                    'userid' => $userId,
                    'courseid' => $courseId
                ]
            ]
        ];
        return $this->call('enrol_manual_enrol_users', $params);
    }
    
    /**
     * Añadir usuario a un grupo
     */
    public function addUserToGroup($groupId, $userId) {
        $params = [
            'members' => [
                [
                    'groupid' => $groupId,
                    'userid' => $userId
                ]
            ]
        ];
        return $this->call('core_group_add_group_members', $params);
    }
    
    // Método Helper: Proceso completo (Crear/Buscar Usuario -> Matricular -> Metelo en grupo)
    public function provisionStudent($courseId, $groupId, $userData) {
        // 1. Buscar si ya existe por email
        $existingUsers = $this->getUsersByField('email', [$userData['email']]);
        $moodleUserId = null;
        
        if (!empty($existingUsers) && isset($existingUsers['users'][0])) {
            $moodleUserId = $existingUsers['users'][0]['id'];
        } else {
            // 2. Crear usuario
            $newUsers = $this->createUser(
                $userData['username'] ?? strtolower(explode('@', $userData['email'])[0]),
                $userData['password'] ?? 'Temporal123!',
                $userData['firstname'],
                $userData['lastname'],
                $userData['email']
            );
            $moodleUserId = $newUsers[0]['id'];
        }
        
        // 3. Matricular
        $this->enrolUser($moodleUserId, $courseId);
        
        // 4. Asignar a grupo
        if ($groupId) {
            $this->addUserToGroup($groupId, $moodleUserId);
        }
        
        return $moodleUserId;
    }

    /**
     * Obtener el progreso de los usuarios en un curso
     */
    public function getCourseUserProgress($courseId) {
        $params = [
            'courseids' => [$courseId]
        ];
        return $this->call('core_completion_get_course_completion_status', ['courseid' => $courseId, 'userid' => 0]); // 0 can mean self or need specific loop
        // Better: core_enrol_get_enrolled_users with course completion info
    }

    /**
     * Obtener usuarios matriculados con su último acceso y progreso
     */
    public function getEnrolledUsers($courseId) {
        $params = [
            'courseid' => $courseId,
            // Removed options to return all fields by default
        ];
        return $this->call('core_enrol_get_enrolled_users', $params);
    }

    /**
     * Obtener los cursos en los que un usuario está matriculado
     */
    public function getUserCourses($userId) {
        return $this->call('core_enrol_get_users_courses', ['userid' => (int)$userId]);
    }

    /**
     * Actualizar metadatos de un curso (ej. visibilidad, nombre)
     */
    public function updateCourse($courseId, $data) {
        $course = ['id' => $courseId];
        if (isset($data['fullname'])) $course['fullname'] = $data['fullname'];
        if (isset($data['shortname'])) $course['shortname'] = $data['shortname'];
        if (isset($data['visible'])) $course['visible'] = (int)$data['visible'];
        
        $params = ['courses' => [$course]];
        return $this->call('core_course_update_courses', $params);
    }

    /**
     * Eliminar un curso de Moodle
     */
    public function deleteCourse($courseId) {
        $params = ['courseids' => [(int)$courseId]];
        return $this->call('core_course_delete_courses', $params);
    }

    /**
     * Desmatricular usuario de un curso (manual unenrol)
     */
    public function unenrolUser($userId, $courseId) {
        // 1. Intentar por base de datos primero (para evitar el bloqueo de permisos del token)
        require_once __DIR__ . '/moodle_db.php';
        $moodleDb = new MoodleDB();
        if ($moodleDb->isConnected()) {
            try {
                $mpdo = $moodleDb->getPDO();
                $prefix = defined('MOODLE_DB_PREFIX') ? MOODLE_DB_PREFIX : 'avefp_';
                
                // Buscar el ID del método de matriculación manual para este curso
                $stmtEnrol = $mpdo->prepare("SELECT id FROM {$prefix}enrol WHERE courseid = ? AND enrol = 'manual' LIMIT 1");
                $stmtEnrol->execute([(int)$courseId]);
                $enrolRow = $stmtEnrol->fetch();
                
                if ($enrolRow) {
                    $enrolId = (int)$enrolRow['id'];
                    // Borrar el registro de la matrícula del usuario
                    $stmtUnenrol = $mpdo->prepare("DELETE FROM {$prefix}user_enrolments WHERE userid = ? AND enrolid = ?");
                    $stmtUnenrol->execute([(int)$userId, $enrolId]);
                    return ['status' => true];
                }
            } catch (Exception $dbEx) {
                // Si falla por base de datos, dejamos que pase al fallback de la API
            }
        }

        // 2. Fallback a la API de Moodle
        $params = [
            'enrolments' => [
                [
                    'userid' => (int)$userId,
                    'courseid' => (int)$courseId
                ]
            ]
        ];
        return $this->call('enrol_manual_unenrol_users', $params);
    }
}
?>
