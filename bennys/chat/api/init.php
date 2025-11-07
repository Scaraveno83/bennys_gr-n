<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_authenticated']); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  body TEXT NULL,
  type ENUM('text','image','voice') NOT NULL DEFAULT 'text',
  file_path VARCHAR(255) NULL,
  duration_seconds SMALLINT UNSIGNED NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_id (id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Sicherstellen, dass ENUM und Spalten auch bei bestehender Tabelle aktuell sind
$pdo->exec("ALTER TABLE chat_messages MODIFY COLUMN type ENUM('text','image','voice') NOT NULL DEFAULT 'text'");

$durationExists = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'duration_seconds'")->fetch(PDO::FETCH_ASSOC);
if (!$durationExists) {
    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN duration_seconds SMALLINT UNSIGNED NULL AFTER file_path");
}

$expiresExists = $pdo->query("SHOW COLUMNS FROM chat_messages LIKE 'expires_at'")->fetch(PDO::FETCH_ASSOC);
if (!$expiresExists) {
    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN expires_at DATETIME NULL DEFAULT NULL AFTER duration_seconds");
    $pdo->exec("ALTER TABLE chat_messages ADD KEY idx_expires (expires_at)");
}


$stmtUser = $pdo->prepare("
    SELECT
        u.username,
        COALESCE(ma.name, u.username, 'Unbekannt') AS display_name
    FROM user_accounts u
    LEFT JOIN mitarbeiter ma ON ma.id = u.mitarbeiter_id
    WHERE u.id = ?
");
$stmtUser->execute([$_SESSION['user_id']]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$mentionCatalog = [];

$mentionStmt = $pdo->query("
    SELECT
        '' AS username,
        NULLIF(ma.name, '') AS display_name
    FROM mitarbeiter ma
    WHERE NULLIF(ma.name, '') <> ''
    ORDER BY display_name ASC
");

if ($mentionStmt) {
    $seen = [];
    while ($row = $mentionStmt->fetch(PDO::FETCH_ASSOC)) {
        $username = $row['username'] ?? '';
        $display = $row['display_name'] ?? '';

        if ($display === '') {
            continue;
        }

        $key = strtolower(trim($display.'|'.$username));
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $mentionCatalog[] = [
            'username' => $username,
            'display_name' => $display,
        ];
    }
}

echo json_encode([
    'ok' => true,
    'self_id' => (int)$_SESSION['user_id'],
    'self_username' => $user['username'] ?? '',
    'self_display_name' => $user['display_name'] ?? '',
    'mention_catalog' => $mentionCatalog,
]);
