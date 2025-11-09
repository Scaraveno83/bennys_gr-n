<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

/* === Filter === */
$filterKW = $_GET['kw'] ?? '';
$filterMitarbeiter = $_GET['mitarbeiter'] ?? '';

$query = "SELECT * FROM wochenaufgaben_archiv WHERE 1";
$params = [];

if ($filterKW) { 
  $query .= " AND kalenderwoche = ?"; 
  $params[] = $filterKW; 
}
if ($filterMitarbeiter) { 
  $query .= " AND mitarbeiter = ?"; 
  $params[] = $filterMitarbeiter; 
}

$query .= " ORDER BY datum DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$eintraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* === Mitarbeiter- und KW-Listen === */
$mitarbeiter = $pdo->query("SELECT DISTINCT mitarbeiter FROM wochenaufgaben_archiv ORDER BY mitarbeiter")->fetchAll(PDO::FETCH_COLUMN);
$wochen = $pdo->query("SELECT DISTINCT kalenderwoche FROM wochenaufgaben_archiv ORDER BY kalenderwoche DESC")->fetchAll(PDO::FETCH_COLUMN);

/* === Summenstatistik (pro KW & Mitarbeiter) === */
$statQuery = "SELECT kalenderwoche, mitarbeiter, produkt, SUM(menge) as summe 
              FROM wochenaufgaben_archiv WHERE 1";
$statParams = [];

if ($filterKW) { 
  $statQuery .= " AND kalenderwoche = ?"; 
  $statParams[] = $filterKW; 
}
if ($filterMitarbeiter) { 
  $statQuery .= " AND mitarbeiter = ?"; 
  $statParams[] = $filterMitarbeiter; 
}

$statQuery .= " GROUP BY kalenderwoche, mitarbeiter, produkt ORDER BY kalenderwoche DESC, mitarbeiter ASC";
$statStmt = $pdo->prepare($statQuery);
$statStmt->execute($statParams);
$statistikRoh = $statStmt->fetchAll(PDO::FETCH_ASSOC);

$statistik = [];
foreach ($statistikRoh as $row) {
  $kw = $row['kalenderwoche'];
  $m = $row['mitarbeiter'];
  $p = $row['produkt'];
  $menge = (int)$row['summe'];

  if (!isset($statistik[$kw])) $statistik[$kw] = [];
  if (!isset($statistik[$kw][$m])) $statistik[$kw][$m] = ['Produkte' => [], 'Gesamt' => 0];

  $statistik[$kw][$m]['Produkte'][$p] = $menge;
  $statistik[$kw][$m]['Gesamt'] += $menge;
}

/* === Löschen === */
if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM wochenaufgaben_archiv WHERE id=?")->execute([(int)$_GET['delete']]);
  header("Location: wochenaufgaben_archiv_edit.php");
  exit;
}

$anzahlEintraege = count($eintraege);
$aktiveWochen = array_unique(array_map(static fn($row) => $row['kalenderwoche'], $eintraege));
$aktiveMitarbeiter = array_unique(array_map(static fn($row) => $row['mitarbeiter'], $eintraege));
$gesamtMenge = array_sum(array_map(static fn($row) => (int)$row['menge'], $eintraege));
$letzteArchivierung = $eintraege[0]['archiviert_am'] ?? null;
$filterAktiv = (bool)$filterKW || (bool)$filterMitarbeiter;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Archivierte Wochenaufgaben | Admin</title>
<link rel="stylesheet" href="../header.css" />
<link rel="stylesheet" href="../styles.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.inventory-filter {
  display: grid;
  gap: 18px;
}

@media (min-width: 840px) {
  .inventory-filter {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: end;
  }
}

.inventory-filter__controls {
  display: grid;
  gap: 16px;
}

@media (min-width: 640px) {
  .inventory-filter__controls {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: center;
  }
}

.inventory-filter__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.archive-summary__grid {
  display: grid;
  gap: 20px;
}

@media (min-width: 920px) {
  .archive-summary__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.archive-summary__card {
  border: 1px solid rgba(57, 255, 20, 0.18);
  border-radius: 18px;
  padding: 20px;
  background: rgba(10, 14, 16, 0.78);
  display: grid;
  gap: 12px;
}

.archive-summary__card h3 {
  margin: 0;
  font-size: 1.2rem;
}

.archive-summary__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
  text-align: left;
}

