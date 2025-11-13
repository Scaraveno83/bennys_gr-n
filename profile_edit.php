<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: admin/login.php');
    exit;
}

$stmt = $pdo->prepare("
  SELECT m.*, u.username, us.status AS calendar_status, us.until AS calendar_until
  FROM user_accounts u
  JOIN mitarbeiter m ON m.id = u.mitarbeiter_id
  LEFT JOIN user_status us ON us.user_id = u.id
  WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) { http_response_code(404); exit('Profil nicht gefunden'); }
if (isset($_POST['beschreibung'])) {
    $stmt = $pdo->prepare("
        UPDATE mitarbeiter
        SET beschreibung = :beschreibung,
            skills = :skills,
            phone = :phone,
            email = :email
        WHERE id = :id
    ");

    $stmt->execute([
        ':beschreibung' => $_POST['beschreibung'] ?? '',
        ':skills'        => $_POST['skills'] ?? '',
        ':phone'         => $_POST['phone'] ?? '',
        ':email'         => $_POST['email'] ?? '',
        ':id'            => $me['id'],
    ]);

    header("Location: profile.php?id=" . $me['id']);
    exit;
}

$bild = (!empty($me['bild_url']) && file_exists($me['bild_url']))
    ? $me['bild_url']
    : 'pics/default-avatar.png';

    $skillsList = [];
if (!empty($me['skills'])) {
    $skillsList = array_filter(array_map('trim', preg_split('/[,;\n]+/', $me['skills'])));
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

$metrics = [];
$metrics[] = [
    'label' => 'Status',
    'value' => $statusInfo['text'],
    'hint'  => $statusInfo['hint'] ?? 'Teamstatus',
];

if (!empty($skillsList)) {
    $metrics[] = [
        'label' => 'Kompetenzen',
        'value' => count($skillsList),
        'hint'  => 'aus dem Profil ersichtlich',
    ];
}

if (!empty($me['phone'])) {
    $metrics[] = [
        'label' => 'Telefon',
        'value' => $me['phone'],
        'hint'  => 'direkte Durchwahl',
    ];
}

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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Profil bearbeiten</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="inventory-page profile-page profile-edit-page">
  <header class="inventory-header profile-header">
    <div class="profile-header__avatar">
      <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Aktuelles Profilbild">
    </div>
    <div class="profile-header__content">
      <h1 class="inventory-title">🛠️ Profil bearbeiten</h1>
      <p class="inventory-description">Halte deine Kontaktdaten und Kompetenzen für das Team auf dem neuesten Stand.</p>
      <p class="inventory-info">Angemeldet als <?= htmlspecialchars($me['username']) ?></p>

      <div class="profile-rank">
        <?php if ($icon && file_exists($icon)): ?>
          <img src="<?= htmlspecialchars($icon) ?>" alt="Rang" class="profile-rank__icon">
        <?php endif; ?>
        <span class="profile-rank__title"><?= htmlspecialchars($me['rang'] ?: 'Unbekannter Rang') ?></span>
      </div>

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

  <section class="inventory-section profile-edit-avatar">
    <h2>Profilbild aktualisieren</h2>
    <p class="inventory-section__intro">Zeig dem Team, wer hinter den Werkzeugen steckt.</p>
    <div class="profile-edit-avatar-grid">
      <div class="profile-edit-avatar-preview">
        <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Aktuelles Profilbild">
        <span class="profile-edit-hint">Aktuelle Vorschau</span>
      </div>
      <form action="upload_avatar.php" method="post" enctype="multipart/form-data" class="inventory-form profile-avatar-form">
        <div class="input-control">
          <label for="avatar">Neues Profilbild</label>
          <input type="file" id="avatar" name="avatar" accept="image/*" class="profile-avatar-input">
        </div>
        <div class="form-actions">
          <button type="submit" class="inventory-submit">Bild hochladen</button>
        </div>
      </form>
    </div>
  </section>

  <section class="inventory-section profile-edit-details">
    <h2>Stammdaten &amp; Kontakt</h2>
    <p class="inventory-section__intro">Diese Angaben werden in deinem internen Teamprofil angezeigt.</p>
    <form action="profile_edit.php" method="post" class="inventory-form">
      <div class="form-grid two-column">
        <div class="input-control input-control--full">
          <label for="beschreibung">Beschreibung</label>
          <textarea id="beschreibung" name="beschreibung" rows="4"><?= htmlspecialchars($me['beschreibung']) ?></textarea>
        </div>

    <div class="input-control input-control--full">
          <label for="skills">Skills</label>
          <input type="text" id="skills" name="skills" class="input-field" value="<?= htmlspecialchars($me['skills']) ?>" placeholder="z. B. Karosserie, Diagnose, Kundenservice">
        </div>

    <div class="input-control">
          <label for="phone">Telefon</label>
          <input type="text" id="phone" name="phone" class="input-field" value="<?= htmlspecialchars($me['phone']) ?>">
        </div>

    <div class="input-control">
          <label for="email">E-Mail</label>
          <input type="email" id="email" name="email" class="input-field" value="<?= htmlspecialchars($me['email']) ?>">
        </div>
      </div>

    <div class="form-actions">
        <button type="submit" class="inventory-submit">💾 Änderungen speichern</button>
        <a href="profile.php?id=<?= (int)$me['id'] ?>" class="inventory-submit inventory-submit--ghost">Zurück zur Profilansicht</a>
      </div>
    </form>
  </section>
</main>
<script src="script.js"></script>
</body>
</html>
