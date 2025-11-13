<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/gamification.php';

$leaderboard = gamification_get_leaderboard($pdo);
$totalEmployees = count($leaderboard);
$totalXp = 0;
foreach ($leaderboard as $entry) {
    $totalXp += (int)($entry['xp']['total'] ?? 0);
}
$averageXp = $totalEmployees > 0 ? $totalXp / $totalEmployees : 0.0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🏆 Gesamtes Ranking | Benny’s Werkstatt</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include 'header.php'; ?>
<main class="leaderboard-page">
  <header class="leaderboard-header">
    <h1>🏆 Gesamtes Ranking</h1>
    <p class="leaderboard-intro">
      Hier findest du die vollständige Rangliste aller Mitarbeitenden. Die Punkte basieren auf abgeschlossenen Wochenaufgaben,
      Aktivitäten im Forum sowie Reaktionen und Kommentare zu den News.
    </p>
    <div class="leaderboard-stats">
      <article class="leaderboard-stat">
        <span class="leaderboard-stat__label">Teilnehmende</span>
        <span class="leaderboard-stat__value"><?= number_format($totalEmployees, 0, ',', '.') ?></span>
      </article>
      <article class="leaderboard-stat">
        <span class="leaderboard-stat__label">Gesamte XP</span>
        <span class="leaderboard-stat__value"><?= number_format($totalXp, 0, ',', '.') ?></span>
      </article>
      <article class="leaderboard-stat">
        <span class="leaderboard-stat__label">Durchschnittliche XP</span>
        <span class="leaderboard-stat__value"><?= number_format($averageXp, 0, ',', '.') ?></span>
      </article>
    </div>
  </header>

  <section class="leaderboard-section">
    <?php if (!empty($leaderboard)): ?>
      <div class="leaderboard-table-wrapper">
        <table class="leaderboard-table">
          <thead>
            <tr>
              <th scope="col">Platz</th>
              <th scope="col">Name</th>
              <th scope="col">Level</th>
              <th scope="col">XP</th>
              <th scope="col">Wochenaufgaben</th>
              <th scope="col">Forum</th>
              <th scope="col">News (Reaktionen)</th>
              <th scope="col">News (Kommentare)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaderboard as $index => $row): ?>
              <?php
                $rank = $index + 1;
                $profilePath = (string)($row['profile_path'] ?? 'profile.php?id=' . (int)($row['id'] ?? 0));
                $levelNumber = (int)($row['level']['number'] ?? 1);
                $levelTitle = trim((string)($row['level']['title'] ?? ''));
              ?>
              <tr class="leaderboard-table__row<?= $rank <= 3 ? ' leaderboard-table__row--top' . $rank : '' ?>">
                <td data-title="Platz">#<?= $rank ?></td>
                <td data-title="Name">
                  <a class="leaderboard-table__name" href="<?= htmlspecialchars($profilePath) ?>">
                    <?= htmlspecialchars((string)($row['name'] ?? 'Unbekannt')) ?>
                  </a>
                  <?php if ($levelTitle !== ''): ?>
                    <span class="leaderboard-table__subtitle"><?= htmlspecialchars($levelTitle) ?></span>
                  <?php endif; ?>
                </td>
                <td data-title="Level"><?= $levelNumber ?></td>
                <td data-title="XP"><?= number_format((int)($row['xp']['total'] ?? 0), 0, ',', '.') ?></td>
                <td data-title="Wochenaufgaben"><?= number_format((int)($row['metrics']['tasks'] ?? 0), 0, ',', '.') ?></td>
                <td data-title="Forum"><?= number_format((int)($row['metrics']['forum_posts'] ?? 0), 0, ',', '.') ?></td>
                <td data-title="News (Reaktionen)"><?= number_format((int)($row['metrics']['news_reactions'] ?? 0), 0, ',', '.') ?></td>
                <td data-title="News (Kommentare)"><?= number_format((int)($row['metrics']['news_comments'] ?? 0), 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="leaderboard-empty">Noch keine Aktivitäten vorhanden. Starte mit den Wochenaufgaben oder teile etwas im Forum!</p>
    <?php endif; ?>
  </section>
</main>

<script src="script.js"></script>
</body>
</html>