<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';
require_once '../includes/wochenaufgaben_helpers.php';
require_once '../includes/wochenaufgaben_penalties.php';

/* === Produkte === */
$produkte = ['Öl', 'Fasern', 'Stoff', 'Eisenbarren', 'Eisenerz'];

/* === Mitarbeiter laden === */
$stmt_mitarbeiter = $pdo->query("SELECT name FROM mitarbeiter ORDER BY name ASC");
$mitarbeiter_liste = $stmt_mitarbeiter->fetchAll(PDO::FETCH_COLUMN);

ensureWochenaufgabenPlanTable($pdo);
wochenaufgaben_penalties_ensure_schema($pdo);

$penaltySettings = wochenaufgaben_penalties_get_settings($pdo);
$penaltyFeedback = null;
$penaltyGenerationSummary = null;
$penaltyPreview = [];
$penaltyRecords = [];

$selectedWeek = normalizeKalenderwoche($_GET['week'] ?? null);
$wochenzeitraum = getWeekPeriod($selectedWeek);
$zeitraumStart = $wochenzeitraum['start_datetime'];
$zeitraumEnde = $wochenzeitraum['end_datetime'];

if (isset($_GET['penalty_mark_paid'])) {
  $penaltyId = (int)$_GET['penalty_mark_paid'];
  if ($penaltyId > 0) {
    wochenaufgaben_penalties_mark_paid($pdo, $penaltyId);
  }
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

if (isset($_GET['penalty_mark_open'])) {
  $penaltyId = (int)$_GET['penalty_mark_open'];
  if ($penaltyId > 0) {
    wochenaufgaben_penalties_mark_open($pdo, $penaltyId);
  }
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_penalty_settings'])) {
  $input = [
    'penalty_base_amount' => str_replace(',', '.', (string)($_POST['penalty_base_amount'] ?? '0')),
    'penalty_per_unit' => str_replace(',', '.', (string)($_POST['penalty_per_unit'] ?? '0')),
    'penalty_threshold_percent' => (int)($_POST['penalty_threshold_percent'] ?? 0),
  ];

  wochenaufgaben_penalties_save_settings($pdo, $input);
  $penaltySettings = wochenaufgaben_penalties_get_settings($pdo);
  $penaltyFeedback = 'Strafgebühren-Einstellungen gespeichert.';
}

$previewCalculated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_penalties'])) {
  $penaltyPreview = wochenaufgaben_penalties_calculate($pdo, $selectedWeek, $penaltySettings);
  $previewCalculated = true;

  $senderId = $_SESSION['user_id'] ?? null;
  $created = 0;
  $updated = 0;
  $messages = 0;
  $candidates = 0;

  foreach ($penaltyPreview as $penaltyRow) {
    if ($penaltyRow['penalty_amount'] <= 0.0) {
      continue;
    }

    $candidates++;
    $result = wochenaufgaben_penalties_store($pdo, $penaltyRow, $senderId);
    if ($result['status'] === 'created') {
      $created++;
    } else {
      $updated++;
    }
    if (!empty($result['message_sent'])) {
      $messages++;
    }
  }

  $penaltyGenerationSummary = [
    'created' => $created,
    'updated' => $updated,
    'messages' => $messages,
    'candidates' => $candidates,
  ];

  $penaltyFeedback = 'Strafgebühren wurden aktualisiert.';
}

if (!$previewCalculated) {
  $penaltyPreview = wochenaufgaben_penalties_calculate($pdo, $selectedWeek, $penaltySettings);
}

$penaltyRecords = wochenaufgaben_penalties_fetch_for_week($pdo, $selectedWeek);

$penaltyStats = [
  'employees' => count($penaltyPreview),
  'with_penalty' => 0,
  'total_amount' => 0.0,
  'avg_completion' => 0.0,
  'total_missing' => 0,
];

