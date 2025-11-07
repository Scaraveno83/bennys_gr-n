<?php
// error_reporting(E_ALL); ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Ungültige Anfrage.');
}

$news_id = (int)($_POST['news_id'] ?? 0);
$comment_text = trim($_POST['comment_text'] ?? '');
$name = trim($_POST['name'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Prüfen, ob News existiert und sichtbar ist
$stmt = $pdo->prepare("SELECT sichtbar_fuer FROM news WHERE id = ?");
$stmt->execute([$news_id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) exit('News nicht gefunden.');

$isPublic   = ($news['sichtbar_fuer'] === 'oeffentlich');
$isLoggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['user_role']);

if (!$isPublic && !$isLoggedIn) exit('Nur für eingeloggte Benutzer verfügbar.');

// Falls eingeloggter Benutzer: Name & ID übernehmen
$user_id = null;
if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'] ?? null;
    $name = $_SESSION['mitarbeiter_name']
        ?? $_SESSION['admin_username']
        ?? $_SESSION['user_name']
        ?? 'Unbekannt';
} elseif ($name === '') {
    $name = 'Gast';
}

if ($news_id <= 0 || $comment_text === '') {
    exit('Ungültige Eingabe.');
}

// Kommentar speichern
$stmt = $pdo->prepare("
    INSERT INTO news_comments (news_id, user_id, name, text, ip_address)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$news_id, $user_id, $name, $comment_text, $ip]);

// Weiterleitung zurück zur News
header("Location: news_archiv.php#news-$news_id");
exit;
?>
