<?php
// includes/Papelera.php

class Papelera {
    
    private static $tableChecked = false;
    
    // Asegurar que existe la tabla papelera
    public static function checkTable($pdo) {
        if (self::$tableChecked) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS papelera (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tabla VARCHAR(100) NOT NULL,
            elemento_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            datos JSON NOT NULL,
            usuario_id INT NULL,
            fecha_borrado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        self::$tableChecked = true;
    }

    /**
     * Archiva un elemento en la papelera antes de ser borrado
     */
    public static function archivar($pdo, $tabla, $elemento_id, $titulo, $datos, $usuario_id = null) {
        self::checkTable($pdo);
        if ($usuario_id === null && isset($_SESSION['user_id'])) {
            $usuario_id = $_SESSION['user_id'];
        }
        $stmt = $pdo->prepare("INSERT INTO papelera (tabla, elemento_id, titulo, datos, usuario_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$tabla, $elemento_id, $titulo, json_encode($datos), $usuario_id]);
    }

    /**
     * Restaura un elemento de la papelera insertándolo de nuevo en sus tablas originales
     */
    public static function restaurar($pdo, $papeleraId) {
        self::checkTable($pdo);
        
        $stmt = $pdo->prepare("SELECT * FROM papelera WHERE id = ?");
        $stmt->execute([$papeleraId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new Exception("El elemento de la papelera no existe.");
        }

        $datos = json_decode($item['datos'], true);
        $tabla = $item['tabla'];

        $pdo->beginTransaction();
        try {
            if ($tabla === 'acciones_formativas') {
                // 1. Restaurar Acción Formativa
                if (!empty($datos['acciones_formativas'])) {
                    self::insertRow($pdo, 'acciones_formativas', $datos['acciones_formativas']);
                }
                // 2. Restaurar Grupos
                if (!empty($datos['grupos'])) {
                    foreach ($datos['grupos'] as $grupo) {
                        self::insertRow($pdo, 'grupos', $grupo);
                    }
                }
                // 3. Restaurar Matrículas
                if (!empty($datos['matriculas'])) {
                    foreach ($datos['matriculas'] as $matricula) {
                        self::insertRow($pdo, 'matriculas', $matricula);
                    }
                }
            } elseif ($tabla === 'usuarios') {
                if (!empty($datos['usuarios'])) {
                    self::insertRow($pdo, 'usuarios', $datos['usuarios']);
                }
            } elseif ($tabla === 'convocatorias') {
                // 1. Restaurar Convocatoria
                if (!empty($datos['convocatorias'])) {
                    self::insertRow($pdo, 'convocatorias', $datos['convocatorias']);
                }
                // 2. Restaurar Planes
                if (!empty($datos['planes'])) {
                    foreach ($datos['planes'] as $plan) {
                        self::insertRow($pdo, 'planes', $plan);
                    }
                }
            } elseif ($tabla === 'alumnos') {
                // 1. Restaurar Alumno
                if (!empty($datos['alumnos'])) {
                    self::insertRow($pdo, 'alumnos', $datos['alumnos']);
                }
                // 2. Restaurar Matrículas
                if (!empty($datos['matriculas'])) {
                    foreach ($datos['matriculas'] as $matricula) {
                        self::insertRow($pdo, 'matriculas', $matricula);
                    }
                }
                // 3. Restaurar Documentos
                if (!empty($datos['documentos_alumno'])) {
                    foreach ($datos['documentos_alumno'] as $doc) {
                        self::insertRow($pdo, 'documentos_alumno', $doc);
                    }
                }
            } else {
                // Fallback genérico
                if (isset($datos[$tabla])) {
                    self::insertRow($pdo, $tabla, $datos[$tabla]);
                } else {
                    self::insertRow($pdo, $tabla, $datos);
                }
            }

            // Eliminar de la papelera
            $stmtDel = $pdo->prepare("DELETE FROM papelera WHERE id = ?");
            $stmtDel->execute([$papeleraId]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Inserta una fila en una tabla respetando los campos y valores originales
     */
    private static function insertRow($pdo, $table, $row) {
        // Verificar si el registro ya existe para evitar errores de duplicado
        $id = $row['id'] ?? null;
        if ($id) {
            $stmtCheck = $pdo->prepare("SELECT id FROM `$table` WHERE id = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetch()) {
                // Si ya existe (ej. re-creado en la base de datos), omitimos para no causar error fatal
                return;
            }
        }
        
        $fields = array_keys($row);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        
        $sql = "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($row));
    }
}
