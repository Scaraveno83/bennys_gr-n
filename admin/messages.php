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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📨 Nachrichten</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main { max-width: 1000px; margin: 100px auto; padding: 0 20px; color: #fff; }
.tabs { display:flex; justify-content:center; gap:12px; margin-bottom:25px; flex-wrap:wrap; }
.tabs a { padding:10px 20px; border:2px solid #39ff14; border-radius:8px; color:#a8ffba; text-decoration:none; font-weight:bold; transition:.3s; }
.tabs a.active, .tabs a:hover { background:linear-gradient(90deg,#39ff14,#76ff65); color:#fff; transform:scale(1.05); }
.table-wrapper { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border-bottom:1px solid rgba(255,255,255,0.1); text-align:left; vertical-align:middle; }
th { background:rgba(57,255,20,0.2); color:#c8ffd5; }
tr:hover { background:rgba(57,255,20,0.05); }
.avatar-icon { width:26px; height:26px; vertical-align:middle; filter:drop-shadow(0 0 5px rgba(57,255,20,.55)); margin-right:6px; }
.rang-badge { background:rgba(57,255,20,.25); border:1px solid rgba(57,255,20,.5); padding:3px 6px; border-radius:6px; font-size:.8rem; color:#fff; margin-left:5px; }
.btn-small { padding:6px 10px; background:linear-gradient(90deg,#39ff14,#76ff65); border:none; border-radius:6px; color:#fff; cursor:pointer; text-decoration:none; transition:.25s; }
.btn-small:hover { transform:scale(1.05); }
.delete-btn { background:#118f2b; border:1px solid rgba(57,255,20,0.6); color:#fff; padding:6px 10px; border-radius:6px; cursor:pointer; transition:.2s; box-shadow:0 0 12px rgba(57,255,20,0.3); }
.delete-btn:hover { background:#39ff14; transform:scale(1.05); }
.unread { font-weight:bold; color:#fff; }
.actions { display:flex; gap:8px; justify-content:flex-end; }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<main>
  <h2 class="section-title">📨 Nachrichten</h2>

  <div class="tabs">
    <a href="?tab=inbox" class="<?= $tab === 'inbox' ? 'active' : '' ?>">📥 Posteingang</a>
    <a href="?tab=sent" class="<?= $tab === 'sent' ? 'active' : '' ?>">📤 Gesendet</a>
    <a href="message_new.php" class="btn-small">➕ Neue Nachricht</a>
  </div>

  <div class="table-wrapper">
  <?php if ($messages): ?>
    <table>
      <tr>
        <th><?= $tab === 'sent' ? 'Empfänger' : 'Absender' ?></th>
        <th>Betreff</th>
        <th>Datum</th>
        <th style="width:100px;">Aktionen</th>
      </tr>
      <?php foreach ($messages as $m): ?>
      <tr id="msg-row-<?= (int)$m['id'] ?>" class="<?= !$m['is_read'] && $tab === 'inbox' ? 'unread' : '' ?>">
        <td>
          <?php if ($tab === 'sent'): ?>
            <?php $icon = $rang_icons[$m['receiver_rang']] ?? 'default.png'; ?>
            <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="avatar-icon" alt="">
            <?= htmlspecialchars($m['receiver_name']) ?>
            <span class="rang-badge"><?= htmlspecialchars($m['receiver_rang']) ?></span>
          <?php else: ?>
            <?php $icon = $rang_icons[$m['sender_rang']] ?? 'default.png'; ?>
            <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="avatar-icon" alt="">
            <?= htmlspecialchars($m['sender_name']) ?>
            <span class="rang-badge"><?= htmlspecialchars($m['sender_rang']) ?></span>
          <?php endif; ?>
        </td>
        <td><a href="message_view.php?id=<?= (int)$m['id'] ?>" class="btn-small"><?= htmlspecialchars($m['subject'] ?: '(Kein Betreff)') ?></a></td>
        <td><?= date('d.m.Y H:i', strtotime($m['sent_at'])) ?></td>
        <td class="actions">
          <form method="POST" action="" class="delete-form">
            <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="delete-btn">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p>Keine Nachrichten vorhanden.</p>
  <?php endif; ?>
  </div>
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
