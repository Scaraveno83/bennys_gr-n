<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'includes/db.php';

/* === Zugriff prüfen (Admin oder Mitarbeiter) === */
if (
    empty($_SESSION['mitarbeiter_name']) &&
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: admin/login.php");
    exit;
}

$nutzername = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Unbekannt';

/* === Produkte abrufen === */
$produkte = $pdo->query("SELECT * FROM kuehlschrank_lager ORDER BY kategorie, produkt")->fetchAll(PDO::FETCH_ASSOC);

/* === Entnahme buchen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produkt_id'])) {
  $id = (int)$_POST['produkt_id'];
  $menge = (int)$_POST['menge'];

  $stmt = $pdo->prepare("SELECT * FROM kuehlschrank_lager WHERE id = ?");
  $stmt->execute([$id]);
  $produkt = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($produkt && $menge > 0 && $produkt['bestand'] >= $menge) {
    $preis_einzel = (float)$produkt['preis'];
    $gesamtpreis = $preis_einzel * $menge;

    // Bestand verringern
    $pdo->prepare("UPDATE kuehlschrank_lager SET bestand = bestand - ? WHERE id = ?")
        ->execute([$menge, $id]);

    // Verlauf speichern
    $pdo->prepare("INSERT INTO kuehlschrank_verlauf (mitarbeiter, produkt, menge, preis_einzel, gesamtpreis, datum)
                   VALUES (?, ?, ?, ?, ?, NOW())")
        ->execute([$nutzername, $produkt['produkt'], $menge, $preis_einzel, $gesamtpreis]);

    // Wochenkosten aktualisieren
    $heute = date('Y-m-d');
    $montag = date('Y-m-d', strtotime('monday this week'));
    $sonntag = date('Y-m-d', strtotime('sunday this week'));

    $check = $pdo->prepare("SELECT id FROM kuehlschrank_wochenkosten WHERE mitarbeiter = ? AND woche_start = ?");
    $check->execute([$nutzername, $montag]);
    if ($check->fetchColumn()) {
      $pdo->prepare("UPDATE kuehlschrank_wochenkosten SET gesamt_kosten = gesamt_kosten + ? 
                     WHERE mitarbeiter = ? AND woche_start = ?")
          ->execute([$gesamtpreis, $nutzername, $montag]);
    } else {
      $pdo->prepare("INSERT INTO kuehlschrank_wochenkosten (mitarbeiter, gesamt_kosten, woche_start, woche_ende)
                     VALUES (?, ?, ?, ?)")
          ->execute([$nutzername, $gesamtpreis, $montag, $sonntag]);
    }
  }

  header("Location: kuehlschrank.php");
  exit;
}

/* === Wochenkosten laden === */
$montag = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT gesamt_kosten FROM kuehlschrank_wochenkosten WHERE mitarbeiter = ? AND woche_start = ?");
$stmt->execute([$nutzername, $montag]);
$wochenkosten = (float)($stmt->fetchColumn() ?? 0.00);

/* === Verlauf (aktuelle Woche) === */
$stmt = $pdo->prepare("
  SELECT * FROM kuehlschrank_verlauf
  WHERE mitarbeiter = ? AND datum >= ?
  ORDER BY datum DESC
");
$stmt->execute([$nutzername, $montag]);
$verlauf = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gesamtProdukte = count($produkte);
$kritischeProdukte = 0;
foreach ($produkte as $produkt) {
    if ((int)$produkt['bestand'] < 3) {
        $kritischeProdukte++;
    }
}

$entnahmenDieseWoche = count($verlauf);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧊 Kühlschranklager | Benny’s Werkstatt</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🧊 Kühlschranklager</h1>
    <p class="inventory-description">
      Snacks & Getränke für das Team – mit automatischer Kostenübersicht pro Woche.
    </p>
    <p class="inventory-info">Deine Entnahmen werden direkt im persönlichen Wochenkonto verbucht.</p>

    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Produkte verfügbar</span>
        <span class="inventory-metric__value"><?= $gesamtProdukte ?></span>
      </div>
      <div class="inventory-metric <?= $kritischeProdukte ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Knapp (&lt; 30 Stück)</span>
        <span class="inventory-metric__value"><?= $kritischeProdukte ?></span>
        <span class="inventory-metric__hint">bitte nachfüllen</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Entnahmen diese Woche</span>
        <span class="inventory-metric__value"><?= $entnahmenDieseWoche ?></span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Kosten dieser Woche</span>
        <span class="inventory-metric__value"><?= number_format($wochenkosten, 2, ',', '.') ?> €</span>
        <span class="inventory-metric__hint">Montag – Sonntag</span>
      </div>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Aktuelle Produkte</h2>
    <p class="inventory-section__intro">Wähle Menge & Produkt – der Bestand aktualisiert sich automatisch.</p>
    <?php if ($produkte): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Produkt</th>
              <th>Kategorie</th>
              <th>Preis (€)</th>
              <th>Bestand</th>
              <th>Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($produkte as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['produkt']) ?></td>
                <td><?= htmlspecialchars($p['kategorie']) ?></td>
                <td><?= number_format($p['preis'], 2, ',', '.') ?></td>
                <td class="<?= $p['bestand'] < 3 ? 'low-stock' : '' ?>"><?= $p['bestand'] ?></td>
                <td>
                  <?php if ($p['bestand'] > 0): ?>
                    <form method="post" class="table-action-form">
                      <input type="hidden" name="produkt_id" value="<?= $p['id'] ?>">
                      <input type="number" name="menge" value="1" min="1" max="<?= $p['bestand'] ?>" class="input-field input-field--compact">
                      <button type="submit" class="inventory-submit inventory-submit--small">🥪 Entnehmen</button>
                    </form>
                  <?php else: ?>
                    <span class="table-note">leer</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-empty">Aktuell sind keine Produkte hinterlegt.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Deine Wochenkosten</h2>
    <div class="inventory-summary-grid">
      <div class="inventory-summary inventory-summary--accent">
        <span class="inventory-summary__label">Kalenderwoche <?= date('W') ?></span>
        <span class="inventory-summary__value"><?= number_format($wochenkosten, 2, ',', '.') ?> €</span>
        <span class="inventory-summary__hint">Stand: <?= date('d.m.Y') ?></span>
      </div>
    </div>
    <p class="inventory-note">Die Abrechnung erfolgt gesammelt über die Lohnbuchhaltung.</p>
  </section>

  <section class="inventory-section">
    <h2>Entnahmen (aktuelle Woche)</h2>
    <?php if ($verlauf): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Datum</th>
              <th>Produkt</th>
              <th>Menge</th>
              <th>Gesamt (€)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($verlauf as $v): ?>
              <tr>
                <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
                <td><?= htmlspecialchars($v['produkt']) ?></td>
                <td><?= (int)$v['menge'] ?></td>
                <td><?= number_format($v['gesamtpreis'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-empty">Du hast diese Woche noch nichts entnommen.</p>
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
