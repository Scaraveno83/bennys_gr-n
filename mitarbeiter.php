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
<link rel="stylesheet" href="profile.css">
<style>
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 24px;
  max-width: 1200px;
  margin: 140px auto;
  padding: 20px;
}
.team-card {
  background: #1b1b1b;
  border: 1px solid rgba(57,255,20,0.35);
  border-radius: 18px;
  padding: 24px;
  text-align: center;
  box-shadow: 0 0 14px rgba(0,0,0,0.5);
  transition: 0.3s;
}
.team-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 0 22px rgba(57,255,20,0.4);
}
.team-card img.avatar {
  width: 150px;
  height: 150px;
  object-fit: cover;
  border-radius: 14px;
  border: 2px solid rgba(57,255,20,0.6);
  box-shadow: 0 0 10px rgba(57,255,20,0.3);
  margin-bottom: 14px;
}
.team-card .rang {
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  color:#a8ffba;
  font-weight:bold;
}
.team-card .rang img { height:20px; }
.team-card .button-main { margin-top:14px; }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="team-grid">
<?php foreach ($team as $m): 
    $bild = (!empty($m['bild_url']) && file_exists($m['bild_url'])) ? $m['bild_url'] : 'pics/default-avatar.png';
    $icon = isset($rang_icons[$m['rang']]) ? 'pics/icons/' . $rang_icons[$m['rang']] : null;
?>
  <div class="team-card">
    <img src="<?= htmlspecialchars($bild) ?>" class="avatar" alt="Avatar">
    <h2><?= htmlspecialchars($m['name']) ?></h2>

    <?php if ($icon && file_exists($icon)): ?>
      <div class="rang">
        <img src="<?= $icon ?>" alt="">
        <?= htmlspecialchars($m['rang']) ?>
      </div>
    <?php else: ?>
      <div class="rang"><?= htmlspecialchars($m['rang']) ?></div>
    <?php endif; ?>

    <?php if (!empty($m['status'])): ?>
      <p style="color:#d4d4d4;"><?= htmlspecialchars($m['status']) ?></p>
    <?php endif; ?>

    <a href="profile.php?id=<?= $m['id'] ?>" class="button-main">Profil ansehen</a>
  </div>
<?php endforeach; ?>
</div>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
