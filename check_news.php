<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user_id'])) exit;

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT last_seen_news FROM user_accounts WHERE id = ?");
$stmt->execute([$userId]);
$lastSeen = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT MAX(erstellt_am) FROM news");
$latest = $stmt->fetchColumn();

if ($latest && (!$lastSeen || strtotime($latest) > strtotime($lastSeen))) {
  echo "new";
}
