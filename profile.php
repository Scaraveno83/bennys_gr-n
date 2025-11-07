<?php
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// Mitarbeiter-ID des eingeloggten Users holen
$logged_mitarbeiter_id = null;

if (!empty($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT mitarbeiter_id FROM user_accounts WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $loggedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $logged_mitarbeiter_id = $loggedUser['mitarbeiter_id'] ?? null;
}





// Wenn Profil-ID übergeben wurde → dieses Profil anzeigen
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $profil_id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM mitarbeiter WHERE id = ?");
    $stmt->execute([$profil_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

} else {
    // Sonst → eigenes Profil
    $stmt = $pdo->prepare("
      SELECT m.*
      FROM user_accounts u
      JOIN mitarbeiter m ON m.id = u.mitarbeiter_id
      WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$me) {
    die("Profil nicht gefunden.");
}

$bild = (!empty($me['bild_url']) && file_exists($me['bild_url']))
    ? $me['bild_url']
    : 'pics/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Mein Profil</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="profile-card">
  <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Profilbild">
  <h1><?= htmlspecialchars($me['name']) ?></h1>
  <?php
$rang_icons = [
  "Geschäftsführung"        => "gf.png",
  "Stv. Geschäftsleitung"   => "stv_leitung.png",
  "Personalleitung"         => "personalleitung.png",
  "Ausbilder/in"            => "ausbilder.png",
  "Tuner/in"                => "tuner.png",
  "Meister/in"              => "meister.png",
  "Mechaniker/in"           => "mechaniker.png",
  "Geselle/Gesellin"        => "geselle.png",
  "Azubi 3.Jahr"            => "azubi3.png",
  "Azubi 2.Jahr"            => "azubi2.png",
  "Azubi 1.Jahr"            => "azubi1.png",
  "Praktikant/in"           => "praktikant.png",
  "Administrator"           => "admin.png"
];

$icon = isset($rang_icons[$me['rang']]) ? "pics/icons/" . $rang_icons[$me['rang']] : null;
?>

<?php if ($icon && file_exists($icon)): ?>
  <p style="display:flex;align-items:center;justify-content:center;gap:8px;">
    <img src="<?= $icon ?>" alt="Rang" style="height:22px;">
    <strong><?= htmlspecialchars($me['rang']) ?></strong>
  </p>
<?php else: ?>
  <p><strong><?= htmlspecialchars($me['rang']) ?></strong></p>
<?php endif; ?>

  <?php if (!empty($me['beschreibung'])): ?>
  <p><?= nl2br(htmlspecialchars($me['beschreibung'])) ?></p>
  <?php endif; ?>

  <?php if (!empty($me['skills'])): ?><p><strong>Skills:</strong> <?= htmlspecialchars($me['skills']) ?></p><?php endif; ?>
  <?php if (!empty($me['status'])): ?><p><strong>Status:</strong> <?= htmlspecialchars($me['status']) ?></p><?php endif; ?>
  <?php if (!empty($me['email'])): ?><p><strong>E-Mail:</strong> <?= htmlspecialchars($me['email']) ?></p><?php endif; ?>
  <?php if (!empty($me['phone'])): ?><p><strong>Telefon:</strong> <?= htmlspecialchars($me['phone']) ?></p><?php endif; ?>

  <br>
<?php if ($me['id'] == $logged_mitarbeiter_id): ?>
    <a href="profile_edit.php" class="button-main">✏️ Profil bearbeiten</a>
<?php endif; ?>

</div>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
