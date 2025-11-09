<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

/* === Produkte === */
$produkte = ['Öl', 'Fasern', 'Stoff', 'Eisenbarren', 'Eisenerz'];

/* === Mitarbeiter laden === */
$stmt_mitarbeiter = $pdo->query("SELECT name FROM mitarbeiter ORDER BY name ASC");
$mitarbeiter_liste = $stmt_mitarbeiter->fetchAll(PDO::FETCH_COLUMN);

/* === Archivierung manuell anstoßen === */
if (isset($_GET['archive'])) {
  $stmt = $pdo->prepare("SELECT * FROM wochenaufgaben WHERE YEARWEEK(datum, 1) < YEARWEEK(CURDATE(), 1)");
  $stmt->execute();
  $alte = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($alte) {
    $archiv = $pdo->prepare("INSERT INTO wochenaufgaben_archiv (mitarbeiter, produkt, menge, datum, kalenderwoche)
                             VALUES (?, ?, ?, ?, ?)");
    $del = $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?");
    foreach ($alte as $row) {
      $archiv->execute([
        $row['mitarbeiter'],
        $row['produkt'],
        $row['menge'],
        $row['datum'],
        date('o-W', strtotime($row['datum']))
      ]);
      $del->execute([$row['id']]);
    }
  }
  header("Location: wochenaufgaben_edit.php?archived=1");
  exit;
}

/* === LÖSCHEN === */
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?")->execute([$id]);
  header("Location: wochenaufgaben_edit.php");
  exit;
}

/* === HINZUFÜGEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $stmt = $pdo->prepare("INSERT INTO wochenaufgaben (mitarbeiter, produkt, menge, datum) VALUES (?, ?, ?, NOW())");
  $stmt->execute([
    trim($_POST['mitarbeiter']),
    trim($_POST['produkt']),
    intval($_POST['menge'])
  ]);
  header("Location: wochenaufgaben_edit.php");
  exit;
}

/* === BEARBEITEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $stmt = $pdo->prepare("UPDATE wochenaufgaben SET mitarbeiter=?, produkt=?, menge=? WHERE id=?");
  $stmt->execute([
    trim($_POST['mitarbeiter']),
    trim($_POST['produkt']),
    intval($_POST['menge']),
    (int)$_POST['edit_id']
  ]);
  header("Location: wochenaufgaben_edit.php");
  exit;
}

/* === EINTRÄGE LADEN === */
$eintraege = $pdo->query("SELECT * FROM wochenaufgaben ORDER BY datum DESC")->fetchAll(PDO::FETCH_ASSOC);

/* === Statistik === */
$montag = date('Y-m-d', strtotime('monday this week'));
$sonntag = date('Y-m-d 23:59:59', strtotime('sunday this week'));

