<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';
require_once 'includes/wochenaufgaben_helpers.php';

/* === Feedback aus vorherigen Aktionen === */
$feedbackMeldung = $_SESSION['wochenaufgaben_error'] ?? null;
unset($_SESSION['wochenaufgaben_error']);

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

/* === Grundkonfiguration === */
$standardProdukte = ['Öl', 'Fasern', 'Stoff', 'Eisenbarren', 'Eisenerz'];
$produkte = $standardProdukte;
$buchbareProdukte = [];
$verfuegbareProdukte = [];
$aufgabenZieleProProdukt = [];
$aufgabenRestMengen = [];
$produktSummen = [];;
$aktuelleWoche = normalizeKalenderwoche(null);
$wochenzeitraum = getWeekPeriod($aktuelleWoche);
$anzeigeMontag = $wochenzeitraum['start_date'];
$anzeigeSonntag = $wochenzeitraum['end_date'];
$zeitraumStart = $wochenzeitraum['start_datetime'];
$zeitraumEnde = $wochenzeitraum['end_datetime'];

ensureWochenaufgabenPlanTable($pdo);

/* === Prüfen, ob Wochenaufgaben zugewiesen wurden === */
$stmtAufgabenCheck = $pdo->prepare(
  "SELECT COUNT(*) FROM wochenaufgaben_plan WHERE mitarbeiter = ? AND kalenderwoche = ?"
);
$stmtAufgabenCheck->execute([$nutzername, $aktuelleWoche]);
$hatAufgaben = $stmtAufgabenCheck->fetchColumn() > 0;

/* === Aufgabenstatus vorbereiten === */
$aufgaben = [];
$aufgabenFortschritt = [];
$summeProzent = 0;
$abgeschlossen = 0;
$anzahlAufgaben = 0;
$durchschnittFortschritt = 0;
$alleAufgabenErledigt = false;

