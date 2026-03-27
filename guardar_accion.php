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
        'demanda_mercado' => $_POST['demanda_mercado'] ?? null,
        // Material Fields
        'hay_material' => isset($_POST['hay_material']) ? 1 : 0,
        'num_entregas' => !empty($_POST['num_entregas']) ? (int)$_POST['num_entregas'] : 0,
        'codigo_entregas' => $_POST['codigo_entregas'] ?? null,
        'num_modulos' => !empty($_POST['num_modulos']) ? (int)$_POST['num_modulos'] : 0,
        'detalle_entregas' => $_POST['detalle_entregas'] ?? null,
        'manual_curso' => isset($_POST['manual_curso']) ? 1 : 0,
        'manual_sensibilizacion' => isset($_POST['manual_sensibilizacion']) ? 1 : 0,
        'carpeta_clasificadora' => isset($_POST['carpeta_clasificadora']) ? 1 : 0,
        'cuaderno_a4' => isset($_POST['cuaderno_a4']) ? 1 : 0,
        'boligrafo' => isset($_POST['boligrafo']) ? 1 : 0,
        'maletin' => isset($_POST['maletin']) ? 1 : 0,
        'otros_materiales' => isset($_POST['otros_materiales']) ? 1 : 0,
        'otros_materiales_txt' => $_POST['otros_materiales_txt'] ?? null,
        'material_extra_info' => $_POST['material_extra_info'] ?? null,
        'notas_gestion' => $_POST['notas_gestion'] ?? null,
        'notas_ejecucion' => $_POST['notas_ejecucion'] ?? null,
        'notas_instalacion' => $_POST['notas_instalacion'] ?? null,
        // New Gestion Fields
        'resp_documentacion_id' => !empty($_POST['resp_documentacion_id']) ? (int)$_POST['resp_documentacion_id'] : null,
        'resp_seguimiento_id' => !empty($_POST['resp_seguimiento_id']) ? (int)$_POST['resp_seguimiento_id'] : null,
        'resp_dudas_id' => !empty($_POST['resp_dudas_id']) ? (int)$_POST['resp_dudas_id'] : null,
        'tutor1_id' => !empty($_POST['tutor1_id']) ? (int)$_POST['tutor1_id'] : null,
        'tutor1_activo' => isset($_POST['tutor1_activo']) ? 1 : 0,
        'tutor2_id' => !empty($_POST['tutor2_id']) ? (int)$_POST['tutor2_id'] : null,
        'tutor2_activo' => isset($_POST['tutor2_activo']) ? 1 : 0,
        'mostrar_otras_consultoras' => isset($_POST['mostrar_otras_consultoras']) ? 1 : 0,
        'alumnos_otras_consultoras' => $_POST['alumnos_otras_consultoras'] ?? null,
        'teleformador_id' => !empty($_POST['teleformador_id']) ? (int)$_POST['teleformador_id'] : null,
        'id_grupo_gestion' => $_POST['id_grupo_gestion'] ?? null,
        'email_tutor_gestion' => $_POST['email_tutor_gestion'] ?? null,
        'nuestra_check' => isset($_POST['nuestra_check']) ? 1 : 0,
        'prioritaria_check' => isset($_POST['prioritaria_check']) ? 1 : 0,
        'num_evaluaciones' => !empty($_POST['num_evaluaciones']) ? (int)$_POST['num_evaluaciones'] : 0,
        'recibi_material1' => isset($_POST['recibi_material1']) ? 1 : 0,
        'recibi_material2' => isset($_POST['recibi_material2']) ? 1 : 0,
        'eval1_check' => isset($_POST['eval1_check']) ? 1 : 0,
        'eval1_titulo' => $_POST['eval1_titulo'] ?? null,
        'eval2_check' => isset($_POST['eval2_check']) ? 1 : 0,
        'eval2_titulo' => $_POST['eval2_titulo'] ?? null,
        'eval3_check' => isset($_POST['eval3_check']) ? 1 : 0,
        'eval3_titulo' => $_POST['eval3_titulo'] ?? null,
        'eval4_check' => isset($_POST['eval4_check']) ? 1 : 0,
        'eval4_titulo' => $_POST['eval4_titulo'] ?? null,
        'supuesto_practico' => $_POST['supuesto_practico'] ?? null,
        'conexia_check' => isset($_POST['conexia_check']) ? 1 : 0,
        'cae_check' => isset($_POST['cae_check']) ? 1 : 0,
        'edite_gestion_check' => isset($_POST['edite_gestion_check']) ? 1 : 0,
        'nivel_gestion' => !empty($_POST['nivel_gestion']) ? (int)$_POST['nivel_gestion'] : 1,
        'paquete_gestion' => $_POST['paquete_gestion'] ?? null,
        'observaciones_gestion' => $_POST['observaciones_gestion'] ?? null
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
                recursos_accion = :recursos_accion, demanda_mercado = :demanda_mercado,
                hay_material = :hay_material, num_entregas = :num_entregas, codigo_entregas = :codigo_entregas,
                num_modulos = :num_modulos, detalle_entregas = :detalle_entregas, manual_curso = :manual_curso,
                manual_sensibilizacion = :manual_sensibilizacion, carpeta_clasificadora = :carpeta_clasificadora,
                cuaderno_a4 = :cuaderno_a4, boligrafo = :boligrafo, maletin = :maletin,
                otros_materiales = :otros_materiales, otros_materiales_txt = :otros_materiales_txt,
                material_extra_info = :material_extra_info,
                notas_gestion = :notas_gestion, notas_ejecucion = :notas_ejecucion,
                notas_instalacion = :notas_instalacion,
                resp_documentacion_id = :resp_documentacion_id, resp_seguimiento_id = :resp_seguimiento_id,
                resp_dudas_id = :resp_dudas_id, tutor1_id = :tutor1_id, tutor1_activo = :tutor1_activo,
                tutor2_id = :tutor2_id, tutor2_activo = :tutor2_activo,
                mostrar_otras_consultoras = :mostrar_otras_consultoras, alumnos_otras_consultoras = :alumnos_otras_consultoras,
                teleformador_id = :teleformador_id, id_grupo_gestion = :id_grupo_gestion,
                email_tutor_gestion = :email_tutor_gestion, nuestra_check = :nuestra_check,
                prioritaria_check = :prioritaria_check, num_evaluaciones = :num_evaluaciones,
                recibi_material1 = :recibi_material1, recibi_material2 = :recibi_material2,
                eval1_check = :eval1_check, eval1_titulo = :eval1_titulo,
                eval2_check = :eval2_check, eval2_titulo = :eval2_titulo,
                eval3_check = :eval3_check, eval3_titulo = :eval3_titulo,
                eval4_check = :eval4_check, eval4_titulo = :eval4_titulo,
                supuesto_practico = :supuesto_practico, conexia_check = :conexia_check,
                cae_check = :cae_check, edite_gestion_check = :edite_gestion_check,
                nivel_gestion = :nivel_gestion, paquete_gestion = :paquete_gestion,
                observaciones_gestion = :observaciones_gestion
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
                contenidos_fes, recursos_accion, demanda_mercado,
                hay_material, num_entregas, codigo_entregas, num_modulos, detalle_entregas,
                manual_curso, manual_sensibilizacion, carpeta_clasificadora, cuaderno_a4,
                boligrafo, maletin, otros_materiales, otros_materiales_txt,
                material_extra_info, notas_gestion, notas_ejecucion,
                notas_instalacion, resp_documentacion_id, resp_seguimiento_id,
                resp_dudas_id, tutor1_id, tutor1_activo, tutor2_id,
                tutor2_activo, mostrar_otras_consultoras, alumnos_otras_consultoras,
                teleformador_id, id_grupo_gestion, email_tutor_gestion,
                nuestra_check, prioritaria_check, num_evaluaciones,
                recibi_material1, recibi_material2, eval1_check, eval1_titulo,
                eval2_check, eval2_titulo, eval3_check, eval3_titulo,
                eval4_check, eval4_titulo, supuesto_practico, conexia_check,
                cae_check, edite_gestion_check, nivel_gestion, paquete_gestion,
                observaciones_gestion
            ) VALUES (
                :plan_id, :nivel, :prioridad, :estado, :destacar_web, :ultimas_plazas, :id_plataforma, 
                :titulo, :abreviatura, :num_accion, :duracion, :p, :d, :t, :modalidad, :area_tematica, 
                :familia_profesional, :horas_teoricas, :horas_practicas, :dias_extra, :asignacion, 
                :modulo_sensib, :modulo_alfab, :encuesta_post, :dur_int_empresas, :dur_emprendimiento, 
                :objetivos, :objetivos_especificos, :contenidos, :contenidos_breves, :que_aprenden, 
                :contenidos_fes, :recursos_accion, :demanda_mercado,
                :hay_material, :num_entregas, :codigo_entregas, :num_modulos, :detalle_entregas,
                :manual_curso, :manual_sensibilizacion, :carpeta_clasificadora, :cuaderno_a4,
                :boligrafo, :maletin, :otros_materiales, :otros_materiales_txt,
                :material_extra_info, :notas_gestion, :notas_ejecucion,
                :notas_instalacion, :resp_documentacion_id, :resp_seguimiento_id,
                :resp_dudas_id, :tutor1_id, :tutor1_activo, :tutor2_id,
                :tutor2_activo, :mostrar_otras_consultoras, :alumnos_otras_consultoras,
                :teleformador_id, :id_grupo_gestion, :email_tutor_gestion,
                :nuestra_check, :prioritaria_check, :num_evaluaciones,
                :recibi_material1, :recibi_material2, :eval1_check, :eval1_titulo,
                :eval2_check, :eval2_titulo, :eval3_check, :eval3_titulo,
                :eval4_check, :eval4_titulo, :supuesto_practico, :conexia_check,
                :cae_check, :edite_gestion_check, :nivel_gestion, :paquete_gestion,
                :observaciones_gestion
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
