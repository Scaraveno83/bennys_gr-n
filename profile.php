<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gamification.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// Mitarbeiter-ID des eingeloggten Users holen
$logged_mitarbeiter_id = null;

if (!empty($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT mitarbeiter_id FROM user_accounts WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $loggedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $logged_mitarbeiter_id = $loggedUser['mitarbeiter_id'] ?? null;
}






// Wenn Profil-ID übergeben wurde → dieses Profil anzeigen
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $profil_id = (int)$_GET['id'];

   $stmt = $pdo->prepare("
      SELECT m.*, us.status AS calendar_status, us.until AS calendar_until
      FROM mitarbeiter m
      LEFT JOIN user_accounts u ON u.mitarbeiter_id = m.id
      LEFT JOIN user_status us ON us.user_id = u.id
      WHERE m.id = ?
    ");
    $stmt->execute([$profil_id]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

} else {
    // Sonst → eigenes Profil
    $stmt = $pdo->prepare("
      SELECT m.*, us.status AS calendar_status, us.until AS calendar_until
      FROM user_accounts u
      JOIN mitarbeiter m ON m.id = u.mitarbeiter_id
      LEFT JOIN user_status us ON us.user_id = u.id
      WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$me) {
    die("Profil nicht gefunden.");
}

$bild = (!empty($me['bild_url']) && file_exists($me['bild_url']))
    ? $me['bild_url']
    : 'pics/default-avatar.png';

    $gamificationProfile = gamification_get_profile($pdo, (int) ($me['id'] ?? 0));

if (!function_exists('profile_number_format')) {
    function profile_number_format(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}

if (!function_exists('profile_status_info')) {
    function profile_status_info(?string $status, ?string $until): array
    {
        $status = $status ?: 'Aktiv';
        $text = $status;
        $hint = null;

        if ($status === 'Abwesend') {
            $text = 'Inaktiv';
            if (!empty($until)) {
                try {
                    $dt = new DateTime($until);
                    $hint = 'Bis ' . $dt->format('d.m.Y H:i') . ' abgemeldet';
                } catch (Exception $e) {
                    $hint = 'Aktuell abgemeldet';
                }
            } else {
                $hint = 'Aktuell abgemeldet';
            }
        } elseif ($status === 'Aktiv') {
            $text = 'Aktiv';
            $hint = 'Im Dienst';
        }

        return ['text' => $text, 'hint' => $hint];
    }
}

$statusInfo = profile_status_info($me['calendar_status'] ?? null, $me['calendar_until'] ?? null);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Mein Profil</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
</head>
<body>
<?php include 'header.php'; ?>
<?php
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

$icon = isset($rang_icons[$me['rang']]) ? "pics/icons/" . $rang_icons[$me['rang']] : null;

$skillsList = [];
if (!empty($me['skills'])) {
    $skillsList = array_filter(array_map('trim', preg_split('/[,;\n]+/', $me['skills'])));
}

$metrics = [];
$metrics[] = [
    'label' => 'Rang',
    'value' => $me['rang'] ?: 'Unbekannt',
    'hint'  => null,
];

$metrics[] = [
    'label' => 'Status',
    'value' => $statusInfo['text'],
    'hint'  => $statusInfo['hint'],
];

if (!empty($skillsList)) {
    $skillsPreview = array_slice($skillsList, 0, 3);
    $skillsHint = implode(', ', $skillsPreview);
    if (count($skillsList) > count($skillsPreview)) {
        $skillsHint .= ' …';
    }

    $metrics[] = [
        'label' => 'Kompetenzen',
        'value' => count($skillsList),
        'hint'  => $skillsHint ?: null,
    ];
}

if (!empty($me['phone'])) {
    $metrics[] = [
        'label' => 'Telefon',
        'value' => $me['phone'],
        'hint'  => 'Direkter Draht',
    ];
}

if (!empty($gamificationProfile)) {
    $metrics[] = [
        'label' => 'Gesamt-XP',
        'value' => profile_number_format((int) ($gamificationProfile['xp']['total'] ?? 0)),
        'hint'  => 'Level ' . ($gamificationProfile['level']['number'] ?? 1),
    ];
}

$contactItems = [
    'E-Mail' => !empty($me['email']) ? $me['email'] : null,
    'Telefon' => !empty($me['phone']) ? $me['phone'] : null,
    'Rang' => !empty($me['rang']) ? $me['rang'] : null,
];
?>

<main class="inventory-page profile-page">
  <header class="inventory-header profile-header">
    <div class="profile-header__avatar">
      <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Profilbild">
    </div>
    <div class="profile-header__content">
      <h1 class="inventory-title">👤 <?= htmlspecialchars($me['name']) ?></h1>

      <div class="profile-rank">
        <?php if ($icon && file_exists($icon)): ?>
          <img src="<?= htmlspecialchars($icon) ?>" alt="Rang" class="profile-rank__icon">
        <?php endif; ?>
        <span class="profile-rank__title"><?= htmlspecialchars($me['rang'] ?: 'Unbekannter Rang') ?></span>
      </div>

      <?php if (!empty($statusInfo['text'])): ?>
        <p class="inventory-description profile-status">
          <?= htmlspecialchars($statusInfo['text']) ?>
          <?php if (!empty($statusInfo['hint'])): ?>
            <span class="profile-status__hint">(<?= htmlspecialchars($statusInfo['hint']) ?>)</span>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if (!empty($metrics)): ?>
        <div class="inventory-metrics profile-metrics">
          <?php foreach ($metrics as $metric): ?>
            <div class="inventory-metric">
              <span class="inventory-metric__label"><?= htmlspecialchars($metric['label']) ?></span>
              <span class="inventory-metric__value"><?= htmlspecialchars($metric['value']) ?></span>
              <?php if (!empty($metric['hint'])): ?>
                <span class="inventory-metric__hint"><?= htmlspecialchars($metric['hint']) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!empty($gamificationProfile)): ?>
    <?php
      $level = $gamificationProfile['level'] ?? [];
      $progress = $gamificationProfile['progress'] ?? [];
      $xpTotal = (int) ($gamificationProfile['xp']['total'] ?? 0);
      $progressNeededRaw = (int) ($progress['xp_needed'] ?? 0);
      $progressMax = $progressNeededRaw > 0 ? $progressNeededRaw : 1;
      $progressValue = $progressNeededRaw > 0
        ? min($progressMax, max(0, (int) ($progress['xp_into_level'] ?? 0)))
        : $progressMax;
      $progressPercent = $progressNeededRaw > 0
        ? min(100, max(0, (float) ($progress['percent'] ?? 0)))
        : 100;
      $xpToNext = null;
      if (!empty($level['next_threshold'])) {
          $xpToNext = max(0, (int) $level['next_threshold'] - $xpTotal);
      }
      $lastAchievement = $gamificationProfile['last_achievement'] ?? null;
      $nextAchievement = null;
      if (!$lastAchievement && !empty($gamificationProfile['achievements'])) {
          foreach ($gamificationProfile['achievements'] as $candidate) {
              if (empty($candidate['unlocked'])) {
                  $nextAchievement = $candidate;
                  break;
              }
          }
      }
    ?>
    <section class="inventory-section profile-gamification">
      <div class="profile-gamification__heading">
        <h2>Level &amp; Erfolge</h2>
        <span class="profile-level-badge">
          Level <?= htmlspecialchars((string) ($level['number'] ?? 1)) ?>
          <?php if (!empty($level['title'])): ?>· <?= htmlspecialchars((string) $level['title']) ?><?php endif; ?>
        </span>
      </div>

      <div class="profile-xp-overview">
        <div class="profile-xp-overview__row">
          <span class="profile-xp-overview__xp"><?= profile_number_format($xpTotal) ?> XP gesamt</span>
          <?php if (!empty($level['next_number'])): ?>
            <span class="profile-xp-overview__next">Noch <?= profile_number_format((int) ($xpToNext ?? 0)) ?> XP bis Level <?= htmlspecialchars((string) $level['next_number']) ?></span>
          <?php else: ?>
            <span class="profile-xp-overview__next">Max-Level erreicht – weiter so!</span>
          <?php endif; ?>
        </div>
        <div
          class="profile-xp-bar"
          role="progressbar"
          aria-valuemin="0"
          aria-valuemax="<?= htmlspecialchars((string) $progressMax) ?>"
          aria-valuenow="<?= htmlspecialchars((string) $progressValue) ?>"
        >
          <span style="width: <?= htmlspecialchars(number_format($progressPercent, 2, '.', '')) ?>%"></span>
        </div>
      </div>

      <div class="profile-xp-breakdown">
        <div class="profile-xp-breakdown__item">
          <span class="profile-xp-breakdown__label">Wochenaufgaben</span>
          <span class="profile-xp-breakdown__value"><?= profile_number_format((int) ($gamificationProfile['metrics']['tasks'] ?? 0)) ?></span>
          <span class="profile-xp-breakdown__hint"><?= profile_number_format((int) ($gamificationProfile['xp_breakdown']['tasks'] ?? 0)) ?> XP</span>
        </div>
        <div class="profile-xp-breakdown__item">
          <span class="profile-xp-breakdown__label">Forum-Beiträge</span>
          <span class="profile-xp-breakdown__value"><?= profile_number_format((int) ($gamificationProfile['metrics']['forum_posts'] ?? 0)) ?></span>
          <span class="profile-xp-breakdown__hint"><?= profile_number_format((int) ($gamificationProfile['xp_breakdown']['forum_posts'] ?? 0)) ?> XP</span>
        </div>
        <div class="profile-xp-breakdown__item">
          <span class="profile-xp-breakdown__label">News-Reaktionen</span>
          <span class="profile-xp-breakdown__value"><?= profile_number_format((int) ($gamificationProfile['metrics']['news_reactions'] ?? 0)) ?></span>
          <span class="profile-xp-breakdown__hint"><?= profile_number_format((int) ($gamificationProfile['xp_breakdown']['news_reactions'] ?? 0)) ?> XP</span>
        </div>
        <div class="profile-xp-breakdown__item">
          <span class="profile-xp-breakdown__label">News-Kommentare</span>
          <span class="profile-xp-breakdown__value"><?= profile_number_format((int) ($gamificationProfile['metrics']['news_comments'] ?? 0)) ?></span>
          <span class="profile-xp-breakdown__hint"><?= profile_number_format((int) ($gamificationProfile['xp_breakdown']['news_comments'] ?? 0)) ?> XP</span>
        </div>
      </div>

      <?php if (!empty($lastAchievement)): ?>
        <div class="profile-achievement-card">
          <span class="profile-achievement-card__eyebrow">Letztes Achievement</span>
          <h3 class="profile-achievement-card__title"><?= htmlspecialchars((string) ($lastAchievement['title'] ?? '')) ?></h3>
          <p class="profile-achievement-card__text"><?= htmlspecialchars((string) ($lastAchievement['description'] ?? '')) ?></p>
        </div>
      <?php elseif (!empty($nextAchievement)): ?>
        <div class="profile-achievement-card profile-achievement-card--locked">
          <span class="profile-achievement-card__eyebrow">Nächstes Ziel</span>
          <h3 class="profile-achievement-card__title"><?= htmlspecialchars((string) ($nextAchievement['title'] ?? '')) ?></h3>
          <p class="profile-achievement-card__text"><?= htmlspecialchars((string) ($nextAchievement['description'] ?? '')) ?></p>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($me['beschreibung'])): ?>
    <section class="inventory-section profile-bio">
      <h2>Über <?= htmlspecialchars($me['name']) ?></h2>
      <p><?= nl2br(htmlspecialchars($me['beschreibung'])) ?></p>
    </section>
  <?php endif; ?>

  <?php if (!empty($skillsList)): ?>
    <section class="inventory-section profile-skills">
      <h2>Kompetenzen</h2>
      <ul class="profile-skills__list">
        <?php foreach ($skillsList as $skill): ?>
          <li><?= htmlspecialchars($skill) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if (array_filter($contactItems)): ?>
    <section class="inventory-section profile-contact">
      <h2>Kontakt &amp; Details</h2>
      <div class="profile-details-grid">
        <?php foreach ($contactItems as $label => $value): ?>
          <?php if (!$value) { continue; } ?>
          <div class="profile-detail">
            <span class="profile-detail__label"><?= htmlspecialchars($label) ?></span>
            <?php if ($label === 'E-Mail'): ?>
              <a class="profile-detail__value" href="mailto:<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></a>
            <?php else: ?>
              <span class="profile-detail__value"><?= htmlspecialchars($value) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($me['id'] == $logged_mitarbeiter_id): ?>
    <section class="inventory-section profile-actions">
      <h2>Aktionen</h2>
      <p>Aktualisiere deine Angaben und halte dein Profil auf dem neuesten Stand.</p>
      <a href="profile_edit.php" class="button-main">✏️ Profil bearbeiten</a>
    </section>
  <?php endif; ?>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
