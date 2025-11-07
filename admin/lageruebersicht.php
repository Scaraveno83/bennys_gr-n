<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';


/* === Funktion zum Laden eines Lagers === */
function ladeLager($pdo, $tabelle) {
  $stmt = $pdo->query("SELECT produkt, bestand FROM $tabelle ORDER BY produkt ASC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* === Lager laden === */
$hauptlager = ladeLager($pdo, 'hauptlager');
$azubilager = ladeLager($pdo, 'azubi_lager');
$buerolager = ladeLager($pdo, 'buero_lager');

/* === Alle Produkte zusammenführen === */
$gesamt = [];

// Funktion, um Bestand aufzusummieren
function addiereBestand(&$gesamt, $liste) {
  foreach ($liste as $item) {
    $produkt = $item['produkt'];
    $menge = (int)$item['bestand'];
    if (!isset($gesamt[$produkt])) $gesamt[$produkt] = 0;
    $gesamt[$produkt] += $menge;
  }
}

addiereBestand($gesamt, $hauptlager);
addiereBestand($gesamt, $azubilager);
addiereBestand($gesamt, $buerolager);
ksort($gesamt, SORT_NATURAL | SORT_FLAG_CASE);

/* === Summen berechnen === */
function summeBestand($lager) {
  $summe = 0;
  foreach ($lager as $eintrag) $summe += (int)$eintrag['bestand'];
  return $summe;
}

$summeHaupt = summeBestand($hauptlager);
$summeAzubi = summeBestand($azubilager);
$summeBuero = summeBestand($buerolager);
$summeGesamt = array_sum($gesamt);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📦 Gesamtlagerübersicht | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
main {
  max-width: 1400px;
  margin: 120px auto;
  padding: 20px;
  color: #fff;
  text-align: center;
}
h2, h3 { color: #76ff65; margin-bottom: 10px; }
section {
  background: rgba(25,25,25,0.9);
  border: 1px solid rgba(57,255,20,0.3);
  border-radius: 15px;
  padding: 25px;
  margin-bottom: 40px;
  box-shadow: 0 0 15px rgba(57,255,20,0.25);
}
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid rgba(57,255,20,0.3); padding: 8px; }
th { background: rgba(57,255,20,0.15); color: #76ff65; }
tr:hover { background: rgba(57,255,20,0.08); }
.summe {
  font-weight: bold;
  color: #76ff65;
  margin-top: 10px;
}
.btn-small {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  color: #fff;
  padding: 6px 12px;
  border-radius: 8px;
  text-decoration: none;
  transition: .3s;
}
.btn-small:hover {
  transform: scale(1.05);
  box-shadow: 0 0 10px rgba(57,255,20,0.5);
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 25px;
}
@media(max-width: 1000px) {
  .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>📦 Gesamtlagerübersicht</h2>
  <p>Hier siehst du alle Produkte aus <b>Hauptlager</b>, <b>Azubilager</b> und <b>Bürolager</b> zusammengefasst.</p>

  <!-- 🔢 Gesamtübersicht -->
  <section>
    <h3>📊 Zusammengefasste Gesamtbestände</h3>
    <table>
      <thead>
        <tr><th>Produkt</th><th>Gesamtbestand</th></tr>
      </thead>
      <tbody>
        <?php foreach ($gesamt as $produkt => $menge): ?>
          <tr>
            <td><?= htmlspecialchars($produkt) ?></td>
            <td><?= htmlspecialchars($menge) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="summe">🔹 Gesamt aller Produkte: <?= number_format($summeGesamt, 0, ',', '.') ?></p>
  </section>

  <!-- Einzel-Lager -->
  <div class="grid">

    <section>
      <h3>🏭 Hauptlager</h3>
      <table>
        <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
        <tbody>
          <?php foreach ($hauptlager as $item): ?>
            <tr><td><?= htmlspecialchars($item['produkt']) ?></td><td><?= htmlspecialchars($item['bestand']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="summe">Gesamt: <?= $summeHaupt ?></p>
      <a href="hauptlager_edit.php" class="btn-small">✏️ Bearbeiten</a>
    </section>

    <section>
      <h3>🧰 Azubilager</h3>
      <table>
        <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
        <tbody>
          <?php foreach ($azubilager as $item): ?>
            <tr><td><?= htmlspecialchars($item['produkt']) ?></td><td><?= htmlspecialchars($item['bestand']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="summe">Gesamt: <?= $summeAzubi ?></p>
      <a href="azubilager_edit.php" class="btn-small">✏️ Bearbeiten</a>
    </section>

    <section>
      <h3>🗂️ Bürolager</h3>
      <table>
        <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
        <tbody>
          <?php foreach ($buerolager as $item): ?>
            <tr><td><?= htmlspecialchars($item['produkt']) ?></td><td><?= htmlspecialchars($item['bestand']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="summe">Gesamt: <?= $summeBuero ?></p>
      <a href="buerolager_edit.php" class="btn-small">✏️ Bearbeiten</a>
    </section>

  </div>

  <div style="margin-top:40px;">
    <a href="dashboard.php" class="btn btn-ghost">← Zurück zum Dashboard</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
