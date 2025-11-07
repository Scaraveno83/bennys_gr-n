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
  echo "<h2 style='color:#76ff65;text-align:center;margin-top:120px;'>🚫 Zugriff verweigert</h2>
        <p style='text-align:center;color:#fff;'>Dein Rang <b>" . htmlspecialchars($userRang ?: 'Unbekannt') . "</b> hat keinen Zugriff auf das Bürolager.</p>";
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

/* === Verlauf laden === */
$verlauf = $pdo->query("SELECT * FROM buero_lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📁 Bürolager | Benny’s Werkstatt</title>
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
form select, form input { width:100%; padding:10px; margin-bottom:12px; border-radius:8px; border:1px solid rgba(57,255,20,0.35); background:rgba(20,20,20,0.85); color:#fff; }
form button {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  border: none; padding: 10px 20px; border-radius: 8px;
  color: #fff; cursor: pointer; transition: .3s;
}
form button:hover { transform: scale(1.05); box-shadow: 0 0 15px rgba(57,255,20,0.6); }
.badge { display:inline-block; padding:3px 10px; border-radius:6px; font-weight:bold; }
.badge.plus { background:rgba(0,200,0,0.2); color:#7CFC00; border:1px solid #7CFC00; }
.badge.minus { background:rgba(0,200,0,0.2); color:#ff6b6b; border:1px solid #ff6b6b; }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>📁 Bürolager</h2>
  <p>Hier können berechtigte Mitarbeiter Büro- und Verwaltungsartikel ein- und auslagern.</p>

  <!-- 📦 Bestände -->
  <div class="card">
    <h3>📦 Aktuelle Bestände</h3>
    <table>
      <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
      <tbody>
        <?php foreach ($bestand as $produkt => $menge): ?>
          <tr>
            <td><?= htmlspecialchars($produkt) ?></td>
            <td class="<?= $menge < 10 ? 'low-stock' : '' ?>"><?= $menge ?></td>
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
  <?php if (!empty($verlauf)): ?>
  <div class="card">
    <h3>🕒 Letzte Aktionen</h3>
    <table>
      <thead><tr><th>Datum</th><th>Produkt</th><th>Menge</th><th>Aktion</th><th>Mitarbeiter</th></tr></thead>
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
