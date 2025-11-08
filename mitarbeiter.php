<?php
session_start();
require_once 'includes/db.php';


// mitarbeiter laden
$stmt = $pdo->query("SELECT * FROM mitarbeiter ORDER BY name ASC");
$team = $stmt->fetchAll(PDO::FETCH_ASSOC);

// rang icons mapping
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

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Team Übersicht</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="page-shell">
  <header class="page-header">
    <h1 class="page-title">Unser Team</h1>
    <p class="page-subtitle">
      Lerne die Crew hinter Benny’s Werkstatt kennen – von Geschäftsführung bis Azubi. Jeder Avatar führt dich direkt
      zum Profil der Kolleginnen und Kollegen.
    </p>
  </header>

  <section class="section-stack">
    <div class="card-grid flexible">
<?php foreach ($team as $m):
    $bild = (!empty($m['bild_url']) && file_exists($m['bild_url'])) ? $m['bild_url'] : 'pics/default-avatar.png';
    $icon = isset($rang_icons[$m['rang']]) ? 'pics/icons/' . $rang_icons[$m['rang']] : null;
?>
  <article class="card glass team-card">
        <img src="<?= htmlspecialchars($bild) ?>" class="avatar" alt="Avatar von <?= htmlspecialchars($m['name']) ?>">
        <h2 class="headline-glow"><?= htmlspecialchars($m['name']) ?></h2>

    <?php if ($icon && file_exists($icon)): ?>
      <span class="rang">
          <img src="<?= $icon ?>" alt="">
          <?= htmlspecialchars($m['rang']) ?>
        </span>
    <?php else: ?>
        <span class="rang"><?= htmlspecialchars($m['rang']) ?></span>
    <?php endif; ?>

    <?php if (!empty($m['status'])): ?>
      <p style="color:#d4d4d4;"><?= htmlspecialchars($m['status']) ?></p>
    <?php endif; ?>

    <a href="profile.php?id=<?= $m['id'] ?>" class="button-main">Profil ansehen</a>
      </article>
<?php endforeach; ?>
</div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
