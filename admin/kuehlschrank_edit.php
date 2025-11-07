<?php
session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';

/* === Aktionen === */

// 🔹 Produkt hinzufügen oder aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produkt_name'])) {
  $name = trim($_POST['produkt_name']);
  $bestand = (int)$_POST['bestand'];
  $preis = (float)$_POST['preis'];
  $kategorie = trim($_POST['kategorie']);

  if (!empty($_POST['id'])) {
    $stmt = $pdo->prepare("UPDATE kuehlschrank_lager SET produkt=?, bestand=?, preis=?, kategorie=? WHERE id=?");
    $stmt->execute([$name, $bestand, $preis, $kategorie, (int)$_POST['id']]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO kuehlschrank_lager (produkt, bestand, preis, kategorie) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $bestand, $preis, $kategorie]);
  }
  header("Location: kuehlschrank_edit.php");
  exit;
}

// 🔹 Produkt löschen
if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM kuehlschrank_lager WHERE id=?")->execute([(int)$_GET['delete']]);
  header("Location: kuehlschrank_edit.php");
  exit;
}

// 🔹 Wochenabschluss
if (isset($_POST['archivieren'])) {
  $montag = date('Y-m-d', strtotime('monday last week'));
  $sonntag = date('Y-m-d', strtotime('sunday last week'));
  $woche = date('W', strtotime('last week'));

  $kosten = $pdo->query("SELECT * FROM kuehlschrank_wochenkosten WHERE woche_start = '$montag'")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($kosten as $k) {
    $pdo->prepare("INSERT INTO kuehlschrank_archiv (mitarbeiter, gesamt_kosten, woche, archiviert_am)
                   VALUES (?, ?, ?, NOW())")->execute([$k['mitarbeiter'], $k['gesamt_kosten'], $woche]);
  }
  $pdo->prepare("DELETE FROM kuehlschrank_wochenkosten WHERE woche_start = ?")->execute([$montag]);
  header("Location: kuehlschrank_edit.php?done=1");
  exit;
}

/* === Daten laden === */
$produkte = $pdo->query("SELECT * FROM kuehlschrank_lager ORDER BY kategorie, produkt")->fetchAll(PDO::FETCH_ASSOC);
$kosten = $pdo->query("SELECT * FROM kuehlschrank_wochenkosten ORDER BY gesamt_kosten DESC")->fetchAll(PDO::FETCH_ASSOC);
$verlauf = $pdo->query("SELECT * FROM kuehlschrank_verlauf ORDER BY datum DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧊 Kühlschranklager verwalten | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
main { max-width: 1200px; margin: 120px auto; padding: 20px; text-align:center; color:#fff; }
.card { background:rgba(25,25,25,0.9); border:1px solid rgba(57,255,20,0.4); border-radius:15px; padding:25px; margin-bottom:40px; box-shadow:0 0 18px rgba(57,255,20,0.25); }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th,td { border:1px solid rgba(57,255,20,0.25); padding:10px; }
th { background:rgba(57,255,20,0.15); color:#76ff65; text-shadow:0 0 10px rgba(57,255,20,0.5); }
input,select { padding:8px; border-radius:6px; border:1px solid rgba(57,255,20,0.3); background:rgba(20,20,20,0.9); color:#fff; }
button { background:linear-gradient(90deg,#39ff14,#76ff65); border:none; padding:8px 14px; border-radius:8px; color:#fff; cursor:pointer; transition:.3s; box-shadow:0 0 14px rgba(57,255,20,0.35); }
button:hover { transform:scale(1.05); box-shadow:0 0 18px rgba(57,255,20,0.6); }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>🧊 Kühlschranklager verwalten</h2>
  <?php if (isset($_GET['done'])): ?>
    <p style="color:#00ff9d;">✅ Wochenabschluss erfolgreich archiviert.</p>
  <?php endif; ?>

  <!-- Produkt hinzufügen -->
  <div class="card">
    <h3>➕ Produkt hinzufügen / bearbeiten</h3>
    <form method="post">
      <input type="hidden" name="id" value="">
      <input type="text" name="produkt_name" placeholder="Produktname" required>
      <input type="number" name="bestand" placeholder="Bestand" required>
      <input type="number" step="0.01" name="preis" placeholder="Preis (€)" required>
      <select name="kategorie">
        <option value="Essen">Essen</option>
        <option value="Trinken">Trinken</option>
      </select>
      <button type="submit">💾 Speichern</button>
    </form>
  </div>

  <!-- Aktuelle Produkte -->
  <div class="card">
    <h3>📦 Aktuelle Produkte</h3>
    <table>
      <thead><tr><th>Produkt</th><th>Kategorie</th><th>Bestand</th><th>Preis (€)</th><th>Aktion</th></tr></thead>
      <tbody>
        <?php foreach ($produkte as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['produkt']) ?></td>
            <td><?= htmlspecialchars($p['kategorie']) ?></td>
            <td><?= (int)$p['bestand'] ?></td>
            <td><?= number_format($p['preis'], 2, ',', '.') ?></td>
            <td><a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Wirklich löschen?')">🗑️</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mitarbeiterkosten -->
  <div class="card">
    <h3>💰 Aktuelle Wochenkosten (Mitarbeiter)</h3>
    <table>
      <thead><tr><th>Mitarbeiter</th><th>Kosten (€)</th><th>Woche</th></tr></thead>
      <tbody>
        <?php foreach ($kosten as $k): ?>
          <tr>
            <td><?= htmlspecialchars($k['mitarbeiter']) ?></td>
            <td><?= number_format($k['gesamt_kosten'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($k['woche_start']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" style="margin-top:20px;">␊
      <button name="archivieren" type="submit" style="background:linear-gradient(90deg,#39ff14,#76ff65);box-shadow:0 0 16px rgba(57,255,20,0.5);">📦 Wochenabschluss & Archivierung</button>
    </form>
  </div>

  <!-- Verlauf -->
  <div class="card">
    <h3>🕒 Letzte Entnahmen</h3>
    <table>
      <thead><tr><th>Datum</th><th>Mitarbeiter</th><th>Produkt</th><th>Menge</th><th>Gesamt (€)</th></tr></thead>
      <tbody>
        <?php foreach ($verlauf as $v): ?>
          <tr>
            <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
            <td><?= htmlspecialchars($v['mitarbeiter']) ?></td>
            <td><?= htmlspecialchars($v['produkt']) ?></td>
            <td><?= (int)$v['menge'] ?></td>
            <td><?= number_format($v['gesamtpreis'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="back-wrap">
    <a href="dashboard.php" class="btn btn-ghost">← Zurück zum Dashboard</a>
    <a href="kuehlschrank_archiv.php" class="btn btn-ghost">📚 Zum Archiv</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
