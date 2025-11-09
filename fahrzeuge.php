<?php
// --- DEBUG optional ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';

// Alle Fahrzeuge laden
$stmt = $pdo->query("SELECT * FROM fahrzeuge ORDER BY fahrzeug_typ ASC, id ASC");
$fahrzeuge = $stmt->fetchAll();

$gesamtFahrzeuge = count($fahrzeuge);
$fahrzeugeMitFahrer = 0;
$pruefungenFaellig = 0;

foreach ($fahrzeuge as $fz) {
    if (!empty(trim($fz['fahrer'] ?? ''))) {
        $fahrzeugeMitFahrer++;
    }

    if (!empty($fz['pruefdatum'])) {
        $diffTage = (strtotime($fz['pruefdatum']) - time()) / 86400;
        if ($diffTage <= 30) {
            $pruefungenFaellig++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Fahrzeuge – Benny's Werkstatt</title>

<meta name="description" content="Übersicht über die Firmenfahrzeuge von Benny’s Werkstatt – inklusive Fahrzeugtyp, Fahrer, Tankstand und Prüfdatum." />
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="header.css" />
<link rel="stylesheet" href="styles.css" />
</head>

<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🚗 Unsere Firmenfahrzeuge</h1>
    <p class="inventory-description">
      Status, Fahrer und Prüftermine aller Flottenfahrzeuge im direkten Überblick.
    </p>

    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Fahrzeuge gesamt</span>
        <span class="inventory-metric__value"><?= $gesamtFahrzeuge ?></span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Zugewiesene Fahrer:innen</span>
        <span class="inventory-metric__value"><?= $fahrzeugeMitFahrer ?></span>
        <span class="inventory-metric__hint">aktive Einsatzbereitschaft</span>
      </div>
      <div class="inventory-metric <?= $pruefungenFaellig ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Prüfungen &lt; 30 Tage</span>
        <span class="inventory-metric__value"><?= $pruefungenFaellig ?></span>
        <span class="inventory-metric__hint">rechtzeitig koordinieren</span>
      </div>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Flottenübersicht</h2>
    <p class="inventory-section__intro">
      Alle Informationen sind für schnelle Service- und TÜV-Abstimmungen optimiert.
    </p>

    <?php if ($fahrzeuge): ?>
      <div class="vehicle-grid">
        <?php foreach ($fahrzeuge as $fz):
          $warnung = false;
          if ($fz['pruefdatum']) {
            $diff = (strtotime($fz['pruefdatum']) - time()) / (60*60*24);
            if ($diff <= 30) $warnung = true;
          }
        ?>
          <article class="card glass vehicle-card">
            <h3><?= htmlspecialchars($fz['fahrzeug_typ']) ?></h3>
            <div class="vehicle-info">
              <p><strong>Kennzeichen:</strong> <?= htmlspecialchars($fz['kennzeichen']) ?></p>
              <p><strong>Fahrer:in:</strong> <?= htmlspecialchars($fz['fahrer'] ?: '–') ?></p>
              <p><strong>Tankstand:</strong> <?= htmlspecialchars($fz['tankstand'] ?: '–') ?></p>
              <p><strong>Beschädigungen:</strong><br><?= nl2br(htmlspecialchars($fz['beschaedigungen'] ?: 'Keine')) ?></p>
              <?php if ($fz['pruefdatum']): ?>
                <span class="pruefdatum <?= $warnung ? 'warn' : '' ?>">
                  Prüfdatum: <?= htmlspecialchars(date('d.m.Y', strtotime($fz['pruefdatum']))) ?>
                  <?= $warnung ? '⚠' : '' ?>
                </span>
              <?php else: ?>
                <span class="pruefdatum">Prüfdatum: –</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="inventory-empty">Aktuell sind keine Fahrzeuge in der Datenbank eingetragen.</p>
    <?php endif; ?>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="script.js"></script>
</body>
</html>
