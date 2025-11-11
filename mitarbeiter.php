<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/gamification.php';


// mitarbeiter laden
$stmt = $pdo->query("SELECT * FROM mitarbeiter ORDER BY rang ASC, name ASC");
$team = $stmt->fetchAll(PDO::FETCH_ASSOC);
$leaderboard = gamification_get_leaderboard($pdo, 5);

// Rang-Sortierung & Icons
$rang_order = [
  "Geschäftsführung"      => 1,
  "Stv. Geschäftsleitung" => 2,
  "Personalleitung"       => 3,
  "Ausbilder/in"          => 4,
  "Tuner/in"              => 5,
  "Meister/in"            => 6,
  "Mechaniker/in"         => 7,
  "Geselle/Gesellin"      => 8,
  "Azubi 3.Jahr"          => 9,
  "Azubi 2.Jahr"          => 10,
  "Azubi 1.Jahr"          => 11,
  "Praktikant/in"         => 12,
  "Administrator"         => 13
];

$rang_icons = [
  "Geschäftsführung"        => "gf.png",
  "Stv. Geschäftsleitung"   => "stv_leitung.png",
  "Personalleitung"         => "personalleitung.png",
  "Ausbilder/in"            => "ausbilder.png",
  "Tuner/in"                => "tuner.png",
  "Meister/in"              => "meister.png",
  "Mechaniker/in"           => "mechaniker.png",
  "Geselle/Gesellin"        => "geselle.png",
  "Azubi 3.Jahr"            => "azubi3.png",
  "Azubi 2.Jahr"            => "azubi2.png",
  "Azubi 1.Jahr"            => "azubi1.png",
  "Praktikant/in"           => "praktikant.png",
  "Administrator"           => "admin.png"
];

function avatar_web_for(array $mitarbeiter): string {
    if (!empty($mitarbeiter['bild_url'])) {
        $url = $mitarbeiter['bild_url'];
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $web = '/' . ltrim($url, '/');
        $fs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . $web;
        if (is_file($fs)) {
            return $web;
        }
    }

    $id = (int)($mitarbeiter['id'] ?? 0);
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $fs = __DIR__ . "/pics/profile/{$id}.{$ext}";
        if (is_file($fs)) {
            return "/pics/profile/{$id}.{$ext}";
        }
    }

    return '/pics/default-avatar.png';
}

function rang_icon_for(string $rang, array $rang_icons): ?string {
    if (!isset($rang_icons[$rang])) {
        return null;
    }

    $file = __DIR__ . '/pics/icons/' . $rang_icons[$rang];
    return is_file($file) ? '/pics/icons/' . $rang_icons[$rang] : null;
}

$team = array_map(static function ($mitglied) use ($rang_icons) {
    $mitglied['_avatar'] = avatar_web_for($mitglied);
    $mitglied['_icon'] = rang_icon_for($mitglied['rang'] ?? '', $rang_icons);
    return $mitglied;
}, $team);

$totalMitarbeiter = count($team);
$uniqueRollen = count(array_unique(array_map(static function ($row) {
    return $row['rang'] ?? '';
}, $team)));
$azubiCount = count(array_filter($team, static function ($row) {
    return isset($row['rang']) && stripos($row['rang'], 'Azubi') !== false;
}));
$avatarsMitglied = count(array_filter($team, static function ($row) {
    return ($row['_avatar'] ?? '/pics/default-avatar.png') !== '/pics/default-avatar.png';
}));

// Team nach Rang gruppieren
$teamNachRang = [];
foreach ($team as $mitglied) {
    $teamNachRang[$mitglied['rang']][] = $mitglied;
}

uksort($teamNachRang, static function ($a, $b) use ($rang_order) {
    $aIndex = $rang_order[$a] ?? 999;
    $bIndex = $rang_order[$b] ?? 999;
    if ($aIndex === $bIndex) {
        return strcasecmp($a, $b);
    }
    return $aIndex <=> $bIndex;
});

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>👥 Team Übersicht | Benny’s Werkstatt</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
<style>
.inventory-page.team-page {
  gap: 32px;
}

