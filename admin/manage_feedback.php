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

// --- Kennzahlen für Header ---
$totalComments = (int)$pdo->query("SELECT COUNT(*) FROM news_comments")->fetchColumn();
$totalReactions = (int)$pdo->query("SELECT COUNT(*) FROM news_reactions_user")->fetchColumn();
$newsWithFeedbackStmt = $pdo->query("
  SELECT COUNT(*)
  FROM (
    SELECT news_id FROM news_comments
    UNION
    SELECT news_id FROM news_reactions_user
  ) AS combined
");
$newsWithFeedback = (int)$newsWithFeedbackStmt->fetchColumn();

$latestComment = $pdo->query("SELECT MAX(created_at) FROM news_comments")->fetchColumn();
$latestReaction = $pdo->query("SELECT MAX(created_at) FROM news_reactions_user")->fetchColumn();
$activityCandidates = array_filter([$latestComment ?: null, $latestReaction ?: null]);
$lastActivity = $activityCandidates ? max($activityCandidates) : null;

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
$selectedNews = null;
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
    foreach ($newsList as $item) {
      if ((int)$item['id'] === $newsId) {
        $selectedNews = $item;
        break;
      }
    }
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
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="inventory-page feedback-page">
  <header class="inventory-header">
    <h1 class="inventory-title">⚙️ Feedback verwalten</h1>
    <p class="inventory-description">
      Kommentare und Reaktionen deiner News an einem Ort sichten, moderieren und analysieren.
    </p>
    <p class="inventory-info">
      Letzte Aktivität:
      <?= $lastActivity ? date('d.m.Y H:i \U\h\r', strtotime($lastActivity)) : 'Noch keine Rückmeldungen' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Kommentare gesamt</span>
        <span class="inventory-metric__value"><?= number_format($totalComments, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Alle News</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Reaktionen gesamt</span>
        <span class="inventory-metric__value"><?= number_format($totalReactions, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Einzelne Nutzeraktionen</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">News mit Feedback</span>
        <span class="inventory-metric__value"><?= number_format($newsWithFeedback, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Kommentare oder Reaktionen</span>
      </article>
    </div>
  </header>

  <nav class="inventory-tabs">
    <div class="inventory-tabs__group">
      <a href="?tab=comments" class="inventory-tab <?= $tab === 'comments' ? 'is-active' : '' ?>">
        <span>💬</span>
        <span>Kommentare</span>
        <span class="inventory-tab__count"><?= number_format($totalComments, 0, ',', '.') ?></span>
      </a>
      <a href="?tab=reactions" class="inventory-tab <?= $tab === 'reactions' ? 'is-active' : '' ?>">
        <span>📊</span>
        <span>Reaktionen</span>
        <span class="inventory-tab__count"><?= number_format($totalReactions, 0, ',', '.') ?></span>
      </a>
    </div>
  </nav>

  <?php if ($tab === 'comments'): ?>
    <section class="inventory-section">
      <h2>💬 Kommentare moderieren</h2>
      <p class="inventory-section__intro">
        Prüfe Rückmeldungen zu deinen News, bearbeite Inhalte oder entferne unangemessene Beiträge.
      </p>

      <?php if ($comments): ?>
        <div class="table-wrap">
          <table class="data-table feedback-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>News</th>
                <th>Verfasser</th>
                <th>Kommentar</th>
                <th>Eingang</th>
                <th>Aktionen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($comments as $c): ?>
                <tr>
                  <td>#<?= (int)$c['id'] ?></td>
                  <td>
                    <div class="feedback-news">
                      <strong><?= htmlspecialchars($c['news_title'] ?? 'Unbekannt') ?></strong>
                      <span class="chip">News-ID <?= (int)$c['news_id'] ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="feedback-author"><?= htmlspecialchars($c['name']) ?></span>
                  </td>
                  <td>
                    <div class="feedback-comment">
                      <div class="feedback-comment__text"><?= nl2br(htmlspecialchars($c['text'])) ?></div>
                      <div class="feedback-comment__actions">
                        <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small feedback-edit-toggle" onclick="toggleEdit(this)">✏️ Bearbeiten</button>
                        <form method="POST" action="" class="feedback-edit-form">
                          <textarea name="new_text" rows="3" required><?= htmlspecialchars($c['text']) ?></textarea>
                          <input type="hidden" name="edit_comment_id" value="<?= (int)$c['id'] ?>">
                          <div class="form-actions">
                            <button type="submit" class="inventory-submit inventory-submit--small">💾 Speichern</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </td>
                  <td><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
                  <td>
                    <form method="POST" action="" class="feedback-inline-form" onsubmit="return confirm('Diesen Kommentar löschen?');">
                      <input type="hidden" name="delete_comment_id" value="<?= (int)$c['id'] ?>">
                      <button type="submit" class="inventory-submit inventory-submit--danger inventory-submit--small">🗑️ Löschen</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="inventory-empty">Keine Kommentare vorhanden.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'reactions'): ?>
    <section class="inventory-section">
      <h2>📊 Reaktionen analysieren</h2>
      <p class="inventory-section__intro">
        Überblick über alle Nutzerreaktionen auf veröffentlichte News.
      </p>

      <?php if (empty($_GET['news_id'])): ?>
        <?php if ($newsList): ?>
          <div class="table-wrap">
            <table class="data-table feedback-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Titel</th>
                  <th>Sichtbar für</th>
                  <th>Gesamtreaktionen</th>
                  <th>Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($newsList as $n): ?>
                  <tr>
                    <td>#<?= (int)$n['id'] ?></td>
                    <td><?= htmlspecialchars($n['titel']) ?></td>
                    <td><?= htmlspecialchars($n['sichtbar_fuer']) ?></td>
                    <td><?= number_format((int)$n['total_reactions'], 0, ',', '.') ?></td>
                    <td>
                      <a href="?tab=reactions&amp;news_id=<?= (int)$n['id'] ?>" class="inventory-submit inventory-submit--small">Ansehen</a>
                      <form method="POST" action="" class="feedback-inline-form" onsubmit="return confirm('Alle Reaktionen zu dieser News löschen?');">
                        <input type="hidden" name="reset_news_id" value="<?= (int)$n['id'] ?>">
                        <button type="submit" class="inventory-submit inventory-submit--danger inventory-submit--small">Reset</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="inventory-empty">Keine News mit Reaktionen gefunden.</p>
        <?php endif; ?>
      <?php else: ?>
        <div class="form-actions">
          <a href="manage_feedback.php?tab=reactions" class="inventory-submit inventory-submit--ghost inventory-submit--small">← Zurück</a>
          <?php if ($selectedNews): ?>
            <span class="chip">News #<?= (int)$selectedNews['id'] ?> – <?= htmlspecialchars($selectedNews['titel']) ?></span>
            <span class="chip">Sichtbar: <?= htmlspecialchars($selectedNews['sichtbar_fuer']) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!empty($details['summary'])): ?>
          <div class="inventory-summary-grid">
            <?php foreach ($details['summary'] as $type => $count): ?>
              <article class="inventory-summary">
                <span class="inventory-summary__label"><?= htmlspecialchars($type) ?></span>
                <span class="inventory-summary__value"><?= number_format((int)$count, 0, ',', '.') ?></span>
                <span class="inventory-summary__hint">Abgegebene Reaktionen</span>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="inventory-empty">Keine Reaktionen vorhanden.</p>
        <?php endif; ?>

        <h3>Einzelne Reaktionen</h3>
        <?php if (!empty($details['users'])): ?>
          <div class="table-wrap">
            <table class="data-table feedback-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User-ID</th>
                  <th>IP</th>
                  <th>Typ</th>
                  <th>Datum</th>
                  <th>Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($details['users'] as $r): ?>
                  <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['user_id'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['ip'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['reaction_type']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                    <td>
                      <form method="POST" action="" class="feedback-inline-form" onsubmit="return confirm('Diese Reaktion löschen?');">
                        <input type="hidden" name="delete_reaction_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="news_id" value="<?= (int)$_GET['news_id'] ?>">
                        <button type="submit" class="inventory-submit inventory-submit--danger inventory-submit--small">🗑️ Löschen</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="inventory-empty">Keine Einzelreaktionen vorhanden.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <div class="form-actions">
    <a href="dashboard.php" class="inventory-submit inventory-submit--ghost">← Zurück zum Dashboard</a>
  </div>
</main>

<script>
function toggleEdit(button) {
  const form = button.nextElementSibling;
  if (!form) return;
  const isVisible = form.classList.toggle('is-visible');
  button.textContent = isVisible ? '❌ Abbrechen' : '✏️ Bearbeiten';
}
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
