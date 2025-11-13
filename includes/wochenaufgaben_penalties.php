<?php

declare(strict_types=1);

require_once __DIR__ . '/wochenaufgaben_helpers.php';

if (!defined('WOCHENAUFGABEN_PENALTY_STATUS_OPEN')) {
    define('WOCHENAUFGABEN_PENALTY_STATUS_OPEN', 'offen');
    define('WOCHENAUFGABEN_PENALTY_STATUS_PAID', 'bezahlt');
}

/**
 * Legt die notwendigen Tabellen für Strafgebühren und Einstellungen an.
 */
function wochenaufgaben_penalties_ensure_schema(PDO $pdo): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS wochenaufgaben_settings (" .
            "  setting_key VARCHAR(64) PRIMARY KEY," .
            "  setting_value VARCHAR(255) NOT NULL" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Ignorieren – fehlende Rechte sollten die Anwendung nicht stoppen.
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS wochenaufgaben_strafen (" .
            "  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY," .
            "  mitarbeiter VARCHAR(255) NOT NULL," .
            "  mitarbeiter_id INT NULL," .
            "  kalenderwoche VARCHAR(10) NOT NULL," .
            "  woche_start DATE NOT NULL," .
            "  woche_ende DATE NOT NULL," .
            "  ziel_summe INT UNSIGNED NOT NULL DEFAULT 0," .
            "  erreicht_summe INT UNSIGNED NOT NULL DEFAULT 0," .
            "  fehlende_summe INT UNSIGNED NOT NULL DEFAULT 0," .
            "  prozent_erfuellt DECIMAL(5,2) NOT NULL DEFAULT 0.00," .
            "  strafe_betrag DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "  basisstrafe DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "  strafe_pro_einheit DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "  mindesterfuellung INT NOT NULL DEFAULT 0," .
            "  status VARCHAR(32) NOT NULL DEFAULT 'offen'," .
            "  erstellt_von INT NULL," .
            "  erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP," .
            "  nachricht_id INT NULL," .
            "  bemerkung TEXT NULL," .
            "  UNIQUE KEY uniq_mitarbeiter_woche (mitarbeiter, kalenderwoche)," .
            "  KEY idx_status (status)," .
            "  KEY idx_woche (kalenderwoche)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Ignorieren – falls die Tabelle nicht angelegt werden kann, bleibt das Modul inaktiv.
    }
}

function wochenaufgaben_penalties_default_settings(): array
{
    return [
        'penalty_base_amount' => 50.0,
        'penalty_per_unit' => 2.5,
        'penalty_threshold_percent' => 80,
    ];
}

function wochenaufgaben_penalties_cast_settings(array $settings): array
{
    $defaults = wochenaufgaben_penalties_default_settings();
    $merged = array_merge($defaults, $settings);

    $merged['penalty_base_amount'] = max(0.0, (float)$merged['penalty_base_amount']);
    $merged['penalty_per_unit'] = max(0.0, (float)$merged['penalty_per_unit']);
    $merged['penalty_threshold_percent'] = (int)round((float)$merged['penalty_threshold_percent']);
    if ($merged['penalty_threshold_percent'] < 0) {
        $merged['penalty_threshold_percent'] = 0;
    }
    if ($merged['penalty_threshold_percent'] > 100) {
        $merged['penalty_threshold_percent'] = 100;
    }

    return $merged;
}

function wochenaufgaben_penalties_get_settings(PDO $pdo): array
{
    $settings = [];
    try {
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM wochenaufgaben_settings');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        // Tabelle existiert evtl. noch nicht
    }

    return wochenaufgaben_penalties_cast_settings($settings);
}

function wochenaufgaben_penalties_save_settings(PDO $pdo, array $settings): void
{
    $settings = wochenaufgaben_penalties_cast_settings($settings);

    $sql = 'INSERT INTO wochenaufgaben_settings (setting_key, setting_value) VALUES (?, ?)'
         . ' ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
    $stmt = $pdo->prepare($sql);

    foreach ($settings as $key => $value) {
        $stmt->execute([$key, (string)$value]);
    }
}

