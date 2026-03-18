<?php
// includes/SessionHandlerDB.php

class SessionHandlerDB implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && isset($row['data'])) {
            return $row['data'];
        }
        return '';
    }

    public function write(string $id, string $data): bool {
        // En MySQL/MariaDB usamos REPLACE INTO si id es PRIMARY KEY o UNIQUE.
        // Nos aseguramos de mantener un log de acceso para el Garbaje Collector.
        $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, last_accessed) VALUES (?, ?, NOW())");
        return $stmt->execute([$id, $data]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_accessed < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        if ($stmt->execute([$max_lifetime])) {
            return $stmt->rowCount() ?? true;
        }
        return false;
    }
}
