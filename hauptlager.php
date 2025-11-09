<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';
require_once __DIR__ . '/includes/visibility.php';
// Zugriff prüfen
enforce_area_access('inventory');

/* === Zugriff nur für eingeloggte Benutzer === */
if (empty($_SESSION['user_id'])) {
  header("Location: admin/login.php");
  exit;
}

/* === Mitarbeiter-Rang aus DB laden === */
$stmt = $pdo->prepare("
  SELECT m.rang
  FROM mitarbeiter m
  JOIN user_accounts u ON u.mitarbeiter_id = m.id
  WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$rang = $stmt->fetchColumn();

/* === Zugriffsbeschränkung anhand Rang === */
$erlaubteRollen = [
  'Geschäftsführung',
  'Stv. Geschäftsleitung',
  'Personalleitung',
  'Ausbilder/in',
  'Tuner/in',
  'Meister/in',
  'Mechaniker/in',
  'Geselle/Gesellin'
];

$isAdmin = ($_SESSION['user_role'] === 'admin');

if (!$isAdmin && (!$rang || !in_array($rang, $erlaubteRollen))) {
  header("Location: index.php");
  exit;
}

/* === Rollen === */
$isAdmin = ($_SESSION['user_role'] === 'admin');
$nutzername = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Unbekannt';

/* === Produkte (vollständige Liste) === */
$produkte = [
  'Absperrung', 'Aluminium', 'Auto Vertrag', 'Bandage', 'Batterien', 'Bauxit',
  'Benzin Kanister', 'BlueV', 'Diamant', 'Eisenbarren', 'Eisenerz', 'Faser',
  'Funk', 'Glasflasche', 'Goldbarren', 'Golderz', 'Handy', 'Holz', 'Holzbrett',
  'Juwel', 'Kegel', 'Lvl.2 Angel', 'Lvl.2 Holzaxt', 'Lvl.2 Schaufel',
  'Lvl.2 Sichel', 'Lvl.2 Spitzhacke', 'Lvl.2 Tasche', 'Lvl.3 Angel',
  'Lvl.3 Holzaxt', 'Lvl.3 Schaufel', 'Lvl.3 Sichel', 'Lvl.3 Spitzhacke',
  'Lvl.3 Tasche', 'Lvl.4 Tasche', 'MonsterV', 'Notfallkit', 'Öl', 'Panikknopf',
  'Pappe', 'Papeir', 'Plastik', 'Plastikflasche', 'Rechnung', 'Repair Kit',
  'Sauberes Wasser', 'Schraubenzieher', 'Stoff', 'Verpackung', 'Wagenheber',
  'Waschlappen'
];

/* === Produkte alphabetisch sortieren === */
sort($produkte, SORT_NATURAL | SORT_FLAG_CASE);

/* === Kennzahlen konfigurieren === */
$lowStockThreshold = 50;

/* === Neue Lageraktion === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $produkt = trim($_POST['produkt']);
  $menge = intval($_POST['menge']);
  $aktion = $_POST['aktion'] ?? '';

  if ($produkt && $menge > 0 && in_array($aktion, ['hinzugefügt', 'entnommen'])) {

    // Verlauf speichern
    $stmt = $pdo->prepare("
      INSERT INTO lager_verlauf (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$produkt, $menge, $aktion, $nutzername]);

    // Wenn Produkt noch nicht im Lager vorhanden ist, anlegen
    $check = $pdo->prepare("SELECT COUNT(*) FROM hauptlager WHERE produkt = ?");
    $check->execute([$produkt]);
    if ($check->fetchColumn() == 0) {
      $pdo->prepare("INSERT INTO hauptlager (produkt, bestand) VALUES (?, 0)")
          ->execute([$produkt]);
    }

    // Bestand anpassen
    if ($aktion === 'hinzugefügt') {
      $pdo->prepare("UPDATE hauptlager SET bestand = bestand + ? WHERE produkt = ?")
          ->execute([$menge, $produkt]);
    } else {
      $pdo->prepare("UPDATE hauptlager SET bestand = GREATEST(bestand - ?, 0) WHERE produkt = ?")
          ->execute([$menge, $produkt]);
    }
  }

  header("Location: hauptlager.php");
  exit;
}

/* === Aktuelle Bestände abrufen === */
$bestand = [];
$stmt = $pdo->query("SELECT produkt, bestand FROM hauptlager ORDER BY produkt ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $bestand[$row['produkt']] = (int)$row['bestand'];
}

/* === Fehlende Produkte mit 0 einfügen (Anzeige) === */
foreach ($produkte as $p) {
  if (!isset($bestand[$p])) {
    $bestand[$p] = 0;
  }
}

ksort($bestand, SORT_NATURAL | SORT_FLAG_CASE);

$letzteAktualisierung = $pdo->query("SELECT datum FROM lager_verlauf ORDER BY datum DESC LIMIT 1")
  ->fetchColumn();

/* === Verlauf laden (nur für Admins) === */
$verlauf = [];
$timelineEntries = [];
if ($isAdmin) {
  $verlauf = $pdo->query("SELECT * FROM lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $timelineEntries = array_slice($verlauf, 0, 12);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🏭 Hauptlager | Benny’s Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🏭 Hauptlager</h1>
    <p class="inventory-description">
      Modernes Control-Center für alle Materialbewegungen im Stammwerk.
    </p>
    <p class="inventory-info">
      Letzte Buchung:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Keine Daten' ?>
    </p>
  </header>

  <section class="inventory-section">
    <h2>Bestandsübersicht</h2>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Produkt</th><th>Bestand</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bestand as $produkt => $menge): ?>
            <tr>
              <td><?= htmlspecialchars($produkt) ?></td>
              <td class="<?= $menge < $lowStockThreshold ? 'low-stock' : '' ?>">
                <?= number_format($menge, 0, ',', '.') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="inventory-section" id="lageraktion">
    <h2>Lageraktion buchen</h2>
    <form method="post" class="inventory-form">
      <div class="input-control">
        <label for="produkt">Produkt</label>
        <select id="produkt" name="produkt" required>
          <option value="">– bitte wählen –</option>
          <?php foreach ($produkte as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="menge">Menge</label>
        <input id="menge" class="input-field" type="number" name="menge" min="1" required>
      </div>

      <div class="input-control">
        <span class="input-label">Aktion</span>
        <div class="inventory-radio-group">
          <label><input type="radio" name="aktion" value="hinzugefügt" checked> Hinzufügen</label>
          <label><input type="radio" name="aktion" value="entnommen"> Entnehmen</label>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">Buchung speichern</button>
        <span class="form-hint">wird in der Verlaufshistorie festgehalten</span>
      </div>
    </form>
  </section>

  <?php if ($isAdmin): ?>
    <section class="inventory-section">
      <h2>Aktivitätsprotokoll</h2>
      <?php if (!empty($timelineEntries)): ?>
        <ul class="inventory-history">
          <?php foreach ($timelineEntries as $entry): ?>
            <?php $isAdd = $entry['aktion'] === 'hinzugefügt'; ?>
            <li class="inventory-history__item <?= $isAdd ? '' : 'inventory-history__item--remove' ?>">
              <div class="inventory-history__headline">
                <strong><?= htmlspecialchars($entry['produkt']) ?></strong>
                <span class="inventory-history__amount">
                  <?= $isAdd ? '+' : '−' ?><?= number_format((int)$entry['menge'], 0, ',', '.') ?>
                </span>
              </div>
              <div class="inventory-history__meta">
                <span><?= date('d.m.Y, H:i \U\h\r', strtotime($entry['datum'])) ?></span>
                <span>von <?= htmlspecialchars($entry['mitarbeiter']) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="inventory-history__empty">Noch keine Buchungen vorhanden.</p>
      <?php endif; ?>
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