<?php
session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';


/* === Archiv laden === */
$archiv = $pdo->query("SELECT * FROM kuehlschrank_archiv ORDER BY archiviert_am DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📚 Kühlschrank-Archiv | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
main { max-width:1100px; margin:120px auto; padding:20px; text-align:center; color:#fff; }
.card { background:rgba(25,25,25,0.9); border:1px solid rgba(57,255,20,0.4); border-radius:15px; padding:25px; box-shadow:0 0 18px rgba(57,255,20,0.25); }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th,td { border:1px solid rgba(57,255,20,0.25); padding:10px; }
th { background:rgba(57,255,20,0.15); color:#76ff65; text-shadow:0 0 10px rgba(57,255,20,0.5); }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>📚 Archivierte Wochen</h2>
  <div class="card">
    <table>
      <thead><tr><th>Woche</th><th>Mitarbeiter</th><th>Kosten (€)</th><th>Archiviert am</th></tr></thead>
      <tbody>
        <?php foreach ($archiv as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['woche']) ?></td>
            <td><?= htmlspecialchars($a['mitarbeiter']) ?></td>
            <td><?= number_format($a['gesamt_kosten'], 2, ',', '.') ?></td>
            <td><?= date('d.m.Y H:i', strtotime($a['archiviert_am'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="back-wrap">
    <a href="kuehlschrank_edit.php" class="btn btn-ghost">← Zurück zur Verwaltung</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
