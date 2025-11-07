<?php
// admin/manage_feedback.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';

// --- Aktiver Tab ---
$tab = $_GET['tab'] ?? 'comments';

// --- Kommentar löschen ---
if (isset($_POST['delete_comment_id'])) {
  $id = (int)$_POST['delete_comment_id'];
  $pdo->prepare("DELETE FROM news_comments WHERE id = ?")->execute([$id]);
  header("Location: manage_feedback.php?tab=comments");
  exit;
}

// --- Kommentar bearbeiten ---
if (isset($_POST['edit_comment_id']) && !empty($_POST['new_text'])) {
  $id = (int)$_POST['edit_comment_id'];
  $text = trim($_POST['new_text']);
  $pdo->prepare("UPDATE news_comments SET text = ? WHERE id = ?")->execute([$text, $id]);
  header("Location: manage_feedback.php?tab=comments");
  exit;
}

// --- Reaktion löschen ---
if (isset($_POST['delete_reaction_id'])) {
  $id = (int)$_POST['delete_reaction_id'];
  $pdo->prepare("DELETE FROM news_reactions_user WHERE id = ?")->execute([$id]);
  header("Location: manage_feedback.php?tab=reactions&news_id=" . (int)$_POST['news_id']);
  exit;
}

// --- Reaktionen resetten ---
if (isset($_POST['reset_news_id'])) {
  $id = (int)$_POST['reset_news_id'];
  $pdo->prepare("DELETE FROM news_reactions_user WHERE news_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM news_reactions WHERE news_id = ?")->execute([$id]);
  header("Location: manage_feedback.php?tab=reactions");
  exit;
}