.archive-summary__list strong {
  color: #fff;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📚 Archivierte Wochenaufgaben</h1>
    <p class="inventory-description">
      Filtere, analysiere und verwalte alle abgeschlossenen Wochenaufgaben inklusive Mengenübersicht je Produkt.
    </p>
    <p class="inventory-info">
      Letzte Archivierung:
      <?= $letzteArchivierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteArchivierung)) : 'Noch keine Archivdaten im aktuellen Filter' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gefilterte Einträge</span>
        <span class="inventory-metric__value"><?= number_format($anzahlEintraege, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">aktuelle Ansicht</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Kalenderwochen</span>
        <span class="inventory-metric__value"><?= number_format(count($aktiveWochen), 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">im Ergebnis</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Mitarbeiter:innen</span>
        <span class="inventory-metric__value"><?= number_format(count($aktiveMitarbeiter), 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">beteiligt</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamte Menge</span>
        <span class="inventory-metric__value"><?= number_format($gesamtMenge, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">aller Produkte</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Filter & Suche</h2>
    <p class="inventory-section__intro">
      Eingrenzung nach Kalenderwoche und Mitarbeitenden – perfekt für gezielte Rückfragen oder Auswertungen.
    </p>

    <form method="get" class="inventory-form inventory-filter">
      <div class="inventory-filter__controls">
        <div class="input-control">
          <label for="filter-kw">Kalenderwoche</label>
          <select id="filter-kw" name="kw" class="inventory-select">
            <option value="">Alle Wochen</option>
            <?php foreach ($wochen as $w): ?>
              <option value="<?= htmlspecialchars($w) ?>" <?= ($w == $filterKW) ? 'selected' : '' ?>>
                <?= htmlspecialchars($w) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-control">
          <label for="filter-mitarbeiter">Mitarbeiter</label>
          <select id="filter-mitarbeiter" name="mitarbeiter" class="inventory-select">
            <option value="">Alle Mitarbeiter</option>
            <?php foreach ($mitarbeiter as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>" <?= ($m == $filterMitarbeiter) ? 'selected' : '' ?>>
                <?= htmlspecialchars($m) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="inventory-filter__actions">
        <button type="submit" class="inventory-submit">🔍 Filter anwenden</button>
        <?php if ($filterAktiv): ?>
          <a class="inventory-submit inventory-submit--ghost" href="wochenaufgaben_archiv_edit.php">Filter zurücksetzen</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Zusammenfassung nach Kalenderwoche</h2>
    <p class="inventory-section__intro">
      Aggregierte Sicht auf alle Produkte und Mengen – gruppiert nach Woche und Mitarbeitenden.
    </p>

    <?php if (!empty($statistik)): ?>
      <div class="archive-summary__grid">
        <?php foreach ($statistik as $kw => $daten): ?>
          <article class="archive-summary__card">
            <header>
              <h3>📅 Kalenderwoche <?= htmlspecialchars($kw) ?></h3>
            </header>
            <div class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Mitarbeiter</th>
                    <th>Produkte & Mengen</th>
                    <th>Gesamt</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($daten as $name => $werte): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($name) ?></strong></td>
                      <td>
                        <ul class="archive-summary__list">
                          <?php foreach ($werte['Produkte'] as $p => $m): ?>
                            <li><strong><?= htmlspecialchars($p) ?>:</strong> <?= number_format($m, 0, ',', '.') ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </td>
                      <td><strong><?= number_format($werte['Gesamt'], 0, ',', '.') ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro" style="color:#ffc8c8;">Keine Statistiken für den aktuellen Filter verfügbar.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Einzelne Archiv-Einträge</h2>
    <p class="inventory-section__intro">
      Vollständige Historie aller archivierten Buchungen mit Löschoption für Fehlbuchungen.
    </p>

    <?php if ($eintraege): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Kalenderwoche</th>
              <th>Mitarbeiter</th>
              <th>Produkt</th>
              <th>Menge</th>
              <th>Datum</th>
              <th>Archiviert am</th>
              <th>Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eintraege as $e): ?>
              <tr>
                <td><?= htmlspecialchars($e['kalenderwoche']) ?></td>
                <td><?= htmlspecialchars($e['mitarbeiter']) ?></td>
                <td><?= htmlspecialchars($e['produkt']) ?></td>
                <td><?= number_format($e['menge'], 0, ',', '.') ?></td>
                <td><?= date('d.m.Y H:i', strtotime($e['datum'])) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($e['archiviert_am'])) ?></td>
                <td>
                  <a class="inventory-submit inventory-submit--danger inventory-submit--small" href="?delete=<?= $e['id'] ?>"
                     onclick="return confirm('Eintrag wirklich löschen?')">🗑️ Löschen</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro" style="color:#ffc8c8;">📭 Keine archivierten Daten für den aktuellen Filter gefunden.</p>
    <?php endif; ?>
  </section>

  <div class="form-actions">
    <a href="dashboard.php" class="inventory-submit inventory-submit--ghost">← Zurück zum Dashboard</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
