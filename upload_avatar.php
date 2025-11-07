<?php
session_start();
require_once __DIR__ . '/includes/db.php';


if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Nicht erlaubt');
}

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
  SELECT m.id, m.bild_url 
  FROM mitarbeiter m
  JOIN user_accounts u ON u.mitarbeiter_id = m.id
  WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) exit('Benutzer nicht gefunden');
if (empty($_FILES['avatar']['tmp_name'])) exit('Keine Datei hochgeladen');

$file = $_FILES['avatar'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$maxSize = 3 * 1024 * 1024;

if ($file['size'] > $maxSize) exit('Datei zu groß (max. 3 MB)');
if (!array_key_exists($file['type'], $allowed)) exit('Nur JPG, PNG oder WEBP erlaubt');

$ext = $allowed[$file['type']];
$uploadDir = __DIR__ . '/pics/profile/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!empty($user['bild_url'])) {
    $oldPath = __DIR__ . '/' . $user['bild_url'];
    if (file_exists($oldPath) && strpos($user['bild_url'], 'default') === false) {
        @unlink($oldPath);
    }
}

$newName = 'pics/profile/user_' . $user['id'] . '_' . date('Ymd_His') . '.' . $ext;
$destPath = __DIR__ . '/' . $newName;
if (!move_uploaded_file($file['tmp_name'], $destPath)) exit('Fehler beim Upload');

$stmt = $pdo->prepare("UPDATE mitarbeiter SET bild_url = ? WHERE id = ?");
$stmt->execute([$newName, $user['id']]);

header('Location: profile_edit.php');
exit;
