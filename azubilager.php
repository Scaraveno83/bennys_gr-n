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

/* === Mitarbeiter-Rang abrufen === */
$stmt = $pdo->prepare("
  SELECT m.rang 
  FROM mitarbeiter m 
  JOIN user_accounts u ON u.mitarbeiter_id = m.id 
  WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$rang = $stmt->fetchColumn();

/* === Zugriffsbeschränkung (nur Azubis, Ausbilder, Admins) === */
$erlaubteRollen = [
  'Geschäftsführung',
  'Stv. Geschäftsleitung',
  'Personalleitung',  
  'Ausbilder/in',
  'Azubi 1.Jahr',
  'Azubi 2.Jahr',
  'Azubi 3.Jahr',
  'Praktikant/in'
];

$isAdmin = ($_SESSION['user_role'] === 'admin');
if (!$isAdmin && (!$rang || !in_array($rang, $erlaubteRollen))) {
  header("Location: index.php");
  exit;
}

/* === Benutzername === */
$nutzername = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Unbekannt';

/* === Produktliste (alphabetisch sortiert, angepasst) === */
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

/* === Neue Lageraktion === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $produkt = trim($_POST['produkt']);
  $menge = intval($_POST['menge']);
  $aktion = $_POST['aktion'] ?? '';

  if ($produkt && $menge > 0 && in_array($aktion, ['hinzugefügt', 'entnommen'])) {

    // Verlauf speichern
    $stmt = $pdo->prepare("
      INSERT INTO azubi_lager_verlauf (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$produkt, $menge, $aktion, $nutzername]);

    // Produkt anlegen, wenn es noch nicht existiert
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

  header("Location: azubilager.php");
  exit;
}

/* === Bestände abrufen === */
$bestand = [];
$stmt = $pdo->query("SELECT produkt, bestand FROM azubi_lager ORDER BY produkt ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $bestand[$row['produkt']] = (int)$row['bestand'];
}

/* === Fehlende Produkte ergänzen === */
foreach ($produkte as $p) {
  if (!isset($bestand[$p])) $bestand[$p] = 0;
}

/* === Verlauf (nur Admin) === */
$verlauf = [];
if ($isAdmin) {
  $verlauf = $pdo->query("SELECT * FROM azubi_lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🧰 Azubilager | Benny’s Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">

<style>
main {
  padding: 120px 40px;
  max-width: 1100px;
  margin: 0 auto;
  color: #fff;
  text-align: center;
}
.card {
  background: rgba(25,25,25,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 25px;
  margin-bottom: 40px;
  box-shadow: 0 0 15px rgba(57,255,20,0.25);
}
h2, h3 { color: #76ff65; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid rgba(57,255,20,0.25); padding: 10px; }
th { background: rgba(57,255,20,0.18); color: #76ff65; }
tr:hover { background: rgba(57,255,20,0.08); }
.low-stock { color: #76ff65; font-weight: bold; text-shadow: 0 0 10px rgba(57,255,20,0.6); }
.badge { display:inline-block; padding:3px 10px; border-radius:6px; font-weight:bold; }
.badge.plus { background:rgba(0,200,0,0.2); color:#7CFC00; border:1px solid #7CFC00; }
.badge.minus { background:rgba(255,0,0,0.2); color:#76ff65; border:1px solid #76ff65; }
form select, form input { width:100%; padding:10px; margin-bottom:12px; border-radius:8px; border:1px solid rgba(57,255,20,0.35); background:rgba(20,20,20,0.85); color:#fff; }
form button {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  border: none; padding: 10px 20px; border-radius: 8px;
  color: #fff; cursor: pointer; transition: .3s;
}
form button:hover { transform: scale(1.05); box-shadow: 0 0 15px rgba(57,255,20,0.6); }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>🧰 Azubilager</h2>
  <p>Hier können Auszubildende und Ausbilder Materialien ein- und auslagern. Admins sehen zusätzlich den vollständigen Verlauf.</p>

  <!-- 📦 Bestände -->
  <div class="card">
    <h3>📦 Aktuelle Bestände</h3>
    <table>
      <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
      <tbody>
        <?php foreach ($bestand as $produkt => $menge): ?>
          <tr>
            <td><?= htmlspecialchars($produkt) ?></td>
            <td class="<?= $menge < 50 ? 'low-stock' : '' ?>"><?= $menge ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ➕➖ Aktionen -->
  <div class="card">
    <h3>➕/➖ Lageraktion durchführen</h3>
    <form method="post">
      <label>Produkt:</label>
      <select name="produkt" required>
        <option value="">– bitte wählen –</option>
        <?php foreach ($produkte as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Menge:</label>
      <input type="number" name="menge" min="1" required>

      <label>Aktion:</label>
      <select name="aktion" required>
        <option value="hinzugefügt">Hinzufügen</option>
        <option value="entnommen">Entnehmen</option>
      </select>

      <button type="submit">Speichern</button>
    </form>
  </div>

  <!-- 🕒 Verlauf -->
  <?php if ($isAdmin): ?>
  <div class="card">
    <h3>🕒 Verlauf aller Aktionen (Admin)</h3>
    <table>
      <thead>
        <tr><th>Datum</th><th>Produkt</th><th>Menge</th><th>Aktion</th><th>Mitarbeiter</th></tr>
      </thead>
      <tbody>
        <?php foreach ($verlauf as $v): ?>
          <tr>
            <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
            <td><?= htmlspecialchars($v['produkt']) ?></td>
            <td><?= htmlspecialchars($v['menge']) ?></td>
            <td><span class="badge <?= $v['aktion']==='hinzugefügt'?'plus':'minus' ?>"><?= htmlspecialchars($v['aktion']) ?></span></td>
            <td><?= htmlspecialchars($v['mitarbeiter']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="script.js"></script>
</body>
</html>
