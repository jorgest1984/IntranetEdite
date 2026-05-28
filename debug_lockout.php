<?php
// ARCHIVO DE DIAGNÓSTICO TEMPORAL — ELIMINAR DESPUÉS DE USAR
$token = $_GET['t'] ?? '';
if ($token !== 'diag2026abc') {
    http_response_code(404);
    die('Not found');
}

require_once 'includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Diagnóstico</title>
<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4}
h2{color:#4ec9b0} pre{background:#252526;padding:15px;border-radius:6px;overflow:auto}
.ok{color:#6a9955} .warn{color:#dcdcaa} .err{color:#f44747}
table{border-collapse:collapse;width:100%} td,th{border:1px solid #555;padding:6px 10px;text-align:left}
th{background:#2d2d30} tr:nth-child(even){background:#252526}
</style></head><body>

<h1>🔍 Diagnóstico Login Lockout</h1>

<?php

// ── 1. VERSIÓN DEL CÓDIGO ─────────────────────────────────────────────────
echo "<h2>1. Versión del código en servidor</h2>";
$index_content = file_get_contents(__DIR__ . '/index.php');
$lines = explode("\n", $index_content);
echo "<pre>";
// Mostrar primeras 50 líneas del index.php para verificar qué versión corre
foreach (array_slice($lines, 0, 55) as $i => $line) {
    echo htmlspecialchars(($i+1) . ": " . $line) . "\n";
}
echo "</pre>";

// ── 2. ESTRUCTURA DE LA TABLA ─────────────────────────────────────────────
echo "<h2>2. Estructura de login_attempts</h2>";
try {
    $cols = $pdo->query("DESCRIBE `login_attempts`")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($cols as $col) {
        $highlight = ($col['Field'] === 'attempt_unix') ? 'class="ok"' : '';
        echo "<tr $highlight><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td><td>{$col['Extra']}</td></tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p class='err'>❌ Tabla no existe o error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── 3. REGISTROS ALMACENADOS ──────────────────────────────────────────────
echo "<h2>3. Últimos 20 registros en login_attempts</h2>";
try {
    $rows = $pdo->query("SELECT * FROM login_attempts ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "<p class='warn'>⚠️ La tabla está VACÍA — los registros no se están guardando</p>";
    } else {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $k) echo "<th>$k</th>";
        echo "</tr>";
        $now = time();
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $k => $v) {
                if ($k === 'attempt_unix') {
                    $diff = $now - (int)$v;
                    $info = $v == 0 ? "<span class='err'>⚠ CERO!</span>" : "<span class='ok'>hace {$diff}s</span>";
                    echo "<td>$v $info</td>";
                } else {
                    echo "<td>" . htmlspecialchars((string)$v) . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── 4. CONTEO REAL DE LA VENTANA ──────────────────────────────────────────
echo "<h2>4. Conteo en ventana de 15 min (lo que ve el sistema)</h2>";
$ahora = time();
$desde = $ahora - 900;
echo "<pre>";
echo "PHP time() ahora : $ahora\n";
echo "PHP date ahora   : " . date('Y-m-d H:i:s', $ahora) . "\n";
echo "Ventana desde    : $desde (" . date('Y-m-d H:i:s', $desde) . ")\n";
echo "Tu IP (REMOTE_ADDR): " . $_SERVER['REMOTE_ADDR'] . "\n";
echo "HTTP_X_FORWARDED_FOR: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '(no presente)') . "\n";
echo "</pre>";

try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE is_successful = 0 AND attempt_unix > ?");
    $st->execute([$desde]);
    $total = $st->fetchColumn();
    echo "<p class='" . ($total > 0 ? 'ok' : 'warn') . "'>Total intentos fallidos en ventana: <strong>$total</strong></p>";
} catch (PDOException $e) {
    echo "<p class='err'>❌ Error en conteo: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── 5. PRUEBA DE INSERT ───────────────────────────────────────────────────
echo "<h2>5. Prueba de INSERT en login_attempts</h2>";
try {
    $test_ip   = $_SERVER['REMOTE_ADDR'];
    $test_user = '__debug_test__';
    $test_unix = time();

    $pdo->prepare("INSERT INTO login_attempts (ip_address, username, attempt_unix, is_successful) VALUES (?, ?, ?, 0)")
        ->execute([$test_ip, $test_user, $test_unix]);
    $inserted_id = $pdo->lastInsertId();
    echo "<p class='ok'>✅ INSERT OK — ID generado: $inserted_id, attempt_unix: $test_unix</p>";

    // Leer de vuelta inmediatamente
    $verify = $pdo->prepare("SELECT * FROM login_attempts WHERE id = ?");
    $verify->execute([$inserted_id]);
    $row_back = $verify->fetch(PDO::FETCH_ASSOC);
    echo "<pre>Leído de vuelta: " . print_r($row_back, true) . "</pre>";

    // Limpiar
    $pdo->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$test_user]);
    echo "<p class='ok'>🗑️ Registro de prueba eliminado</p>";
} catch (PDOException $e) {
    echo "<p class='err'>❌ INSERT falló: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── 6. AUTOCOMMIT ─────────────────────────────────────────────────────────
echo "<h2>6. Estado de AutoCommit y Transacción PDO</h2>";
try {
    $autocommit = $pdo->query("SELECT @@autocommit")->fetchColumn();
    echo "<p>@@autocommit MySQL: <strong>$autocommit</strong> " . ($autocommit ? '<span class="ok">✅ ON</span>' : '<span class="err">❌ OFF — los INSERTs no se guardan!</span>') . "</p>";
    echo "<p>PDO inTransaction(): <strong>" . ($pdo->inTransaction() ? '<span class="err">SÍ — transacción abierta!</span>' : '<span class="ok">NO</span>') . "</strong></p>";
} catch (PDOException $e) {
    echo "<p class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p class='warn'>⚠️ ELIMINAR ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE DIAGNOSTICAR</p>";
?>
</body></html>
