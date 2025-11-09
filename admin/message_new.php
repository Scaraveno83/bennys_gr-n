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
$reply_subject = trim($_GET['reply_subject'] ?? '');

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
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="inventory-page message-compose-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📝 Nachricht verfassen</h1>
    <p class="inventory-description">Starte ein neues Gespräch mit einem Kollegen oder antworte auf eine vorhandene Nachricht.</p>
    <p class="inventory-info">Wähle einen Empfänger, ergänze Betreff und Text – den Rest erledigt das System automatisch.</p>
  </header>

  <section class="inventory-section">
    <form method="POST" action="" class="message-compose-form">
      <div class="message-compose-field">
        <label for="receiver">Empfänger</label>
        <select name="receiver" id="receiver" required>
          <option value="">– Empfänger auswählen –</option>
          <?php foreach ($users as $u): if ($u['id'] != $userId): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $reply_to === (int)$u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['rang']) ?>)
            </option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <div class="message-compose-field">
        <label for="subject">Betreff <span class="field-hint">optional</span></label>
        <input type="text" name="subject" id="subject" maxlength="255" placeholder="Betreff hinzufügen" value="<?= htmlspecialchars($reply_subject) ?>">
      </div>

      <div class="message-compose-field">
        <label for="message">Nachricht</label>
        <textarea name="message" id="message" rows="8" required placeholder="Nachricht eingeben..."></textarea>
      </div>

      <div class="message-detail__actions message-compose-actions">
        <button type="submit" class="inventory-submit">📤 Senden</button>
        <a href="messages.php" class="inventory-submit inventory-submit--ghost">⬅️ Abbrechen</a>
      </div>
    </form>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>