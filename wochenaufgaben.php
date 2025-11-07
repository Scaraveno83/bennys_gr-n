<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';

/* === Zugriff prüfen (Admin oder Mitarbeiter) === */
if (
    empty($_SESSION['mitarbeiter_name']) &&
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header("Location: admin/login.php");
    exit;
}

$nutzername = $_SESSION['mitarbeiter_name'] ?? $_SESSION['admin_username'] ?? 'Unbekannt';


/* === Rang des Mitarbeiters abrufen === */
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

/* === Produkte === */
$produkte = ['Öl', 'Fasern', 'Stoff', 'Eisenbarren', 'Eisenerz'];

/* === Ranggruppen für Lagerzuweisung === */
$azubiRollen = [
  'Azubi 1.Jahr',
  'Azubi 2.Jahr',
  'Azubi 3.Jahr',
  'Praktikant/in'
];

$hauptlagerRollen = [
  'Geschäftsführung',
  'Stv. Geschäftsleitung',
  'Personalleitung',
  'Ausbilder/in',
  'Tuner/in',
  'Meister/in',
  'Mechaniker/in',
  'Geselle/Gesellin'
];

/* === AUTOMATISCHE ARCHIVIERUNG: alte Wochen verschieben === */
$alteEintraege = $pdo->query("
  SELECT * FROM wochenaufgaben
  WHERE YEARWEEK(datum, 1) < YEARWEEK(CURDATE(), 1)
")->fetchAll(PDO::FETCH_ASSOC);

if ($alteEintraege) {
  $archiv = $pdo->prepare("
    INSERT INTO wochenaufgaben_archiv (mitarbeiter, produkt, menge, datum, kalenderwoche)
    VALUES (?, ?, ?, ?, ?)
  ");
  $delete = $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?");
  foreach ($alteEintraege as $alt) {
    $archiv->execute([
      $alt['mitarbeiter'],
      $alt['produkt'],
      $alt['menge'],
      $alt['datum'],
      date('o-W', strtotime($alt['datum']))
    ]);
    $delete->execute([$alt['id']]);
  }
}

/* === AKTION: Eintrag hinzufügen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $produkt = trim($_POST['produkt']);
  $menge = intval($_POST['menge']);

  if ($produkt && $menge > 0) {
    // Eintrag speichern
    $stmt = $pdo->prepare("
      INSERT INTO wochenaufgaben (mitarbeiter, produkt, menge, datum)
      VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$nutzername, $produkt, $menge]);

    // Lager bestimmen
    if ($userRang && in_array($userRang, $azubiRollen)) {
      $lagerTabelle = "azubi_lager";
      $verlaufTabelle = "azubi_lager_verlauf";
    } else {
      $lagerTabelle = "hauptlager";
      $verlaufTabelle = "lager_verlauf";
    }

    // Produkt anlegen, falls nicht vorhanden
    $check = $pdo->prepare("SELECT COUNT(*) FROM $lagerTabelle WHERE produkt = ?");
    $check->execute([$produkt]);
    if ($check->fetchColumn() == 0) {
      $pdo->prepare("INSERT INTO $lagerTabelle (produkt, bestand) VALUES (?, 0)")
          ->execute([$produkt]);
    }

    // Bestand erhöhen
    $pdo->prepare("UPDATE $lagerTabelle SET bestand = bestand + ? WHERE produkt = ?")
        ->execute([$menge, $produkt]);

    // Verlauf speichern
    $pdo->prepare("
      INSERT INTO $verlaufTabelle (produkt, menge, aktion, mitarbeiter, datum)
      VALUES (?, ?, 'hinzugefügt', ?, NOW())
    ")->execute([$produkt, $menge, $nutzername]);
  }

  header("Location: wochenaufgaben.php");
  exit;
}

/* === Nur aktuelle Woche abrufen === */
$montag = date('Y-m-d', strtotime('monday this week'));
$sonntag = date('Y-m-d 23:59:59', strtotime('sunday this week'));

$stmt = $pdo->prepare("
  SELECT * FROM wochenaufgaben
  WHERE mitarbeiter = ? AND datum BETWEEN ? AND ?
  ORDER BY datum DESC
");
$stmt->execute([$nutzername, $montag, $sonntag]);
$eintraege = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* === Gesamtwerte berechnen === */
$gesamt = array_fill_keys($produkte, 0);
$gesamt['Summe'] = 0;
foreach ($eintraege as $e) {
  $p = $e['produkt'];
  $menge = (int)$e['menge'];
  if (isset($gesamt[$p])) $gesamt[$p] += $menge;
  $gesamt['Summe'] += $menge;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Meine Wochenaufgaben – Benny's Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="header.css" />
<link rel="stylesheet" href="styles.css" />

<style>
main {
  padding: 120px 40px 80px;
  max-width: 1000px;
  margin: 0 auto;
  text-align: center;
}
.card {
  background: rgba(25,25,25,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 25px;
  margin-bottom: 40px;
  color: #fff;
  box-shadow: 0 0 15px rgba(57,255,20,0.25);
}
form input, form select {
  width: 100%;
  padding: 10px;
  margin-bottom: 12px;
  border-radius: 8px;
  border: 1px solid rgba(57,255,20,0.35);
  background: rgba(20,20,20,0.85);
  color: #fff;
}
form button {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  transition: .3s;
}
form button:hover {
  transform: scale(1.05);
  box-shadow: 0 0 15px rgba(57,255,20,0.6);
}
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
th, td {
  border: 1px solid rgba(57,255,20,0.25);
  padding: 10px;
}
th {
  background: rgba(57,255,20,0.18);
  color: #76ff65;
}
tfoot td {
  background: rgba(57,255,20,0.15);
  font-weight: bold;
  color: #c8ffd5;
}
tr:hover {
  background: rgba(57,255,20,0.08);
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2 class="section-title">📦 Meine Wochenaufgaben</h2>
  <p>Hallo <strong><?= htmlspecialchars($nutzername) ?></strong>!  
  Hier siehst du, was du diese Woche (<?= date('d.m.', strtotime($montag)) ?> – <?= date('d.m.Y', strtotime($sonntag)) ?>) gefarmt hast.</p>

  <!-- Formular: Neuer Eintrag -->
  <div class="card">
    <h3>➕ Neuer Eintrag</h3>
    <form method="post">
      <input type="hidden" name="add" value="1">
      <label>Produkt:</label>
      <select name="produkt" required>
        <option value="">– bitte wählen –</option>
        <?php foreach ($produkte as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Menge:</label>
      <input type="number" name="menge" min="1" placeholder="z. B. 50" required>

      <button type="submit">Eintragen</button>
    </form>
  </div>

  <!-- Statistik -->
  <div class="card">
    <h3>📊 Meine Wochenstatistik</h3>
    <?php if ($eintraege): ?>
      <table>
        <thead>
          <tr>
            <th>Datum</th>
            <th>Produkt</th>
            <th>Menge</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eintraege as $e): ?>
            <tr>
              <td><?= date('d.m.Y H:i', strtotime($e['datum'])) ?></td>
              <td><?= htmlspecialchars($e['produkt']) ?></td>
              <td><?= htmlspecialchars($e['menge']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td><strong>Gesamt</strong></td>
            <td>
              <?php foreach ($produkte as $p): ?>
                <?= htmlspecialchars($p) ?>: <?= (int)$gesamt[$p] ?><br>
              <?php endforeach; ?>
            </td>
            <td><strong><?= (int)$gesamt['Summe'] ?></strong></td>
          </tr>
        </tfoot>
      </table>
    <?php else: ?>
      <p>Du hast diese Woche noch keine Aufgaben eingetragen.</p>
    <?php endif; ?>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
