<?php
session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';
require_once '../includes/kuehlschrank_invoices.php';

fridge_invoices_ensure_schema($pdo);

$invoiceFlash = $_SESSION['fridge_invoice_notice'] ?? null;
unset($_SESSION['fridge_invoice_notice']);

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
  try {
    $pdo->beginTransaction();

    $wocheMeta = $pdo->query(
      "SELECT woche_start, woche_ende FROM kuehlschrank_wochenkosten ORDER BY woche_start DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$wocheMeta) {
      $pdo->rollBack();
      header("Location: kuehlschrank_edit.php?archiv=leer");
      exit;
    }

    $wocheStart = $wocheMeta['woche_start'];
    $wocheEnde = $wocheMeta['woche_ende'] ?? null;

    $kostenStmt = $pdo->prepare(
      "SELECT mitarbeiter, gesamt_kosten FROM kuehlschrank_wochenkosten WHERE woche_start = ?"
    );
    $kostenStmt->execute([$wocheStart]);
    $kosten = $kostenStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$kosten) {
      $pdo->rollBack();
      header("Location: kuehlschrank_edit.php?archiv=leer");
      exit;
    }

    $kwNummer = (int)date('W', strtotime($wocheStart));
    $kwWert = sprintf('%02d', $kwNummer);
    $startFormatted = date('d.m.', strtotime($wocheStart));
    $wocheEnde = $wocheEnde ?: date('Y-m-d', strtotime($wocheStart . ' +6 days'));
    $endFormatted = date('d.m.', strtotime($wocheEnde));
    $wocheLabel = sprintf('KW %s (%s – %s)', $kwWert, $startFormatted, $endFormatted);

    $insertStmt = $pdo->prepare(
      "INSERT INTO kuehlschrank_archiv (mitarbeiter, gesamt_kosten, woche, archiviert_am) VALUES (?, ?, ?, NOW())"
    );

    $invoiceCreated = 0;
    $invoiceMessages = 0;
    $invoiceDuplicates = [];
    $invoiceNoAccount = [];

    foreach ($kosten as $k) {
      $insertStmt->execute([$k['mitarbeiter'], $k['gesamt_kosten'], $kwWert]);

      $betrag = (float)$k['gesamt_kosten'];
      if ($betrag <= 0) {
        continue;
      }

      $mitarbeiterName = $k['mitarbeiter'];
      if (fridge_invoice_exists($pdo, $mitarbeiterName, $wocheStart, $wocheEnde)) {
        $invoiceDuplicates[] = $mitarbeiterName;
        continue;
      }

      $periodeLabel = fridge_invoice_period_label($wocheStart, $wocheEnde);
      $invoiceId = fridge_invoice_create(
        $pdo,
        $mitarbeiterName,
        $wocheStart,
        $wocheEnde,
        $betrag,
        $_SESSION['user_id'] ?? null,
        false,
        $periodeLabel
      );
      $invoiceCreated++;

      $items = fridge_invoice_collect_items($pdo, $mitarbeiterName, $wocheStart, $wocheEnde);
      $subject = '🍫 Kühlschrankabrechnung ' . $periodeLabel;
      $messageText = fridge_invoice_build_message($mitarbeiterName, $periodeLabel, $betrag, $items);

      $receiverId = fridge_invoice_find_user_id($pdo, $mitarbeiterName);
      $senderId = $_SESSION['user_id'] ?? null;

      if ($receiverId && $senderId) {
        $messageId = fridge_invoice_send_message($pdo, $senderId, $receiverId, $subject, $messageText);
        fridge_invoice_attach_message($pdo, $invoiceId, $messageId);
        $invoiceMessages++;
      } else {
        $invoiceNoAccount[] = $mitarbeiterName;
      }
    }

    $deleteStmt = $pdo->prepare("DELETE FROM kuehlschrank_wochenkosten WHERE woche_start = ?");
    $deleteStmt->execute([$wocheStart]);

    $pdo->commit();

    $redirectParams = ['done' => 1];
    if ($wocheLabel) {
      $redirectParams['woche_label'] = $wocheLabel;
    }

    $_SESSION['fridge_invoice_notice'] = [
      'created' => $invoiceCreated,
      'messages' => $invoiceMessages,
      'duplicates' => $invoiceDuplicates,
      'no_account' => $invoiceNoAccount,
    ];

    header('Location: kuehlschrank_edit.php?' . http_build_query($redirectParams));
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    header("Location: kuehlschrank_edit.php?archiv=fehler");
    exit;
  }
}

