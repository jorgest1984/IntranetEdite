<?php require 'includes/config.php'; print_r(json_encode(\->query('SELECT * FROM matriculas WHERE id=47')->fetch(PDO::FETCH_ASSOC)));
