<?php
session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';


/* === Archiv laden === */
$archiv = $pdo->query("SELECT * FROM kuehlschrank_archiv ORDER BY archiviert_am DESC")->fetchAll(PDO::FETCH_ASSOC);

$gesamtKosten = array_sum(array_map(static fn($row) => (float)$row['gesamt_kosten'], $archiv));
$eintraegeGesamt = count($archiv);
$wochen = array_unique(array_map(static fn($row) => $row['woche'], $archiv));
$mitarbeiter = array_unique(array_map(static fn($row) => $row['mitarbeiter'], $archiv));
$letzteArchivierung = $archiv[0]['archiviert_am'] ?? null;

$wochenUebersicht = [];
foreach ($archiv as $eintrag) {
  $woche = $eintrag['woche'];
  if (!isset($wochenUebersicht[$woche])) {
    $wochenUebersicht[$woche] = [
      'kosten' => 0,
      'eintraege' => 0,
      'archiviert_am' => $eintrag['archiviert_am'],
      'mitarbeiter' => [],
    ];
  }

  $wochenUebersicht[$woche]['kosten'] += (float)$eintrag['gesamt_kosten'];
  $wochenUebersicht[$woche]['eintraege']++;
  $wochenUebersicht[$woche]['mitarbeiter'][$eintrag['mitarbeiter']] =
    ($wochenUebersicht[$woche]['mitarbeiter'][$eintrag['mitarbeiter']] ?? 0) + (float)$eintrag['gesamt_kosten'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📚 Kühlschrank-Archiv | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.archive-summary__grid {
  display: grid;
  gap: 18px;
}

@media (min-width: 920px) {
  .archive-summary__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.archive-week {
  border: 1px solid rgba(57, 255, 20, 0.18);
  border-radius: 16px;
  padding: 18px;
  background: rgba(10, 14, 16, 0.78);
  display: grid;
  gap: 12px;
}

.archive-week__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 18px;
  font-size: 0.9rem;
  color: rgba(200, 255, 210, 0.85);
}

.archive-week__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
  text-align: left;
}

.archive-week__list strong {
  color: #fff;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📚 Kühlschrank-Archiv</h1>
    <p class="inventory-description">
      Hier findest du alle abgeschlossenen Wochen aus dem Kühlschranklager – inklusive Kostenübersicht pro Teammitglied.
    </p>
    <p class="inventory-info">
      Letzte Archivierung:
      <?= $letzteArchivierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteArchivierung)) : 'Noch keine Daten archiviert' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Archivierte Wochen</span>
        <span class="inventory-metric__value"><?= number_format(count($wochen), 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">distinct gespeichert</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamtkosten</span>
        <span class="inventory-metric__value">€ <?= number_format($gesamtKosten, 2, ',', '.') ?></span>
        <span class="inventory-metric__hint">über alle Einträge</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Archivierte Einträge</span>
        <span class="inventory-metric__value"><?= number_format($eintraegeGesamt, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Zeilen in der Historie</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Teammitglieder</span>
        <span class="inventory-metric__value"><?= number_format(count($mitarbeiter), 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">mit archivierten Kosten</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>Wöchentliche Zusammenfassung</h2>
    <p class="inventory-section__intro">
      Kompakte Übersicht je Kalenderwoche mit Summe der Kosten und beteiligten Mitarbeitenden.
    </p>

    <?php if ($wochenUebersicht): ?>
      <div class="archive-summary__grid">
        <?php foreach ($wochenUebersicht as $woche => $daten): ?>
          <article class="archive-week">
            <header>
              <h3 style="margin:0; font-size:1.25rem;">📅 Woche <?= htmlspecialchars($woche) ?></h3>
            </header>
            <div class="archive-week__meta">
              <span>Einträge: <?= number_format($daten['eintraege'], 0, ',', '.') ?></span>
              <span>Gesamt: € <?= number_format($daten['kosten'], 2, ',', '.') ?></span>
              <span>Archiviert am <?= date('d.m.Y H:i', strtotime($daten['archiviert_am'])) ?></span>
            </div>
            <ul class="archive-week__list">
              <?php foreach ($daten['mitarbeiter'] as $name => $kosten): ?>
                <li><strong><?= htmlspecialchars($name) ?>:</strong> € <?= number_format($kosten, 2, ',', '.') ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro" style="color:#ffc8c8;">Noch keine Archivdaten vorhanden.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Alle Archivbuchungen</h2>
    <p class="inventory-section__intro">
      Detaillierte Historie jeder archivierten Buchung inklusive Zuordnung zum Teammitglied.
    </p>

    <?php if ($archiv): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Woche</th><th>Mitarbeiter</th><th>Kosten (€)</th><th>Archiviert am</th></tr>
          </thead>
          <tbody>
            <?php foreach ($archiv as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['woche']) ?></td>
                <td><?= htmlspecialchars($a['mitarbeiter']) ?></td>
                <td>€ <?= number_format($a['gesamt_kosten'], 2, ',', '.') ?></td>
                <td><?= date('d.m.Y H:i', strtotime($a['archiviert_am'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro" style="color:#ffc8c8;">Keine Buchungen im Archiv gespeichert.</p>
    <?php endif; ?>
  </section>

  <div class="form-actions">
    <a href="kuehlschrank_edit.php" class="inventory-submit inventory-submit--ghost">← Zurück zur Verwaltung</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