.team-grid {
  display: grid;
  gap: 24px;
}

.team-leaderboard {
  display: grid;
  gap: 18px;
}

.team-leaderboard__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.team-leaderboard__intro {
  margin: 0;
  color: rgba(200, 255, 210, 0.75);
  font-size: 0.95rem;
}

.team-leaderboard__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 12px;
}

.team-leaderboard__item {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 14px;
  align-items: center;
  padding: 14px 18px;
  border-radius: 16px;
  border: 1px solid rgba(57, 255, 20, 0.18);
  background: rgba(9, 13, 15, 0.84);
  box-shadow: inset 0 0 0 1px rgba(57, 255, 20, 0.06);
}

.team-leaderboard__rank {
  font-size: 1.1rem;
  font-weight: 700;
  color: rgba(118, 255, 101, 0.9);
}

.team-leaderboard__content {
  display: grid;
  gap: 4px;
}

.team-leaderboard__name {
  font-weight: 700;
  color: #fff;
  text-decoration: none;
}

.team-leaderboard__name:hover,
.team-leaderboard__name:focus-visible {
  color: rgba(118, 255, 101, 0.95);
}

.team-leaderboard__meta {
  font-size: 0.85rem;
  color: rgba(200, 255, 210, 0.7);
}

.team-leaderboard__score {
  font-size: 0.95rem;
  font-weight: 600;
  color: rgba(222, 255, 232, 0.85);
}

.team-leaderboard__empty {
  margin: 0;
  color: rgba(200, 255, 210, 0.72);
}

.team-group {
  display: grid;
  gap: 18px;
}

.team-group__header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.1rem;
  color: rgba(200, 255, 210, 0.85);
  font-weight: 600;
}

