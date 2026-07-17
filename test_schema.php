<?php
require 'includes/config.php';
print_r($pdo->query('SHOW COLUMNS FROM cursos')->fetchAll(PDO::FETCH_COLUMN));
print_r($pdo->query('SHOW COLUMNS FROM acciones_formativas')->fetchAll(PDO::FETCH_COLUMN));
print_r($pdo->query('SHOW COLUMNS FROM grupos')->fetchAll(PDO::FETCH_COLUMN));
