<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

// Verzeichnis für Bilder
$uploadDir = $_SERVER['DOCUMENT_ROOT'].'/bennys/chat/uploads/';

// Alte Nachrichten mit expires_at < NOW() holen
$sql = "SELECT id, file_path, type FROM chat_messages WHERE expires_at < NOW()";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bilder löschen
foreach ($rows as $row) {
    if (in_array($row['type'] ?? '', ['image','voice'], true) && !empty($row['file_path'])) {
        $full = $_SERVER['DOCUMENT_ROOT'] . $row['file_path']; // z.B. /var/www/.../bennys/chat/uploads/xyz.png
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

// DB aufräumen
$del = $pdo->prepare("DELETE FROM chat_messages WHERE expires_at < NOW()");
$del->execute();

echo json_encode([
    'ok' => true,
    'deleted' => count($rows)
]);
