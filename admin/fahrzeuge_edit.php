<?php
session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';


/* === LÖSCHEN === */
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM fahrzeuge WHERE id = ?")->execute([$id]);
  header("Location: fahrzeuge_edit.php");
  exit;
}

/* === Mitarbeiter laden für Dropdown === */
$fahrer_stmt = $pdo->query("SELECT name FROM mitarbeiter ORDER BY name ASC");
$fahrer_liste = $fahrer_stmt->fetchAll(PDO::FETCH_COLUMN);

/* === HINZUFÜGEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $stmt = $pdo->prepare("
    INSERT INTO fahrzeuge (fahrzeug_typ, kennzeichen, fahrer, tankstand, beschaedigungen, pruefdatum)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $_POST['fahrzeug_typ'],
    $_POST['kennzeichen'],
    $_POST['fahrer'],
    $_POST['tankstand'],
    $_POST['beschaedigungen'],
    $_POST['pruefdatum'] ?: null
  ]);
  header("Location: fahrzeuge_edit.php");
  exit;
}

/* === BEARBEITEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $stmt = $pdo->prepare("
    UPDATE fahrzeuge
    SET fahrzeug_typ=?, kennzeichen=?, fahrer=?, tankstand=?, beschaedigungen=?, pruefdatum=?
    WHERE id=?
  ");
  $stmt->execute([
    $_POST['fahrzeug_typ'],
    $_POST['kennzeichen'],
    $_POST['fahrer'],
    $_POST['tankstand'],
    $_POST['beschaedigungen'],
    $_POST['pruefdatum'] ?: null,
    $_POST['edit_id']
  ]);
  header("Location: fahrzeuge_edit.php");
  exit;
}

/* === LADEN === */
$fahrzeuge = $pdo->query("SELECT * FROM fahrzeuge ORDER BY id ASC")->fetchAll();

/* === Fahrzeugtypen === */
$fahrzeug_typen = ['GBBISONHF', 'Jugular', 'BRASTX'];

