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
  $stmt = $pdo->prepare("INSERT INTO fahrzeuge (fahrzeug_typ, kennzeichen, fahrer, tankstand, beschaedigungen, pruefdatum)
                         VALUES (?, ?, ?, ?, ?, ?)");
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
  $stmt = $pdo->prepare("UPDATE fahrzeuge
                          SET fahrzeug_typ=?, kennzeichen=?, fahrer=?, tankstand=?, beschaedigungen=?, pruefdatum=?
                          WHERE id=?");
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
$fahrzeuge = $pdo->query("SELECT * FROM fahrzeuge ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

/* === Fahrzeugtypen === */
$fahrzeug_typen = ['GBBISONHF', 'Jugular', 'BRASTX'];

/* === Kennzeichen pro Typ === */
$kennzeichen_map = [
  'GBBISONHF' => ["NFV 124","RAS 391","VXX 095","UXC 037","HMK 245"],
  'Jugular'   => ["UZL 323","XIY 594","IKV 547","AXX 743","UDZ 359","KBU 339","ITT 343","MLL 139","EVY 813"],
  'BRASTX'    => ["XVX 271","KJS 019","JAC 153","ISX 602","LPU 767"]
];

/* === Kennzahlen === */
$anzahlFahrzeuge = count($fahrzeuge);
$anzahlFahrer = count($fahrer_liste);
$wartungFällig = 0;
$durchschnittTank = 0;

foreach ($fahrzeuge as $fz) {
  $tank = preg_replace('/[^0-9.]/', '', (string)$fz['tankstand']);
  if ($tank !== '') {
    $durchschnittTank += (float)$tank;
  }
  if (!empty($fz['pruefdatum'])) {
    $diff = (strtotime($fz['pruefdatum']) - time()) / (60 * 60 * 24);
    if ($diff <= 30) {
      $wartungFällig++;
    }
  }
}
$durchschnittTank = $anzahlFahrzeuge ? $durchschnittTank / $anzahlFahrzeuge : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🚗 Fahrzeuge verwalten | Admin</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.vehicle-form-grid {
  display: grid;
  gap: 18px;
}

@media (min-width: 900px) {
  .vehicle-form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.vehicle-table select,
.vehicle-table textarea,
.vehicle-table input[type="text"],
.vehicle-table input[type="date"] {
  width: 100%;
  background: rgba(10, 12, 13, 0.9);
  border: 1px solid rgba(57, 255, 20, 0.25);
  border-radius: 10px;
  padding: 10px 12px;
  color: #fff;
  font: inherit;
}

.vehicle-table textarea {
  resize: vertical;
  min-height: 48px;
}

.vehicle-table td {
  vertical-align: top;
}

.vehicle-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.pruef-warnung {
  margin-top: 6px;
  color: #ffb4d4;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🚗 Fahrzeugverwaltung</h1>
    <p class="inventory-description">
      Pflege Dienstfahrzeuge, hinterlege Fahrtenbuch-Informationen und plane Prüfungen – alles an einem Ort.
    </p>
    <p class="inventory-info">
      Aktiver Fuhrpark: <?= $anzahlFahrzeuge ?> Fahrzeuge · verfügbare Fahrer:innen: <?= $anzahlFahrer ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Fahrzeuge</span>
        <span class="inventory-metric__value"><?= number_format($anzahlFahrzeuge, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">in der Datenbank</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Ø Tankstand</span>
        <span class="inventory-metric__value"><?= number_format($durchschnittTank, 1, ',', '.') ?>%</span>
        <span class="inventory-metric__hint">auf Basis eingetragener Werte</span>
      </article>
      <article class="inventory-metric <?= $wartungFällig ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Prüfung fällig (≤30 Tage)</span>
        <span class="inventory-metric__value"><?= number_format($wartungFällig, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Bitte zeitnah planen</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Neues Fahrzeug eintragen</h2>
    <p class="inventory-section__intro">
      Hinterlege neue Fahrzeuge inklusive Fahrer:in und Prüftermin.
    </p>

    <form method="post" class="inventory-form">
      <input type="hidden" name="add" value="1">

      <div class="vehicle-form-grid">
        <div class="input-control">
          <label for="fahrzeug_typ_add">Fahrzeugtyp</label>
          <select id="fahrzeug_typ_add" name="fahrzeug_typ" class="inventory-select" required>
            <option value="">– bitte wählen –</option>
            <?php foreach ($fahrzeug_typen as $typ): ?>
              <option value="<?= $typ ?>"><?= $typ ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-control">
          <label for="kennzeichen_add">Kennzeichen</label>
          <select id="kennzeichen_add" name="kennzeichen" class="inventory-select" required>
            <option value="">– bitte Typ wählen –</option>
          </select>
        </div>

        <div class="input-control">
          <label for="fahrer_add">Fahrer:in</label>
          <select id="fahrer_add" name="fahrer" class="inventory-select" required>
            <option value="">– bitte Fahrer wählen –</option>
            <?php foreach ($fahrer_liste as $fahrer): ?>
              <option value="<?= htmlspecialchars($fahrer) ?>"><?= htmlspecialchars($fahrer) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-control">
          <label for="tankstand_add">Tankstand</label>
          <input id="tankstand_add" class="input-field" type="text" name="tankstand" placeholder="z. B. 75%">
        </div>

        <div class="input-control">
          <label for="beschaedigungen_add">Beschädigungen / Hinweise</label>
          <textarea id="beschaedigungen_add" name="beschaedigungen" rows="3" placeholder="z. B. Kratzer hinten links"></textarea>
        </div>

        <div class="input-control">
          <label for="pruefdatum_add">Nächste Prüfung</label>
          <input id="pruefdatum_add" class="input-field" type="date" name="pruefdatum">
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">+ Fahrzeug hinzufügen</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Fahrzeuge im Überblick</h2>
    <?php if ($fahrzeuge): ?>
      <div class="table-wrap">
        <table class="data-table vehicle-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Typ</th>
              <th>Kennzeichen</th>
              <th>Fahrer:in</th>
              <th>Tankstand</th>
              <th>Schäden / Hinweise</th>
              <th>Prüfdatum</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fahrzeuge as $fz): ?>
              <?php
                $warnung = false;
                if (!empty($fz['pruefdatum'])) {
                  $diff = (strtotime($fz['pruefdatum']) - time()) / (60*60*24);
                  $warnung = $diff <= 30;
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
                        <option value="<?= $kennz ?>" <?= $fz['kennzeichen'] === $kennz ? 'selected' : '' ?>><?= $kennz ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="fahrer" required>
                      <option value="">– bitte wählen –</option>
                      <?php foreach ($fahrer_liste as $fahrer): ?>
                        <option value="<?= htmlspecialchars($fahrer) ?>" <?= ($fz['fahrer'] === $fahrer) ? 'selected' : '' ?>><?= htmlspecialchars($fahrer) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="text" name="tankstand" value="<?= htmlspecialchars($fz['tankstand']) ?>"></td>
                  <td><textarea name="beschaedigungen" rows="2"><?= htmlspecialchars($fz['beschaedigungen']) ?></textarea></td>
                  <td>
                    <input type="date" name="pruefdatum" value="<?= htmlspecialchars($fz['pruefdatum']) ?>">
                    <?php if ($warnung): ?>
                      <div class="pruef-warnung">⚠ Prüfung fällig!</div>
                    <?php endif; ?>
                  </td>
                  <td class="vehicle-actions">
                    <input type="hidden" name="edit_id" value="<?= $fz['id'] ?>">
                    <button type="submit" class="inventory-submit inventory-submit--small">💾 Speichern</button>
                    <a href="?delete=<?= $fz['id'] ?>" class="inventory-submit inventory-submit--ghost inventory-submit--small"
                       onclick="return confirm('Fahrzeug wirklich löschen?')">🗑️</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Derzeit sind keine Fahrzeuge eingetragen.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script>
const kennzeichenMap = <?= json_encode($kennzeichen_map) ?>;
const fahrzeugeData = <?= json_encode($fahrzeuge) ?>;
const addTypeSelect = document.getElementById('fahrzeug_typ_add');
const addKennzSelect = document.getElementById('kennzeichen_add');

addTypeSelect.addEventListener('change', () => {
  const selectedType = addTypeSelect.value;
  addKennzSelect.innerHTML = '<option value="">– bitte wählen –</option>';
  if (kennzeichenMap[selectedType]) {
    kennzeichenMap[selectedType].forEach(k => {
      const opt = document.createElement('option');
      opt.value = k;
      opt.textContent = k;
      addKennzSelect.appendChild(opt);
    });
  }
});

document.querySelectorAll('.fahrzeug-edit-form').forEach(form => {
  const typSelect = form.querySelector('.fahrzeug_typ_edit');
  const kennzSelect = form.querySelector('.kennzeichen_edit');
  const fahrzeugId = form.querySelector('input[name="edit_id"]').value;

  const fillKennzeichen = (selectedType) => {
    const currentVehicle = fahrzeugeData.find(f => f.id == fahrzeugId);
    kennzSelect.innerHTML = '';
    if (kennzeichenMap[selectedType]) {
      kennzeichenMap[selectedType].forEach((k, index) => {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = k;
        if (currentVehicle && currentVehicle.fahrzeug_typ === selectedType && currentVehicle.kennzeichen === k) {
          opt.selected = true;
        } else if ((!currentVehicle || currentVehicle.fahrzeug_typ !== selectedType) && index === 0) {
          opt.selected = true;
        }
        kennzSelect.appendChild(opt);
      });
    }
  };

  const updateVehicleRecord = () => {
    const record = fahrzeugeData.find(f => f.id == fahrzeugId);
    if (record) {
      record.fahrzeug_typ = typSelect.value;
      record.kennzeichen = kennzSelect.value;
    }
  };

  typSelect.addEventListener('change', () => {
    fillKennzeichen(typSelect.value);
    updateVehicleRecord();
  });

  kennzSelect.addEventListener('change', updateVehicleRecord);

  updateVehicleRecord();

  // Falls bereits ein Typ vorausgewählt ist, behalten wir die initiale Option.
});
</script>
<script src="../script.js"></script>
</body>
</html>