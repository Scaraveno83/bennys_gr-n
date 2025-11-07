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
  $stmt = $pdo->prepare("
    SELECT * FROM wochenaufgaben
    WHERE YEARWEEK(datum, 1) < YEARWEEK(CURDATE(), 1)
  ");
  $stmt->execute();
  $alte = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($alte) {
    $archiv = $pdo->prepare("
      INSERT INTO wochenaufgaben_archiv (mitarbeiter, produkt, menge, datum, kalenderwoche)
      VALUES (?, ?, ?, ?, ?)
    ");
    $del = $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?");
    foreach ($alte as $row) {
      $archiv->execute([
        $row['mitarbeiter'], $row['produkt'], $row['menge'], $row['datum'], date('o-W', strtotime($row['datum']))
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
  $stmt = $pdo->prepare("
    INSERT INTO wochenaufgaben (mitarbeiter, produkt, menge, datum)
    VALUES (?, ?, ?, NOW())
  ");
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
  $stmt = $pdo->prepare("
    UPDATE wochenaufgaben SET mitarbeiter=?, produkt=?, menge=? WHERE id=?
  ");
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

$stmt = $pdo->prepare("
  SELECT mitarbeiter, produkt, SUM(menge) as summe
  FROM wochenaufgaben
  WHERE datum BETWEEN ? AND ?
  GROUP BY mitarbeiter, produkt
  ORDER BY mitarbeiter
");
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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Wochenaufgaben verwalten | Admin</title>

<link rel="stylesheet" href="../header.css" />
<link rel="stylesheet" href="../styles.css" />
<style>
main, .cards-section { padding:120px 50px 60px; max-width:1300px; margin:0 auto; text-align:center; }
.card, .form-card, .table-card, .statistik-card {
  background:rgba(25,25,25,0.9);
  border:1px solid rgba(57,255,20,0.4);
  border-radius:15px;
  padding:25px;
  margin-bottom:40px;
  color:#fff;
  box-shadow:0 0 15px rgba(57,255,20,0.25);
}
form input, form select {
  width:100%;
  padding:10px;
  margin-bottom:12px;
  border-radius:10px;
  border:1px solid rgba(57,255,20,0.35);
  background:rgba(20,20,20,0.9);
  color:#fff;
}
form button {
  background:linear-gradient(90deg,#39ff14,#76ff65);
  border:none;
  padding:10px 20px;
  border-radius:8px;
  color:#fff;
  cursor:pointer;
  transition:.3s;
}
form button:hover {
  transform:scale(1.05);
  box-shadow:0 0 15px rgba(57,255,20,0.6);
}
table {
  width:100%;
  border-collapse:collapse;
  margin-top:20px;
}
th,td {
  border:1px solid rgba(57,255,20,0.3);
  padding:10px;
  vertical-align:middle;
}
th {
  background:rgba(57,255,20,0.18);
  color:#76ff65;
}
.actions {
  display:flex;
  justify-content:center;
  gap:8px;
}
.btn-archive {
  display:inline-block;
  margin-top:10px;
  background:#39ff14;
  color:#fff;
  padding:10px 18px;
  border-radius:8px;
  text-decoration:none;
  transition:.3s;
}
.btn-archive:hover { background:#76ff65; }
.notice {
  background:rgba(20,60,20,.85);
  border:1px solid #4CAF50;
  padding:14px 16px;
  margin:8px auto 24px;
  border-radius:10px;
  color:#dff6df;
  max-width:640px;
  box-shadow:0 0 14px rgba(76,175,80,.25);
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <section class="cards-section">
    <h2 class="section-title">📦 Wochenaufgaben verwalten</h2>

    <?php if (isset($_GET['archived'])): ?>
      <div class="notice">
        ✅ Alte Wochen wurden erfolgreich archiviert.
      </div>
    <?php endif; ?>

    <a href="?archive=1" class="btn-archive">📁 Alte Wochen archivieren</a>
    <a href="wochenaufgaben_archiv_edit.php" class="btn-archive" style="background:#666;">📚 Archiv ansehen</a>

    <!-- Statistik -->
    <div class="card glass statistik-card">
      <h3>📊 Wochenstatistik (<?= date('d.m.Y', strtotime($montag)) ?> – <?= date('d.m.Y', strtotime($sonntag)) ?>)</h3>
      <?php if (!empty($statistik)): ?>
        <table class="admin-table">
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
                <?php foreach ($produkte as $p): ?><td><?= $werte[$p] ?: '-' ?></td><?php endforeach; ?>
                <td><strong><?= $werte['Gesamt'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p>Keine Daten für diese Woche.</p><?php endif; ?>
    </div>

    <!-- Formular -->
    <div class="card glass form-card">
      <h3>➕ Neuer Eintrag</h3>
      <form method="post">
        <input type="hidden" name="add" value="1">
        <label>Mitarbeiter:</label>
        <select name="mitarbeiter" required>
          <option value="">– Mitarbeiter wählen –</option>
          <?php foreach ($mitarbeiter_liste as $m): ?>
            <option><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Produkt:</label>
        <select name="produkt" required>
          <option value="">– Produkt wählen –</option>
          <?php foreach ($produkte as $p): ?>
            <option><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Menge:</label>
        <input type="number" name="menge" min="1" placeholder="z. B. 50" required>

        <button type="submit" class="btn btn-primary">+ Eintrag speichern</button>
      </form>
    </div>

    <!-- Einträge -->
    <div class="card glass table-card">
      <h3>📋 Bestehende Einträge</h3>
      <?php if ($eintraege): ?>
        <table class="admin-table">
          <thead><tr><th>ID</th><th>Mitarbeiter</th><th>Produkt</th><th>Menge</th><th>Datum</th><th>Aktionen</th></tr></thead>
          <tbody>
            <?php foreach ($eintraege as $e): ?>
            <tr>
              <form method="post">
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
                <td class="actions">
                  <input type="hidden" name="edit_id" value="<?= $e['id'] ?>">
                  <button type="submit" class="btn btn-primary" title="Speichern">💾</button>
                  <a class="btn btn-ghost" href="?delete=<?= $e['id'] ?>" onclick="return confirm('Eintrag wirklich löschen?')" title="Löschen">🗑️</a>
                </td>
              </form>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p>Keine Einträge vorhanden.</p><?php endif; ?>
    </div>

    <div class="back-wrap" style="margin-top:28px;text-align:center;">
      <a href="dashboard.php" class="btn btn-ghost">← Zurück zum Dashboard</a>
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