if ($hatAufgaben) {
  $stmtAufgaben = $pdo->prepare(
    "SELECT id, produkt, zielmenge, erstellt_am FROM wochenaufgaben_plan WHERE mitarbeiter = ? AND kalenderwoche = ? ORDER BY erstellt_am, id"
  );
  $stmtAufgaben->execute([$nutzername, $aktuelleWoche]);
  $aufgaben = $stmtAufgaben->fetchAll(PDO::FETCH_ASSOC);

  if ($aufgaben) {
    foreach ($aufgaben as $aufgabe) {
      $produkt = $aufgabe['produkt'];
      $ziel = (int) $aufgabe['zielmenge'];
      $aufgabenZieleProProdukt[$produkt] = ($aufgabenZieleProProdukt[$produkt] ?? 0) + $ziel;
    }

    $buchbareProdukte = array_values(array_unique(array_keys($aufgabenZieleProProdukt)));
    $produkte = array_values(array_unique(array_merge($produkte, $buchbareProdukte)));
    $stmtSummen = $pdo->prepare(
      "SELECT produkt, SUM(menge) AS summe FROM wochenaufgaben WHERE mitarbeiter = ? AND datum BETWEEN ? AND ? GROUP BY produkt"
    );
    $stmtSummen->execute([$nutzername, $zeitraumStart, $zeitraumEnde]);
    $produktSummen = [];
    foreach ($stmtSummen->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $produktSummen[$row['produkt']] = (int) $row['summe'];
    }

    $verbrauchteLeistung = [];
    $alleAufgabenErledigt = true;

    foreach ($aufgaben as $aufgabe) {
      $produkt = $aufgabe['produkt'];
      $ziel = (int) $aufgabe['zielmenge'];
      $bereitsVerbraucht = $verbrauchteLeistung[$produkt] ?? 0;
      $gesamtErreicht = $produktSummen[$produkt] ?? 0;
      $verfuegbar = max(0, $gesamtErreicht - $bereitsVerbraucht);
      $erreicht = min($ziel, $verfuegbar);
      $verbrauchteLeistung[$produkt] = $bereitsVerbraucht + $erreicht;

      $prozent = $ziel > 0
        ? (int) round(min(100, ($erreicht / $ziel) * 100))
        : ($erreicht > 0 ? 100 : 0);
      $erledigt = $ziel > 0 ? $erreicht >= $ziel : $erreicht > 0;

      $aufgabenFortschritt[] = [
        'produkt' => $produkt,
        'ziel' => $ziel,
        'erreicht' => $erreicht,
        'prozent' => $prozent,
        'erledigt' => $erledigt,
      ];

      $summeProzent += $prozent;
      if ($erledigt) {
        $abgeschlossen++;
      } else {
        $alleAufgabenErledigt = false;
      }
    }

    $anzahlAufgaben = count($aufgabenFortschritt);
    if ($anzahlAufgaben > 0) {
      $durchschnittFortschritt = (int) round($summeProzent / $anzahlAufgaben);
    }

    if ($anzahlAufgaben === 0) {
      $alleAufgabenErledigt = false;
    }

    foreach ($aufgabenZieleProProdukt as $produktName => $zielSumme) {
      $bereitsGebucht = $produktSummen[$produktName] ?? 0;
      $aufgabenRestMengen[$produktName] = max(0, $zielSumme - $bereitsGebucht);
      if ($aufgabenRestMengen[$produktName] > 0) {
        $verfuegbareProdukte[] = $produktName;
      }
    }
  }
}

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
  if (!$hatAufgaben) {
    $_SESSION['wochenaufgaben_error'] = 'Es wurden dir keine Wochenaufgaben zugewiesen. Ohne Aufgaben kannst du keine Buchungen vornehmen.';
    header("Location: wochenaufgaben.php");
    exit;
  }

  if ($alleAufgabenErledigt) {
    $_SESSION['wochenaufgaben_error'] = 'Deine Wochenaufgaben für diese Woche sind bereits vollständig erledigt. Weitere Buchungen sind nicht mehr möglich.';
    header("Location: wochenaufgaben.php");
    exit;
  }

  $produkt = trim($_POST['produkt']);
  $menge = intval($_POST['menge']);

  f ($produkt && $menge > 0) {
    if (!in_array($produkt, $buchbareProdukte, true)) {
      $_SESSION['wochenaufgaben_error'] = 'Das ausgewählte Produkt gehört nicht zu deinen zugewiesenen Wochenaufgaben.';
      header("Location: wochenaufgaben.php");
      exit;
    }

    $verbleibendeMenge = $aufgabenRestMengen[$produkt] ?? 0;
    if ($verbleibendeMenge <= 0) {
      $_SESSION['wochenaufgaben_error'] = 'Für dieses Produkt hast du deine Wochenaufgabe bereits vollständig erfüllt. Bitte buche deine übrigen Aufgaben.';
      header("Location: wochenaufgaben.php");
      exit;
    }

    if ($menge > $verbleibendeMenge) {
      $_SESSION['wochenaufgaben_error'] = sprintf(
        'Du kannst für %s höchstens noch %d verbuchen.',
        $produkt,
        $verbleibendeMenge
      );
      header("Location: wochenaufgaben.php");
      exit;
    }

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
$stmt = $pdo->prepare("
  SELECT * FROM wochenaufgaben
  WHERE mitarbeiter = ? AND datum BETWEEN ? AND ?
  ORDER BY datum DESC
");
$stmt->execute([$nutzername, $zeitraumStart, $zeitraumEnde]);
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

$anzahlEintraege = count($eintraege);
$letzterEintrag = $eintraege[0]['datum'] ?? null;

/* === Zugewiesene Aufgaben & Fortschritt === */
if (!$hatAufgaben) {
  $aufgabenFortschritt = [];
  $summeProzent = 0;
  $abgeschlossen = 0;
  $anzahlAufgaben = 0;
  $durchschnittFortschritt = 0;
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
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📦 Meine Wochenaufgaben</h1>
    <p class="inventory-description">
      Hallo <strong><?= htmlspecialchars($nutzername) ?></strong>!<br>
      Hier siehst du deine gebuchten Ressourcen für die Kalenderwoche <?= htmlspecialchars(substr($aktuelleWoche, -2)) ?>
      (<?= date('d.m.', strtotime($anzeigeMontag)) ?> – <?= date('d.m.Y', strtotime($anzeigeSonntag)) ?>).
    </p>
    <p class="inventory-info">
      Einträge werden automatisch dem passenden Lager (Azubi oder Hauptlager) gutgeschrieben.
    </p>

    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Einträge diese Woche</span>
        <span class="inventory-metric__value"><?= $anzahlEintraege ?></span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Gesamtmenge</span>
        <span class="inventory-metric__value"><?= (int)$gesamt['Summe'] ?></span>
        <span class="inventory-metric__hint">über alle Produkte</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Zugewiesene Aufgaben</span>
        <span class="inventory-metric__value"><?= $anzahlAufgaben ?></span>
        <span class="inventory-metric__hint"><?= $abgeschlossen ?> erledigt</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Ø Fortschritt</span>
        <span class="inventory-metric__value"><?= $durchschnittFortschritt ?>%</span>
        <span class="inventory-metric__hint">über alle Aufgaben</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Letzte Buchung</span>
        <span class="inventory-metric__value">
          <?= $letzterEintrag ? date('d.m.Y', strtotime($letzterEintrag)) : '–' ?>
        </span>
        <span class="inventory-metric__hint">
          <?= $letzterEintrag ? date('H:i \U\h\r', strtotime($letzterEintrag)) : 'keine Daten' ?>
        </span>
      </div>
   </div>
  </header>

  <?php if ($feedbackMeldung): ?>
    <div class="inventory-alert inventory-alert--error">
      <?= htmlspecialchars($feedbackMeldung) ?>
    </div>
  <?php endif; ?>

   <section class="inventory-section">
    <h2>Meine Wochenziele</h2>
    <?php if ($aufgabenFortschritt): ?>
      <div class="tasks-grid">
        <?php foreach ($aufgabenFortschritt as $aufgabe): ?>
          <article class="task-card <?= $aufgabe['erledigt'] ? 'task-card--done' : '' ?>">
            <header class="task-card__header">
              <span class="task-card__title"><?= htmlspecialchars($aufgabe['produkt']) ?></span>
              <span class="task-card__badge"><?= $aufgabe['erledigt'] ? '✅' : '🎯' ?></span>
            </header>
            <p class="task-card__meta">
              Ziel: <strong><?= (int)$aufgabe['ziel'] ?></strong> • Erreicht: <strong><?= (int)$aufgabe['erreicht'] ?></strong>
            </p>
            <div class="task-progress">
              <div class="task-progress__bar" style="width: <?= max(0, min(100, $aufgabe['prozent'])) ?>%"></div>
            </div>
            <p class="task-card__progress">Fortschritt: <?= $aufgabe['prozent'] ?>%</p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro">Für diese Woche wurden dir noch keine Aufgaben zugewiesen.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Neuen Eintrag erfassen</h2>
    <?php if ($anzahlAufgaben > 0 && !$alleAufgabenErledigt): ?>
      <?php if (!empty($verfuegbareProdukte)): ?>
        <p class="inventory-section__intro">
          Bitte buche jede abgeschlossene Aufgabe mit Produktart und Menge.
        </p>
        <form method="post" class="inventory-form">
          <input type="hidden" name="add" value="1">
          <div class="input-control">
            <label for="produkt">Produkt</label>
            <select id="produkt" name="produkt" required>
              <option value="">– bitte wählen –</option>
              <?php foreach ($verfuegbareProdukte as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="input-control">
            <label for="menge">Menge</label>
            <input id="menge" class="input-field" type="number" name="menge" min="1" placeholder="z. B. 50" required>
          </div>

          <div class="form-actions">
            <button type="submit" class="inventory-submit">Eintrag speichern</button>
            <span class="form-hint">Wird automatisch im richtigen Lager verbucht.</span>
          </div>
        </form>
      <?php else: ?>
        <p class="inventory-section__intro">
          Für deine verbleibenden Aufgaben wurden alle benötigten Produkte bereits vollständig verbucht.
        </p>
      <?php endif; ?>
    <?php elseif ($anzahlAufgaben > 0): ?>
      <p class="inventory-section__intro">
        ✅ Du hast alle Wochenaufgaben dieser Woche erledigt. Neue Buchungen sind erst wieder in der nächsten Woche möglich.
      </p>
    <?php else: ?>
      <p class="inventory-section__intro">
        Dir wurden für diese Woche keine Aufgaben zugewiesen. Sobald Aufgaben vorliegen, kannst du hier Einträge erfassen.
      </p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Wochenstatistik</h2>
    <?php if ($eintraege): ?>
      <div class="table-wrap">
        <table class="data-table">
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
        </table>
      </div>

      <div class="inventory-summary-grid">
        <?php foreach ($produkte as $p): ?>
          <div class="inventory-summary">
            <span class="inventory-summary__label"><?= htmlspecialchars($p) ?></span>
            <span class="inventory-summary__value"><?= (int)$gesamt[$p] ?></span>
          </div>
        <?php endforeach; ?>
        <div class="inventory-summary inventory-summary--accent">
          <span class="inventory-summary__label">Gesamtmenge</span>
          <span class="inventory-summary__value"><?= (int)$gesamt['Summe'] ?></span>
        </div>
      </div>
    <?php else: ?>
      <p class="inventory-empty">Du hast diese Woche noch keine Aufgaben eingetragen.</p>
    <?php endif; ?>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