/* === Daten laden === */
$produkte = $pdo->query("SELECT * FROM kuehlschrank_lager ORDER BY kategorie, produkt")->fetchAll(PDO::FETCH_ASSOC);
$kosten = $pdo->query("SELECT * FROM kuehlschrank_wochenkosten ORDER BY gesamt_kosten DESC")->fetchAll(PDO::FETCH_ASSOC);
$verlauf = $pdo->query("SELECT * FROM kuehlschrank_verlauf ORDER BY datum DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$lowStockThreshold = 5;
$anzahlProdukte = count($produkte);
$gesamtBestand = array_sum(array_map(static fn($row) => (int)$row['bestand'], $produkte));
$gesamtWert = array_sum(array_map(static fn($row) => (float)$row['preis'] * (int)$row['bestand'], $produkte));
$summeKosten = array_sum(array_map(static fn($row) => (float)$row['gesamt_kosten'], $kosten));
$letzteAktualisierung = $verlauf[0]['datum'] ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>🧊 Kühlschranklager verwalten | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.inventory-section--split {
  display: grid;
  gap: 24px;
}

@media (min-width: 960px) {
  .inventory-section--split {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.inventory-history-table td .badge {
  justify-content: center;
  min-width: 110px;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🧊 Kühlschranklager verwalten</h1>
    <p class="inventory-description">
      Behalte Snacks, Getränke und Kosten für das Team im Blick. Hier pflegst du Sortiment, Bestände und Wochenabschlüsse.
    </p>
    <p class="inventory-info">
      Letzte Aktion:
      <?= $letzteAktualisierung ? date('d.m.Y H:i \U\h\r', strtotime($letzteAktualisierung)) : 'Noch keine Buchung erfasst' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Produkte gelistet</span>
        <span class="inventory-metric__value"><?= number_format($anzahlProdukte, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">nach Kategorie sortiert</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Bestand gesamt</span>
        <span class="inventory-metric__value"><?= number_format($gesamtBestand, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Einheiten verfügbar</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Warenwert</span>
        <span class="inventory-metric__value">€ <?= number_format($gesamtWert, 2, ',', '.') ?></span>
        <span class="inventory-metric__hint">theoretisch verfügbar</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="kuehlschrank_rechnungen.php" class="inventory-submit">💶 Rechnungen verwalten</a>
    </div>
  </section>

  <?php if (isset($_GET['done'])): ?>
    <section class="inventory-section">
      <h2>Wochenabschluss</h2>
      <p class="inventory-section__intro" style="color:#86ffb5;">
        ✅ Wochenabschluss erfolgreich archiviert<?php if (isset($_GET['woche_label'])): ?> – <?= htmlspecialchars($_GET['woche_label']) ?><?php endif; ?>.
      </p>
    </section>
  <?php endif; ?>

  <?php if (!empty($invoiceFlash)): ?>
    <section class="inventory-section">
      <h2>Kühlschrankabrechnungen</h2>
      <p class="inventory-section__intro" style="color:#86ffb5;">
        💶 <?= (int)$invoiceFlash['created'] ?> Rechnung(en) erzeugt, <?= (int)$invoiceFlash['messages'] ?> Nachricht(en) versendet.
      </p>
      <?php if (!empty($invoiceFlash['no_account'])): ?>
        <p class="inventory-section__intro" style="color:#ffdb87;">
          ⚠️ Für folgende Personen konnte keine Nachricht verschickt werden: <?= htmlspecialchars(implode(', ', array_unique($invoiceFlash['no_account']))) ?>.
          Bitte prüfe, ob Benutzerkonten vorhanden sind.
        </p>
      <?php endif; ?>
      <?php if (!empty($invoiceFlash['duplicates'])): ?>
        <p class="inventory-section__intro" style="color:#ff9a9a;">
          🔁 Für <?= htmlspecialchars(implode(', ', array_unique($invoiceFlash['duplicates']))) ?> existierte bereits eine Rechnung und es wurde keine neue erstellt.
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (isset($_GET['archiv']) && $_GET['archiv'] === 'leer'): ?>
    <section class="inventory-section">
      <h2>Wochenabschluss</h2>
      <p class="inventory-section__intro" style="color:#ff9a9a;">
        ⚠️ Keine offenen Wochenkosten gefunden – es wurde nichts archiviert.
      </p>
    </section>
  <?php endif; ?>

  <?php if (isset($_GET['archiv']) && $_GET['archiv'] === 'fehler'): ?>
    <section class="inventory-section">
      <h2>Wochenabschluss</h2>
      <p class="inventory-section__intro" style="color:#ff9a9a;">
        ❌ Beim Archivieren ist ein Fehler aufgetreten. Bitte erneut versuchen.
      </p>
    </section>
  <?php endif; ?>

  <section class="inventory-section">
    <h2>Produkt hinzufügen oder anpassen</h2>
    <p class="inventory-section__intro">
      Trage neue Artikel ein oder aktualisiere bestehende – Preise wirken sich auf die Auswertung automatisch aus.
    </p>

    <form method="post" class="inventory-form">
      <input type="hidden" name="id" value="">

      <div class="inventory-section--split">
        <div class="input-control">
          <label for="produkt_name">Produktname</label>
          <input id="produkt_name" class="input-field" type="text" name="produkt_name" placeholder="z. B. Mate" required>
        </div>

        <div class="input-control">
          <label for="bestand">Bestand</label>
          <input id="bestand" class="input-field" type="number" name="bestand" min="0" placeholder="z. B. 24" required>
        </div>

        <div class="input-control">
          <label for="preis">Preis (€)</label>
          <input id="preis" class="input-field" type="number" step="0.01" name="preis" min="0" placeholder="1.50" required>
        </div>

        <div class="input-control">
          <label for="kategorie">Kategorie</label>
          <select id="kategorie" name="kategorie" class="inventory-select">
            <option value="Essen">Essen</option>
            <option value="Trinken">Trinken</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">💾 Speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Aktuelle Produkte</h2>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Produkt</th>
            <th>Kategorie</th>
            <th>Bestand</th>
            <th>Preis (€)</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($produkte as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['produkt']) ?></td>
              <td><span class="chip"><?= htmlspecialchars($p['kategorie']) ?></span></td>
              <td class="<?= ((int)$p['bestand'] < $lowStockThreshold) ? 'low-stock' : '' ?>">
                <?= number_format($p['bestand'], 0, ',', '.') ?>
              </td>
              <td>€ <?= number_format($p['preis'], 2, ',', '.') ?></td>
              <td>
                <a class="inventory-submit inventory-submit--ghost inventory-submit--small" href="?delete=<?= $p['id'] ?>"
                   onclick="return confirm('Wirklich löschen?')">🗑️</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Aktuelle Wochenkosten</h2>
    <p class="inventory-section__intro">
      Gesamtsumme dieser Woche: <strong>€ <?= number_format($summeKosten, 2, ',', '.') ?></strong>
    </p>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Mitarbeiter</th><th>Kosten (€)</th><th>Woche</th></tr>
        </thead>
        <tbody>
          <?php foreach ($kosten as $k): ?>
            <tr>
              <td><?= htmlspecialchars($k['mitarbeiter']) ?></td>
              <td>€ <?= number_format($k['gesamt_kosten'], 2, ',', '.') ?></td>
              <td><?= date('d.m.Y', strtotime($k['woche_start'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" class="form-actions">
      <button name="archivieren" type="submit" class="inventory-submit">📦 Wochenabschluss &amp; Archivierung</button>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Letzte Entnahmen</h2>
    <div class="table-wrap">
      <table class="data-table inventory-history-table">
        <thead>
          <tr><th>Datum</th><th>Mitarbeiter</th><th>Produkt</th><th>Menge</th><th>Gesamt (€)</th></tr>
        </thead>
        <tbody>
          <?php foreach ($verlauf as $v): ?>
            <tr>
              <td><?= date('d.m.Y H:i', strtotime($v['datum'])) ?></td>
              <td><?= htmlspecialchars($v['mitarbeiter']) ?></td>
              <td><?= htmlspecialchars($v['produkt']) ?></td>
              <td><?= number_format($v['menge'], 0, ',', '.') ?></td>
              <td>€ <?= number_format($v['gesamtpreis'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="inventory-section">
    <h2>Schnellzugriff</h2>
    <div class="form-actions" style="justify-content:flex-start;">
      <a href="dashboard.php" class="button-secondary">← Zurück zum Dashboard</a>
      <a href="kuehlschrank_archiv.php" class="button-secondary">📚 Zum Archiv</a>
      <a href="lageruebersicht.php" class="button-secondary">📦 Lagerübersicht</a>
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