.team-cards {
  display: grid;
  gap: 22px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.team-card {
  position: relative;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid rgba(57, 255, 20, 0.18);
  background: linear-gradient(145deg, rgba(8, 12, 14, 0.92), rgba(12, 22, 18, 0.85));
  box-shadow:
    inset 0 0 0 1px rgba(57, 255, 20, 0.05),
    0 22px 48px rgba(0, 0, 0, 0.35);
  display: grid;
  gap: 14px;
  text-align: center;
}

.team-card__avatar {
  width: 110px;
  height: 110px;
  object-fit: cover;
  border-radius: 18px;
  border: 2px solid rgba(57, 255, 20, 0.3);
  box-shadow: 0 18px 32px rgba(57, 255, 20, 0.18);
  margin: 0 auto;
}

.team-card__name {
  font-size: 1.35rem;
  font-weight: 700;
  margin: 0;
}

.team-card__rang {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid rgba(57, 255, 20, 0.24);
  background: rgba(57, 255, 20, 0.12);
  color: rgba(210, 255, 220, 0.92);
  font-weight: 600;
  margin: 0 auto;
}

.team-card__rang img {
  height: 20px;
}

.team-card__description {
  color: rgba(255, 255, 255, 0.7);
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.6;
}

.team-card__meta {
  display: grid;
  gap: 6px;
  color: rgba(200, 255, 210, 0.78);
  font-size: 0.9rem;
}

.team-card__meta strong {
  color: #fff;
}

.team-card__actions {
  display: flex;
  justify-content: center;
  margin-top: 8px;
}

.team-card__status {
  color: rgba(118, 255, 101, 0.85);
  font-weight: 600;
}

@media (max-width: 640px) {
  .team-card {
    padding: 20px;
  }

  .team-card__avatar {
    width: 96px;
    height: 96px;
  }
  
  .team-leaderboard__item {
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }

  .team-leaderboard__score {
    justify-self: flex-start;
  }
}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page team-page">
  <header class="inventory-header">
    <h1 class="inventory-title">👥 Unser Team</h1>
    <p class="inventory-description">
      Lerne die Crew hinter Benny’s Werkstatt kennen – von Geschäftsführung bis Azubi. Jeder Avatar führt direkt zum persönlichen Profil.
    </p>
    <p class="inventory-info">
      Aktualisiert: <?= date('d.m.Y') ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Teamgröße</span>
        <span class="inventory-metric__value"><?= number_format($totalMitarbeiter, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">aktive Einträge</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Rollen</span>
        <span class="inventory-metric__value"><?= number_format($uniqueRollen, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">vom Vorstand bis Praktikum</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Auszubildende</span>
        <span class="inventory-metric__value"><?= number_format($azubiCount, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Azubi-Level 1–3</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Individuelle Avatare</span>
        <span class="inventory-metric__value"><?= number_format($avatarsMitglied, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">mit eigenem Profilbild</span>
      </article>
    </div>
  </header>

  <section class="inventory-section team-leaderboard">
    <div class="team-leaderboard__heading">
      <h2>🏆 Gamification Rangliste</h2>
      <p class="team-leaderboard__intro">Punkte aus Wochenaufgaben, Forum und News-Reaktionen – immer live aktualisiert.</p>
    </div>

    <?php if (!empty($leaderboard)): ?>
      <ol class="team-leaderboard__list">
        <?php foreach ($leaderboard as $index => $entry): ?>
          <li class="team-leaderboard__item">
            <span class="team-leaderboard__rank">#<?= $index + 1 ?></span>
            <div class="team-leaderboard__content">
              <a class="team-leaderboard__name" href="profile.php?id=<?= (int) ($entry['id'] ?? 0) ?>">
                <?= htmlspecialchars((string) ($entry['name'] ?? 'Unbekannt')) ?>
              </a>
              <span class="team-leaderboard__meta">Level <?= htmlspecialchars((string) ($entry['level']['number'] ?? 1)) ?> · <?= htmlspecialchars((string) ($entry['level']['title'] ?? '')) ?></span>
            </div>
            <span class="team-leaderboard__score"><?= number_format((int) ($entry['xp']['total'] ?? 0), 0, ',', '.') ?> XP</span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p class="team-leaderboard__empty">Noch keine Aktivitäten erfasst – starte mit deinen ersten Wochenaufgaben!</p>
    <?php endif; ?>
  </section>

  <section class="inventory-section">
    <h2>Team nach Rang</h2>
    <p class="inventory-section__intro">
      Jede Ranggruppe zeigt die verantwortlichen Köpfe und Ansprechpersonen. Klicke auf "Profil ansehen", um mehr über Skills und Hintergrund zu erfahren.
    </p>

    <?php if (empty($teamNachRang)): ?>
      <p class="inventory-section__intro">Derzeit sind keine Teammitglieder eingetragen.</p>
    <?php else: ?>
        <div class="team-grid">
      <?php foreach ($teamNachRang as $rang => $personen): ?>
        <div class="team-group">
          <div class="team-group__header">⭐ <?= htmlspecialchars($rang) ?></div>
          <div class="team-cards">
            <?php foreach ($personen as $m): ?>
              <article class="team-card">
                <img src="<?= htmlspecialchars($m['_avatar']) ?>" class="team-card__avatar" alt="Avatar von <?= htmlspecialchars($m['name']) ?>">
                <h3 class="team-card__name"><?= htmlspecialchars($m['name']) ?></h3>

    <div class="team-card__rang">
                  <?php if (!empty($m['_icon'])): ?>
                    <img src="<?= htmlspecialchars($m['_icon']) ?>" alt="">
                  <?php endif; ?>
                  <?= htmlspecialchars($m['rang']) ?>
                </div>

                <?php if (!empty($m['beschreibung'])): ?>
                  <p class="team-card__description"><?= nl2br(htmlspecialchars($m['beschreibung'])) ?></p>
                <?php endif; ?>

                <div class="team-card__meta">
                  <?php if (!empty($m['status'])): ?>
                    <span class="team-card__status">Status: <?= htmlspecialchars($m['status']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($m['skills'])): ?>
                    <span><strong>Skills:</strong> <?= htmlspecialchars($m['skills']) ?></span>
                  <?php endif; ?>
                </div>

                <div class="team-card__actions">
                  <a class="inventory-submit inventory-submit--small" href="profile.php?id=<?= $m['id'] ?>">Profil ansehen</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
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
