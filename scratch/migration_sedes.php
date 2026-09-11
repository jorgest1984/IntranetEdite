<?php
// scratch/migration_sedes.php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MIGRACIÓN DE CENTROS DE IMPARTICIÓN ===\n";

$centros_oficiales = [
    [
        'nombre' => 'GRANADA',
        'direccion' => 'Calle Benjamin Franklin 1',
        'provincia' => 'Granada',
        'cp' => '18100',
        'telefono' => '958089725',
        'email_contacto' => 'granada@grupoefp.es'
    ],
    [
        'nombre' => 'MADRID',
        'direccion' => 'Paseo de la Castellana 100',
        'provincia' => 'Madrid',
        'cp' => '28046',
        'telefono' => '910000000',
        'email_contacto' => 'madrid@grupoefp.es'
    ],
    [
        'nombre' => 'VALLADOLID',
        'direccion' => 'Calle Santiago 15',
        'provincia' => 'Valladolid',
        'cp' => '47001',
        'telefono' => '983000000',
        'email_contacto' => 'valladolid@grupoefp.es'
    ],
    [
        'nombre' => 'ALMERÍA',
        'direccion' => 'Paseo de Almería 25',
        'provincia' => 'Almería',
        'cp' => '04001',
        'telefono' => '950000000',
        'email_contacto' => 'almeria@grupoefp.es'
    ]
];

foreach ($centros_oficiales as $c) {
    $stmt = $pdo->prepare("SELECT id FROM centros WHERE UPPER(nombre) = ? OR UPPER(nombre) LIKE ?");
    $stmt->execute([mb_strtoupper($c['nombre'], 'UTF-8'), '%' . mb_strtoupper($c['nombre'], 'UTF-8') . '%']);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Centro existente: {$c['nombre']} (ID {$existing['id']})\n";
        // Actualizar nombre estandarizado si hace falta
        $pdo->prepare("UPDATE centros SET nombre = ? WHERE id = ?")->execute([$c['nombre'], $existing['id']]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO centros (nombre, direccion, provincia, cp, telefono, email_contacto, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmtIns->execute([$c['nombre'], $c['direccion'], $c['provincia'], $c['cp'], $c['telefono'], $c['email_contacto']]);
        $newId = $pdo->lastInsertId();
        echo "Centro insertado: {$c['nombre']} (ID {$newId})\n";
    }
}

echo "\n--- LISTADO DE CENTROS FINAL ---\n";
$stmtList = $pdo->query("SELECT id, nombre, provincia FROM centros ORDER BY id ASC");
print_r($stmtList->fetchAll(PDO::FETCH_ASSOC));
