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

$id = (int)($_GET['id'] ?? 0);

/* === Nachricht abrufen === */
$stmt = $pdo->prepare("
  SELECT 
    m.*,
    COALESCE(s_m.name, ua_s.username) AS sender_name,
    COALESCE(s_m.rang, 'Administrator') AS sender_rang,
    COALESCE(r_m.name, ua_r.username) AS receiver_name,
    COALESCE(r_m.rang, 'Administrator') AS receiver_rang
  FROM user_messages m
  LEFT JOIN user_accounts ua_s ON ua_s.id = m.sender_id
  LEFT JOIN mitarbeiter s_m ON s_m.id = ua_s.mitarbeiter_id
  LEFT JOIN user_accounts ua_r ON ua_r.id = m.receiver_id
  LEFT JOIN mitarbeiter r_m ON r_m.id = ua_r.mitarbeiter_id
  WHERE m.id = ?
    AND (m.sender_id = ? OR m.receiver_id = ?)
");
$stmt->execute([$id, $userId, $userId]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
  echo "<p>Nachricht nicht gefunden oder kein Zugriff.</p>";
  exit;
}

/* === Als gelesen markieren === */
if ($msg['receiver_id'] == $userId && !$msg['is_read']) {
  $pdo->prepare("UPDATE user_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
}

/* === Nachricht löschen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];
  $stmt = $pdo->prepare("DELETE FROM user_messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)");
  $stmt->execute([$deleteId, $userId, $userId]);
  header("Location: messages.php?tab=" . ($msg['sender_id'] == $userId ? 'sent' : 'inbox'));
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($msg['subject'] ?: '(Kein Betreff)') ?></title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main { max-width: 800px; margin: 100px auto; padding: 0 20px; color: #fff; }
.message-box { background: rgba(25,25,25,0.85); padding: 20px; border-radius: 10px; border: 1px solid rgba(57,255,20,0.3); }
.header { display:flex; align-items:center; gap:10px; margin-bottom:15px; }
.avatar-icon { width:32px; height:32px; filter:drop-shadow(0 0 5px rgba(57,255,20,.6)); }
.rang-badge { background:rgba(57,255,20,.25); border:1px solid rgba(57,255,20,.5); padding:3px 6px; border-radius:6px; font-size:.8rem; color:#fff; margin-left:5px; }
.btn-small { padding:8px 14px; background:linear-gradient(90deg,#39ff14,#76ff65); border:none; border-radius:6px; color:#fff; cursor:pointer; text-decoration:none; transition:.25s; }
.btn-small:hover { box-shadow:0 0 10px rgba(57,255,20,.6); transform:scale(1.05); }
.delete-btn { background:#118f2b; border:1px solid rgba(57,255,20,0.6); border-radius:6px; color:#fff; padding:8px 14px; cursor:pointer; transition:all 0.25s ease; box-shadow:0 0 14px rgba(57,255,20,0.35); }
.delete-btn:hover { background:#39ff14; transform:scale(1.05); }
h3 { color:#c8ffd5; margin-bottom:10px; }
p { line-height:1.5; white-space:pre-line; }
.actions { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main>
  <h2 class="section-title">📨 Nachricht</h2>

  <div class="message-box" id="msg-box">
    <div class="header">
      <?php $icon = $rang_icons[$msg['sender_rang']] ?? 'default.png'; ?>
      <img src="../pics/icons/<?= htmlspecialchars($icon) ?>" class="avatar-icon" alt="">
      <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
      <span class="rang-badge"><?= htmlspecialchars($msg['sender_rang']) ?></span>
      <span style="margin-left:auto;opacity:.8;">📅 <?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?></span>
    </div>

    <h3><?= htmlspecialchars($msg['subject'] ?: '(Kein Betreff)') ?></h3>
    <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>

    <div class="actions">
      <a href="message_new.php?reply_to=<?= (int)$msg['sender_id'] ?>" class="btn-small">↩️ Antworten</a>
      <a href="messages.php" class="btn-small">⬅️ Zurück</a>

      <!-- 🗑️ Löschbutton -->
      <form method="POST" action="" id="deleteForm" style="display:inline;">
        <input type="hidden" name="delete_id" value="<?= (int)$msg['id'] ?>">
        <button type="submit" class="delete-btn">🗑️ Löschen</button>
      </form>
    </div>
  </div>
</main>

<script src="../script.js"></script>
<script>
document.getElementById('deleteForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  if (!confirm('Diese Nachricht wirklich löschen?')) return;
  const form = e.target;
  const data = new FormData(form);
  const res = await fetch('', { method: 'POST', body: data, headers: {'X-Requested-With':'XMLHttpRequest'} });
  if (res.ok) {
    const msgBox = document.getElementById('msg-box');
    msgBox.style.transition = '0.3s';
    msgBox.style.opacity = '0';
    setTimeout(() => window.location.href = 'messages.php', 400);
  } else {
    alert('Fehler beim Löschen.');
  }
});
</script>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
