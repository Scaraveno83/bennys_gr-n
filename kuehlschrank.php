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

/* === Produkte abrufen === */
$produkte = $pdo->query("SELECT * FROM kuehlschrank_lager ORDER BY kategorie, produkt")->fetchAll(PDO::FETCH_ASSOC);

/* === Entnahme buchen === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produkt_id'])) {
  $id = (int)$_POST['produkt_id'];
  $menge = (int)$_POST['menge'];

  $stmt = $pdo->prepare("SELECT * FROM kuehlschrank_lager WHERE id = ?");
  $stmt->execute([$id]);
  $produkt = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($produkt && $menge > 0 && $produkt['bestand'] >= $menge) {
    $preis_einzel = (float)$produkt['preis'];
    $gesamtpreis = $preis_einzel * $menge;

    // Bestand verringern
    $pdo->prepare("UPDATE kuehlschrank_lager SET bestand = bestand - ? WHERE id = ?")
        ->execute([$menge, $id]);

    // Verlauf speichern
    $pdo->prepare("INSERT INTO kuehlschrank_verlauf (mitarbeiter, produkt, menge, preis_einzel, gesamtpreis, datum)
                   VALUES (?, ?, ?, ?, ?, NOW())")
        ->execute([$nutzername, $produkt['produkt'], $menge, $preis_einzel, $gesamtpreis]);

    // Wochenkosten aktualisieren
    $heute = date('Y-m-d');
    $montag = date('Y-m-d', strtotime('monday this week'));
    $sonntag = date('Y-m-d', strtotime('sunday this week'));

    $check = $pdo->prepare("SELECT id FROM kuehlschrank_wochenkosten WHERE mitarbeiter = ? AND woche_start = ?");
    $check->execute([$nutzername, $montag]);
    if ($check->fetchColumn()) {
      $pdo->prepare("UPDATE kuehlschrank_wochenkosten SET gesamt_kosten = gesamt_kosten + ? 
                     WHERE mitarbeiter = ? AND woche_start = ?")
          ->execute([$gesamtpreis, $nutzername, $montag]);
    } else {
      $pdo->prepare("INSERT INTO kuehlschrank_wochenkosten (mitarbeiter, gesamt_kosten, woche_start, woche_ende)
                     VALUES (?, ?, ?, ?)")
          ->execute([$nutzername, $gesamtpreis, $montag, $sonntag]);
    }
  }

  header("Location: kuehlschrank.php");
  exit;
}

/* === Wochenkosten laden === */
$montag = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT gesamt_kosten FROM kuehlschrank_wochenkosten WHERE mitarbeiter = ? AND woche_start = ?");
$stmt->execute([$nutzername, $montag]);
$wochenkosten = (float)($stmt->fetchColumn() ?? 0.00);

/* === Verlauf (aktuelle Woche) === */
$stmt = $pdo->prepare("
  SELECT * FROM kuehlschrank_verlauf
  WHERE mitarbeiter = ? AND datum >= ?
  ORDER BY datum DESC
");
$stmt->execute([$nutzername, $montag]);
$verlauf = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧊 Kühlschranklager | Benny’s Werkstatt</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
<style>
main { padding:120px 40px; max-width:1000px; margin:0 auto; color:#fff; text-align:center; }
.card { background:rgba(25,25,25,0.9); border:1px solid rgba(57,255,20,0.4); border-radius:15px; padding:25px; margin-bottom:40px; box-shadow:0 0 18px rgba(57,255,20,0.25); }
h2,h3{ color:#76ff65; text-shadow:0 0 10px rgba(57,255,20,0.5); }
.low-stock{ color:#76ff65; font-weight:bold; text-shadow:0 0 10px rgba(57,255,20,0.55); }
button { background:linear-gradient(90deg,#39ff14,#76ff65); border:none; padding:10px 20px; border-radius:8px; color:#fff; cursor:pointer; transition:.3s; box-shadow:0 0 16px rgba(57,255,20,0.35); }
button:hover{ transform:scale(1.05); box-shadow:0 0 20px rgba(57,255,20,0.6); }
</style>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>🧊 Kühlschranklager</h2>
  <p>Hier kannst du Essen & Getränke entnehmen. Deine Kosten werden automatisch berechnet.</p>

  <div class="card">
    <h3>📦 Aktuelle Produkte</h3>
    <table>
      <thead><tr><th>Produkt</th><th>Kategorie</th><th>Preis (€)</th><th>Bestand</th><th>Aktion</th></tr></thead>
      <tbody>
        <?php foreach ($produkte as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['produkt']) ?></td>
            <td><?= htmlspecialchars($p['kategorie']) ?></td>
            <td><?= number_format($p['preis'], 2, ',', '.') ?></td>
            <td class="<?= $p['bestand'] < 3 ? 'low-stock' : '' ?>"><?= $p['bestand'] ?></td>
            <td>
              <?php if ($p['bestand'] > 0): ?>
                <form method="post" style="display:inline-block;">
                  <input type="hidden" name="produkt_id" value="<?= $p['id'] ?>">
                  <input type="number" name="menge" value="1" min="1" max="<?= $p['bestand'] ?>" style="width:60px;">
                  <button type="submit">🥪 Entnehmen</button>
                </form>
              <?php else: ?>
                <span style="color:#888;">leer</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>💰 Deine Wochenkosten</h3>
    <p style="font-size:1.2rem;">Bisherige Kosten: <strong><?= number_format($wochenkosten, 2, ',', '.') ?> €</strong></p>
  </div>

  <div class="card">
    <h3>🧾 Deine Entnahmen (aktuelle Woche)</h3>
    <?php if ($verlauf): ?>
      <table>
        <thead><tr><th>Datum</th><th>Produkt</th><th>Menge</th><th>Gesamt (€)</th></tr></thead>
        <tbody>
          <?php foreach ($verlauf as $v): ?>
            <tr>
              <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
              <td><?= htmlspecialchars($v['produkt']) ?></td>
              <td><?= (int)$v['menge'] ?></td>
              <td><?= number_format($v['gesamtpreis'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>Du hast diese Woche noch nichts entnommen.</p>
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
