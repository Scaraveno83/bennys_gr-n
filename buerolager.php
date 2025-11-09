<?php
session_start();
require_once 'includes/db.php';
require_once __DIR__ . '/includes/visibility.php';
// Zugriff prüfen
enforce_area_access('inventory');

/* === Zugriffskontrolle === */
if (empty($_SESSION['user_role']) && empty($_SESSION['admin_logged_in'])) {
  header("Location: admin/login.php");
  exit;
}

/* === Mitarbeitername und Rang === */
$nutzername = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Unbekannt';
$userRang = null;

if (!empty($_SESSION['user_id'])) {
  $stmt = $pdo->prepare("
    SELECT m.rang
    FROM mitarbeiter m
    JOIN user_accounts u ON u.mitarbeiter_id = m.id
    WHERE u.id = ?
  ");
  $stmt->execute([$_SESSION['user_id']]);
  $userRang = $stmt->fetchColumn();
}

/* === Admin-Check === */
$isAdmin = (
  (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ||
  (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
);

/* === Erlaubte Ränge === */
$erlaubteRollen = [
  'Geschäftsführung',
  'Stv. Geschäftsleitung',
  'Personalleitung'
];

/* === Zugriff verweigern === */
if (!$isAdmin && (!$userRang || !in_array($userRang, $erlaubteRollen))) {
  echo "<h2 style='color:#76ff65;text-align:center;margin-top:120px;'>🚫 Zugriff verweigert</h2>"
      . "<p style='text-align:center;color:#fff;'>Dein Rang <b>" . htmlspecialchars($userRang ?: 'Unbekannt') . "</b> hat keinen Zugriff auf das Bürolager.</p>";
  exit;
}

/* === Produktliste (alphabetisch, vereinheitlicht) === */
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
sort($produkte, SORT_NATURAL | SORT_FLAG_CASE);

$lowStockThreshold = 10;

/* === Neue Lageraktion === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $produkt = trim($_POST['produkt']);
  $menge = intval($_POST['menge']);
  $aktion = $_POST['aktion'] ?? '';

  if ($produkt && $menge > 0 && in_array($aktion, ['hinzugefügt', 'entnommen'])) {

    // Verlauf speichern
    $stmt = $pdo->prepare("
      INSERT INTO buero_lager_verlauf (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$produkt, $menge, $aktion, $nutzername]);

    // Produkt ggf. anlegen
    $check = $pdo->prepare("SELECT COUNT(*) FROM buero_lager WHERE produkt = ?");
    $check->execute([$produkt]);
    if ($check->fetchColumn() == 0) {
      $pdo->prepare("INSERT INTO buero_lager (produkt, bestand) VALUES (?, 0)")->execute([$produkt]);
    }

    // Bestand anpassen
    if ($aktion === 'hinzugefügt') {
      $pdo->prepare("UPDATE buero_lager SET bestand = bestand + ? WHERE produkt = ?")->execute([$menge, $produkt]);
    } else {
      $pdo->prepare("UPDATE buero_lager SET bestand = GREATEST(bestand - ?, 0) WHERE produkt = ?")->execute([$menge, $produkt]);
    }
  }

  header("Location: buerolager.php");
  exit;
}

/* === Bestände abrufen === */
$bestand = [];
$stmt = $pdo->query("SELECT produkt, bestand FROM buero_lager ORDER BY produkt ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $bestand[$row['produkt']] = (int)$row['bestand'];
}

foreach ($produkte as $p) {
  if (!isset($bestand[$p])) {
    $bestand[$p] = 0;
  }
}
ksort($bestand, SORT_NATURAL | SORT_FLAG_CASE);

/* === Verlauf laden === */
$verlauf = $pdo->query("SELECT * FROM buero_lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$timelineEntries = array_slice($verlauf, 0, 12);

$letzteAktualisierung = $verlauf[0]['datum'] ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📁 Bürolager | Benny’s Werkstatt</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📁 Bürolager</h1>
    <p class="inventory-description">
      Verbrauchsmaterialien für Büro und Verwaltung im Überblick.
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
    <h2>Buchung erfassen</h2>
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
        <span class="form-hint">erscheint unmittelbar im Verlauf</span>
      </div>
    </form>
  </section>

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
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>