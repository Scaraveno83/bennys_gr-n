<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');


/* === Nachricht löschen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];
  $pdo->prepare("DELETE FROM user_messages WHERE id = ?")->execute([$deleteId]);

  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
  }

  header("Location: manage_messages.php?msg=deleted");
  exit;
}

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

/* === Nachrichten abrufen === */
$query = "
  SELECT m.*, 
         COALESCE(s_m.name, ua_s.username) AS sender_name,
         COALESCE(s_m.rang, 'Administrator') AS sender_rang,
         COALESCE(r_m.name, ua_r.username) AS receiver_name,
         COALESCE(r_m.rang, 'Administrator') AS receiver_rang
  FROM user_messages m
  LEFT JOIN user_accounts ua_s ON ua_s.id = m.sender_id
  LEFT JOIN mitarbeiter s_m ON s_m.id = ua_s.mitarbeiter_id
  LEFT JOIN user_accounts ua_r ON ua_r.id = m.receiver_id
  LEFT JOIN mitarbeiter r_m ON r_m.id = ua_r.mitarbeiter_id
  WHERE 1
";

$params = [];

if ($search) {
  $query .= " AND (
    s_m.name LIKE :s OR r_m.name LIKE :s OR
    m.subject LIKE :s OR m.message LIKE :s
  )";
  $params['s'] = "%$search%";
}

if ($filter === 'unread') {
  $query .= " AND m.is_read = 0";
} elseif ($filter === 'read') {
  $query .= " AND m.is_read = 1";
}

$query .= " ORDER BY m.sent_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM user_messages")->fetchColumn();
$unreadMessages = (int)$pdo->query("SELECT COUNT(*) FROM user_messages WHERE is_read = 0")->fetchColumn();
$filteredCount = count($messages);

$filterLabel = [
  '' => 'Alle Nachrichten',
  'unread' => 'Ungelesene Nachrichten',
  'read' => 'Gelesene Nachrichten',
][$filter] ?? 'Alle Nachrichten';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📨 Nachrichtenverwaltung | Admin</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<main class="inventory-page messages-page messages-manage-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📨 Nachrichtenverwaltung</h1>
    <p class="inventory-description">
      Alle internen Nachrichten zentral überwachen, filtern und verwalten.
    </p>
    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamt</span>
        <span class="inventory-metric__value"><?= number_format($totalMessages, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Nachrichten im System</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Ungelesen</span>
        <span class="inventory-metric__value"><?= number_format($unreadMessages, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Erfordert Aufmerksamkeit</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Aktuelle Ansicht</span>
        <span class="inventory-metric__value"><?= number_format($filteredCount, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint"><?= htmlspecialchars($filterLabel) ?></span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      <p class="inventory-note inventory-note--success">Nachricht erfolgreich gelöscht.</p>
    <?php endif; ?>

    <form method="GET" class="inventory-filters">
      <div class="inventory-filters__group">
        <label for="search" class="sr-only">Nachrichten durchsuchen</label>
        <span class="search-field">
          <input
            type="search"
            id="search"
            name="search"
            placeholder="Namen, Betreff oder Nachricht suchen"
            value="<?= htmlspecialchars($search) ?>"
          >
        </span>

        <label for="filter" class="sr-only">Lesestatus filtern</label>
        <select id="filter" name="filter" class="inventory-select">
          <option value="">Alle Nachrichten</option>
          <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Nur ungelesene</option>
          <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Nur gelesene</option>
        </select>
      </div>

      <div class="inventory-filters__actions">
        <button type="submit" class="inventory-submit inventory-submit--small">🔍 Filtern</button>
        <?php if ($search || $filter): ?>
          <a href="manage_messages.php" class="inventory-submit inventory-submit--ghost inventory-submit--small">Filter zurücksetzen</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($messages): ?>
      <div class="table-wrap">
        <table class="data-table message-table message-manage-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Absender</th>
              <th>Empfänger</th>
              <th>Betreff</th>
              <th>Datum</th>
              <th>Status</th>
              <th class="message-actions__header">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $m): ?>
              <?php
                $rowClasses = ['message-row'];
                if (empty($m['is_read'])) {
                  $rowClasses[] = 'message-row--unread';
                }
                $senderIcon = $rang_icons[$m['sender_rang']] ?? 'default.png';
                $receiverIcon = $rang_icons[$m['receiver_rang']] ?? 'default.png';
              ?>
              <tr id="msg-row-<?= (int)$m['id'] ?>" class="<?= implode(' ', $rowClasses) ?>">
                <td class="message-id">#<?= (int)$m['id'] ?></td>
                <td>
                  <div class="message-participant">
                    <img src="../pics/icons/<?= htmlspecialchars($senderIcon) ?>" class="message-avatar" alt="">
                    <div class="message-participant__info">
                      <span class="message-participant__name"><?= htmlspecialchars($m['sender_name']) ?></span>
                      <span class="rang-badge"><?= htmlspecialchars($m['sender_rang']) ?></span>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="message-participant">
                    <img src="../pics/icons/<?= htmlspecialchars($receiverIcon) ?>" class="message-avatar" alt="">
                    <div class="message-participant__info">
                      <span class="message-participant__name"><?= htmlspecialchars($m['receiver_name']) ?></span>
                      <span class="rang-badge"><?= htmlspecialchars($m['receiver_rang']) ?></span>
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
                <td>
                  <span class="message-status <?= $m['is_read'] ? 'message-status--read' : 'message-status--unread' ?>">
                    <?= $m['is_read'] ? '📖 Gelesen' : '✉️ Ungelesen' ?>
                  </span>
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
      <p class="inventory-empty">Keine Nachrichten gefunden.</p>
    <?php endif; ?>

    <div class="form-actions">
      <a href="dashboard.php" class="inventory-submit inventory-submit--ghost inventory-submit--small">⬅️ Zurück zum Dashboard</a>
    </div>
  </section>
</main>

<script>
document.querySelectorAll('.delete-form').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!confirm('Diese Nachricht wirklich löschen?')) {
      return;
    }

    const data = new FormData(form);
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const json = await response.json().catch(() => null);
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