if ($penaltyPreview) {
  $sumPercent = 0.0;
  foreach ($penaltyPreview as $previewRow) {
    $sumPercent += (float)$previewRow['prozent_erfuellt'];
    $penaltyStats['total_missing'] += (int)$previewRow['fehlende_summe'];
    if ($previewRow['penalty_amount'] > 0) {
      $penaltyStats['with_penalty']++;
      $penaltyStats['total_amount'] += (float)$previewRow['penalty_amount'];
    }
  }
  $penaltyStats['avg_completion'] = $penaltyStats['employees'] > 0
    ? round($sumPercent / $penaltyStats['employees'], 1)
    : 0.0;
  $penaltyStats['total_amount'] = round($penaltyStats['total_amount'], 2);
}

/* === Archivierung manuell anstoßen === */
if (isset($_GET['archive'])) {
  $stmt = $pdo->prepare("SELECT * FROM wochenaufgaben WHERE YEARWEEK(datum, 1) < YEARWEEK(CURDATE(), 1)");
  $stmt->execute();
  $alte = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($alte) {
    $archiv = $pdo->prepare("INSERT INTO wochenaufgaben_archiv (mitarbeiter, produkt, menge, datum, kalenderwoche)
                             VALUES (?, ?, ?, ?, ?)");
    $del = $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?");
    foreach ($alte as $row) {
      $archiv->execute([
        $row['mitarbeiter'],
        $row['produkt'],
        $row['menge'],
        $row['datum'],
        date('o-W', strtotime($row['datum']))
      ]);
      $del->execute([$row['id']]);
    }
  }
  header("Location: wochenaufgaben_edit.php?archived=1&week=" . urlencode($selectedWeek));
    exit;
}

/* === LÖSCHEN === */
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $pdo->prepare("DELETE FROM wochenaufgaben WHERE id = ?")->execute([$id]);
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

/* === PLANUNG: AUFGABE LÖSCHEN === */
if (isset($_GET['delete_task'])) {
  $taskId = (int)$_GET['delete_task'];
  $pdo->prepare("DELETE FROM wochenaufgaben_plan WHERE id = ?")->execute([$taskId]);
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

/* === HINZUFÜGEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $stmt = $pdo->prepare("INSERT INTO wochenaufgaben (mitarbeiter, produkt, menge, datum) VALUES (?, ?, ?, NOW())");
  $stmt->execute([
    trim($_POST['mitarbeiter']),
    trim($_POST['produkt']),
    intval($_POST['menge'])
  ]);
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

/* === BEARBEITEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $stmt = $pdo->prepare("UPDATE wochenaufgaben SET mitarbeiter=?, produkt=?, menge=? WHERE id=?");
  $stmt->execute([
    trim($_POST['mitarbeiter']),
    trim($_POST['produkt']),
    intval($_POST['menge']),
    (int)$_POST['edit_id']
  ]);
  header("Location: wochenaufgaben_edit.php?week=" . urlencode($selectedWeek));
  exit;
}

/* === PLANUNG: AUFGABE HINZUFÜGEN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
  $planWeek = normalizeKalenderwoche($_POST['kalenderwoche'] ?? $selectedWeek, $selectedWeek);
  $mitarbeiter = trim($_POST['mitarbeiter']);

  $produkte = $_POST['produkte'] ?? ($_POST['produkt'] ?? []);
  $zielmengen = $_POST['zielmengen'] ?? ($_POST['zielmenge'] ?? []);

  if (!is_array($produkte)) {
    $produkte = [$produkte];
  }
  if (!is_array($zielmengen)) {
    $zielmengen = [$zielmengen];
  }

  if ($mitarbeiter !== '') {
    $stmt = $pdo->prepare("INSERT INTO wochenaufgaben_plan (mitarbeiter, produkt, zielmenge, kalenderwoche) VALUES (?, ?, ?, ?)");

    foreach ($produkte as $index => $produkt) {
      $produkt = trim((string)$produkt);
      $zielmenge = isset($zielmengen[$index]) ? max(0, (int)$zielmengen[$index]) : 0;

      if ($produkt !== '' && $zielmenge > 0) {
        $stmt->execute([$mitarbeiter, $produkt, $zielmenge, $planWeek]);
      }
    }
  }

  header("Location: wochenaufgaben_edit.php?week=" . urlencode($planWeek));
  exit;
}

/* === PLANUNG: AUFGABE AKTUALISIEREN === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task_id'])) {
  $planWeek = normalizeKalenderwoche($_POST['kalenderwoche'] ?? $selectedWeek, $selectedWeek);
  $mitarbeiter = trim($_POST['mitarbeiter']);
  $produkt = trim($_POST['produkt']);
  $zielmenge = max(0, (int)$_POST['zielmenge']);
  $taskId = (int)$_POST['edit_task_id'];

  if ($taskId > 0 && $mitarbeiter !== '' && $produkt !== '' && $zielmenge > 0) {
    $stmt = $pdo->prepare("UPDATE wochenaufgaben_plan SET mitarbeiter=?, produkt=?, zielmenge=?, kalenderwoche=? WHERE id=?");
    $stmt->execute([$mitarbeiter, $produkt, $zielmenge, $planWeek, $taskId]);
  }

  header("Location: wochenaufgaben_edit.php?week=" . urlencode($planWeek));
  exit;
}

/* === EINTRÄGE LADEN (selektierte Woche) === */
$stmtEintraege = $pdo->prepare("SELECT * FROM wochenaufgaben WHERE datum BETWEEN ? AND ? ORDER BY datum DESC");
$stmtEintraege->execute([$zeitraumStart, $zeitraumEnde]);
$eintraege = $stmtEintraege->fetchAll(PDO::FETCH_ASSOC);

/* === Statistik === */
$stmt = $pdo->prepare("SELECT mitarbeiter, produkt, SUM(menge) as summe
                       FROM wochenaufgaben
                       WHERE datum BETWEEN ? AND ?
                       GROUP BY mitarbeiter, produkt
                       ORDER BY mitarbeiter");
$stmt->execute([$zeitraumStart, $zeitraumEnde]);
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

$anzahlEintraege = count($eintraege);
$gesamtMenge = array_sum(array_map(static fn($entry) => (int)$entry['menge'], $eintraege));
$letzteAktualisierung = $eintraege[0]['datum'] ?? null;

/* === Aufgabenplanung laden === */
$stmtPlan = $pdo->prepare("SELECT * FROM wochenaufgaben_plan WHERE kalenderwoche = ? ORDER BY mitarbeiter, produkt, erstellt_am, id");
$stmtPlan->execute([$selectedWeek]);
$geplanteAufgaben = $stmtPlan->fetchAll(PDO::FETCH_ASSOC);

$leistungen = [];
foreach ($daten as $row) {
  $leistungen[$row['mitarbeiter']][$row['produkt']] = (int)$row['summe'];
}

$geplanteAufgabenGruppiert = [];
$geplanteAufgabenMitFortschritt = [];
$erledigteAufgaben = 0;
$summeFortschrittGeplant = 0;
$verbrauchtePlanLeistung = [];
foreach ($geplanteAufgaben as $aufgabe) {
  $mitarbeiter = $aufgabe['mitarbeiter'];
  $produkt = $aufgabe['produkt'];
  $ziel = (int)$aufgabe['zielmenge'];
  $bereitsVerbraucht = $verbrauchtePlanLeistung[$mitarbeiter][$produkt] ?? 0;
  $gesamtErreicht = $leistungen[$mitarbeiter][$produkt] ?? 0;
  $verfuegbar = max(0, $gesamtErreicht - $bereitsVerbraucht);
  $erreicht = min($ziel, $verfuegbar);
  $prozent = $ziel > 0 ? (int)round(min(100, ($erreicht / $ziel) * 100)) : ($erreicht > 0 ? 100 : 0);
  $verbrauchtePlanLeistung[$mitarbeiter][$produkt] = $bereitsVerbraucht + $erreicht;
  $aufgabeMitFortschritt = [
    'id' => $aufgabe['id'],
    'mitarbeiter' => $mitarbeiter,
    'produkt' => $produkt,
    'ziel' => $ziel,
    'erreicht' => $erreicht,
    'prozent' => $prozent,
    'erledigt' => $ziel > 0 ? $erreicht >= $ziel : $erreicht > 0,
    'kalenderwoche' => $aufgabe['kalenderwoche'],
  ];
  $geplanteAufgabenMitFortschritt[] = $aufgabeMitFortschritt;
  $geplanteAufgabenGruppiert[$mitarbeiter][] = $aufgabeMitFortschritt;
  $summeFortschrittGeplant += $prozent;
  if ($ziel > 0 ? $erreicht >= $ziel : $erreicht > 0) {
    $erledigteAufgaben++;
  }
}

$anzahlGeplanteAufgaben = count($geplanteAufgaben);
$durchschnittFortschrittGeplant = $anzahlGeplanteAufgaben > 0 ? (int)round($summeFortschrittGeplant / $anzahlGeplanteAufgaben) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>📦 Wochenaufgaben verwalten | Admin</title>
<link rel="stylesheet" href="../header.css" />
<link rel="stylesheet" href="../styles.css" />
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.weekly-grid {
  display: grid;
  gap: 20px;
}

@media (min-width: 960px) {
  .weekly-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.weekly-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.penalty-grid {
  display: grid;
  gap: 16px;
}

@media (min-width: 900px) {
  .penalty-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.penalty-card {
  background: rgba(10, 12, 13, 0.85);
  border: 1px solid rgba(57, 255, 20, 0.25);
  border-radius: 16px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.penalty-card h3 {
  margin: 0;
  font-size: 1.15rem;
  color: #8effa8;
}

.penalty-card p {
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.badge--open {
  background: rgba(255, 196, 0, 0.15);
  color: #ffd866;
}

.badge--paid {
  background: rgba(118, 255, 101, 0.12);
  color: #86ffb5;
}

.penalty-summary {
  display: grid;
  gap: 8px;
}

.penalty-summary strong {
  color: #eafff1;
}

.weekly-table select,
.weekly-table input[type="number"] {
  width: 100%;
  background: rgba(10, 12, 13, 0.9);
  border: 1px solid rgba(57, 255, 20, 0.25);
  border-radius: 10px;
  padding: 10px 12px;
  color: #fff;
  font: inherit;
}

.task-product-list {
  display: grid;
  gap: 12px;
  margin-bottom: 12px;
}

.task-product-row {
  display: grid;
  gap: 12px;
}

.task-product-row .remove-task-row {
  justify-self: start;
}

@media (min-width: 720px) {
  .task-product-row {
    grid-template-columns: 1fr 180px auto;
    align-items: center;
  }
}

.task-product-add {
  margin-top: 4px;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📦 Wochenaufgaben verwalten</h1>
    <p class="inventory-description">
      Koordiniere Aufgaben, Produktionsziele und Lagerabgaben für jede Woche. Alle Änderungen werden live im Team-Dashboard angezeigt.
    </p>
    <p class="inventory-info">
      Aktive Kalenderwoche: KW <?= htmlspecialchars(substr($selectedWeek, -2)) ?>
      (<?= date('d.m.', strtotime($wochenzeitraum['start_date'])) ?> – <?= date('d.m.Y', strtotime($wochenzeitraum['end_date'])) ?>)
    </p>
    <p class="inventory-info">
      Letzte Aktualisierung:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Noch keine Einträge erfasst' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Aktive Einträge</span>
        <span class="inventory-metric__value"><?= number_format($anzahlEintraege, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">für diese Woche</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamtmenge</span>
        <span class="inventory-metric__value"><?= number_format($gesamtMenge, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">über alle Produkte</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Produkte</span>
        <span class="inventory-metric__value"><?= count($produkte) ?></span>
        <span class="inventory-metric__hint">definierte Ressourcen</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Geplante Aufgaben</span>
        <span class="inventory-metric__value"><?= number_format($anzahlGeplanteAufgaben, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint"><?= $erledigteAufgaben ?> erledigt · Ø <?= $durchschnittFortschrittGeplant ?>%</span>
      </article>
    </div>
  </header>

  <?php if (isset($_GET['archived'])): ?>
    <section class="inventory-section">
      <h2>Archivierung</h2>
      <p class="inventory-section__intro" style="color:#86ffb5;">
        ✅ Alte Wochen wurden erfolgreich archiviert.
      </p>
    </section>
  <?php endif; ?>

  <section class="inventory-section">
    <h2>Schnellaktionen</h2>
    <div class="weekly-actions">
      <a href="?archive=1&amp;week=<?= urlencode($selectedWeek) ?>" class="inventory-submit inventory-submit--ghost">📁 Alte Wochen archivieren</a>
      <a href="wochenaufgaben_archiv_edit.php" class="inventory-submit inventory-submit--ghost">📚 Archiv ansehen</a>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Kalenderwoche wechseln</h2>
    <form method="get" class="inventory-form weekly-grid">
      <div class="input-control">
        <label for="week">Kalenderwoche</label>
        <input id="week" type="week" name="week" value="<?= htmlspecialchars($selectedWeek) ?>" required>
      </div>
      <div class="form-actions" style="align-self:end;">
        <button type="submit" class="inventory-submit">🔄 Woche anzeigen</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Strafgebühren &amp; Fairness</h2>
    <p class="inventory-section__intro">
      Nicht erfüllte Wochenaufgaben führen zu einer anteiligen Strafgebühr. Die Höhe richtet sich nach den fehlenden Einheiten
      und der individuell konfigurierbaren Basisstrafe.
    </p>

    <?php if ($penaltyFeedback): ?>
      <div class="penalty-card" role="status">
        <h3>Update</h3>
        <p><?= htmlspecialchars($penaltyFeedback) ?></p>
        <?php if ($penaltyGenerationSummary): ?>
          <div class="penalty-summary">
            <span><strong><?= $penaltyGenerationSummary['candidates'] ?></strong> Mitarbeitende mit Strafgebühr berechnet</span>
            <span><strong><?= $penaltyGenerationSummary['created'] ?></strong> neu · <strong><?= $penaltyGenerationSummary['updated'] ?></strong> aktualisiert</span>
            <span><strong><?= $penaltyGenerationSummary['messages'] ?></strong> Benachrichtigungen versendet</span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="penalty-grid">
      <article class="penalty-card">
        <h3>Einstellungen</h3>
        <p>
          Passe die Strafgebühren an. Mitarbeitende zahlen nur den Anteil, den sie nicht erfüllt haben.
          Ab der definierten Mindest-Erfüllung entfällt die Strafe automatisch.
        </p>
        <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="inventory-form">
          <input type="hidden" name="save_penalty_settings" value="1">

          <div class="input-control">
            <label for="penalty_base_amount">Basisstrafe (100&nbsp;% unerfüllt)</label>
            <input id="penalty_base_amount" type="number" min="0" step="0.01" name="penalty_base_amount"
              value="<?= htmlspecialchars(number_format($penaltySettings['penalty_base_amount'], 2, '.', '')) ?>" required>
            <p class="form-hint">Wird anteilig mit dem fehlenden Prozentanteil multipliziert.</p>
          </div>

          <div class="input-control">
            <label for="penalty_per_unit">Strafe pro fehlender Einheit</label>
            <input id="penalty_per_unit" type="number" min="0" step="0.01" name="penalty_per_unit"
              value="<?= htmlspecialchars(number_format($penaltySettings['penalty_per_unit'], 2, '.', '')) ?>" required>
          </div>

          <div class="input-control">
            <label for="penalty_threshold_percent">Mindest-Erfüllung in %</label>
            <input id="penalty_threshold_percent" type="number" min="0" max="100" step="1"
              name="penalty_threshold_percent" value="<?= (int)$penaltySettings['penalty_threshold_percent'] ?>" required>
            <p class="form-hint">Ab diesem Erfüllungsgrad wird keine Strafe ausgelöst.</p>
          </div>

          <div class="form-actions">
            <button type="submit" class="inventory-submit">💾 Einstellungen speichern</button>
          </div>
        </form>
      </article>

      <article class="penalty-card">
        <h3>Aktuelle Woche (<?= htmlspecialchars($selectedWeek) ?>)</h3>
        <?php if ($penaltyPreview): ?>
          <div class="penalty-summary">
            <span><strong><?= number_format($penaltyStats['employees'], 0, ',', '.') ?></strong> Mitarbeitende mit Wochenzielen</span>
            <span>Ø Erfüllung: <strong><?= number_format($penaltyStats['avg_completion'], 1, ',', '.') ?>%</strong></span>
            <span>Fehlende Einheiten: <strong><?= number_format($penaltyStats['total_missing'], 0, ',', '.') ?></strong></span>
            <span>Potentielle Strafsumme: <strong><?= number_format($penaltyStats['total_amount'], 2, ',', '.') ?> €</strong>
              (<?= number_format($penaltyStats['with_penalty'], 0, ',', '.') ?> Personen)</span>
          </div>
        <?php else: ?>
          <p>Für die ausgewählte Woche wurden noch keine Aufgaben geplant.</p>
        <?php endif; ?>

        <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="inventory-form">
          <input type="hidden" name="generate_penalties" value="1">
          <button type="submit" class="inventory-submit" <?= $penaltyPreview ? '' : 'disabled' ?>>
            💶 Strafgebühren berechnen &amp; Rechnungen versenden
          </button>
        </form>

        <?php if (!$penaltyPreview): ?>
          <p class="form-hint">Sobald Aufgaben geplant und gebucht sind, kannst du hier Strafen generieren.</p>
        <?php endif; ?>
      </article>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Berechnete Strafgebühren</h2>
    <p class="inventory-section__intro">
      Übersicht aller Strafgebühren in der aktuellen Kalenderwoche. Von hier aus kannst du Zahlungen markieren oder offene
      Forderungen nachverfolgen.
    </p>

    <?php if ($penaltyRecords): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <th>Erfüllung</th>
              <th>Fehlend</th>
              <th>Betrag</th>
              <th>Status</th>
              <th>Nachricht</th>
              <th>Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($penaltyRecords as $record): ?>
              <?php
                $statusClass = ($record['status'] === WOCHENAUFGABEN_PENALTY_STATUS_PAID) ? 'badge--paid' : 'badge--open';
                $statusLabel = $record['status'] === WOCHENAUFGABEN_PENALTY_STATUS_PAID ? 'bezahlt' : 'offen';
                $periodLabel = wochenaufgaben_penalties_period_label($record['woche_start'], $record['woche_ende']);
              ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($record['mitarbeiter']) ?></strong><br>
                  <small><?= htmlspecialchars($periodLabel) ?></small>
                </td>
                <td><?= number_format((float)$record['prozent_erfuellt'], 1, ',', '.') ?>%</td>
                <td><?= number_format((int)$record['fehlende_summe'], 0, ',', '.') ?></td>
                <td><?= number_format((float)$record['strafe_betrag'], 2, ',', '.') ?> €</td>
                <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                <td><?= $record['nachricht_id'] ? '✅ gesendet' : '⏳ offen' ?></td>
                <td>
                  <?php if ($record['status'] === WOCHENAUFGABEN_PENALTY_STATUS_PAID): ?>
                    <a class="inventory-submit inventory-submit--ghost inventory-submit--small"
                      href="?week=<?= urlencode($selectedWeek) ?>&amp;penalty_mark_open=<?= (int)$record['id'] ?>">↩️ offen setzen</a>
                  <?php else: ?>
                    <a class="inventory-submit inventory-submit--ghost inventory-submit--small"
                      href="?week=<?= urlencode($selectedWeek) ?>&amp;penalty_mark_paid=<?= (int)$record['id'] ?>">✅ als bezahlt markieren</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-note">Für diese Woche liegen noch keine Strafgebühren vor.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Aufgabenplanung</h2>
    <p class="inventory-section__intro">
      Weise Mitarbeitenden individuelle Wochenziele zu. Die Fortschritte aktualisieren sich automatisch anhand der gebuchten Mengen.
    </p>
    <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="inventory-form weekly-grid">
      <input type="hidden" name="add_task" value="1">
      <input type="hidden" name="kalenderwoche" value="<?= htmlspecialchars($selectedWeek) ?>">

      <div class="input-control">
        <label for="task_mitarbeiter">Mitarbeiter:in</label>
        <select id="task_mitarbeiter" name="mitarbeiter" class="inventory-select" required>
          <option value="">– Mitarbeiter wählen –</option>
          <?php foreach ($mitarbeiter_liste as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control input-control--full">
        <label>Produkte &amp; Zielmengen</label>
        <div id="task-product-list" class="task-product-list" aria-live="polite">
          <div class="task-product-row">
            <select name="produkte[]" class="inventory-select" required>
              <option value="">– Produkt wählen –</option>
              <?php foreach ($produkte as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
            <input class="input-field" type="number" name="zielmengen[]" min="1" placeholder="z. B. 150" required>
            <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small remove-task-row" aria-label="Produkt entfernen">✖</button>
          </div>
        </div>

        <button type="button" id="add-task-row" class="inventory-submit inventory-submit--ghost task-product-add">+ Weiteres Produkt hinzufügen</button>
        <p class="form-hint">Füge mehrere Produkte hinzu, um einem Mitarbeitenden verschiedene Ziele auf einmal zuzuweisen.</p>

        <template id="task-product-template">
          <div class="task-product-row">
            <select name="produkte[]" class="inventory-select" required>
              <option value="">– Produkt wählen –</option>
              <?php foreach ($produkte as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
            <input class="input-field" type="number" name="zielmengen[]" min="1" placeholder="z. B. 150" required>
            <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small remove-task-row" aria-label="Produkt entfernen">✖</button>
          </div>
        </template>
      </div>

      <div class="form-actions" style="align-self:end;">
        <button type="submit" class="inventory-submit">+ Aufgaben speichern</button>
      </div>
    </form>

    <?php if ($geplanteAufgabenMitFortschritt): ?>
      <div class="table-wrap">
        <table class="data-table weekly-table">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <th>Produkt</th>
              <th>Ziel</th>
              <th>Erreicht</th>
              <th>Fortschritt</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($geplanteAufgabenMitFortschritt as $aufgabe): ?>
              <tr class="<?= $aufgabe['erledigt'] ? 'table-row--success' : '' ?>">
                <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="weekly-edit-form">
                  <td>
                    <select name="mitarbeiter" required>
                      <?php foreach ($mitarbeiter_liste as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($aufgabe['mitarbeiter'] === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="produkt" required>
                      <?php foreach ($produkte as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= ($aufgabe['produkt'] === $p) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="number" name="zielmenge" value="<?= (int)$aufgabe['ziel'] ?>" min="1" required></td>
                  <td><strong><?= (int)$aufgabe['erreicht'] ?></strong></td>
                  <td style="min-width:160px;">
                    <div class="task-progress">
                      <div class="task-progress__bar" style="width: <?= max(0, min(100, $aufgabe['prozent'])) ?>%"></div>
                    </div>
                    <small><?= $aufgabe['prozent'] ?>%</small>
                  </td>
                  <td class="weekly-actions">
                    <input type="hidden" name="edit_task_id" value="<?= $aufgabe['id'] ?>">
                    <input type="hidden" name="kalenderwoche" value="<?= htmlspecialchars($selectedWeek) ?>">
                    <button type="submit" class="inventory-submit inventory-submit--small">💾</button>
                    <a class="inventory-submit inventory-submit--ghost inventory-submit--small" href="?delete_task=<?= $aufgabe['id'] ?>&amp;week=<?= urlencode($selectedWeek) ?>" onclick="return confirm('Aufgabe wirklich löschen?');">🗑️</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Noch keine Aufgaben für KW <?= htmlspecialchars(substr($selectedWeek, -2)) ?> geplant.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Wochenstatistik</h2>
    <p class="inventory-section__intro">
      Zeitraum: <?= date('d.m.Y', strtotime($wochenzeitraum['start_date'])) ?> – <?= date('d.m.Y', strtotime($wochenzeitraum['end_date'])) ?>
    </p>
    <?php if (!empty($statistik)): ?>
      <div class="table-wrap">
        <table class="data-table weekly-table">
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
                <?php foreach ($produkte as $p): ?>
                  <td><?= $werte[$p] ?: '–' ?></td>
                <?php endforeach; ?>
                <td><strong><?= $werte['Gesamt'] ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Keine Buchungen für KW <?= htmlspecialchars(substr($selectedWeek, -2)) ?>.</p>
    <?php endif; ?>
  </section>

    <section class="inventory-section">
      <h2>Neuen Eintrag erstellen</h2>
      <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="inventory-form weekly-grid">
      <input type="hidden" name="add" value="1">

      <div class="input-control">
        <label for="mitarbeiter_add">Mitarbeiter:in</label>
        <select id="mitarbeiter_add" name="mitarbeiter" class="inventory-select" required>
          <option value="">– Mitarbeiter wählen –</option>
          <?php foreach ($mitarbeiter_liste as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="produkt_add">Produkt</label>
        <select id="produkt_add" name="produkt" class="inventory-select" required>
          <option value="">– Produkt wählen –</option>
          <?php foreach ($produkte as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="input-control">
        <label for="menge_add">Menge</label>
        <input id="menge_add" class="input-field" type="number" name="menge" min="1" placeholder="z. B. 50" required>
      </div>

      <div class="form-actions" style="align-self:end;">
        <button type="submit" class="inventory-submit">+ Eintrag speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Aktive Einträge</h2>
    <?php if ($eintraege): ?>
      <div class="table-wrap">
        <table class="data-table weekly-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mitarbeiter</th>
              <th>Produkt</th>
              <th>Menge</th>
              <th>Datum</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eintraege as $e): ?>
              <tr>
                <form method="post" action="?week=<?= urlencode($selectedWeek) ?>" class="weekly-edit-form">
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
                  <td class="weekly-actions">
                    <input type="hidden" name="edit_id" value="<?= $e['id'] ?>">
                    <button type="submit" class="inventory-submit inventory-submit--small">💾</button>
                    <a class="inventory-submit inventory-submit--ghost inventory-submit--small" href="?delete=<?= $e['id'] ?>&amp;week=<?= urlencode($selectedWeek) ?>"
                       onclick="return confirm('Eintrag wirklich löschen?')">🗑️</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
   <?php else: ?>
      <p class="inventory-section__intro">Keine Einträge für KW <?= htmlspecialchars(substr($selectedWeek, -2)) ?> gefunden.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
      <a href="wochenaufgaben_archiv_edit.php" class="button-secondary">📚 Archiv</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const productList = document.querySelector('#task-product-list');
  const addButton = document.querySelector('#add-task-row');
  const template = document.querySelector('#task-product-template');

  if (!productList || !addButton || !template) {
    return;
  }

  const toggleRemoveButtons = () => {
    const rows = productList.querySelectorAll('.task-product-row');
    rows.forEach((row) => {
      const removeButton = row.querySelector('.remove-task-row');
      if (removeButton) {
        removeButton.style.display = rows.length > 1 ? '' : 'none';
      }
    });
  };

  addButton.addEventListener('click', () => {
    const clone = template.content.firstElementChild.cloneNode(true);
    productList.appendChild(clone);
    toggleRemoveButtons();
  });

  productList.addEventListener('click', (event) => {
    const target = event.target.closest('.remove-task-row');
    if (!target) {
      return;
    }

    if (productList.children.length <= 1) {
      return;
    }

    const row = target.closest('.task-product-row');
    if (row) {
      row.remove();
      toggleRemoveButtons();
    }
  });

  toggleRemoveButtons();
});
</script>

<script src="../script.js"></script>
</body>
</html>