$stmt = $pdo->prepare("SELECT mitarbeiter, produkt, SUM(menge) as summe
                       FROM wochenaufgaben
                       WHERE datum BETWEEN ? AND ?
                       GROUP BY mitarbeiter, produkt
                       ORDER BY mitarbeiter");
$stmt->execute([$montag, $sonntag]);
$daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statistik = [];
foreach ($daten as $row) {
  $m = $row['mitarbeiter'];
  $p = $row['produkt'];
  $menge = (int)$row['summe'];
  if (!isset($statistik[$m])) {
    $statistik[$m] = array_fill_keys($produkte, 0);
    $statistik[$m]['Gesamt'] = 0;
  }
  $statistik[$m][$p] = $menge;
  $statistik[$m]['Gesamt'] += $menge;
}

$anzahlEintraege = count($eintraege);
$gesamtMenge = array_sum(array_map(static fn($entry) => (int)$entry['menge'], $eintraege));
$letzteAktualisierung = $eintraege[0]['datum'] ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>📦 Wochenaufgaben verwalten | Admin</title>
<link rel="stylesheet" href="../header.css" />
<link rel="stylesheet" href="../styles.css" />
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.weekly-grid {
  display: grid;
  gap: 20px;
}

@media (min-width: 960px) {
  .weekly-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.weekly-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.weekly-table select,
.weekly-table input[type="number"] {
  width: 100%;
  background: rgba(10, 12, 13, 0.9);
  border: 1px solid rgba(57, 255, 20, 0.25);
  border-radius: 10px;
  padding: 10px 12px;
  color: #fff;
  font: inherit;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📦 Wochenaufgaben verwalten</h1>
    <p class="inventory-description">
      Koordiniere Aufgaben, Produktionsziele und Lagerabgaben für jede Woche. Alle Änderungen werden live im Team-Dashboard angezeigt.
    </p>
    <p class="inventory-info">
      Letzte Aktualisierung:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Noch keine Einträge erfasst' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Aktive Einträge</span>
        <span class="inventory-metric__value"><?= number_format($anzahlEintraege, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">für diese Woche</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamtmenge</span>
        <span class="inventory-metric__value"><?= number_format($gesamtMenge, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">über alle Produkte</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Produkte</span>
        <span class="inventory-metric__value"><?= count($produkte) ?></span>
        <span class="inventory-metric__hint">definierte Ressourcen</span>
      </article>
    </div>
  </header>

  <?php if (isset($_GET['archived'])): ?>
    <section class="inventory-section">
      <h2>Archivierung</h2>
      <p class="inventory-section__intro" style="color:#86ffb5;">
        ✅ Alte Wochen wurden erfolgreich archiviert.
      </p>
    </section>
  <?php endif; ?>

  <section class="inventory-section">
    <h2>Schnellaktionen</h2>
    <div class="weekly-actions">
      <a href="?archive=1" class="inventory-submit inventory-submit--ghost">📁 Alte Wochen archivieren</a>
      <a href="wochenaufgaben_archiv_edit.php" class="inventory-submit inventory-submit--ghost">📚 Archiv ansehen</a>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Wochenstatistik</h2>
    <p class="inventory-section__intro">
      Zeitraum: <?= date('d.m.Y', strtotime($montag)) ?> – <?= date('d.m.Y', strtotime($sonntag)) ?>
    </p>
    <?php if (!empty($statistik)): ?>
      <div class="table-wrap">
        <table class="data-table weekly-table">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <?php foreach ($produkte as $p): ?><th><?= htmlspecialchars($p) ?></th><?php endforeach; ?>
              <th>Gesamt</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($statistik as $mitarbeiter => $werte): ?>
              <tr>
                <td><strong><?= htmlspecialchars($mitarbeiter) ?></strong></td>
                <?php foreach ($produkte as $p): ?>
                  <td><?= $werte[$p] ?: '–' ?></td>
                <?php endforeach; ?>
                <td><strong><?= $werte['Gesamt'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Keine Daten für die laufende Woche.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Neuen Eintrag erstellen</h2>
    <form method="post" class="inventory-form weekly-grid">
      <input type="hidden" name="add" value="1">

      <div class="input-control">
        <label for="mitarbeiter_add">Mitarbeiter:in</label>
        <select id="mitarbeiter_add" name="mitarbeiter" class="inventory-select" required>
          <option value="">– Mitarbeiter wählen –</option>
          <?php foreach ($mitarbeiter_liste as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="produkt_add">Produkt</label>
        <select id="produkt_add" name="produkt" class="inventory-select" required>
          <option value="">– Produkt wählen –</option>
          <?php foreach ($produkte as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="menge_add">Menge</label>
        <input id="menge_add" class="input-field" type="number" name="menge" min="1" placeholder="z. B. 50" required>
      </div>

      <div class="form-actions" style="align-self:end;">
        <button type="submit" class="inventory-submit">+ Eintrag speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Aktive Einträge</h2>
    <?php if ($eintraege): ?>
      <div class="table-wrap">
        <table class="data-table weekly-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mitarbeiter</th>
              <th>Produkt</th>
              <th>Menge</th>
              <th>Datum</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eintraege as $e): ?>
              <tr>
                <form method="post" class="weekly-edit-form">
                  <td><?= $e['id'] ?></td>
                  <td>
                    <select name="mitarbeiter" required>
                      <?php foreach ($mitarbeiter_liste as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($e['mitarbeiter'] === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="produkt" required>
                      <?php foreach ($produkte as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= ($e['produkt'] === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="number" name="menge" value="<?= $e['menge'] ?>" min="1"></td>
                  <td><?= date('d.m.Y H:i', strtotime($e['datum'])) ?></td>
                  <td class="weekly-actions">
                    <input type="hidden" name="edit_id" value="<?= $e['id'] ?>">
                    <button type="submit" class="inventory-submit inventory-submit--small">💾</button>
                    <a class="inventory-submit inventory-submit--ghost inventory-submit--small" href="?delete=<?= $e['id'] ?>"
                       onclick="return confirm('Eintrag wirklich löschen?')">🗑️</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Keine Einträge vorhanden.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
      <a href="wochenaufgaben_archiv_edit.php" class="button-secondary">📚 Archiv</a>
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