function wochenaufgaben_penalties_find_user_account_id(PDO $pdo, string $mitarbeiterName): ?int
{
    if ($mitarbeiterName === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT ua.id
             FROM mitarbeiter m
             JOIN user_accounts ua ON ua.mitarbeiter_id = m.id
             WHERE m.name = ?
             LIMIT 1'
        );
        $stmt->execute([$mitarbeiterName]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
    } catch (Throwable $e) {
        // ignoriere Fehler
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM user_accounts WHERE username = ? LIMIT 1');
        $stmt->execute([$mitarbeiterName]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
    } catch (Throwable $e) {
        // ignoriere Fehler
    }

    return null;
}

function wochenaufgaben_penalties_find_employee_id(PDO $pdo, string $mitarbeiterName): ?int
{
    if ($mitarbeiterName === '') {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM mitarbeiter WHERE name = ? LIMIT 1');
        $stmt->execute([$mitarbeiterName]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    } catch (Throwable $e) {
        return null;
    }
}

function wochenaufgaben_penalties_format_currency(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function wochenaufgaben_penalties_period_label(string $start, string $ende): string
{
    $startTs = strtotime($start);
    $endTs = strtotime($ende);
    $kw = (int)date('W', $startTs ?: time());

    return sprintf(
        'KW %02d (%s – %s)',
        $kw,
        $startTs ? date('d.m.', $startTs) : $start,
        $endTs ? date('d.m.', $endTs) : $ende
    );
}

/**
 * Liefert den Leistungsstand aller geplanten Mitarbeiter für die Woche.
 *
 * @return array<int, array<string, mixed>>
 */
function wochenaufgaben_penalties_calculate(PDO $pdo, string $kalenderwoche, array $settings): array
{
    $settings = wochenaufgaben_penalties_cast_settings($settings);
    $periode = getWeekPeriod($kalenderwoche);

    $plansStmt = $pdo->prepare(
        'SELECT mitarbeiter, produkt, zielmenge
         FROM wochenaufgaben_plan
         WHERE kalenderwoche = ?'
    );
    $plansStmt->execute([$periode['kalenderwoche']]);
    $plans = $plansStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$plans) {
        return [];
    }

    $targets = [];
    foreach ($plans as $plan) {
        $mitarbeiter = $plan['mitarbeiter'];
        $produkt = $plan['produkt'];
        $ziel = (int)$plan['zielmenge'];

        if (!isset($targets[$mitarbeiter])) {
            $targets[$mitarbeiter] = [
                'total' => 0,
                'produkte' => [],
            ];
        }

        $targets[$mitarbeiter]['total'] += $ziel;
        $targets[$mitarbeiter]['produkte'][$produkt] = ($targets[$mitarbeiter]['produkte'][$produkt] ?? 0) + $ziel;
    }

    if (!$targets) {
        return [];
    }

    $leistungenStmt = $pdo->prepare(
        'SELECT mitarbeiter, produkt, SUM(menge) AS summe
         FROM wochenaufgaben
         WHERE datum BETWEEN ? AND ?
         GROUP BY mitarbeiter, produkt'
    );
    $leistungenStmt->execute([$periode['start_datetime'], $periode['end_datetime']]);
    $leistungen = $leistungenStmt->fetchAll(PDO::FETCH_ASSOC);

    $ergebnisse = [];
    $leistungNachMitarbeiter = [];
    foreach ($leistungen as $leistung) {
        $m = $leistung['mitarbeiter'];
        if (!isset($targets[$m])) {
            continue;
        }
        $produkt = $leistung['produkt'];
        $summe = (int)$leistung['summe'];
        $leistungNachMitarbeiter[$m][$produkt] = $summe;
    }

    foreach ($targets as $mitarbeiter => $zielDaten) {
        $gesamtZiel = (int)$zielDaten['total'];
        if ($gesamtZiel <= 0) {
            continue;
        }

        $produkte = $zielDaten['produkte'];
        $leistungProProdukt = $leistungNachMitarbeiter[$mitarbeiter] ?? [];
        $erreichtBegrenzt = 0;
        $erreichtGesamt = 0;
        $produktDetails = [];

        foreach ($produkte as $produkt => $zielmenge) {
            $erreicht = (int)($leistungProProdukt[$produkt] ?? 0);
            $erreichtGesamt += $erreicht;
            $angerechnet = min($zielmenge, $erreicht);
            $fehlend = max(0, $zielmenge - $erreicht);

            $erreichtBegrenzt += $angerechnet;
            $produktDetails[] = [
                'produkt' => $produkt,
                'ziel' => $zielmenge,
                'erreicht' => $erreicht,
                'angerechnet' => $angerechnet,
                'fehlend' => $fehlend,
            ];
        }

        $fehlendGesamt = max(0, $gesamtZiel - $erreichtBegrenzt);
        $ratio = $gesamtZiel > 0 ? min(1.0, $erreichtBegrenzt / $gesamtZiel) : 1.0;
        $percent = $ratio * 100;

        $threshold = $settings['penalty_threshold_percent'] / 100;
        if ($ratio >= $threshold) {
            $strafe = 0.0;
        } else {
            $fehlbetragEinheiten = (float)$fehlendGesamt * $settings['penalty_per_unit'];
            $anteiligeBasis = (1 - $ratio) * $settings['penalty_base_amount'];
            $strafe = max(0.0, round($fehlbetragEinheiten + $anteiligeBasis, 2));
        }

        $ergebnisse[] = [
            'mitarbeiter' => $mitarbeiter,
            'kalenderwoche' => $periode['kalenderwoche'],
            'woche_start' => $periode['start_date'],
            'woche_ende' => $periode['end_date'],
            'periode_label' => wochenaufgaben_penalties_period_label($periode['start_date'], $periode['end_date']),
            'ziel_summe' => $gesamtZiel,
            'erreicht_summe' => $erreichtBegrenzt,
            'erreicht_gesamt' => $erreichtGesamt,
            'fehlende_summe' => $fehlendGesamt,
            'prozent_erfuellt' => round($percent, 2),
            'penalty_amount' => $strafe,
            'basisstrafe' => $settings['penalty_base_amount'],
            'penalty_per_unit' => $settings['penalty_per_unit'],
            'threshold_percent' => $settings['penalty_threshold_percent'],
            'produkt_details' => $produktDetails,
        ];
    }

    return $ergebnisse;
}

function wochenaufgaben_penalties_build_message(array $penaltyRow): string
{
    $lines = [];
    $lines[] = sprintf('Hallo %s,', $penaltyRow['mitarbeiter']);
    $lines[] = '';
    $lines[] = sprintf(
        'du hast deine Wochenaufgaben für %s nur zu %.0f%% erfüllt.',
        $penaltyRow['periode_label'],
        $penaltyRow['prozent_erfuellt']
    );

    if ($penaltyRow['fehlende_summe'] > 0) {
        $lines[] = sprintf(
            'Es fehlen insgesamt %d Einheit(en) deiner geplanten Aufgaben.',
            $penaltyRow['fehlende_summe']
        );
    }

    $lines[] = '';
    $lines[] = 'Berechnung der Strafgebühr:';

    $anteiligeBasis = max(0.0, round((1 - ($penaltyRow['prozent_erfuellt'] / 100)) * $penaltyRow['basisstrafe'], 2));
    $fehlbetrag = max(0.0, round($penaltyRow['fehlende_summe'] * $penaltyRow['penalty_per_unit'], 2));

    $lines[] = sprintf(
        '- Basisstrafe (%.2f €) × unerfüllter Anteil (%.0f%%) = %s €',
        $penaltyRow['basisstrafe'],
        max(0, 100 - $penaltyRow['prozent_erfuellt']),
        wochenaufgaben_penalties_format_currency($anteiligeBasis)
    );
    $lines[] = sprintf(
        '- Fehlende Menge: %d × %.2f € = %s €',
        $penaltyRow['fehlende_summe'],
        $penaltyRow['penalty_per_unit'],
        wochenaufgaben_penalties_format_currency($fehlbetrag)
    );
    $lines[] = sprintf('= Zu zahlender Betrag: %s €', wochenaufgaben_penalties_format_currency($penaltyRow['penalty_amount']));

    if (!empty($penaltyRow['produkt_details'])) {
        $lines[] = '';
        $lines[] = 'Offene Aufgaben je Produkt:';
        foreach ($penaltyRow['produkt_details'] as $detail) {
            if ($detail['fehlend'] <= 0) {
                continue;
            }
            $lines[] = sprintf(
                '- %s: %d von %d Einheit(en) offen',
                $detail['produkt'],
                $detail['fehlend'],
                $detail['ziel']
            );
        }
    }

    $lines[] = '';
    $lines[] = 'Bitte begleiche den Betrag zeitnah und gib Bescheid, sobald er beglichen wurde.';
    $lines[] = '';
    $lines[] = 'Vielen Dank für deinen Einsatz!';

    return implode("\n", $lines);
}

/**
 * Speichert oder aktualisiert eine Strafe und verschickt optional eine Nachricht.
 *
 * @return array{status:string, message_sent:bool, record_id:int|null}
 */
function wochenaufgaben_penalties_store(PDO $pdo, array $penaltyRow, ?int $senderId = null): array
{
    $receiverId = wochenaufgaben_penalties_find_user_account_id($pdo, $penaltyRow['mitarbeiter']);
    $mitarbeiterId = wochenaufgaben_penalties_find_employee_id($pdo, $penaltyRow['mitarbeiter']);

    $existing = null;
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM wochenaufgaben_strafen WHERE mitarbeiter = ? AND kalenderwoche = ? LIMIT 1'
        );
        $stmt->execute([$penaltyRow['mitarbeiter'], $penaltyRow['kalenderwoche']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $existing = null;
    }

    $status = 'created';
    $messageSent = false;
    $recordId = null;

    $shouldSendMessage = $penaltyRow['penalty_amount'] > 0.0;

    if ($existing && isset($existing['strafe_betrag'], $existing['nachricht_id'])) {
        $differenz = abs((float)$existing['strafe_betrag'] - (float)$penaltyRow['penalty_amount']);
        if ($existing['nachricht_id'] && $differenz < 0.01) {
            $shouldSendMessage = false;
        }
    }

    if ($existing) {
        $status = 'updated';
        $recordId = (int)$existing['id'];
        $stmt = $pdo->prepare(
            'UPDATE wochenaufgaben_strafen
             SET ziel_summe = ?, erreicht_summe = ?, fehlende_summe = ?, prozent_erfuellt = ?, strafe_betrag = ?,
                 basisstrafe = ?, strafe_pro_einheit = ?, mindesterfuellung = ?, bemerkung = NULL, erstellt_von = ?,
                 woche_start = ?, woche_ende = ?, nachricht_id = IFNULL(nachricht_id, ?)
             WHERE id = ?'
        );
        $stmt->execute([
            $penaltyRow['ziel_summe'],
            $penaltyRow['erreicht_summe'],
            $penaltyRow['fehlende_summe'],
            $penaltyRow['prozent_erfuellt'],
            $penaltyRow['penalty_amount'],
            $penaltyRow['basisstrafe'],
            $penaltyRow['penalty_per_unit'],
            $penaltyRow['threshold_percent'],
            $senderId,
            $penaltyRow['woche_start'],
            $penaltyRow['woche_ende'],
            $existing['nachricht_id'] ?? null,
            $recordId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO wochenaufgaben_strafen
                (mitarbeiter, mitarbeiter_id, kalenderwoche, woche_start, woche_ende, ziel_summe, erreicht_summe, fehlende_summe,
                 prozent_erfuellt, strafe_betrag, basisstrafe, strafe_pro_einheit, mindesterfuellung, status, erstellt_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $penaltyRow['mitarbeiter'],
            $mitarbeiterId,
            $penaltyRow['kalenderwoche'],
            $penaltyRow['woche_start'],
            $penaltyRow['woche_ende'],
            $penaltyRow['ziel_summe'],
            $penaltyRow['erreicht_summe'],
            $penaltyRow['fehlende_summe'],
            $penaltyRow['prozent_erfuellt'],
            $penaltyRow['penalty_amount'],
            $penaltyRow['basisstrafe'],
            $penaltyRow['penalty_per_unit'],
            $penaltyRow['threshold_percent'],
            WOCHENAUFGABEN_PENALTY_STATUS_OPEN,
            $senderId,
        ]);
        $recordId = (int)$pdo->lastInsertId();
    }

    if (!$shouldSendMessage) {
        return [
            'status' => $status,
            'message_sent' => false,
            'record_id' => $recordId,
        ];
    }

    if ($receiverId !== null) {
        $subject = sprintf('📦 Wochenaufgaben-Strafe %s', $penaltyRow['periode_label']);
        $message = wochenaufgaben_penalties_build_message($penaltyRow);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO user_messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $senderId ?? $receiverId,
                $receiverId,
                $subject,
                $message,
            ]);
            $messageId = (int)$pdo->lastInsertId();
            $messageSent = true;

            $stmtUpdate = $pdo->prepare(
                'UPDATE wochenaufgaben_strafen SET nachricht_id = ? WHERE id = ?'
            );
            $stmtUpdate->execute([$messageId, $recordId]);
        } catch (Throwable $e) {
            // Nachricht konnte nicht versendet werden – weiter ohne Fehler.
        }
    }

    return [
        'status' => $status,
        'message_sent' => $messageSent,
        'record_id' => $recordId,
    ];
}

function wochenaufgaben_penalties_fetch_for_week(PDO $pdo, string $kalenderwoche): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM wochenaufgaben_strafen WHERE kalenderwoche = ? ORDER BY mitarbeiter'
        );
        $stmt->execute([$kalenderwoche]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function wochenaufgaben_penalties_mark_paid(PDO $pdo, int $penaltyId): void
{
    $stmt = $pdo->prepare(
        'UPDATE wochenaufgaben_strafen
         SET status = ?, bemerkung = NULL
         WHERE id = ?'
    );
    $stmt->execute([WOCHENAUFGABEN_PENALTY_STATUS_PAID, $penaltyId]);
}

function wochenaufgaben_penalties_mark_open(PDO $pdo, int $penaltyId): void
{
    $stmt = $pdo->prepare(
        'UPDATE wochenaufgaben_strafen
         SET status = ?, bemerkung = NULL
         WHERE id = ?'
    );
    $stmt->execute([WOCHENAUFGABEN_PENALTY_STATUS_OPEN, $penaltyId]);
}