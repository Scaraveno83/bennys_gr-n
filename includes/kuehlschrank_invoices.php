<?php
if (!defined('KUEHLSCHRANK_INVOICE_STATUS_OPEN')) {
    define('KUEHLSCHRANK_INVOICE_STATUS_OPEN', 'offen');
    define('KUEHLSCHRANK_INVOICE_STATUS_PAID', 'bezahlt');
}

function fridge_invoices_ensure_schema(PDO $pdo): void
{
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS kuehlschrank_rechnungen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mitarbeiter VARCHAR(255) NOT NULL,
        mitarbeiter_id INT NULL,
        woche_start DATE NOT NULL,
        woche_ende DATE NOT NULL,
        betrag DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(32) NOT NULL DEFAULT 'offen',
        erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        bezahlt_am DATETIME NULL,
        erstellt_von INT NULL,
        nachricht_id INT NULL,
        periode_label VARCHAR(120) NULL,
        bemerkung TEXT NULL,
        manuell TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_status (status),
        INDEX idx_mitarbeiter (mitarbeiter),
        INDEX idx_woche (woche_start, woche_ende)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL;

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        // Tabelle konnte nicht erstellt werden – ignorieren, falls keine Rechte.
    }
}

function fridge_invoice_find_user_id(PDO $pdo, string $mitarbeiterName): ?int
{
    if ($mitarbeiterName === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT ua.id
         FROM mitarbeiter m
         JOIN user_accounts ua ON ua.mitarbeiter_id = m.id
         WHERE m.name = ?
         LIMIT 1"
    );
    $stmt->execute([$mitarbeiterName]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    // Fallback: direkter Username
    $stmt = $pdo->prepare(
        "SELECT id FROM user_accounts WHERE username = ? LIMIT 1"
    );
    $stmt->execute([$mitarbeiterName]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function fridge_invoice_period_label(string $start, string $ende): string
{
    $startTs = strtotime($start);
    $endTs = strtotime($ende);
    $kw = (int)date('W', $startTs);
    return sprintf(
        'KW %02d (%s – %s)',
        $kw,
        date('d.m.', $startTs),
        date('d.m.', $endTs)
    );
}

function fridge_invoice_collect_items(PDO $pdo, string $mitarbeiterName, string $start, string $ende): array
{
    $stmt = $pdo->prepare(
        "SELECT produkt,
                SUM(menge) AS gesamt_menge,
                SUM(gesamtpreis) AS gesamt_preis
         FROM kuehlschrank_verlauf
         WHERE mitarbeiter = ?
           AND DATE(datum) BETWEEN ? AND ?
         GROUP BY produkt
         ORDER BY gesamt_preis DESC, produkt ASC"
    );
    $stmt->execute([$mitarbeiterName, $start, $ende]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $row): array {
        return [
            'produkt' => $row['produkt'],
            'menge' => (int)$row['gesamt_menge'],
            'summe' => (float)$row['gesamt_preis'],
        ];
    }, $rows);
}

function fridge_invoice_calculate_total(PDO $pdo, string $mitarbeiterName, string $start, string $ende): float
{
    $stmt = $pdo->prepare(
        "SELECT SUM(gesamtpreis) FROM kuehlschrank_verlauf
         WHERE mitarbeiter = ? AND DATE(datum) BETWEEN ? AND ?"
    );
    $stmt->execute([$mitarbeiterName, $start, $ende]);
    return (float)($stmt->fetchColumn() ?? 0.0);
}

function fridge_invoice_create(
    PDO $pdo,
    string $mitarbeiterName,
    string $start,
    string $ende,
    float $betrag,
    ?int $erstelltVon = null,
    bool $manuell = false,
    ?string $periodeLabel = null,
    ?string $bemerkung = null
): int {
    $mitarbeiterId = null;
    try {
        $stmt = $pdo->prepare("SELECT id FROM mitarbeiter WHERE name = ? LIMIT 1");
        $stmt->execute([$mitarbeiterName]);
        $mitarbeiterId = $stmt->fetchColumn();
        $mitarbeiterId = $mitarbeiterId ? (int)$mitarbeiterId : null;
    } catch (Throwable $e) {
        $mitarbeiterId = null;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO kuehlschrank_rechnungen
            (mitarbeiter, mitarbeiter_id, woche_start, woche_ende, betrag, status,
             erstellt_von, periode_label, manuell, bemerkung)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $status = KUEHLSCHRANK_INVOICE_STATUS_OPEN;
    $stmt->execute([
        $mitarbeiterName,
        $mitarbeiterId,
        $start,
        $ende,
        $betrag,
        $status,
        $erstelltVon,
        $periodeLabel,
        $manuell ? 1 : 0,
        $bemerkung,
    ]);

    return (int)$pdo->lastInsertId();
}

function fridge_invoice_attach_message(PDO $pdo, int $invoiceId, int $messageId): void
{
    $stmt = $pdo->prepare(
        "UPDATE kuehlschrank_rechnungen SET nachricht_id = ? WHERE id = ?"
    );
    $stmt->execute([$messageId, $invoiceId]);
}

function fridge_invoice_send_message(
    PDO $pdo,
    int $senderId,
    int $receiverId,
    string $subject,
    string $message
): int {
    $stmt = $pdo->prepare(
        "INSERT INTO user_messages (sender_id, receiver_id, subject, message)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$senderId, $receiverId, $subject, $message]);
    return (int)$pdo->lastInsertId();
}

function fridge_invoice_mark_paid(PDO $pdo, int $invoiceId): void
{
    $stmt = $pdo->prepare(
        "UPDATE kuehlschrank_rechnungen
         SET status = ?, bezahlt_am = NOW()
         WHERE id = ?"
    );
    $stmt->execute([KUEHLSCHRANK_INVOICE_STATUS_PAID, $invoiceId]);
}

function fridge_invoice_mark_open(PDO $pdo, int $invoiceId): void
{
    $stmt = $pdo->prepare(
        "UPDATE kuehlschrank_rechnungen
         SET status = ?, bezahlt_am = NULL
         WHERE id = ?"
    );
    $stmt->execute([KUEHLSCHRANK_INVOICE_STATUS_OPEN, $invoiceId]);
}

function fridge_invoice_format_currency(float $betrag): string
{
    return number_format($betrag, 2, ',', '.');
}

function fridge_invoice_build_message(
    string $mitarbeiter,
    string $periodeLabel,
    float $betrag,
    array $items
): string {
    $lines = [];
    $lines[] = sprintf('Hallo %s,', $mitarbeiter);
    $lines[] = '';
    $lines[] = sprintf('hier ist deine Kühlschrankabrechnung für %s.', $periodeLabel);
    $lines[] = '';

    if ($items) {
        $lines[] = 'Aufstellung deiner Entnahmen:';
        foreach ($items as $item) {
            $lines[] = sprintf(
                '- %s × %d = %s €',
                $item['produkt'],
                $item['menge'],
                fridge_invoice_format_currency($item['summe'])
            );
        }
        $lines[] = '';
    }

    $lines[] = sprintf('Gesamtsumme: %s €', fridge_invoice_format_currency($betrag));
    $lines[] = '';
    $lines[] = 'Bitte überweise den Betrag und gib im Adminbereich Bescheid, sobald die Zahlung erfolgt ist.';
    $lines[] = '';
    $lines[] = 'Vielen Dank und guten Appetit!';

    return implode("\n", $lines);
}

function fridge_invoice_exists(PDO $pdo, string $mitarbeiterName, string $start, string $ende): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM kuehlschrank_rechnungen
         WHERE mitarbeiter = ? AND woche_start = ? AND woche_ende = ?"
    );
    $stmt->execute([$mitarbeiterName, $start, $ende]);
    return (int)$stmt->fetchColumn() > 0;
}

function fridge_invoice_fetch(PDO $pdo, array $filter = []): array
{
    $where = [];
    $params = [];

    if (!empty($filter['status'])) {
        $where[] = 'status = ?';
        $params[] = $filter['status'];
    }

    if (!empty($filter['mitarbeiter'])) {
        $where[] = 'mitarbeiter = ?';
        $params[] = $filter['mitarbeiter'];
    }

    if (!empty($filter['search'])) {
        $where[] = 'mitarbeiter LIKE ?';
        $params[] = '%' . $filter['search'] . '%';
    }

    $sql = 'SELECT * FROM kuehlschrank_rechnungen';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY erstellt_am DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fridge_invoice_totals(PDO $pdo): array
{
    $totals = [
        'offen_betrag' => 0.0,
        'offen_anzahl' => 0,
        'bezahlt_betrag' => 0.0,
        'bezahlt_anzahl' => 0,
    ];

    try {
        $stmt = $pdo->query(
            "SELECT status, COUNT(*) AS anzahl, SUM(betrag) AS summe
             FROM kuehlschrank_rechnungen
             GROUP BY status"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['status'] === KUEHLSCHRANK_INVOICE_STATUS_OPEN) {
                $totals['offen_betrag'] = (float)($row['summe'] ?? 0.0);
                $totals['offen_anzahl'] = (int)$row['anzahl'];
            } elseif ($row['status'] === KUEHLSCHRANK_INVOICE_STATUS_PAID) {
                $totals['bezahlt_betrag'] = (float)($row['summe'] ?? 0.0);
                $totals['bezahlt_anzahl'] = (int)$row['anzahl'];
            }
        }
    } catch (Throwable $e) {
        // Tabelle existiert evtl. noch nicht
    }

    return $totals;
}