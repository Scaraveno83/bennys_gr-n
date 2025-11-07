<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/includes/forum_helpers.php';
forum_require_login();
$rooms = $pdo->query("SELECT id, title, icon, COALESCE(sort_order,0) AS sort_order FROM forum_rooms ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$stats = [];
foreach ($rooms as $r) {
  $st = $pdo->prepare("SELECT 
      (SELECT COUNT(*) FROM forum_threads WHERE room_id = ?) AS threads,
      (SELECT MAX(p.created_at) 
         FROM forum_threads t 
         LEFT JOIN forum_posts p ON p.thread_id = t.id 
       WHERE t.room_id = ?) AS last_activity");
  $st->execute([$r['id'], $r['id']]);
  $stats[$r['id']] = $st->fetch(PDO::FETCH_ASSOC) ?: ['threads'=>0,'last_activity'=>null];
}
?><!DOCTYPE html><html lang="de"><head>
<meta charset="UTF-8"><title>Forum | Räume</title>
<link rel="stylesheet" href="/header.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/forum.css">
</head><body>
<?php include __DIR__ . '/header.php'; ?>
<div class="forum-wrap">
  <div class="forum-card"><h2 class="forum-title">Werkstatt-Forum</h2><p class="forum-muted">Interner Bereich – nur für Mitarbeiter sichtbar.</p></div>
  <div class="rooms">
    <?php foreach($rooms as $r): $stat=$stats[$r['id']]; ?>
      <a class="room-link" href="/forum_room.php?id=<?= (int)$r['id'] ?>">
        <div class="room-name">
          <span class="room-icon"><?= h($r['icon'] ?? '🧰') ?></span>
          <span><?= h($r['title'] ?? '') ?></span>
        </div>
        <div class="forum-muted" style="margin-top:6px;">
          <?= (int)($stat['threads'] ?? 0) ?> Themen • Letzte Aktivität: <?= !empty($stat['last_activity']) ? date('d.m.Y H:i', strtotime($stat['last_activity'])) : '—' ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<!-- FOOTER -->
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="/script.js"></script>
</body></html>

