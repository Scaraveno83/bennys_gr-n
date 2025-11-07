<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

/* === Zugriffsschutz: Nur Admins dürfen diese Seite aufrufen === */
if (
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');


/* === Nachricht löschen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];
  $pdo->prepare("DELETE FROM user_messages WHERE id = ?")->execute([$deleteId]);
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📨 Nachrichtenverwaltung | Admin</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main { max-width: 1100px; margin: 100px auto; padding: 0 20px; color: #fff; }
.search-bar { display:flex; gap:10px; justify-content:center; margin-bottom:20px; flex-wrap:wrap; }
.search-bar input, .search-bar select {
  padding:8px 12px; border-radius:6px; border:1px solid rgba(57,255,20,0.4);
  background:#111; color:#fff; min-width:200px;
}
.search-bar button {
  padding:8px 14px; border:none; border-radius:6px;
  background:linear-gradient(90deg,#39ff14,#76ff65);
  color:#fff; cursor:pointer;
}
.search-bar button:hover { transform:scale(1.05); }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border-bottom:1px solid rgba(255,255,255,0.1); text-align:left; }
th { background:rgba(57,255,20,0.2); color:#c8ffd5; }
.avatar { width:24px; height:24px; vertical-align:middle; margin-right:6px; filter:drop-shadow(0 0 5px rgba(57,255,20,.6)); }
.rang-badge { background:rgba(57,255,20,.25); border:1px solid rgba(57,255,20,.5); padding:3px 6px; border-radius:6px; font-size:.8rem; color:#fff; margin-left:5px; }
.btn-del { background:#118f2b; border:1px solid rgba(57,255,20,0.6); color:#fff; padding:6px 10px; border-radius:6px; cursor:pointer; transition:.2s; box-shadow:0 0 12px rgba(57,255,20,0.3); }
.btn-del:hover { background:#39ff14; transform:scale(1.05); }
.notice { background:rgba(20,60,20,.85); border:1px solid #4CAF50; color:#dff6df; padding:10px 15px; border-radius:8px; margin-bottom:20px; text-align:center; }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main>
  <h2 class="section-title">📨 Nachrichtenverwaltung</h2>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="notice">✅ Nachricht erfolgreich gelöscht.</div>
  <?php endif; ?>

  <form method="GET" class="search-bar">
    <input type="text" name="search" placeholder="Suche nach Nutzer, Betreff, Text ..." value="<?= htmlspecialchars($search) ?>">
    <select name="filter">
      <option value="">– Filter –</option>
      <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Ungelesene</option>
      <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Gelesene</option>
    </select>
    <button type="submit">🔍 Suchen</button>
  </form>

  <div class="table-wrap">
    <?php if ($messages): ?>
      <table>
        <tr>
          <th>ID</th>
          <th>Absender</th>
          <th>Empfänger</th>
          <th>Betreff</th>
          <th>Datum</th>
          <th>Status</th>
          <th>Aktion</th>
        </tr>
        <?php foreach ($messages as $m): ?>
        <tr>
          <td><?= (int)$m['id'] ?></td>
          <td>
            <?php $icon = $rang_icons[$m['sender_rang']] ?? 'default.png'; ?>
            <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="avatar">
            <?= htmlspecialchars($m['sender_name']) ?>
            <span class="rang-badge"><?= htmlspecialchars($m['sender_rang']) ?></span>
          </td>
          <td>
            <?php $icon = $rang_icons[$m['receiver_rang']] ?? 'default.png'; ?>
            <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="avatar">
            <?= htmlspecialchars($m['receiver_name']) ?>
            <span class="rang-badge"><?= htmlspecialchars($m['receiver_rang']) ?></span>
          </td>
          <td><?= htmlspecialchars($m['subject'] ?: '(Kein Betreff)') ?></td>
          <td><?= date('d.m.Y H:i', strtotime($m['sent_at'])) ?></td>
          <td><?= $m['is_read'] ? '📖 Gelesen' : '✉️ Ungelesen' ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Diese Nachricht wirklich löschen?');">
              <input type="hidden" name="delete_id" value="<?= (int)$m['id'] ?>">
              <button type="submit" class="btn-del">🗑️ Löschen</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>Keine Nachrichten gefunden.</p>
    <?php endif; ?>
  </div>

  <div style="margin-top:25px;text-align:center;">
    <a href="dashboard.php" class="btn-small">⬅️ Zurück zum Dashboard</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
