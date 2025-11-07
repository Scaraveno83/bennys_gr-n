<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['user_id'])) {
  echo 0;
  exit;
}

$stmt = $pdo->prepare("
  SELECT COUNT(*) FROM user_messages 
  WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
echo (int)$stmt->fetchColumn();
