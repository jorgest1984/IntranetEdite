<?php require 'includes/config.php'; print_r(json_encode(\->query('DESCRIBE alumnos')->fetchAll(PDO::FETCH_ASSOC)));
