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

$subject = $msg['subject'] ?: '(Kein Betreff)';
$replySubject = preg_match('/^re:/i', $subject) ? $subject : 'Re: ' . $subject;
$backUrl = 'messages.php' . ($msg['sender_id'] == $userId ? '?tab=sent' : '?tab=inbox');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($subject) ?></title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="inventory-page message-view-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📨 Nachricht ansehen</h1>
    <p class="inventory-description">Betreff: <?= htmlspecialchars($subject) ?></p>
    <p class="inventory-info">Gesendet am <?= date('d.m.Y H:i', strtotime($msg['sent_at'])) ?></p>
  </header>

  <section class="inventory-section">
    <article class="message-detail" id="msg-box">
      <div class="message-detail__participants">
        <?php $senderIcon = $rang_icons[$msg['sender_rang']] ?? 'default.png'; ?>
        <div class="message-participant-card">
          <span class="message-participant-card__label">Von</span>
          <img src="../pics/icons/<?= htmlspecialchars($senderIcon) ?>" class="message-participant-card__avatar" alt="">
          <div class="message-participant-card__meta">
            <span class="message-participant-card__name"><?= htmlspecialchars($msg['sender_name']) ?></span>
            <span class="rang-badge"><?= htmlspecialchars($msg['sender_rang']) ?></span>
          </div>
        </div>

        <?php $receiverIcon = $rang_icons[$msg['receiver_rang']] ?? 'default.png'; ?>
        <div class="message-participant-card message-participant-card--receiver">
          <span class="message-participant-card__label">An</span>
          <img src="../pics/icons/<?= htmlspecialchars($receiverIcon) ?>" class="message-participant-card__avatar" alt="">
          <div class="message-participant-card__meta">
            <span class="message-participant-card__name"><?= htmlspecialchars($msg['receiver_name']) ?></span>
            <span class="rang-badge"><?= htmlspecialchars($msg['receiver_rang']) ?></span>
          </div>
        </div>
      </div>

      <div class="message-detail__subject">
        <h2><?= htmlspecialchars($subject) ?></h2>
      </div>

      <div class="message-detail__body">
        <?= nl2br(htmlspecialchars($msg['message'])) ?>
      </div>

      <div class="message-detail__actions">
        <a href="message_new.php?reply_to=<?= (int)$msg['sender_id'] ?>&reply_subject=<?= urlencode($replySubject) ?>" class="inventory-submit inventory-submit--small">↩️ Antworten</a>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="inventory-submit inventory-submit--small inventory-submit--ghost">⬅️ Zurück</a>
        <form method="POST" action="" id="deleteForm" class="message-detail__delete-form">
          <input type="hidden" name="delete_id" value="<?= (int)$msg['id'] ?>">
          <button type="submit" class="inventory-submit inventory-submit--small inventory-submit--danger">🗑️ Löschen</button>
        </form>
      </div>
    </article>
  </section>
</main>

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
    setTimeout(() => window.location.href = '<?= $backUrl ?>', 400);
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