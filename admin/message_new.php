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

$reply_to = (int)($_GET['reply_to'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $receiver = (int)$_POST['receiver'];
  $subject = trim($_POST['subject']);
  $message = trim($_POST['message']);

  $stmt = $pdo->prepare("INSERT INTO user_messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
  $stmt->execute([$userId, $receiver, $subject, $message]);

  header("Location: messages.php?tab=sent");
  exit;
}

/* === Empfänger-Liste abrufen ===
   Enthält jetzt:
   - Alle Mitarbeiter (via mitarbeiter)
   - Admins (auch ohne Mitarbeiter-Zuordnung)
*/
$stmt = $pdo->query("
  SELECT ua.id, 
         COALESCE(m.name, ua.username) AS name,
         COALESCE(m.rang, 'Administrator') AS rang
  FROM user_accounts ua
  LEFT JOIN mitarbeiter m ON ua.mitarbeiter_id = m.id
  WHERE ua.active = 1
  ORDER BY m.name IS NULL, m.name ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Neue Nachricht</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main { max-width: 800px; margin: 100px auto; padding: 0 20px; color: #fff; }
form { background: rgba(25,25,25,0.8); padding: 20px; border-radius: 10px; border: 1px solid rgba(57,255,20,0.4); }
label { display:block; margin-top:10px; font-weight:600; color:#c8ffd5; }
select, input, textarea {
  width:100%; padding:8px;
  border-radius:6px; background:#111; color:#fff;
  border:1px solid rgba(57,255,20,0.3);
}
button {
  margin-top:15px; padding:10px 18px;
  background:linear-gradient(90deg,#39ff14,#76ff65);
  border:none; border-radius:8px;
  color:#fff; cursor:pointer; font-weight:600;
}
button:hover { box-shadow:0 0 10px rgba(57,255,20,0.7); transform:scale(1.03); }
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main>
  <h2 class="section-title">📝 Neue Nachricht schreiben</h2>

  <form method="POST" action="">
    <label>Empfänger:</label>
    <select name="receiver" required>
      <option value="">– Empfänger auswählen –</option>
      <?php foreach ($users as $u): if ($u['id'] != $userId): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $reply_to === (int)$u['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['rang']) ?>)
        </option>
      <?php endif; endforeach; ?>
    </select>

    <label>Betreff:</label>
    <input type="text" name="subject" maxlength="255" placeholder="Betreff (optional)">

    <label>Nachricht:</label>
    <textarea name="message" rows="6" required placeholder="Nachricht eingeben..."></textarea>

    <button type="submit">📤 Senden</button>
  </form>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