/* === Kennzeichen pro Typ === */
$kennzeichen_map = [
  'GBBISONHF' => ["NFV 124","RAS 391","VXX 095","UXC 037","HMK 245"],
  'Jugular'   => ["UZL 323","XIY 594","IKV 547","AXX 743","UDZ 359","KBU 339","ITT 343","MLL 139","EVY 813"],
  'BRASTX'    => ["XVX 271","KJS 019","JAC 153","ISX 602","LPU 767"]
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fahrzeuge verwalten | Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">

<style>
main {
  max-width: 1200px;
  margin: 120px auto 80px;
  padding: 0 40px;
}
.card.glass { margin-bottom: 40px; }
.fahrzeug-form {
  display: grid;
  gap: 10px;
  margin-top: 15px;
}
.fahrzeug-form input,
.fahrzeug-form textarea,
.fahrzeug-form select {
  background: rgba(20,20,20,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 8px;
  padding: 8px 10px;
  color: #fff;
  font-family: inherit;
}
.fahrzeug-form select option {
  background: #111;
  color: #fff;
}
.fahrzeug-form input:focus,
.fahrzeug-form textarea:focus,
.fahrzeug-form select:focus {
  outline: none;
  border-color: #76ff65;
  box-shadow: 0 0 12px rgba(57,255,20,0.6)
}
.fahrzeug-tabelle {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}
.fahrzeug-tabelle th, .fahrzeug-tabelle td {
  padding: 10px;
  border-bottom: 1px solid rgba(57,255,20,0.3);
}
.fahrzeug-tabelle th {
  background: rgba(57,255,20,0.1);
  color: #76ff65;
}
.fahrzeug-tabelle tr:hover {
  background: rgba(57,255,20,0.08);
}
.fahrzeug-tabelle input,
.fahrzeug-tabelle textarea,
.fahrzeug-tabelle select {
  width: 100%;
  background: rgba(25,25,25,0.9);
  color: #fff;
  border: 1px solid rgba(57,255,20,0.3);
  border-radius: 6px;
  padding: 5px 8px;
  font-family: inherit;
}
.fahrzeug-tabelle select option {
  background: #111;
  color: #fff;
}
.fahrzeug-tabelle button {
  padding: 6px 10px;
  font-size: 0.9rem;
}
.pruef-warnung {
  color: #39ff14;
  font-weight: bold;
  text-shadow: 0 0 8px rgba(57,255,20,.7);
  font-size: 0.85rem;
  margin-top: 3px;
}
.back-btn {
  display: inline-block;
  margin-top: 40px;
  text-decoration: none;
  border: 2px solid #39ff14;
  color: #39ff14;
  padding: 12px 28px;
  border-radius: 10px;
  font-weight: bold;
  transition: all 0.3s ease;
  box-shadow: 0 0 8px rgba(57,255,20,0.4);
}
.back-btn:hover {
  background: linear-gradient(90deg, #39ff14, #76ff65);
  color: #fff;
  box-shadow: 0 0 25px rgba(57,255,20,.9), 0 0 45px rgba(57,255,20,.6);
  transform: scale(1.05);
}
.back-container { text-align: center; }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <section class="cards-section">
    <h2 class="section-title">🚗 Fahrzeugverwaltung</h2>

    <!-- Neues Fahrzeug hinzufügen -->
    <div class="card glass">
      <h3>Neues Fahrzeug hinzufügen</h3>
      <form method="post" class="fahrzeug-form" id="addForm">
        <input type="hidden" name="add" value="1">

        <label>Fahrzeugtyp:</label>
        <select name="fahrzeug_typ" id="fahrzeug_typ_add" required>
          <option value="">– bitte wählen –</option>
          <?php foreach ($fahrzeug_typen as $typ): ?>
            <option value="<?= $typ ?>"><?= $typ ?></option>
          <?php endforeach; ?>
        </select>

        <label>Kennzeichen:</label>
        <select name="kennzeichen" id="kennzeichen_add" required>
          <option value="">– bitte Typ wählen –</option>
        </select>

        <label>Fahrer:</label>
        <select name="fahrer" required>
          <option value="">– bitte Fahrer wählen –</option>
          <?php foreach ($fahrer_liste as $fahrer): ?>
            <option value="<?= htmlspecialchars($fahrer) ?>"><?= htmlspecialchars($fahrer) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Tankstand:</label>
        <input type="text" name="tankstand">

        <label>Beschädigungen:</label>
        <textarea name="beschaedigungen" rows="3"></textarea>

        <label>Prüfdatum:</label>
        <input type="date" name="pruefdatum">

        <button type="submit" class="btn btn-primary">+ Fahrzeug hinzufügen</button>
      </form>
    </div>

    <!-- Fahrzeugliste -->
    <div class="card glass">
      <h3>Bestehende Fahrzeuge</h3>
      <?php if ($fahrzeuge): ?>
        <table class="fahrzeug-tabelle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Typ</th>
              <th>Kennzeichen</th>
              <th>Fahrer</th>
              <th>Tank</th>
              <th>Schäden</th>
              <th>Prüfdatum</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($fahrzeuge as $fz): 
            $warnung = false;
            if ($fz['pruefdatum']) {
              $diff = (strtotime($fz['pruefdatum']) - time()) / (60*60*24);
              if ($diff <= 30) $warnung = true;
            }
          ?>
            <tr>
              <form method="post" class="fahrzeug-edit-form">
                <td><?= $fz['id'] ?></td>
                <td>
                  <select name="fahrzeug_typ" class="fahrzeug_typ_edit" required>
                    <?php foreach ($fahrzeug_typen as $typ): ?>
                      <option value="<?= $typ ?>" <?= $fz['fahrzeug_typ'] === $typ ? 'selected' : '' ?>><?= $typ ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="kennzeichen" class="kennzeichen_edit" required>
                    <?php
                    $aktTyp = $fz['fahrzeug_typ'];
                    foreach ($kennzeichen_map[$aktTyp] as $kennz):
                    ?>
                      <option value="<?= $kennz ?>" <?= $fz['kennzeichen'] === $kennz ? 'selected' : '' ?>>
                        <?= $kennz ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="fahrer" required>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($fahrer_liste as $fahrer): ?>
                      <option value="<?= htmlspecialchars($fahrer) ?>" <?= ($fz['fahrer'] === $fahrer) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fahrer) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" name="tankstand" value="<?= htmlspecialchars($fz['tankstand']) ?>"></td>
                <td><textarea name="beschaedigungen" rows="2"><?= htmlspecialchars($fz['beschaedigungen']) ?></textarea></td>
                <td>
                  <input type="date" name="pruefdatum" value="<?= htmlspecialchars($fz['pruefdatum']) ?>">
                  <?php if ($warnung): ?><div class="pruef-warnung">⚠ bald prüfen!</div><?php endif; ?>
                </td>
                <td>
                  <input type="hidden" name="edit_id" value="<?= $fz['id'] ?>">
                  <button type="submit" class="btn btn-primary">💾</button>
                  <a href="?delete=<?= $fz['id'] ?>" class="btn btn-ghost" onclick="return confirm('Fahrzeug wirklich löschen?')">🗑️</a>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Keine Fahrzeuge vorhanden.</p>
      <?php endif; ?>
    </div>

    <div class="back-container">
      <a href="dashboard.php" class="back-btn">← Zurück zum Dashboard</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Adminbereich</p>
</footer>

<script>
const kennzeichenMap = <?= json_encode($kennzeichen_map) ?>;
document.getElementById('fahrzeug_typ_add').addEventListener('change', function() {
  const selectedType = this.value;
  const kennzSelect = document.getElementById('kennzeichen_add');
  kennzSelect.innerHTML = '<option value="">– bitte wählen –</option>';
  if (kennzeichenMap[selectedType]) {
    kennzeichenMap[selectedType].forEach(k => {
      const opt = document.createElement('option');
      opt.value = k;
      opt.textContent = k;
      kennzSelect.appendChild(opt);
    });
  }
});
document.querySelectorAll('.fahrzeug-edit-form').forEach(form => {
  const typSelect = form.querySelector('.fahrzeug_typ_edit');
  const kennzSelect = form.querySelector('.kennzeichen_edit');
  typSelect.addEventListener('change', () => {
    const selectedType = typSelect.value;
    kennzSelect.innerHTML = '';
    if (kennzeichenMap[selectedType]) {
      kennzeichenMap[selectedType].forEach(k => {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = k;
        kennzSelect.appendChild(opt);
      });
    }
  });
});
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
