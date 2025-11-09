<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

/* === Hilfsfunktionen === */
function ladeLager(PDO $pdo, string $tabelle): array {
  $stmt = $pdo->query("SELECT produkt, bestand FROM $tabelle ORDER BY produkt ASC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function summeBestand(array $lager): int {
  $summe = 0;
  foreach ($lager as $eintrag) {
    $summe += (int)$eintrag['bestand'];
  }
  return $summe;
}

function maxDatum(PDO $pdo, string $tabelle): ?string {
  return $pdo->query("SELECT MAX(datum) FROM $tabelle")->fetchColumn() ?: null;
}

/* === Lager laden === */
$hauptlager = ladeLager($pdo, 'hauptlager');
$azubilager = ladeLager($pdo, 'azubi_lager');
$buerolager = ladeLager($pdo, 'buero_lager');

/* === Zusammenführen === */
$gesamt = [];
$aggregate = static function(array $liste) use (&$gesamt): void {
  foreach ($liste as $item) {
    $produkt = $item['produkt'];
    $menge = (int)$item['bestand'];
    if (!isset($gesamt[$produkt])) {
      $gesamt[$produkt] = 0;
    }
    $gesamt[$produkt] += $menge;
  }
};

$aggregate($hauptlager);
$aggregate($azubilager);
$aggregate($buerolager);
ksort($gesamt, SORT_NATURAL | SORT_FLAG_CASE);

/* === Summen === */
$summeHaupt = summeBestand($hauptlager);
$summeAzubi = summeBestand($azubilager);
$summeBuero = summeBestand($buerolager);
$summeGesamt = array_sum($gesamt);

$distinctProdukte = count($gesamt);
$kritischGesamt = array_reduce($gesamt, static fn($carry, $menge) => $carry + ($menge < 50 ? 1 : 0), 0);

$letzteAktualisierung = array_filter([
  maxDatum($pdo, 'lager_verlauf'),
  maxDatum($pdo, 'azubi_lager_verlauf'),
  maxDatum($pdo, 'buero_lager_verlauf'),
]);
$letzteAktualisierung = $letzteAktualisierung ? max($letzteAktualisierung) : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📦 Gesamtlagerübersicht | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.lager-grid {
  display: grid;
  gap: 24px;
}

@media (min-width: 1080px) {
  .lager-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.lager-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.9rem;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📦 Gesamtlagerübersicht</h1>
    <p class="inventory-description">
      Ein Blick auf alle Bestände aus Haupt-, Azubi- und Bürolager. Ideal für Inventuren, Abgleiche oder Bestellentscheidungen.
    </p>
    <p class="inventory-info">
      Letzte Aktualisierung:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Noch keine Buchung erfasst' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Produkte gesamt</span>
        <span class="inventory-metric__value"><?= number_format($distinctProdukte, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">über alle Lager hinweg</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamtbestand</span>
        <span class="inventory-metric__value"><?= number_format($summeGesamt, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Einheiten aktuell erfasst</span>
      </article>
      <article class="inventory-metric <?= $kritischGesamt ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Kritische Produkte</span>
        <span class="inventory-metric__value"><?= number_format($kritischGesamt, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Bestand unter 50</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Gesamte Bestände</h2>
    <p class="inventory-section__intro">
      Konsolidierte Übersicht aller Lager – sortiert nach Produktname.
    </p>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Produkt</th><th>Gesamtbestand</th></tr>
        </thead>
        <tbody>
          <?php foreach ($gesamt as $produkt => $menge): ?>
            <tr>
              <td><?= htmlspecialchars($produkt) ?></td>
              <td class="<?= $menge < 50 ? 'low-stock' : '' ?>"><?= number_format($menge, 0, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-actions" style="justify-content:flex-start;">
      <span class="chip">Gesamtmenge: <?= number_format($summeGesamt, 0, ',', '.') ?></span>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Bestände nach Lager</h2>
    <div class="lager-grid">
      <article class="inventory-section" style="gap:16px;">
        <header>
          <h3>🏭 Hauptlager</h3>
          <div class="lager-card__meta">
            <span class="chip">Gesamt: <?= number_format($summeHaupt, 0, ',', '.') ?></span>
            <a class="button-secondary" href="hauptlager_edit.php">✏️ Bearbeiten</a>
          </div>
        </header>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
            <tbody>
              <?php foreach ($hauptlager as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['produkt']) ?></td>
                  <td><?= number_format($item['bestand'], 0, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>

      <article class="inventory-section" style="gap:16px;">
        <header>
          <h3>🧰 Azubilager</h3>
          <div class="lager-card__meta">
            <span class="chip">Gesamt: <?= number_format($summeAzubi, 0, ',', '.') ?></span>
            <a class="button-secondary" href="azubilager_edit.php">✏️ Bearbeiten</a>
          </div>
        </header>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
            <tbody>
              <?php foreach ($azubilager as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['produkt']) ?></td>
                  <td><?= number_format($item['bestand'], 0, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>

      <article class="inventory-section" style="gap:16px;">
        <header>
          <h3>🗂️ Bürolager</h3>
          <div class="lager-card__meta">
            <span class="chip">Gesamt: <?= number_format($summeBuero, 0, ',', '.') ?></span>
            <a class="button-secondary" href="buero_lager_edit.php">✏️ Bearbeiten</a>
          </div>
        </header>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Produkt</th><th>Bestand</th></tr></thead>
            <tbody>
              <?php foreach ($buerolager as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['produkt']) ?></td>
                  <td><?= number_format($item['bestand'], 0, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
      <a href="hauptlager_edit.php" class="button-secondary">🏭 Hauptlager</a>
      <a href="azubilager_edit.php" class="button-secondary">🧰 Azubilager</a>
      <a href="buero_lager_edit.php" class="button-secondary">🗂️ Bürolager</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>