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

$skillsList = [];
if (!empty($me['skills'])) {
    $skillsList = array_filter(array_map('trim', preg_split('/[,;\n]+/', $me['skills'])));
}

$metrics = [];
$metrics[] = [
    'label' => 'Rang',
    'value' => $me['rang'] ?: 'Unbekannt',
    'hint'  => null,
];

if (!empty($me['status'])) {
    $metrics[] = [
        'label' => 'Status',
        'value' => $me['status'],
        'hint'  => 'Aktuelle Einsatzlage',
    ];
}

if (!empty($skillsList)) {
    $skillsPreview = array_slice($skillsList, 0, 3);
    $skillsHint = implode(', ', $skillsPreview);
    if (count($skillsList) > count($skillsPreview)) {
        $skillsHint .= ' …';
    }

    $metrics[] = [
        'label' => 'Kompetenzen',
        'value' => count($skillsList),
        'hint'  => $skillsHint ?: null,
    ];
}

if (!empty($me['phone'])) {
    $metrics[] = [
        'label' => 'Telefon',
        'value' => $me['phone'],
        'hint'  => 'Direkter Draht',
    ];
}

$contactItems = [
    'E-Mail' => !empty($me['email']) ? $me['email'] : null,
    'Telefon' => !empty($me['phone']) ? $me['phone'] : null,
    'Rang' => !empty($me['rang']) ? $me['rang'] : null,
];
?>

<main class="inventory-page profile-page">
  <header class="inventory-header profile-header">
    <div class="profile-header__avatar">
      <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Profilbild">
    </div>
    <div class="profile-header__content">
      <h1 class="inventory-title">👤 <?= htmlspecialchars($me['name']) ?></h1>

      <div class="profile-rank">
        <?php if ($icon && file_exists($icon)): ?>
          <img src="<?= htmlspecialchars($icon) ?>" alt="Rang" class="profile-rank__icon">
        <?php endif; ?>
        <span class="profile-rank__title"><?= htmlspecialchars($me['rang'] ?: 'Unbekannter Rang') ?></span>
      </div>

      <?php if (!empty($me['status'])): ?>
        <p class="inventory-description profile-status"><?= htmlspecialchars($me['status']) ?></p>
      <?php endif; ?>

      <?php if (!empty($metrics)): ?>
        <div class="inventory-metrics profile-metrics">
          <?php foreach ($metrics as $metric): ?>
            <div class="inventory-metric">
              <span class="inventory-metric__label"><?= htmlspecialchars($metric['label']) ?></span>
              <span class="inventory-metric__value"><?= htmlspecialchars($metric['value']) ?></span>
              <?php if (!empty($metric['hint'])): ?>
                <span class="inventory-metric__hint"><?= htmlspecialchars($metric['hint']) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!empty($me['beschreibung'])): ?>
    <section class="inventory-section profile-bio">
      <h2>Über <?= htmlspecialchars($me['name']) ?></h2>
      <p><?= nl2br(htmlspecialchars($me['beschreibung'])) ?></p>
    </section>
  <?php endif; ?>

  <?php if (!empty($skillsList)): ?>
    <section class="inventory-section profile-skills">
      <h2>Kompetenzen</h2>
      <ul class="profile-skills__list">
        <?php foreach ($skillsList as $skill): ?>
          <li><?= htmlspecialchars($skill) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if (array_filter($contactItems)): ?>
    <section class="inventory-section profile-contact">
      <h2>Kontakt &amp; Details</h2>
      <div class="profile-details-grid">
        <?php foreach ($contactItems as $label => $value): ?>
          <?php if (!$value) { continue; } ?>
          <div class="profile-detail">
            <span class="profile-detail__label"><?= htmlspecialchars($label) ?></span>
            <?php if ($label === 'E-Mail'): ?>
              <a class="profile-detail__value" href="mailto:<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></a>
            <?php else: ?>
              <span class="profile-detail__value"><?= htmlspecialchars($value) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($me['id'] == $logged_mitarbeiter_id): ?>
    <section class="inventory-section profile-actions">
      <h2>Aktionen</h2>
      <p>Aktualisiere deine Angaben und halte dein Profil auf dem neuesten Stand.</p>
      <a href="profile_edit.php" class="button-main">✏️ Profil bearbeiten</a>
    </section>
  <?php endif; ?>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
