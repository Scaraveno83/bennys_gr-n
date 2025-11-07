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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Archivierte Wochenaufgaben | Admin</title>
<link rel="stylesheet" href="../header.css" />
<link rel="stylesheet" href="../styles.css">
<style>
main { padding:120px 50px; max-width:1300px; margin:0 auto; color:#fff; text-align:center; }
h2,h3 { color:#76ff65; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th,td { border:1px solid rgba(57,255,20,0.3); padding:10px; }
th { background:rgba(57,255,20,0.2); color:#76ff65; }
.filter-form { margin-bottom:30px; }
.filter-form select { padding:8px; border-radius:6px; background:#222; color:#fff; border:1px solid rgba(255,60,60,0.4); margin:0 8px; }
.btn-clear { background:#555; color:#fff; padding:8px 14px; border-radius:6px; text-decoration:none; }
.statistik-card { background:rgba(25,25,25,0.9); border:1px solid rgba(57,255,20,0.4); border-radius:15px; padding:25px; margin-bottom:40px; box-shadow:0 0 15px rgba(57,255,20,0.25); }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>📚 Archivierte Wochenaufgaben</h2>

  <form method="get" class="filter-form">
    <label>Kalenderwoche:</label>
    <select name="kw">
      <option value="">Alle</option>
      <?php foreach ($wochen as $w): ?>
        <option value="<?= htmlspecialchars($w) ?>" <?= ($w==$filterKW)?'selected':'' ?>><?= htmlspecialchars($w) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Mitarbeiter:</label>
    <select name="mitarbeiter">
      <option value="">Alle</option>
      <?php foreach ($mitarbeiter as $m): ?>
        <option value="<?= htmlspecialchars($m) ?>" <?= ($m==$filterMitarbeiter)?'selected':'' ?>><?= htmlspecialchars($m) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit">🔍 Filtern</button>
    <a href="wochenaufgaben_archiv_edit.php" class="btn-clear">Zurücksetzen</a>
  </form>

  <!-- 🔹 Summenstatistik -->
  <?php if (!empty($statistik)): ?>
  <div class="statistik-card">
    <h3>📊 Zusammenfassung</h3>
    <?php foreach ($statistik as $kw => $daten): ?>
      <h4 style="color:#c8ffd5; margin-top:20px;">📅 Kalenderwoche <?= htmlspecialchars($kw) ?></h4>
      <table>
        <thead>
          <tr>
            <th>Mitarbeiter</th>
            <th>Produkte & Mengen</th>
            <th>Gesamt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daten as $mitarbeiter => $werte): ?>
            <tr>
              <td><strong><?= htmlspecialchars($mitarbeiter) ?></strong></td>
              <td style="text-align:left;">
                <?php foreach ($werte['Produkte'] as $p => $m): ?>
                  <?= htmlspecialchars($p) ?>: <?= $m ?><br>
                <?php endforeach; ?>
              </td>
              <td><strong><?= $werte['Gesamt'] ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 🔹 Detailtabelle -->
  <?php if ($eintraege): ?>
  <h3>📋 Einzelne Archiv-Einträge</h3>
  <table>
    <thead>
      <tr><th>KW</th><th>Mitarbeiter</th><th>Produkt</th><th>Menge</th><th>Datum</th><th>Archiviert am</th><th>Aktion</th></tr>
    </thead>
    <tbody>
      <?php foreach ($eintraege as $e): ?>
      <tr>
        <td><?= htmlspecialchars($e['kalenderwoche']) ?></td>
        <td><?= htmlspecialchars($e['mitarbeiter']) ?></td>
        <td><?= htmlspecialchars($e['produkt']) ?></td>
        <td><?= htmlspecialchars($e['menge']) ?></td>
        <td><?= date('d.m.Y H:i', strtotime($e['datum'])) ?></td>
        <td><?= date('d.m.Y H:i', strtotime($e['archiviert_am'])) ?></td>
        <td><a href="?delete=<?= $e['id'] ?>" onclick="return confirm('Eintrag wirklich löschen?')">🗑️</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p>📭 Keine archivierten Daten gefunden.</p>
  <?php endif; ?>

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
