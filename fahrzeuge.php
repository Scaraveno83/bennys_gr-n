<?php
// --- DEBUG optional ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';

// Alle Fahrzeuge laden
$stmt = $pdo->query("SELECT * FROM fahrzeuge ORDER BY fahrzeug_typ ASC, id ASC");
$fahrzeuge = $stmt->fetchAll();
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

<style>
main {
  padding: 120px 40px 80px;
  max-width: 1200px;
  margin: 0 auto;
}

.vehicles-section {
  text-align: center;
}

.vehicle-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 25px;
  margin-top: 40px;
}

.vehicle-card {
  background: rgba(25,25,25,0.8);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 20px;
  text-align: left;
  color: #f5f5f5;
  box-shadow: 0 0 20px rgba(57,255,20,0.25);
  backdrop-filter: blur(10px);
  transition: all 0.4s ease;
}
.vehicle-card:hover {
  transform: translateY(-5px) scale(1.02);
  box-shadow: 0 0 30px rgba(57,255,20,0.5);
}

.vehicle-card h3 {
  margin-top: 0;
  color: #76ff65;
  text-shadow: 0 0 15px rgba(57,255,20,0.8);
}

.vehicle-info {
  margin-top: 10px;
  font-size: 0.95rem;
  line-height: 1.5;
}

.vehicle-info strong {
  color: #39ff14;
}

.pruefdatum {
  margin-top: 10px;
  font-size: 0.9rem;
  padding: 6px 10px;
  border-radius: 6px;
  display: inline-block;
  background: rgba(57,255,20,0.15);
  box-shadow: 0 0 8px rgba(57,255,20,0.4);
}

.pruefdatum.warn {
  background: rgba(57,255,20,0.3);
  box-shadow: 0 0 15px rgba(57,255,20,0.6);
  color: #fff;
  font-weight: bold;
  text-shadow: 0 0 6px rgba(57,255,20,0.8);
}
</style>
</head>

<body>
<?php include 'header.php'; ?>

<main>
  <section class="vehicles-section">
    <h2 class="section-title">🚗 Unsere Firmenfahrzeuge</h2>
    <p>Hier siehst du die aktuelle Einteilung und den Zustand unserer Fahrzeuge.</p>

    <?php if ($fahrzeuge): ?>
      <div class="vehicle-grid">
        <?php foreach ($fahrzeuge as $fz): 
          $warnung = false;
          if ($fz['pruefdatum']) {
            $diff = (strtotime($fz['pruefdatum']) - time()) / (60*60*24);
            if ($diff <= 30) $warnung = true;
          }
        ?>
          <div class="vehicle-card">
            <h3><?= htmlspecialchars($fz['fahrzeug_typ']) ?></h3>
            <div class="vehicle-info">
              <p><strong>Kennzeichen:</strong> <?= htmlspecialchars($fz['kennzeichen']) ?></p>
              <p><strong>Fahrer:</strong> <?= htmlspecialchars($fz['fahrer'] ?: '–') ?></p>
              <p><strong>Tankstand:</strong> <?= htmlspecialchars($fz['tankstand'] ?: '–') ?></p>
              <p><strong>Beschädigungen:</strong><br><?= nl2br(htmlspecialchars($fz['beschaedigungen'] ?: 'Keine')) ?></p>
              <?php if ($fz['pruefdatum']): ?>
                <div class="pruefdatum <?= $warnung ? 'warn' : '' ?>">
                  Prüfdatum: <?= htmlspecialchars(date('d.m.Y', strtotime($fz['pruefdatum']))) ?>
                  <?= $warnung ? '⚠' : '' ?>
                </div>
              <?php else: ?>
                <div class="pruefdatum">Prüfdatum: –</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>Aktuell sind keine Fahrzeuge in der Datenbank eingetragen.</p>
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
