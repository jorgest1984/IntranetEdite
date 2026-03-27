<?php
// guardar_accion.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitización básica y recolección de datos
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    
    $data = [
        'plan_id' => !empty($_POST['plan_id']) ? (int)$_POST['plan_id'] : null,
        'nivel' => $_POST['nivel'] ?? null,
        'prioridad' => $_POST['prioridad'] ?? null,
        'estado' => $_POST['estado'] ?? null,
        'destacar_web' => isset($_POST['destacar_web']) ? (int)$_POST['destacar_web'] : 0,
        'ultimas_plazas' => isset($_POST['ultimas_plazas']) ? 1 : 0,
        'id_plataforma' => $_POST['id_plataforma'] ?? null,
        'titulo' => $_POST['titulo'] ?? null,
        'abreviatura' => $_POST['abreviatura'] ?? null,
        'num_accion' => !empty($_POST['num_accion']) ? (int)$_POST['num_accion'] : 0,
        'duracion' => !empty($_POST['duracion']) ? (int)$_POST['duracion'] : 0,
        'p' => !empty($_POST['p']) ? (int)$_POST['p'] : 0,
        'd' => !empty($_POST['d']) ? (int)$_POST['d'] : 0,
        't' => !empty($_POST['t']) ? (int)$_POST['t'] : 0,
        'modalidad' => $_POST['modalidad'] ?? null,
        'area_tematica' => $_POST['area_tematica'] ?? null,
        'familia_profesional' => $_POST['familia_profesional'] ?? null,
        'horas_teoricas' => !empty($_POST['horas_teoricas']) ? (int)$_POST['horas_teoricas'] : 0,
        'horas_practicas' => !empty($_POST['horas_practicas']) ? (int)$_POST['horas_practicas'] : 0,
        'dias_extra' => !empty($_POST['dias_extra']) ? (int)$_POST['dias_extra'] : 0,
        'asignacion' => $_POST['asignacion'] ?? null,
        'modulo_sensib' => isset($_POST['modulo_sensib']) ? 1 : 0,
        'modulo_alfab' => isset($_POST['modulo_alfab']) ? 1 : 0,
        'encuesta_post' => isset($_POST['encuesta_post']) ? 1 : 0,
        'dur_int_empresas' => $_POST['dur_int_empresas'] ?? null,
        'dur_emprendimiento' => $_POST['dur_emprendimiento'] ?? null,
        'objetivos' => $_POST['objetivos'] ?? null,
        'objetivos_especificos' => $_POST['objetivos_especificos'] ?? null,
        'contenidos' => $_POST['contenidos'] ?? null,
        'contenidos_breves' => $_POST['contenidos_breves'] ?? null,
        'que_aprenden' => $_POST['que_aprenden'] ?? null,
        'contenidos_fes' => $_POST['contenidos_fes'] ?? null,
        'recursos_accion' => $_POST['recursos_accion'] ?? null,
        'demanda_mercado' => $_POST['demanda_mercado'] ?? null
    ];

    try {
        if ($id) {
            // Update
            $sql = "UPDATE acciones_formativas SET 
                plan_id = :plan_id, nivel = :nivel, prioridad = :prioridad, estado = :estado, 
                destacar_web = :destacar_web, ultimas_plazas = :ultimas_plazas, id_plataforma = :id_plataforma, 
                titulo = :titulo, abreviatura = :abreviatura, num_accion = :num_accion, duracion = :duracion, 
                p = :p, d = :d, t = :t, modalidad = :modalidad, area_tematica = :area_tematica, 
                familia_profesional = :familia_profesional, horas_teoricas = :horas_teoricas, 
                horas_practicas = :horas_practicas, dias_extra = :dias_extra, asignacion = :asignacion, 
                modulo_sensib = :modulo_sensib, modulo_alfab = :modulo_alfab, encuesta_post = :encuesta_post, 
                dur_int_empresas = :dur_int_empresas, dur_emprendimiento = :dur_emprendimiento, 
                objetivos = :objetivos, objetivos_especificos = :objetivos_especificos, contenidos = :contenidos, 
                contenidos_breves = :contenidos_breves, que_aprenden = :que_aprenden, contenidos_fes = :contenidos_fes, 
                recursos_accion = :recursos_accion, demanda_mercado = :demanda_mercado
                WHERE id = :id";
            $data['id'] = $id;
        } else {
            // Insert
            $sql = "INSERT INTO acciones_formativas (
                plan_id, nivel, prioridad, estado, destacar_web, ultimas_plazas, id_plataforma, 
                titulo, abreviatura, num_accion, duracion, p, d, t, modalidad, area_tematica, 
                familia_profesional, horas_teoricas, horas_practicas, dias_extra, asignacion, 
                modulo_sensib, modulo_alfab, encuesta_post, dur_int_empresas, dur_emprendimiento, 
                objetivos, objetivos_especificos, contenidos, contenidos_breves, que_aprenden, 
                contenidos_fes, recursos_accion, demanda_mercado
            ) VALUES (
                :plan_id, :nivel, :prioridad, :estado, :destacar_web, :ultimas_plazas, :id_plataforma, 
                :titulo, :abreviatura, :num_accion, :duracion, :p, :d, :t, :modalidad, :area_tematica, 
                :familia_profesional, :horas_teoricas, :horas_practicas, :dias_extra, :asignacion, 
                :modulo_sensib, :modulo_alfab, :encuesta_post, :dur_int_empresas, :dur_emprendimiento, 
                :objetivos, :objetivos_especificos, :contenidos, :contenidos_breves, :que_aprenden, 
                :contenidos_fes, :recursos_accion, :demanda_mercado
            )";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        
        $new_id = $id ? $id : $pdo->lastInsertId();
        
        // Audit log
        audit_log($pdo, $id ? 'UPDATE' : 'INSERT', 'acciones_formativas', $new_id, null, $data);
        
        header("Location: ficha_accion_formativa.php?id=$new_id&success=1");
        exit();

    } catch (Throwable $e) {
        die("Error al guardar la acción formativa: " . $e->getMessage());
    }
}
