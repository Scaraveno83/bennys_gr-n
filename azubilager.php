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

$lowStockThreshold = 50;

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
  if (!isset($bestand[$p])) {
    $bestand[$p] = 0;
  }
}
ksort($bestand, SORT_NATURAL | SORT_FLAG_CASE);

/* === Kennzahlen berechnen === */
$gesamtMenge = array_sum($bestand);
$kritischeBestande = array_filter($bestand, fn($menge) => $menge < $lowStockThreshold);
$anzahlKritisch = count($kritischeBestande);
$anzahlProdukte = count($bestand);
$durchschnittBestand = $anzahlProdukte > 0 ? round($gesamtMenge / $anzahlProdukte) : 0;
$kritischeQuote = $anzahlProdukte > 0 ? round(($anzahlKritisch / $anzahlProdukte) * 100) : 0;
$maxBestand = !empty($bestand) ? max($bestand) : 0;

$watchlistCandidates = array_filter(
  $bestand,
  fn($menge) => $menge <= max($lowStockThreshold * 1.3, $lowStockThreshold + 15)
);
$watchlistSorted = $watchlistCandidates;
asort($watchlistSorted, SORT_NUMERIC);
$watchlist = array_slice($watchlistSorted, 0, 6, true);

$letzteAktualisierung = $pdo->query("SELECT datum FROM azubi_lager_verlauf ORDER BY datum DESC LIMIT 1")
  ->fetchColumn();

