<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['new_news' => 0]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Zeitpunkt der letzten gesehenen News abrufen
$stmt = $pdo->prepare("SELECT last_seen_news FROM user_accounts WHERE id = ?");
$stmt->execute([$userId]);
$lastSeen = $stmt->fetchColumn();

// Neue News prüfen (öffentlich + intern)
if ($lastSeen) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM news
        WHERE erstellt_am > ?
        AND sichtbar_fuer IN ('oeffentlich', 'intern')
    ");
    $stmt->execute([$lastSeen]);
} else {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM news
        WHERE sichtbar_fuer IN ('oeffentlich', 'intern')
    ");
}

$count = (int)$stmt->fetchColumn();

// Ausgabe (kein HTML, kein echo davor!)
echo json_encode(['new_news' => $count]);
exit;
