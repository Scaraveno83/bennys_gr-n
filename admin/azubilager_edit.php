<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';


/* === Produktliste (alphabetisch sortiert) === */
$produkteListe = [
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
sort($produkteListe, SORT_NATURAL | SORT_FLAG_CASE);

/* === Aktionen === */

// 🔹 Eintrag löschen
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $pdo->prepare("DELETE FROM azubi_lager_verlauf WHERE id = ?")->execute([$id]);
  header("Location: azubilager_edit.php");
  exit;
}

// 🔹 Lagerbestand manuell anpassen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  $produkt = trim($_POST['produkt']);
  $menge = (int)$_POST['menge'];
  $aktion = $_POST['aktion'] ?? 'hinzugefügt';
  $admin = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

  if ($produkt && $menge > 0) {
    // Verlauf speichern
    $stmt = $pdo->prepare("
      INSERT INTO azubi_lager_verlauf (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$produkt, $menge, $aktion, $admin]);

    // Wenn Produkt noch nicht im Lager ist → anlegen
    $check = $pdo->prepare("SELECT COUNT(*) FROM azubi_lager WHERE produkt = ?");
    $check->execute([$produkt]);
    if ($check->fetchColumn() == 0) {
      $pdo->prepare("INSERT INTO azubi_lager (produkt, bestand) VALUES (?, 0)")
          ->execute([$produkt]);
    }

    // Bestand anpassen
    if ($aktion === 'hinzugefügt') {
      $pdo->prepare("UPDATE azubi_lager SET bestand = bestand + ? WHERE produkt = ?")
          ->execute([$menge, $produkt]);
    } else {
      $pdo->prepare("UPDATE azubi_lager SET bestand = GREATEST(bestand - ?, 0) WHERE produkt = ?")
          ->execute([$menge, $produkt]);
    }
  }

  header("Location: azubilager_edit.php");
  exit;
}

/* === Daten laden === */
$produkte = $pdo->query("SELECT * FROM azubi_lager ORDER BY produkt ASC")->fetchAll(PDO::FETCH_ASSOC);
$verlauf = $pdo->query("SELECT * FROM azubi_lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$lowStockThreshold = 50;
$gesamtBestand = array_sum(array_map(static fn($row) => (int)$row['bestand'], $produkte));
$anzahlProdukte = count($produkte);
$kritischeProdukte = array_reduce(
  $produkte,
  static fn($carry, $row) => $carry + (((int)$row['bestand'] < $lowStockThreshold) ? 1 : 0),
  0
);
$letzteAktualisierung = $verlauf[0]['datum'] ?? null;
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧾 Azubilager verwalten | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.inventory-history-table td .badge {
  min-width: 110px;
  justify-content: center;
}

.inventory-history-table td .badge.glow {
  color: #041004;
}

.inventory-history-table td .badge.negative {
  color: #ffb4d4;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🧾 Azubilager verwalten</h1>
    <p class="inventory-description">
      Feineinstellungen für alle Buchungen aus dem Azubilager – ideal für Korrekturen und Fehlerbereinigungen.
    </p>
    <p class="inventory-info">
      Letzte Anpassung:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Noch keine Buchung erfasst' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Produkte im Lager</span>
        <span class="inventory-metric__value"><?= number_format($anzahlProdukte, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">alphabetisch sortiert</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamtbestand</span>
        <span class="inventory-metric__value"><?= number_format($gesamtBestand, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Einheiten aktuell erfasst</span>
      </article>

      <article class="inventory-metric <?= $kritischeProdukte ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Kritische Bestände</span>
        <span class="inventory-metric__value"><?= number_format($kritischeProdukte, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">unter <?= $lowStockThreshold ?> Stück</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Bestand korrigieren</h2>
    <p class="inventory-section__intro">
      Dokumentiere manuelle Anpassungen – die Historie aktualisiert sich automatisch.
    </p>

    <form method="post" class="inventory-form">
      <input type="hidden" name="update" value="1">

      <div class="input-control">
        <label for="produkt">Produkt</label>
        <select id="produkt" name="produkt" class="inventory-select" required>
          <option value="">– Produkt wählen –</option>
          <?php foreach ($produkteListe as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="menge">Menge</label>
        <input id="menge" class="input-field" type="number" name="menge" min="1" placeholder="z. B. 25" required>
      </div>

      <div class="input-control">
        <span class="input-label">Aktion</span>
        <div class="inventory-radio-group">
          <label class="inventory-radio" for="aktion-hinzufuegen">
            <input id="aktion-hinzufuegen" type="radio" name="aktion" value="hinzugefügt" checked>
            <span>Hinzufügen</span>
          </label>
          <label class="inventory-radio" for="aktion-entnehmen">
            <input id="aktion-entnehmen" type="radio" name="aktion" value="entnommen">
            <span>Entnehmen</span>
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">Buchung speichern</button>
        <a class="inventory-submit inventory-submit--ghost" href="lageruebersicht.php">Gesamtübersicht öffnen</a>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Aktuelle Bestände</h2>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Produkt</th><th>Bestand</th></tr>
        </thead>
        <tbody>
          <?php foreach ($produkte as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['produkt']) ?></td>
              <td class="<?= ((int)$p['bestand'] < $lowStockThreshold) ? 'low-stock' : '' ?>">
                <?= number_format($p['bestand'], 0, ',', '.') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Letzte Aktionen</h2>
    <div class="table-wrap">
      <table class="data-table inventory-history-table">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Produkt</th>
            <th>Menge</th>
            <th>Aktion</th>
            <th>Mitarbeiter</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($verlauf as $v): ?>
            <?php $isAdd = $v['aktion'] === 'hinzugefügt'; ?>
            <tr>
              <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
              <td><?= htmlspecialchars($v['produkt']) ?></td>
              <td><?= number_format($v['menge'], 0, ',', '.') ?></td>
              <td>
                <span class="badge <?= $isAdd ? 'glow' : 'negative' ?>">
                  <?= $isAdd ? 'Hinzugefügt' : 'Entnommen' ?>
                </span>
              </td>
              <td><?= htmlspecialchars($v['mitarbeiter']) ?></td>
              <td>
                <a class="inventory-submit inventory-submit--ghost inventory-submit--small" href="?delete=<?= $v['id'] ?>"
                   onclick="return confirm('Eintrag wirklich löschen?')">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
      <a href="lageruebersicht.php" class="button-secondary">📦 Lagerübersicht</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
