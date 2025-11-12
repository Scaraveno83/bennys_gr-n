<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if (empty($_POST['news_id']) || empty($_POST['reaction'])) {
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Anfrage']);
    exit;
}

$newsId   = (int)$_POST['news_id'];
$reaction = $_POST['reaction'];
$ip       = $_SERVER['REMOTE_ADDR'];
$userId   = $_SESSION['user_id'] ?? null;

// Prüfen, ob diese Reaktion bereits existiert
$stmt = $pdo->prepare("SELECT id FROM news_reactions_user WHERE news_id = ? AND reaction_type = ? AND (user_id = ? OR ip = ?)");
$stmt->execute([$newsId, $reaction, $userId, $ip]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Reaktion entfernen
    $pdo->prepare("DELETE FROM news_reactions_user WHERE id = ?")->execute([$existing['id']]);
    $pdo->prepare("UPDATE news_reactions SET count = GREATEST(count-1,0) WHERE news_id = ? AND reaction_type = ?")
        ->execute([$newsId, $reaction]);
    $pdo->prepare("DELETE FROM news_reactions WHERE news_id = ? AND reaction_type = ? AND count <= 0")
        ->execute([$newsId, $reaction]);
} else {
    // Neue Reaktion
    $pdo->prepare("INSERT INTO news_reactions_user (news_id, user_id, ip, reaction_type) VALUES (?, ?, ?, ?)")
        ->execute([$newsId, $userId, $ip, $reaction]);
    $pdo->prepare("INSERT INTO news_reactions (news_id, reaction_type, count) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE count = count + 1")->execute([$newsId, $reaction]);
}

// Neue Zähler zurückgeben
$stmt = $pdo->prepare("SELECT reaction_type, count FROM news_reactions WHERE news_id = ?");
$stmt->execute([$newsId]);
$fetchedReactions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$reactions = [];
foreach ($fetchedReactions as $type => $count) {
    $reactions[$type] = (int)$count;
}

// Falls die aggregierte Zeile entfernt wurde, trotzdem 0 für die betroffene Reaktion zurückgeben
if (!isset($reactions[$reaction])) {
    $reactions[$reaction] = 0;
}

echo json_encode(['status' => 'success', 'reactions' => $reactions]);
exit;
