<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';
require_once '../includes/kuehlschrank_invoices.php';

fridge_invoices_ensure_schema($pdo);

$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['status'])) {
        $statusFilter = $_POST['status'];
    }
    if (isset($_POST['q'])) {
        $searchFilter = trim($_POST['q']);
    }
}

$allowedStatus = [
    '' => 'Alle',
    KUEHLSCHRANK_INVOICE_STATUS_OPEN => 'Offen',
    KUEHLSCHRANK_INVOICE_STATUS_PAID => 'Bezahlt',
];

$actionFlash = $_SESSION['fridge_invoice_admin_flash'] ?? null;
unset($_SESSION['fridge_invoice_admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentQuery = [];
    if ($statusFilter !== '' && isset($allowedStatus[$statusFilter])) {
        $currentQuery['status'] = $statusFilter;
    }
    if ($searchFilter !== '') {
        $currentQuery['q'] = $searchFilter;
    }

    $redirectUrl = 'kuehlschrank_rechnungen.php' . ($currentQuery ? '?' . http_build_query($currentQuery) : '');

    try {
        if (isset($_POST['mark_paid'])) {
            $invoiceId = (int)$_POST['invoice_id'];
            fridge_invoice_mark_paid($pdo, $invoiceId);
            $_SESSION['fridge_invoice_admin_flash'] = [
                'type' => 'success',
                'text' => 'Rechnung als bezahlt markiert.',
            ];
        } elseif (isset($_POST['mark_open'])) {
            $invoiceId = (int)$_POST['invoice_id'];
            fridge_invoice_mark_open($pdo, $invoiceId);
            $_SESSION['fridge_invoice_admin_flash'] = [
                'type' => 'success',
                'text' => 'Rechnung wieder als offen markiert.',
            ];
        } elseif (isset($_POST['send_reminder'])) {
            $invoiceId = (int)$_POST['invoice_id'];
            $stmt = $pdo->prepare('SELECT * FROM kuehlschrank_rechnungen WHERE id = ?');
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($invoice) {
                $receiverId = fridge_invoice_find_user_id($pdo, $invoice['mitarbeiter']);
                $senderId = $_SESSION['user_id'] ?? null;

                if ($receiverId && $senderId) {
                    $items = fridge_invoice_collect_items($pdo, $invoice['mitarbeiter'], $invoice['woche_start'], $invoice['woche_ende']);
                    $label = $invoice['periode_label'] ?: fridge_invoice_period_label($invoice['woche_start'], $invoice['woche_ende']);
                    $subject = '⏰ Erinnerung: Kühlschrankabrechnung ' . $label;
                    $messageText = fridge_invoice_build_message($invoice['mitarbeiter'], $label, (float)$invoice['betrag'], $items);
                    $messageId = fridge_invoice_send_message($pdo, $senderId, $receiverId, $subject, $messageText);
                    fridge_invoice_attach_message($pdo, $invoiceId, $messageId);

                    $_SESSION['fridge_invoice_admin_flash'] = [
                        'type' => 'success',
                        'text' => 'Erinnerung erfolgreich versendet.',
                    ];
                } else {
                    $_SESSION['fridge_invoice_admin_flash'] = [
                        'type' => 'warning',
                        'text' => 'Kein verknüpftes Benutzerkonto gefunden – Erinnerung konnte nicht verschickt werden.',
                    ];
                }
            } else {
                $_SESSION['fridge_invoice_admin_flash'] = [
                    'type' => 'error',
                    'text' => 'Rechnung nicht gefunden.',
                ];
            }
        } elseif (isset($_POST['manual_invoice'])) {
            $mitarbeiterName = trim($_POST['manual_name'] ?? '');
            $start = $_POST['manual_start'] ?? '';
            $ende = $_POST['manual_end'] ?? '';
            $bemerkung = trim($_POST['manual_note'] ?? '');
            $customAmount = $_POST['manual_amount'] ?? '';
            $periodeLabelInput = trim($_POST['manual_label'] ?? '');

            if (!$mitarbeiterName) {
                throw new RuntimeException('Bitte einen Mitarbeiter auswählen.');
            }

            if (!$start) {
                $start = date('Y-m-d', strtotime('monday this week'));
            }
            if (!$ende) {
                $ende = date('Y-m-d');
            }

            if (strtotime($start) === false || strtotime($ende) === false) {
                throw new RuntimeException('Ungültiger Zeitraum angegeben.');
            }
            if (strtotime($start) > strtotime($ende)) {
                throw new RuntimeException('Startdatum darf nicht nach dem Enddatum liegen.');
            }

            $betrag = null;
            if ($customAmount !== '') {
                $cleanAmount = str_replace(['€', ' '], '', $customAmount);
                $cleanAmount = str_replace('.', '', $cleanAmount);
                $cleanAmount = str_replace(',', '.', $cleanAmount);
                $betrag = (float)$cleanAmount;
            } else {
                $betrag = fridge_invoice_calculate_total($pdo, $mitarbeiterName, $start, $ende);
            }

            if ($betrag <= 0) {
                throw new RuntimeException('Es wurden keine Entnahmen im gewählten Zeitraum gefunden.');
            }

            if (fridge_invoice_exists($pdo, $mitarbeiterName, $start, $ende)) {
                throw new RuntimeException('Für diesen Zeitraum existiert bereits eine Rechnung.');
            }

            $periodeLabel = $periodeLabelInput ?: fridge_invoice_period_label($start, $ende);
            $invoiceId = fridge_invoice_create(
                $pdo,
                $mitarbeiterName,
                $start,
                $ende,
                $betrag,
                $_SESSION['user_id'] ?? null,
                true,
                $periodeLabel,
                $bemerkung
            );

            $items = fridge_invoice_collect_items($pdo, $mitarbeiterName, $start, $ende);
            $receiverId = fridge_invoice_find_user_id($pdo, $mitarbeiterName);
            $senderId = $_SESSION['user_id'] ?? null;

            if ($receiverId && $senderId) {
                $subject = '🍫 Kühlschrankabrechnung ' . $periodeLabel;
                $messageText = fridge_invoice_build_message($mitarbeiterName, $periodeLabel, $betrag, $items);
                if ($bemerkung !== '') {
                    $messageText .= "\n\nHinweis: " . $bemerkung;
                }
                $messageId = fridge_invoice_send_message($pdo, $senderId, $receiverId, $subject, $messageText);
                fridge_invoice_attach_message($pdo, $invoiceId, $messageId);
            }

            $_SESSION['fridge_invoice_admin_flash'] = [
                'type' => 'success',
                'text' => 'Manuelle Rechnung wurde erstellt und versendet.',
            ];
        }
    } catch (RuntimeException $e) {
        $_SESSION['fridge_invoice_admin_flash'] = [
            'type' => 'error',
            'text' => $e->getMessage(),
        ];
    } catch (Throwable $e) {
        $_SESSION['fridge_invoice_admin_flash'] = [
            'type' => 'error',
            'text' => 'Unbekannter Fehler: ' . $e->getMessage(),
        ];
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$filter = [];
if ($statusFilter !== '' && isset($allowedStatus[$statusFilter])) {
    $filter['status'] = $statusFilter;
}
if ($searchFilter !== '') {
    $filter['search'] = $searchFilter;
}

$invoices = fridge_invoice_fetch($pdo, $filter);
$totals = fridge_invoice_totals($pdo);

$mitarbeiterStmt = $pdo->query('SELECT name FROM mitarbeiter ORDER BY name ASC');
$mitarbeiterList = $mitarbeiterStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>💶 Kühlschrankabrechnungen | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.invoice-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.manual-invoice-form {
  display: grid;
  gap: 16px;
}

.manual-invoice-grid {
  display: grid;
  gap: 16px;
}

@media (min-width: 900px) {
  .manual-invoice-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.manual-invoice-grid .field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.invoice-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.85rem;
}

.invoice-status--open {
  background: rgba(255, 182, 66, 0.15);
  border: 1px solid rgba(255, 182, 66, 0.45);
  color: #ffb642;
}

.invoice-status--paid {
  background: rgba(118, 255, 101, 0.12);
  border: 1px solid rgba(118, 255, 101, 0.35);
  color: #76ff65;
}

.flash-box {
  border-radius: 14px;
  padding: 14px 18px;
  margin-bottom: 18px;
}

.flash-box--success {
  background: rgba(118, 255, 101, 0.12);
  border: 1px solid rgba(118, 255, 101, 0.4);
  color: #b9ffc0;
}

.flash-box--error {
  background: rgba(255, 118, 118, 0.12);
  border: 1px solid rgba(255, 118, 118, 0.4);
  color: #ffb6b6;
}

.flash-box--warning {
  background: rgba(255, 214, 102, 0.12);
  border: 1px solid rgba(255, 214, 102, 0.4);
  color: #ffe6a8;
}
</style>
</head>
<body>
<?php include '../header.php'; ?>
<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">💶 Kühlschrankabrechnungen</h1>
    <p class="inventory-description">
      Überblick über alle generierten Rechnungen inklusive Zahlungsstatus und Nachrichtenversand.
    </p>
    <p class="inventory-info">
      Offene Summe: € <?= htmlspecialchars(fridge_invoice_format_currency($totals['offen_betrag'])) ?> ·
      Offene Rechnungen: <?= (int)$totals['offen_anzahl'] ?> ·
      Bereits bezahlt: € <?= htmlspecialchars(fridge_invoice_format_currency($totals['bezahlt_betrag'])) ?>
    </p>
    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Rechnungen gesamt</span>
        <span class="inventory-metric__value"><?= number_format(count($invoices), 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">inkl. Filter</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Offene Rechnungen</span>
        <span class="inventory-metric__value"><?= number_format($totals['offen_anzahl'], 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Summe: € <?= fridge_invoice_format_currency($totals['offen_betrag']) ?></span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Bezahlt</span>
        <span class="inventory-metric__value"><?= number_format($totals['bezahlt_anzahl'], 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Summe: € <?= fridge_invoice_format_currency($totals['bezahlt_betrag']) ?></span>
      </article>
    </div>
  </header>

  <?php if (!empty($actionFlash)): ?>
    <?php
      $flashType = $actionFlash['type'] ?? 'success';
      $flashClass = [
        'success' => 'flash-box--success',
        'error' => 'flash-box--error',
        'warning' => 'flash-box--warning',
      ][$flashType] ?? 'flash-box--success';
    ?>
    <section class="inventory-section">
      <div class="flash-box <?= $flashClass ?>">
        <?= htmlspecialchars($actionFlash['text'] ?? '') ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="inventory-section">
    <h2>Filter</h2>
    <form method="GET" class="inventory-filters">
      <div class="inventory-filters__group">
        <label class="sr-only" for="status">Status</label>
        <select id="status" name="status" class="inventory-select">
          <?php foreach ($allowedStatus as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="sr-only" for="q">Mitarbeiter</label>
        <span class="search-field">
          <input type="search" id="q" name="q" placeholder="Nach Name suchen" value="<?= htmlspecialchars($searchFilter) ?>">
        </span>
      </div>
      <div class="inventory-filters__actions">
        <button type="submit" class="inventory-submit inventory-submit--small">🔍 Anwenden</button>
        <?php if ($statusFilter !== '' || $searchFilter !== ''): ?>
          <a href="kuehlschrank_rechnungen.php" class="inventory-submit inventory-submit--ghost inventory-submit--small">Zurücksetzen</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Rechnungsübersicht</h2>
    <?php if ($invoices): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Mitarbeiter</th>
              <th>Zeitraum</th>
              <th>Betrag</th>
              <th>Status</th>
              <th>Erstellt am</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $invoice): ?>
              <?php
                $statusClass = $invoice['status'] === KUEHLSCHRANK_INVOICE_STATUS_PAID
                  ? 'invoice-status invoice-status--paid'
                  : 'invoice-status invoice-status--open';
                $periodeLabel = $invoice['periode_label'] ?: fridge_invoice_period_label($invoice['woche_start'], $invoice['woche_ende']);
              ?>
              <tr>
                <td>#<?= (int)$invoice['id'] ?></td>
                <td><?= htmlspecialchars($invoice['mitarbeiter']) ?></td>
                <td>
                  <strong><?= htmlspecialchars($periodeLabel) ?></strong><br>
                  <small><?= date('d.m.Y', strtotime($invoice['woche_start'])) ?> – <?= date('d.m.Y', strtotime($invoice['woche_ende'])) ?></small>
                  <?php if (!empty($invoice['bemerkung'])): ?>
                    <br><small>💬 <?= htmlspecialchars($invoice['bemerkung']) ?></small>
                  <?php endif; ?>
                </td>
                <td>€ <?= fridge_invoice_format_currency((float)$invoice['betrag']) ?></td>
                <td><span class="<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($invoice['status'])) ?></span></td>
                <td>
                  <?= date('d.m.Y H:i', strtotime($invoice['erstellt_am'])) ?>
                  <?php if ($invoice['bezahlt_am']): ?>
                    <br><small>Bezahlt am <?= date('d.m.Y H:i', strtotime($invoice['bezahlt_am'])) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="invoice-actions">
                    <form method="POST">
                      <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                      <?php if ($statusFilter !== '' && isset($allowedStatus[$statusFilter])): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                      <?php endif; ?>
                      <?php if ($searchFilter !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchFilter) ?>">
                      <?php endif; ?>
                      <?php if ($invoice['status'] === KUEHLSCHRANK_INVOICE_STATUS_OPEN): ?>
                        <button type="submit" name="mark_paid" class="inventory-submit inventory-submit--small">✅ Bezahlt</button>
                      <?php else: ?>
                        <button type="submit" name="mark_open" class="inventory-submit inventory-submit--ghost inventory-submit--small">↩️ Offen</button>
                      <?php endif; ?>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                      <?php if ($statusFilter !== '' && isset($allowedStatus[$statusFilter])): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                      <?php endif; ?>
                      <?php if ($searchFilter !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($searchFilter) ?>">
                      <?php endif; ?>
                      <button type="submit" name="send_reminder" class="inventory-submit inventory-submit--ghost inventory-submit--small">🔔 Erinnerung</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="inventory-section__intro" style="color:#ffdb87;">Keine Rechnungen für die gewählte Filterkombination gefunden.</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Manuelle Abrechnung erstellen</h2>
    <p class="inventory-section__intro">
      Erstelle eine individuelle Abrechnung – zum Beispiel bei Austritten oder Sonderfällen. Der Zeitraum kann frei gewählt werden.
    </p>
    <form method="POST" class="manual-invoice-form">
      <input type="hidden" name="manual_invoice" value="1">
      <div class="manual-invoice-grid">
        <div class="field">
          <label for="manual_name">Mitarbeiter</label>
          <select id="manual_name" name="manual_name" required>
            <option value="">– auswählen –</option>
            <?php foreach ($mitarbeiterList as $name): ?>
              <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="manual_label">Titel / Zeitraum-Label</label>
          <input type="text" id="manual_label" name="manual_label" placeholder="z.B. Austritt 12.05.2024">
        </div>
        <div class="field">
          <label for="manual_start">Startdatum</label>
          <input type="date" id="manual_start" name="manual_start" value="<?= htmlspecialchars(date('Y-m-d', strtotime('monday this week'))) ?>">
        </div>
        <div class="field">
          <label for="manual_end">Enddatum</label>
          <input type="date" id="manual_end" name="manual_end" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
        </div>
        <div class="field">
          <label for="manual_amount">Betrag (optional)</label>
          <input type="text" id="manual_amount" name="manual_amount" placeholder="Berechnete Summe verwenden">
          <small class="inventory-info" style="margin:0;">Leer lassen, um automatisch aus dem Verlauf zu berechnen.</small>
        </div>
        <div class="field">
          <label for="manual_note">Notiz (optional)</label>
          <textarea id="manual_note" name="manual_note" rows="2" placeholder="z.B. Barzahlung erwartet"></textarea>
        </div>
      </div>
      <button type="submit" class="inventory-submit">➕ Manuelle Rechnung erzeugen</button>
    </form>
  </section>

  <div class="form-actions">
    <a href="kuehlschrank_edit.php" class="inventory-submit inventory-submit--ghost">← Zurück zur Kühlschrankverwaltung</a>
  </div>
</main>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>
<script src="../script.js"></script>
</body>
</html>