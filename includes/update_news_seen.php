<?php
session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE user_accounts SET last_seen_news = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

http_response_code(204); // kein Inhalt, aber kein Fehler
exit;
