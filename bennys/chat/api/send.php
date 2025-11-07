<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'not_authenticated']); exit;
}
$userId = (int)$_SESSION['user_id'];

$body = trim($_POST['body'] ?? '');
$filePath = null;
$type = 'text';
$durationSeconds = null;

$hasImage = !empty($_FILES['image']['tmp_name']);
$hasVoice = !empty($_FILES['voice']['tmp_name']);

if ($body === '' && !$hasImage && !$hasVoice) {
  echo json_encode(['ok'=>false,'error'=>'empty_message']); exit;
}

if ($hasVoice && (!isset($_FILES['voice']['error']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK)) {
  echo json_encode(['ok'=>false,'error'=>'voice_upload_error']); exit;
}

if ($hasImage && (!isset($_FILES['image']['error']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK)) {
  echo json_encode(['ok'=>false,'error'=>'image_upload_error']); exit;
}

if ($hasVoice) {
  $tmp = $_FILES['voice']['tmp_name'];

  $maxSize = 8 * 1024 * 1024; // 8 MB Sicherheitslimit
  if (!empty($_FILES['voice']['size']) && $_FILES['voice']['size'] > $maxSize) {
    echo json_encode(['ok'=>false,'error'=>'voice_too_large']); exit;
  }

  $ext = strtolower(pathinfo($_FILES['voice']['name'], PATHINFO_EXTENSION));
  $fallbackExt = 'webm';
  $knownVoiceExt = [
    'webm','weba','ogg','oga','ogx','mp3','wav','wave','m4a','mp4','m4v','mov','aac','3gp','3gpp','3g2','mka','opus','caf'
  ];
  if ($ext === '' || !in_array($ext, $knownVoiceExt, true)) {
    // versuche aus dem MIME-Type eine passende Extension abzuleiten
    $mime = '';
    if (function_exists('mime_content_type')) {
      $mime = mime_content_type($tmp) ?: '';
    }
    if (!$mime && !empty($_FILES['voice']['type'])) {
      $mime = $_FILES['voice']['type'];
    }
    $mime = strtolower(trim((string)$mime));
    if ($mime !== '') {
      if (strpos($mime, ';') !== false) {
        $mime = trim(strtok($mime, ';'));
      }
      $mimeMap = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/vnd.wave' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'audio/aac' => 'aac',
        'audio/opus' => 'opus',
        'application/ogg' => 'ogg',
        'application/x-ogg' => 'ogg',
        'audio/3gpp' => '3gp',
        'audio/3gpp2' => '3g2',
        'video/3gpp' => '3gp',
        'video/3gpp2' => '3g2',
        'audio/x-matroska' => 'mka',
        'video/x-matroska' => 'mka',
        'application/x-matroska' => 'mka'
      ];
      if (isset($mimeMap[$mime])) {
        $ext = $mimeMap[$mime];
      }
    }
  }

  if ($ext === '' || !in_array($ext, $knownVoiceExt, true)) {
    $ext = $fallbackExt;
  }

  $filename = uniqid('voice_', true).'.'.$ext;
  $dir = $_SERVER['DOCUMENT_ROOT'].'/bennys/chat/uploads/';
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  if (!move_uploaded_file($tmp, $dir.$filename)) { echo json_encode(['ok'=>false,'error'=>'upload_fail']); exit; }
  $filePath = '/bennys/chat/uploads/'.$filename;
  $type = 'voice';
  $durationSeconds = isset($_POST['voice_duration']) ? (int)$_POST['voice_duration'] : 0;
  if ($durationSeconds < 1) $durationSeconds = null;
  if ($durationSeconds !== null && $durationSeconds > 120) $durationSeconds = 120;
  $body = ''; // keine Textnachricht für Voice
}

if ($type !== 'voice' && $hasImage) {
  $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
  $tmp = $_FILES['image']['tmp_name'];
  $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ($_FILES['image']['type'] ?? '');
  if (!in_array($mime, $allowed)) { echo json_encode(['ok'=>false,'error'=>'bad_mime']); exit; }
  $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'png');
  $filename = uniqid('chat_', true).'.'.$ext;
  $dir = $_SERVER['DOCUMENT_ROOT'].'/bennys/chat/uploads/';
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  if (!move_uploaded_file($tmp, $dir.$filename)) { echo json_encode(['ok'=>false,'error'=>'upload_fail']); exit; }
  $filePath = '/bennys/chat/uploads/'.$filename;
  $type = 'image';
}

$stmt = $pdo->prepare("
    INSERT INTO chat_messages (user_id, body, type, file_path, duration_seconds, expires_at)
    VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))
");
$stmt->execute([
    $userId,
    $body !== '' ? $body : null,
    $type,
    $filePath,
    $durationSeconds
]);


echo json_encode(['ok'=>true,'message_id'=>$pdo->lastInsertId()]);
