<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'error'=>'not_authenticated']); exit;
}

$userId = (int)$_SESSION['user_id'];
$messageId = (int)($_GET['id'] ?? 0);

if ($messageId <= 0) {
    echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit;
}

// Nachricht + Bildpfad holen
$stmt = $pdo->prepare("SELECT user_id, file_path FROM chat_messages WHERE id = ?");
$stmt->execute([$messageId]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    echo json_encode(['ok'=>false,'error'=>'not_found']); exit;
}

$owner = (int)$msg['user_id'];
$file = $msg['file_path'];

// Löschen: selbst oder Leitung/Admin
$canDelete = false;

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $canDelete = true;
} elseif ($owner === $userId) {
    $canDelete = true;
}

if (!$canDelete) {
    echo json_encode(['ok'=>false,'error'=>'no_permission']); exit;
}

// Falls Bild vorhanden → löschen
if (!empty($file)) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $file;
    if (file_exists($fullPath)) unlink($fullPath);
}

// Nachricht löschen
$stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
$stmt->execute([$messageId]);

echo json_encode(['ok'=>true]);
