<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/includes/forum_helpers.php';
forum_require_login();
// Räume + Aktivitäts-Statistiken einsammeln
$rooms = $pdo->query("SELECT id, title, icon, COALESCE(sort_order,0) AS sort_order FROM forum_rooms ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$stats = [];
$totalThreads = 0;
$latestActivity = null;

foreach ($rooms as $r) {
 $st = $pdo->prepare("SELECT
      (SELECT COUNT(*) FROM forum_threads WHERE room_id = ?) AS threads,
      (SELECT MAX(p.created_at)
         FROM forum_threads t
         LEFT JOIN forum_posts p ON p.thread_id = t.id
       WHERE t.room_id = ?) AS last_activity");
  $st->execute([$r['id'], $r['id']]);
  $stats[$r['id']] = $st->fetch(PDO::FETCH_ASSOC) ?: ['threads' => 0, 'last_activity' => null];

  $threads = (int)($stats[$r['id']]['threads'] ?? 0);
  $totalThreads += $threads;

  $lastActivity = $stats[$r['id']]['last_activity'] ?? null;
  if ($lastActivity && (!$latestActivity || $lastActivity > $latestActivity)) {
    $latestActivity = $lastActivity;
  }
}

$totalRooms = count($rooms);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Forum | Räume</title>
<link rel="stylesheet" href="/header.css">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/forum.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="inventory-page forum-page">
  <header class="inventory-header forum-header">
    <div>
      <h1 class="inventory-title">🛠️ Werkstatt-Forum</h1>
      <p class="inventory-description">Interner Bereich – nur für Mitarbeiter sichtbar. Tauscht euch zu laufenden Projekten, Einsätzen und Ideen aus.</p>
    </div>
    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Räume</span>
        <span class="inventory-metric__value"><?= $totalRooms ?></span>
        <span class="inventory-metric__hint">Aktive Kategorien</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Themen</span>
        <span class="inventory-metric__value"><?= $totalThreads ?></span>
        <span class="inventory-metric__hint">Diskussionen insgesamt</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Letzte Aktivität</span>
        <span class="inventory-metric__value">
          <?= $latestActivity ? date('d.m.Y', strtotime($latestActivity)) : '—' ?>
        </span>
        <span class="inventory-metric__hint">
          <?= $latestActivity ? date('H:i \U\h\r', strtotime($latestActivity)) : 'Keine Beiträge' ?>
        </span>
      </div>
    </div>
  </header>

  <section class="inventory-section forum-section">
    <div>
      <h2 class="forum-section__title">Forenräume</h2>
      <p class="inventory-section__intro">Wähle einen Bereich aus, um offene Tickets, Reparaturen oder Team-News zu verfolgen.</p>
    </div>
    <div class="forum-grid">
      <?php foreach ($rooms as $r): $stat = $stats[$r['id']]; ?>
        <a class="forum-room-card" href="/forum_room.php?id=<?= (int)$r['id'] ?>">
          <div class="forum-room-card__header">
            <span class="forum-room-card__icon"><?= h($r['icon'] ?? '🧰') ?></span>
            <span class="forum-room-card__title"><?= h($r['title'] ?? '') ?></span>
          </div>
          <dl class="forum-room-card__meta">
            <div>
              <dt>Themen</dt>
              <dd><?= (int)($stat['threads'] ?? 0) ?></dd>
            </div>
            <div>
              <dt>Letzte Aktivität</dt>
              <dd><?= !empty($stat['last_activity']) ? date('d.m.Y H:i', strtotime($stat['last_activity'])) : 'Noch keine Beiträge' ?></dd>
            </div>
          </dl>
        </a>
      <?php endforeach; ?>
      <?php if (!$rooms): ?>
        <p class="inventory-empty">Es wurden noch keine Forenräume angelegt.</p>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="/script.js"></script>
</body>
</html>
