<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Ungültige Anfrage.');

$commentId = (int)($_POST['comment_id'] ?? 0);
$newText   = trim($_POST['new_text'] ?? '');
$userId    = $_SESSION['user_id'] ?? null;
$userRole  = $_SESSION['user_role'] ?? '';

// Kommentar laden
$stmt = $pdo->prepare("SELECT user_id FROM news_comments WHERE id = ?");
$stmt->execute([$commentId]);
$comment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comment) exit('Kommentar nicht gefunden.');

// Berechtigung prüfen (Ersteller oder Admin)
if ($comment['user_id'] != $userId && $userRole !== 'admin') {
    exit('Keine Berechtigung, diesen Kommentar zu bearbeiten.');
}

if ($newText === '') exit('Kommentar darf nicht leer sein.');

// Kommentar aktualisieren
$upd = $pdo->prepare("UPDATE news_comments SET text = ?, created_at = NOW() WHERE id = ?");
$upd->execute([$newText, $commentId]);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'news_archiv.php'));
exit;
?>