// --- Daten laden ---
$comments = [];
if ($tab === 'comments') {
  $stmt = $pdo->query("
    SELECT c.id, c.news_id, c.name, c.text, c.created_at, n.titel AS news_title
    FROM news_comments c
    LEFT JOIN news n ON c.news_id = n.id
    ORDER BY c.created_at DESC
  ");
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$newsList = [];
$details = [];
if ($tab === 'reactions') {
  $stmt = $pdo->query("
    SELECT n.id, n.titel, n.sichtbar_fuer,
           COALESCE(SUM(r.count), 0) AS total_reactions
    FROM news n
    LEFT JOIN news_reactions r ON n.id = r.news_id
    GROUP BY n.id
    ORDER BY n.erstellt_am DESC
  ");
  $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!empty($_GET['news_id'])) {
    $newsId = (int)$_GET['news_id'];
    $rStmt = $pdo->prepare("SELECT reaction_type, count FROM news_reactions WHERE news_id = ?");
    $rStmt->execute([$newsId]);
    $summary = $rStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $uStmt = $pdo->prepare("
      SELECT id, user_id, ip, reaction_type, created_at
      FROM news_reactions_user
      WHERE news_id = ?
      ORDER BY created_at DESC
    ");
    $uStmt->execute([$newsId]);
    $details = [
      'summary' => $summary,
      'users'   => $uStmt->fetchAll(PDO::FETCH_ASSOC)
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Admin – Feedback verwalten</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main {
  max-width: 1100px;
  margin: 100px auto;
  padding: 0 20px;
  color: #fff;
}
.tabs {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 25px;
}
.tabs a {
  padding: 10px 20px;
  border: 2px solid #39ff14;
  border-radius: 8px;
  color: #a8ffba;
  text-decoration: none;
  font-weight: bold;
  box-shadow: 0 0 10px rgba(57,255,20,0.4);
  transition: all 0.3s ease;
}
.tabs a.active {
  background: linear-gradient(90deg, #39ff14, #76ff65);
  color: #fff;
}
.tabs a:hover {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  color: #fff;
  transform: scale(1.05);
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}
th, td {
  padding: 10px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  vertical-align: top;
}
th {
  background: rgba(57,255,20,0.2);
  color: #c8ffd5;
}
tr:hover {
  background: rgba(57,255,20,0.05);
}
textarea {
  width: 100%;
  background: rgba(25,25,25,0.9);
  color: #fff;
  border: 1px solid rgba(57,255,20,0.3);
  border-radius: 6px;
  padding: 6px;
}
.btn-small {
  padding: 6px 10px;
  background: linear-gradient(90deg,#39ff14,#76ff65);
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: .3s;
}
.btn-small:hover {
  box-shadow: 0 0 10px rgba(57,255,20,0.7);
}
.edit-form { display: none; margin-top: 6px; }
.edit-btn {
  background: none;
  border: none;
  color: #c8ffd5;
  cursor: pointer;
  font-size: 0.9rem;
}
.edit-btn:hover { text-decoration: underline; }
.summary-box {
  background: rgba(25,25,25,0.8);
  border: 1px solid rgba(57,255,20,0.3);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 20px;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main>
  <h2 class="section-title">⚙️ Feedback verwalten</h2>

  <div class="tabs">
    <a href="?tab=comments" class="<?= $tab === 'comments' ? 'active' : '' ?>">💬 Kommentare</a>
    <a href="?tab=reactions" class="<?= $tab === 'reactions' ? 'active' : '' ?>">📊 Reaktionen</a>
  </div>

  <!-- Kommentare -->
  <?php if ($tab === 'comments'): ?>
    <?php if ($comments): ?>
      <table>
        <tr>
          <th>ID</th><th>News</th><th>Name</th><th>Kommentar</th><th>Datum</th><th>Aktionen</th>
        </tr>
        <?php foreach ($comments as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><strong><?= htmlspecialchars($c['news_title'] ?? 'Unbekannt') ?></strong><br><small>#<?= (int)$c['news_id'] ?></small></td>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td>
            <div class="comment-text"><?= nl2br(htmlspecialchars($c['text'])) ?></div>
            <button class="edit-btn" onclick="toggleEdit(this)">✏️ Bearbeiten</button>
            <form method="POST" action="" class="edit-form">
              <textarea name="new_text" rows="3" required><?= htmlspecialchars($c['text']) ?></textarea>
              <input type="hidden" name="edit_comment_id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn-small">💾 Speichern</button>
            </form>
          </td>
          <td><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
          <td>
            <form method="POST" action="">
              <input type="hidden" name="delete_comment_id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn-small" onclick="return confirm('Diesen Kommentar löschen?')">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>Keine Kommentare vorhanden.</p>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Reaktionen -->
  <?php if ($tab === 'reactions'): ?>
    <?php if (empty($_GET['news_id'])): ?>
      <table>
        <tr><th>ID</th><th>Titel</th><th>Sichtbar für</th><th>Gesamtreaktionen</th><th>Aktionen</th></tr>
        <?php foreach ($newsList as $n): ?>
        <tr>
          <td><?= (int)$n['id'] ?></td>
          <td><?= htmlspecialchars($n['titel']) ?></td>
          <td><?= htmlspecialchars($n['sichtbar_fuer']) ?></td>
          <td><?= (int)$n['total_reactions'] ?></td>
          <td>
            <a href="?tab=reactions&news_id=<?= (int)$n['id'] ?>" class="btn-small">Ansehen</a>
            <form method="POST" action="" style="display:inline;">
              <input type="hidden" name="reset_news_id" value="<?= (int)$n['id'] ?>">
              <button type="submit" class="btn-small" onclick="return confirm('Alle Reaktionen zu dieser News löschen?')">Reset</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <a href="manage_feedback.php?tab=reactions" class="btn-small">← Zurück</a>
      <div class="summary-box">
        <h3>Zusammenfassung</h3>
        <?php if (!empty($details['summary'])): ?>
          <?php foreach ($details['summary'] as $type => $count): ?>
            <p><?= htmlspecialchars($type) ?>: <?= (int)$count ?></p>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Keine Reaktionen vorhanden.</p>
        <?php endif; ?>
      </div>
      <h3>Einzelne Reaktionen</h3>
      <?php if (!empty($details['users'])): ?>
        <table>
          <tr><th>ID</th><th>User-ID</th><th>IP</th><th>Typ</th><th>Datum</th><th>Aktion</th></tr>
          <?php foreach ($details['users'] as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['user_id'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['ip'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['reaction_type']) ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td>
              <form method="POST" action="">
                <input type="hidden" name="delete_reaction_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="news_id" value="<?= (int)$_GET['news_id'] ?>">
                <button type="submit" class="btn-small" onclick="return confirm('Diese Reaktion löschen?')">🗑️</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <p>Keine Einzelreaktionen vorhanden.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
  <div style="margin-top:40px;">
    <a href="dashboard.php" class="btn btn-ghost">← Zurück zum Dashboard</a>
  </div>
</main>

<script>
function toggleEdit(btn) {
  const form = btn.nextElementSibling;
  if (!form) return;
  form.style.display = form.style.display === 'block' ? 'none' : 'block';
  btn.textContent = form.style.display === 'block' ? '❌ Abbrechen' : '✏️ Bearbeiten';
}
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