/* === Verlauf (nur Admin) === */
$verlauf = [];
$timelineEntries = [];
if ($isAdmin) {
  $verlauf = $pdo->query("SELECT * FROM azubi_lager_verlauf ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $timelineEntries = array_slice($verlauf, 0, 12);
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
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page">
  <section class="inventory-hero">
    <div class="inventory-hero__content">
      <span class="inventory-hero__tag">Ausbildung &amp; Nachwuchs</span>
      <h1 class="inventory-hero__title">🧰 Azubilager</h1>
      <p class="inventory-hero__lead">
        Ein kreatives Dashboard für unser Nachwuchsteam. Materialien für Werkstatt, Projekte und Schulungen
        sind sofort greifbar, inklusive smarter Watchlist für Mentor:innen.
      </p>
      <div class="inventory-hero__meta">
        <span class="meta-chip">
          <span class="meta-label">Letzte Buchung</span>
          <span class="meta-value">
            <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Keine Daten' ?>
          </span>
        </span>
        <span class="meta-chip">
          <span class="meta-label">Ø Bestand pro Artikel</span>
          <span class="meta-value"><?= number_format($durchschnittBestand, 0, ',', '.') ?> Stk.</span>
        </span>
        <span class="meta-chip <?= $anzahlKritisch > 0 ? 'is-alert' : '' ?>">
          <span class="meta-label">Kritischer Anteil</span>
          <span class="meta-value"><?= $kritischeQuote ?>%</span>
        </span>
      </div>
      <div class="inventory-hero__actions">
        <a class="inventory-cta" href="#lageraktion">Aktion dokumentieren</a>
        <span class="inventory-hero__hint">Engpässe für die Ausbildung werden prominent hervorgehoben.</span>
      </div>
    </div>
  </section>

  <section class="metric-deck" aria-label="Kennzahlen">
    <article class="metric-card metric-card--accent">
      <span class="metric-card__icon" aria-hidden="true">🎓</span>
      <div class="metric-card__values">
        <span class="metric-card__label">Materialtypen</span>
        <span class="metric-card__value"><?= number_format($anzahlProdukte, 0, ',', '.') ?></span>
      </div>
      <span class="metric-card__foot">Für Ausbildung & Workshops</span>
    </article>
    <article class="metric-card">
      <span class="metric-card__icon" aria-hidden="true">📦</span>
      <div class="metric-card__values">
        <span class="metric-card__label">Gesamtmenge</span>
        <span class="metric-card__value"><?= number_format($gesamtMenge, 0, ',', '.') ?></span>
      </div>
      <div class="metric-card__progress" role="presentation">
        <span class="metric-card__bar" style="--fill: <?= min(100, max(8, 100 - $kritischeQuote)) ?>%;"></span>
      </div>
      <span class="metric-card__foot">Sichere Bestände: <?= max(0, 100 - $kritischeQuote) ?>%</span>
    </article>
    <article class="metric-card metric-card--warning <?= $anzahlKritisch > 0 ? 'is-active' : '' ?>">
      <span class="metric-card__icon" aria-hidden="true">⚠️</span>
      <div class="metric-card__values">
        <span class="metric-card__label">Kritische Artikel</span>
        <span class="metric-card__value"><?= number_format($anzahlKritisch, 0, ',', '.') ?></span>
      </div>
      <span class="metric-card__foot">Unter <?= $lowStockThreshold ?> Stück</span>
    </article>
    <article class="metric-card">
      <span class="metric-card__icon" aria-hidden="true">🧑‍🏫</span>
      <div class="metric-card__values">
        <span class="metric-card__label">Mentor:innen-Insights</span>
        <span class="metric-card__value"><?= $isAdmin ? number_format(count($timelineEntries), 0, ',', '.') : '–' ?></span>
      </div>
      <span class="metric-card__foot">Aktivitäten (Admin)</span>
    </article>
  </section>

  <section class="inventory-layout">
    <article class="inventory-panel inventory-panel--table" data-inventory>
      <header class="panel-header">
        <div class="panel-titles">
          <h2>Live-Bestände</h2>
          <p>Alle Lern- und Werkstattmaterialien mit direkter Engpassmarkierung.</p>
        </div>
        <div class="panel-actions">
          <label class="search-field">
            <span class="sr-only">Bestand durchsuchen</span>
            <input type="search" placeholder="Produkt suchen…" data-table-search>
          </label>
          <button type="button" class="chip-toggle" data-table-filter="low-stock">
            <span>Nur kritische Bestände</span>
          </button>
        </div>
      </header>
      <div class="table-wrap">
        <table class="data-table" data-inventory-table>
          <thead>
            <tr><th>Produkt</th><th>Bestand</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bestand as $produkt => $menge): ?>
              <tr>
                <td><?= htmlspecialchars($produkt) ?></td>
                <td class="<?= $menge < $lowStockThreshold ? 'low-stock' : '' ?>">
                  <?= number_format($menge, 0, ',', '.') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="empty-state" data-empty-state hidden>
          Keine Treffer für deine Filtereinstellung. Passe Suche oder Filter an.
        </p>
      </div>
      <footer class="panel-footer">
        <div class="panel-bubble">
          <span class="indicator-dot indicator-dot--critical"></span>
          <span><?= number_format($anzahlKritisch, 0, ',', '.') ?> Artikel unter <?= $lowStockThreshold ?> Stück</span>
        </div>
        <div class="panel-bubble">
          <span class="indicator-dot indicator-dot--safe"></span>
          <span>Ø Bestand: <?= number_format($durchschnittBestand, 0, ',', '.') ?> Stück</span>
        </div>
      </footer>
    </article>

    <aside class="inventory-sidebar">
      <article class="inventory-panel inventory-panel--insights">
        <header class="panel-header">
          <div class="panel-titles">
            <h2>Watchlist</h2>
            <p>Materialien, die für kommende Übungen nachgefüllt werden sollten.</p>
          </div>
        </header>
        <?php if (!empty($watchlist)): ?>
          <ol class="watchlist">
            <?php foreach ($watchlist as $produkt => $menge): ?>
              <?php
                $progress = $maxBestand > 0 ? round(($menge / $maxBestand) * 100) : 0;
                $progress = max(6, min(100, $progress));
              ?>
              <li class="watchlist-item <?= $menge < $lowStockThreshold ? 'is-critical' : '' ?>">
                <div class="watchlist-row">
                  <span class="watchlist-name"><?= htmlspecialchars($produkt) ?></span>
                  <span class="watchlist-count"><?= number_format($menge, 0, ',', '.') ?></span>
                </div>
                <span class="watchlist-bar" style="--fill: <?= $progress ?>%;"></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="watchlist-empty">Aktuell ist ausreichend Material für alle Schulungen vorhanden.</p>
        <?php endif; ?>
      </article>

      <article class="inventory-panel inventory-panel--form" id="lageraktion">
        <header class="panel-header">
          <div class="panel-titles">
            <h2>Aktion festhalten</h2>
            <p>Ideal für Auszubildende: schnell und intuitiv neue Buchungen dokumentieren.</p>
          </div>
        </header>
        <form method="post" class="inventory-form">
          <div class="input-control">
            <label for="produkt">Produkt</label>
            <select id="produkt" name="produkt" required>
              <option value="">– bitte wählen –</option>
              <?php foreach ($produkte as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-columns">
            <div class="input-control">
              <label for="menge">Menge</label>
              <input id="menge" class="input-field" type="number" name="menge" min="1" required>
            </div>
            <div class="input-control">
              <span class="input-label">Aktion</span>
              <div class="segmented-control">
                <input type="radio" id="aktion-add-azubi" name="aktion" value="hinzugefügt" checked>
                <label for="aktion-add-azubi">Hinzufügen</label>
                <input type="radio" id="aktion-remove-azubi" name="aktion" value="entnommen">
                <label for="aktion-remove-azubi">Entnehmen</label>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="inventory-submit">Buchung speichern</button>
            <span class="form-hint">Mentor:innen behalten den Überblick</span>
          </div>
        </form>
      </article>
    </aside>

    <?php if ($isAdmin): ?>
      <article class="inventory-panel inventory-panel--full inventory-panel--history">
        <header class="panel-header">
          <div class="panel-titles">
            <h2>Aktivitätsprotokoll</h2>
            <p>Die letzten <?= number_format(count($timelineEntries), 0, ',', '.') ?> Lernlager-Buchungen (Admin).</p>
          </div>
        </header>
        <?php if (!empty($timelineEntries)): ?>
          <ol class="history-timeline">
            <?php foreach ($timelineEntries as $entry): ?>
              <?php $isAdd = $entry['aktion'] === 'hinzugefügt'; ?>
              <li class="history-item <?= $isAdd ? 'is-add' : 'is-remove' ?>">
                <span class="history-icon" aria-hidden="true"><?= $isAdd ? '➕' : '➖' ?></span>
                <div class="history-body">
                  <div class="history-headline">
                    <strong><?= htmlspecialchars($entry['produkt']) ?></strong>
                    <span class="history-quantity">
                      <?= $isAdd ? '+' : '−' ?><?= number_format((int)$entry['menge'], 0, ',', '.') ?>
                    </span>
                  </div>
                  <div class="history-meta">
                    <span><?= date('d.m.Y, H:i \U\h\r', strtotime($entry['datum'])) ?></span>
                    <span>von <?= htmlspecialchars($entry['mitarbeiter']) ?></span>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="history-empty">Noch keine Buchungen vorhanden.</p>
        <?php endif; ?>
      </article>
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