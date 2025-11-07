<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';


$stmt = $pdo->prepare("SELECT * FROM content WHERE section = 'team' LIMIT 1");
$stmt->execute();
$team = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $text = trim($_POST['text'] ?? '');

    if ($team) {
        $update = $pdo->prepare("UPDATE content SET title = ?, text = ? WHERE section = 'team'");
        $update->execute([$title, $text]);
    } else {
        $insert = $pdo->prepare("INSERT INTO content (section, title, text) VALUES ('team', ?, ?)");
        $insert->execute([$title, $text]);
    }

    header("Location: edit_team.php?saved=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team bearbeiten | Benny’s Werkstatt</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<?php include '../includes/admin_form_style.php'; ?>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2 class="section-title">👨‍🔧 „Unser Team“ bearbeiten</h2>
  <?php if (isset($_GET['saved'])): ?>
  <p class="success">✅ Änderungen wurden erfolgreich gespeichert.</p>
  <?php endif; ?>
  <form method="POST">
    <label for="title">Titel:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($team['title'] ?? '') ?>" required>
    <label for="text">Text:</label>
    <textarea id="text" name="text" required><?= htmlspecialchars($team['text'] ?? '') ?></textarea>
    <button type="submit">💾 Speichern</button>
  </form>
  <a class="back-link" href="dashboard.php">← Zurück zum Dashboard</a>
</main>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
