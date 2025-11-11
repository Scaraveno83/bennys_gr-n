<?php

declare(strict_types=1);

/**
 * Stellt sicher, dass die Planungstabelle für Wochenaufgaben vorhanden ist.
 */
function ensureWochenaufgabenPlanTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS wochenaufgaben_plan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mitarbeiter VARCHAR(255) NOT NULL,
            produkt VARCHAR(255) NOT NULL,
            zielmenge INT UNSIGNED NOT NULL,
            kalenderwoche VARCHAR(10) NOT NULL,
            erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kalenderwoche (kalenderwoche),
            INDEX idx_mitarbeiter (mitarbeiter)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Normalisiert einen Kalenderwochen-String in das Format YYYY-Www.
 */
function normalizeKalenderwoche(?string $input, ?string $fallback = null): string
{
    $fallback ??= date('o-\WW');
    if (is_string($input) && preg_match('/^\d{4}-W\d{2}$/', $input)) {
        return $input;
    }
    return $fallback;
}

/**
 * Ermittelt den Zeitraum (Montag–Sonntag) einer Kalenderwoche.
 *
 * @return array{
 *     kalenderwoche: string,
 *     start_date: string,
 *     start_datetime: string,
 *     end_date: string,
 *     end_datetime: string
 * }
 */
function getWeekPeriod(string $kalenderwoche): array
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $kalenderwoche, $matches)) {
        $kalenderwoche = date('o-\WW');
        preg_match('/^(\d{4})-W(\d{2})$/', $kalenderwoche, $matches);
    }

    $year = (int) $matches[1];
    $week = (int) $matches[2];

    $monday = new DateTime();
    $monday->setISODate($year, $week);
    $sunday = clone $monday;
    $sunday->modify('sunday this week');

    return [
        'kalenderwoche'   => $kalenderwoche,
        'start_date'      => $monday->format('Y-m-d'),
        'start_datetime'  => $monday->format('Y-m-d 00:00:00'),
        'end_date'        => $sunday->format('Y-m-d'),
        'end_datetime'    => $sunday->format('Y-m-d 23:59:59'),
    ];
}
