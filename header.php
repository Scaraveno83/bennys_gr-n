<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🧭 Basis-Pfad automatisch bestimmen (funktioniert für /, /admin/, /arcade/, /calendar/)
$scriptPath = $_SERVER['PHP_SELF'];
if (
    strpos($scriptPath, '/admin/') !== false ||
    strpos($scriptPath, '/arcade/') !== false ||
    strpos($scriptPath, '/calendar/') !== false
) {
    $basePath = '../';
} else {
    $basePath = '';
}

// Verbindung nur, wenn DB noch nicht eingebunden
if (!isset($pdo)) {
    require_once $basePath . 'includes/db.php';
}

/* === Rang des eingeloggten Mitarbeiters abrufen === */
$userRang = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT m.rang 
        FROM mitarbeiter m
        JOIN user_accounts u ON u.mitarbeiter_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userRang = $stmt->fetchColumn();
}

/* === Nachrichten-Zähler === */
$unreadMessages = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        $stmtMsg = $pdo->prepare("
            SELECT COUNT(*) FROM user_messages 
            WHERE receiver_id = ? AND is_read = 0
        ");
        $stmtMsg->execute([$_SESSION['user_id']]);
        $unreadMessages = (int)$stmtMsg->fetchColumn();
    } catch (Exception $e) {
        $unreadMessages = 0;
    }
}

/* === Erlaubte Ränge === */
$azubiErlaubteRollen = [
    'Geschäftsführung', 'Stv. Geschäftsleitung', 'Personalleitung',
    'Ausbilder/in', 'Azubi 1.Jahr', 'Azubi 2.Jahr', 'Azubi 3.Jahr', 'Praktikant/in'
];

$hauptlagerErlaubteRollen = [
    'Geschäftsführung', 'Stv. Geschäftsleitung', 'Personalleitung',
    'Ausbilder/in', 'Tuner/in', 'Meister/in', 'Mechaniker/in', 'Geselle/Gesellin'
];

$bueroErlaubteRollen = [
    'Geschäftsführung', 'Stv. Geschäftsleitung', 'Personalleitung'
];

