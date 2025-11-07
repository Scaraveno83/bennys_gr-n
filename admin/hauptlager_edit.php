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
  $pdo->prepare("DELETE FROM lager_verlauf WHERE id = ?")->execute([$id]);
  header("Location: hauptlager_edit.php");
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
      INSERT INTO lager_verlauf (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$produkt, $menge, $aktion, $admin]);

    // Wenn Produkt noch nicht im Lager vorhanden ist → anlegen
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

  header("Location: hauptlager_edit.php");
  exit;
}

/* === Daten laden === */
$produkte = $pdo->query("SELECT * FROM hauptlager ORDER BY produkt ASC")->fetchAll(PDO::FETCH_ASSOC);
$verlauf = $pdo->query("SELECT * FROM lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧾 Hauptlager verwalten | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
main {
  max-width: 1200px;
  margin: 120px auto;
  padding: 20px;
  text-align: center;
  color: #fff;
}
h2, h3 { color: #76ff65; }

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
th, td {
  border: 1px solid rgba(57,255,20,0.3);
  padding: 10px;
}
th {
  background: rgba(57,255,20,0.15);
  color: #76ff65;
}
tr:hover { background: rgba(57,255,20,0.08); }

form {
  margin-bottom: 30px;
  background: rgba(25,25,25,0.9);
  border: 1px solid rgba(57,255,20,0.3);
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 0 15px rgba(57,255,20,0.25);
}
select, input {
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid rgba(57,255,20,0.3);
  background: rgba(30,30,30,0.9);
  color: #fff;
}
button {
  padding: 8px 14px;
  border-radius: 8px;
  border: none;
  background: linear-gradient(90deg,#39ff14,#76ff65);
  color: #fff;
  cursor: pointer;
  transition: .3s;
}
button:hover {
  transform: scale(1.05);
  box-shadow: 0 0 10px rgba(57,255,20,0.6);
}
.badge.plus { color: #4cff4c; font-weight: bold; }
.badge.minus { color: #ff6b6b; font-weight: bold; }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>🧾 Hauptlager verwalten</h2>
  <p>Hier kannst du alle Lagerbestände manuell korrigieren, hinzufügen oder Einträge löschen.</p>

  <!-- 🔧 Manuelle Änderung -->
  <form method="post">
    <input type="hidden" name="update" value="1">

    <label>Produkt:</label>
    <select name="produkt" required>
      <option value="">– Produkt wählen –</option>
      <?php foreach ($produkteListe as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Menge:</label>
    <input type="number" name="menge" min="1" placeholder="Menge" required>

    <label>Aktion:</label>
    <select name="aktion">
      <option value="hinzugefügt">+ Hinzufügen</option>
      <option value="entnommen">– Entnehmen</option>
    </select>

    <button type="submit">💾 Bestätigen</button>
  </form>

  <!-- 📦 Aktuelle Bestände -->
  <h3>📦 Aktuelle Bestände</h3>
  <table>
    <thead>
      <tr><th>Produkt</th><th>Bestand</th></tr>
    </thead>
    <tbody>
      <?php foreach ($produkte as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['produkt']) ?></td>
          <td><?= htmlspecialchars($p['bestand']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- 🕒 Verlauf -->
  <h3 style="margin-top:40px;">🕒 Verlauf der letzten Aktionen</h3>
  <table>
    <thead>
      <tr><th>Datum</th><th>Produkt</th><th>Menge</th><th>Aktion</th><th>Mitarbeiter</th><th>Löschen</th></tr>
    </thead>
    <tbody>
      <?php foreach ($verlauf as $v): ?>
        <tr>
          <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
          <td><?= htmlspecialchars($v['produkt']) ?></td>
          <td><?= htmlspecialchars($v['menge']) ?></td>
          <td><span class="badge <?= $v['aktion']==='hinzugefügt'?'plus':'minus' ?>"><?= htmlspecialchars($v['aktion']) ?></span></td>
          <td><?= htmlspecialchars($v['mitarbeiter']) ?></td>
          <td><a href="?delete=<?= $v['id'] ?>" onclick="return confirm('Eintrag wirklich löschen?')">🗑️</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="back-wrap" style="margin-top:40px;">
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
