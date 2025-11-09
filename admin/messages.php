<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId) {
  header("Location: ../login.php");
  exit;
}

$tab = $_GET['tab'] ?? 'inbox';

/* === Rang-Icons === */
$rang_icons = [
  "Geschäftsführung" => "gf.png",
  "Stv. Geschäftsleitung" => "stv_leitung.png",
  "Personalleitung" => "personalleitung.png",
  "Ausbilder/in" => "ausbilder.png",
  "Tuner/in" => "tuner.png",
  "Meister/in" => "meister.png",
  "Mechaniker/in" => "mechaniker.png",
  "Geselle/Gesellin" => "geselle.png",
  "Azubi 3.Jahr" => "azubi3.png",
  "Azubi 2.Jahr" => "azubi2.png",
  "Azubi 1.Jahr" => "azubi1.png",
  "Praktikant/in" => "praktikant.png",
  "Administrator" => "admin.png"
];

/* === Nachricht löschen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];
  $stmt = $pdo->prepare("DELETE FROM user_messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
  $stmt->execute([$deleteId, $userId, $userId]);
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    echo json_encode(['success' => true]);
    exit;
  }
  header("Location: messages.php?tab=" . urlencode($tab));
  exit;
}

/* === Nachrichten abrufen === */
if ($tab === 'sent') {
  $stmt = $pdo->prepare("
    SELECT m.*, 
           COALESCE(r_m.name, ua_r.username) AS receiver_name,
           COALESCE(r_m.rang, 'Administrator') AS receiver_rang
    FROM user_messages m
    LEFT JOIN user_accounts ua_r ON ua_r.id = m.receiver_id
    LEFT JOIN mitarbeiter r_m ON r_m.id = ua_r.mitarbeiter_id
    WHERE m.sender_id = ?
    ORDER BY m.sent_at DESC
  ");
  $stmt->execute([$userId]);
} else {
  $stmt = $pdo->prepare("
    SELECT m.*, 
           COALESCE(s_m.name, ua_s.username) AS sender_name,
           COALESCE(s_m.rang, 'Administrator') AS sender_rang
    FROM user_messages m
    LEFT JOIN user_accounts ua_s ON ua_s.id = m.sender_id
    LEFT JOIN mitarbeiter s_m ON s_m.id = ua_s.mitarbeiter_id
    WHERE m.receiver_id = :user_id
       OR (:user_role = 'admin' AND m.receiver_id IS NULL)
    ORDER BY m.sent_at DESC
  ");
  $stmt->execute(['user_id' => $userId, 'user_role' => $userRole]);
}
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalMessages = count($messages);
$unreadCount = 0;
if ($tab === 'inbox') {
  foreach ($messages as $message) {
    if (empty($message['is_read'])) {
      $unreadCount++;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📨 Nachrichten</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<main class="inventory-page messages-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📨 Nachrichten</h1>
    <p class="inventory-description">
      Interne Mitteilungen verwalten, lesen und neue Gespräche starten.
    </p>
    <p class="inventory-info">
      <?= $tab === 'inbox'
        ? sprintf('%d ungelesen · %d insgesamt im Posteingang', $unreadCount, $totalMessages)
        : sprintf('%d gesendete Nachrichten', $totalMessages)
      ?>
    </p>
  </header>

  <section class="inventory-section">
    <div class="inventory-tabs">
      <div class="inventory-tabs__group">
        <a href="?tab=inbox" class="inventory-tab <?= $tab === 'inbox' ? 'is-active' : '' ?>">📥 Posteingang</a>
        <a href="?tab=sent" class="inventory-tab <?= $tab === 'sent' ? 'is-active' : '' ?>">📤 Gesendet</a>
      </div>
      <a href="message_new.php" class="inventory-submit inventory-submit--small message-new-btn">➕ Neue Nachricht</a>
    </div>

    <?php if ($messages): ?>
      <div class="table-wrap">
        <table class="data-table message-table">
          <thead>
            <tr>
              <th><?= $tab === 'sent' ? 'Empfänger' : 'Absender' ?></th>
              <th>Betreff</th>
              <th>Datum</th>
              <th class="message-actions__header">Aktionen</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($messages as $m): ?>
            <?php
              $rowClasses = ['message-row'];
              if ($tab === 'inbox' && empty($m['is_read'])) {
                $rowClasses[] = 'message-row--unread';
              }
              $icon = $tab === 'sent'
                ? ($rang_icons[$m['receiver_rang']] ?? 'default.png')
                : ($rang_icons[$m['sender_rang']] ?? 'default.png');
              $displayName = $tab === 'sent' ? $m['receiver_name'] : $m['sender_name'];
              $displayRank = $tab === 'sent' ? $m['receiver_rang'] : $m['sender_rang'];
            ?>
            <tr id="msg-row-<?= (int)$m['id'] ?>" class="<?= implode(' ', $rowClasses) ?>">
              <td>
                <div class="message-participant">
                  <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="message-avatar" alt="">
                  <div class="message-participant__info">
                    <span class="message-participant__name"><?= htmlspecialchars($displayName) ?></span>
                    <span class="rang-badge"><?= htmlspecialchars($displayRank) ?></span>
                  </div>
                </div>
              </td>
              <td>
                <a href="message_view.php?id=<?= (int)$m['id'] ?>" class="message-subject">
                  <?= htmlspecialchars($m['subject'] ?: '(Kein Betreff)') ?>
                </a>
              </td>
              <td>
                <span class="message-date"><?= date('d.m.Y H:i', strtotime($m['sent_at'])) ?></span>
              </td>
              <td class="message-actions">
                <form method="POST" action="" class="delete-form">
                  <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
                  <button type="submit" class="delete-btn" aria-label="Nachricht löschen">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="empty-state">Keine Nachrichten vorhanden.</p>
    <?php endif; ?>
  </section>
</main>

<script>
document.querySelectorAll('.delete-form').forEach(form => {
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!confirm('Diese Nachricht wirklich löschen?')) return;
    const data = new FormData(form);
    const res = await fetch('', { method: 'POST', body: data, headers: {'X-Requested-With':'XMLHttpRequest'} });
    const json = await res.json().catch(() => null);
    if (json?.success) {
      const row = form.closest('tr');
      row.style.transition = '0.3s';
      row.style.opacity = '0';
      setTimeout(() => row.remove(), 300);
    } else {
      alert('Fehler beim Löschen.');
    }
  });
});
</script>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>