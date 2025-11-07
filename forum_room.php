<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/includes/forum_helpers.php';
forum_require_login();
$room_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, title, icon, description FROM forum_rooms WHERE id=?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) { die('Raum nicht gefunden'); }
$threadsStmt = $pdo->prepare("
  SELECT t.id, t.title, t.created_at, m.name AS author_name, m.rang AS author_rang
    FROM forum_threads t
    LEFT JOIN mitarbeiter m ON m.id = t.author_id
   WHERE t.room_id = ?
   ORDER BY t.created_at DESC
");
$threadsStmt->execute([$room_id]);
$threads = $threadsStmt->fetchAll(PDO::FETCH_ASSOC);
?><!DOCTYPE html><html lang="de"><head>
<meta charset="UTF-8"><title><?= h($room['title'] ?? '') ?> | Forum</title>
<link rel="stylesheet" href="/header.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/forum.css">
</head><body>
<?php include __DIR__ . '/header.php'; ?>
<div class="forum-wrap">
  <div class="forum-card">
    <h2 class="forum-title"><?= h(($room['icon'] ?? '').' '.($room['title'] ?? '')) ?></h2>
    <?php if (!empty($room['description'])): ?><p class="forum-muted"><?= h($room['description']) ?></p><?php endif; ?>
  </div>
  <div class="threads">
    <?php if (!$threads): ?><p class="forum-muted">Keine Themen vorhanden.</p><?php endif; ?>
    <?php foreach($threads as $t): ?>
      <a class="thread-link" href="/forum_thread.php?id=<?= (int)$t['id'] ?>">
        <span class="thread-title"><?= h($t['title'] ?? '') ?></span>
        <span class="forum-muted"><?= h(($t['author_name'] ?? '')) ?><?= !empty($t['author_rang']) ? ' • '.h($t['author_rang']) : '' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <a href="/forum_new_thread.php?room=<?= (int)$room['id'] ?>" class="button-main" style="margin-top:12px;">+ Neues Thema</a>
</div>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>

  <div class="footer-buttons">
    <a href="/forum.php" class="footer-btn">← Zurück</a>
    <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
  </div>
</footer>


<script src="/script.js"></script>

</body>
</html>