/* === Admin-Freigaben === */
$isAdmin = (
    (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ||
    (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
);

/* === Adminbereich erlaubte Ränge === */
$adminErlaubteRollen = [
    'Geschäftsführung', 'Stv. Geschäftsleitung', 'Personalleitung'
];
?>

<header>
  <div class="header-inner">
    <!-- 🏁 Logo -->
    <a href="<?= $basePath ?>index.php" class="brand">
      <img src="<?= $basePath ?>pics/header_logo.png" alt="Benny’s Original Motor Works" class="brand-banner">
    </a>

    <!-- 📋 Menü -->
    <div class="menu-container">
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mainMenu">
        <span class="menu-toggle__icon" aria-hidden="true">
          <span class="menu-toggle__bar"></span>
          <span class="menu-toggle__bar"></span>
          <span class="menu-toggle__bar"></span>
        </span>
        <span class="menu-toggle__label">Menü</span>
      </button>
      <nav class="dropdown" id="mainMenu">

        <!-- 🏠 Allgemein -->
        <span class="dropdown-category">🏠 Allgemein</span>
        <a href="<?= $basePath ?>index.php">🏠Startseite</a>
        <a href="<?= $basePath ?>index.php#about">🌐Über uns</a>
        <a href="<?= $basePath ?>index.php#services">⚙️Leistungen</a>
        <a href="<?= $basePath ?>index.php#team">🤜🤛Team</a>
        <a href="<?= $basePath ?>gallery.php">🎬Galerie</a>
        <a href="<?= $basePath ?>mitarbeiter.php">👨‍🔧 Mitarbeiter</a>
        <a href="<?= $basePath ?>news_archiv.php">📰 News</a>
        <?php if (!empty($_SESSION['user_id'])): ?><a href="<?= $basePath ?>forum.php">💬 Forum</a><?php endif; ?>
        

        <?php if (
            !empty($_SESSION['user_role']) ||
            (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
        ): ?>

          <!-- 🧰 Werkstatt -->
          <span class="dropdown-category">🧰 Werkstatt</span>
          <a href="<?= $basePath ?>fahrzeuge.php">🚗 Dienstfahrzeuge</a>
          <a href="<?= $basePath ?>wochenaufgaben.php">📅 Wochenaufgaben</a>
          <a href="<?= $basePath ?>kuehlschrank.php">🥪 Kühlschrank</a>

          <!-- 🔧 Hauptlager -->
          <?php if ($isAdmin || ($userRang && in_array($userRang, $hauptlagerErlaubteRollen))): ?>
            <a href="<?= $basePath ?>hauptlager.php">🔧 Hauptlager</a>
          <?php endif; ?>

          <!-- 🪛 Azubilager -->
          <?php if ($isAdmin || ($userRang && in_array($userRang, $azubiErlaubteRollen))): ?>
            <a href="<?= $basePath ?>azubilager.php">🪛 Azubilager</a>
          <?php endif; ?>

          <!-- 📁 Bürolager -->
          <?php if ($isAdmin || ($userRang && in_array($userRang, $bueroErlaubteRollen))): ?>
            <a href="<?= $basePath ?>buerolager.php">📁 Bürolager</a>
          <?php endif; ?>

          <!-- 👥 Personal -->
          <span class="dropdown-category">👥 Personal</span>
          <a href="<?= $basePath ?>calendar/calendar.php">📆 Kalender</a>
        <a href="<?= $basePath ?>pricing_center.php">💵 Preise & 📄✍️ Verträge</a>

          <!-- 💬 Nachrichten -->
          <a href="<?= $basePath ?>admin/messages.php">
            📨 Nachrichten<?= $unreadMessages > 0 ? " <span class='msg-count'>{$unreadMessages}</span>" : "" ?>
          </a>

        <?php endif; ?>

        <!-- 🛠️ Verwaltung -->
        <?php if ($isAdmin || ($userRang && in_array($userRang, $adminErlaubteRollen))): ?>
          <span class="dropdown-category">🛠️ Verwaltung</span>
          <a href="<?= $basePath ?>admin/dashboard.php">⚙️ Admin-Dashboard</a>
          <a href="<?= $basePath ?>admin/news_manage.php">📰 News verwalten</a>
        <?php endif; ?>

        <!-- 🔓 Login / Logout -->
         <?php if (!empty($_SESSION['user_id'])): ?>
          <a href="<?= $basePath ?>profile.php">👤 Mein Profil</a>
         <?php endif; ?>

        <span class="dropdown-category">🔐 Zugriff</span>
        <?php if (
            !empty($_SESSION['user_role']) ||
            (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
        ): ?>
          <a href="<?= $basePath ?>admin/logout.php" style="color:#76ff65;">🚪 Abmelden</a>
        <?php else: ?>
          <a href="<?= $basePath ?>admin/login.php">🔑 Login</a>
        <?php endif; ?>

      </nav>
    </div>
  </div>
</header>

<!-- Nachrichten-Liveupdate (stört Menü nicht) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  setInterval(() => {
    fetch('<?= $basePath ?>admin/check_unread_messages.php')
      .then(res => res.text())
      .then(count => {
        const link = document.querySelector('a[href$="admin/messages.php"]');
        if (!link) return;

        let badge = link.querySelector('.msg-count');
        const num = parseInt(count) || 0;

        if (num > 0) {
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'msg-count';
            link.appendChild(badge);
          }
          badge.textContent = num;
        } else if (badge) {
          badge.remove();
        }
      })
      .catch(() => {});
  }, 30000);
});
</script>
<?php include $_SERVER['DOCUMENT_ROOT'].'/bennys/chat/chat.php'; ?>
