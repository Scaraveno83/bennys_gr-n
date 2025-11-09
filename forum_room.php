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
$activityStmt = $pdo->prepare("SELECT MAX(p.created_at) FROM forum_posts p JOIN forum_threads t ON t.id = p.thread_id WHERE t.room_id = ?");
$activityStmt->execute([$room_id]);
$latestActivity = $activityStmt->fetchColumn();

$threadCount = count($threads);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8"><title><?= h($room['title'] ?? '') ?> | Forum</title>
<link rel="stylesheet" href="/header.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/forum.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="inventory-page forum-page">
  <header class="inventory-header forum-header">
    <div>
      <h1 class="inventory-title"><?= h(($room['icon'] ?? '') . ' ' . ($room['title'] ?? '')) ?></h1>
      <?php if (!empty($room['description'])): ?>
        <p class="inventory-description"><?= h($room['description']) ?></p>
      <?php else: ?>
        <p class="inventory-description">Diskussionen und Einsatzprotokolle für diesen Bereich.</p>
      <?php endif; ?>
    </div>
    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Themen</span>
        <span class="inventory-metric__value"><?= $threadCount ?></span>
        <span class="inventory-metric__hint">Aktive Diskussionen</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Letzte Aktivität</span>
        <span class="inventory-metric__value"><?= $latestActivity ? date('d.m.Y', strtotime($latestActivity)) : '—' ?></span>
        <span class="inventory-metric__hint"><?= $latestActivity ? date('H:i \U\h\r', strtotime($latestActivity)) : 'Noch keine Antworten' ?></span>
      </div>
    </div>
  </header>

  <section class="inventory-section forum-section">
    <div>
      <h2 class="forum-section__title">Themenübersicht</h2>
      <p class="inventory-section__intro">Hier findest du alle gestarteten Threads im gewählten Bereich.</p>
    </div>

    <?php if (!$threads): ?>
      <p class="inventory-empty">Noch keine Themen vorhanden. Starte den ersten Thread!</p>
    <?php else: ?>
      <div class="forum-thread-list">
        <?php foreach ($threads as $t): ?>
          <a class="forum-thread-card" href="/forum_thread.php?id=<?= (int)$t['id'] ?>">
            <div>
              <span class="forum-thread-card__title"><?= h($t['title'] ?? '') ?></span>
              <span class="forum-thread-card__meta">Erstellt von <?= h($t['author_name'] ?? 'Unbekannt') ?><?= !empty($t['author_rang']) ? ' • ' . h($t['author_rang']) : '' ?></span>
            </div>
            <span class="forum-thread-card__time"><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="inventory-section forum-section forum-actions">
    <div class="forum-actions__inner">
      <div>
        <h2 class="forum-section__title">Neues Thema erstellen</h2>
        <p class="inventory-section__intro">Teile Updates oder stell Fragen – so bleibt das Team immer im Bilde.</p>
      </div>
      <a href="/forum_new_thread.php?room=<?= (int)$room['id'] ?>" class="inventory-submit">+ Neues Thema</a>
    </div>
  </section>
</main>

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
