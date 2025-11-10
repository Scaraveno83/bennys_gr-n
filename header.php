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

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$navItems = [
    [
        'label' => 'Home',
        'href' => $basePath . 'index.php',
        'activePaths' => ['index.php'],
    ],
    [
        'label' => 'Über uns',
        'href' => $basePath . 'index.php#about',
    ],
    [
        'label' => 'Leistungen',
        'href' => $basePath . 'index.php#services',
    ],
    [
        'label' => 'Galerie',
        'href' => $basePath . 'gallery.php',
        'activePaths' => ['gallery.php'],
    ],
    [
        'label' => 'Mitarbeiter',
        'href' => $basePath . 'mitarbeiter.php',
        'activePaths' => ['mitarbeiter.php'],
    ],
    [
        'label' => 'News',
        'href' => $basePath . 'news_archiv.php',
        'activePaths' => ['news_archiv.php'],
    ],
];

if (!empty($_SESSION['user_id'])) {
    $navItems[] = [
        'label' => 'Forum',
        'href' => $basePath . 'forum.php',
        'activePaths' => ['forum.php', 'forum_room.php', 'forum_thread.php', 'forum_new_thread.php'],
    ];
}

$showRestricted = !empty($_SESSION['user_role']) || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if ($showRestricted) {
    $navItems[] = [
        'label' => 'Kalender',
        'href' => $basePath . 'calendar/calendar.php',
        'activePaths' => ['calendar.php'],
    ];
    $navItems[] = [
        'label' => 'Preise',
        'href' => $basePath . 'pricing_center.php',
        'activePaths' => ['pricing_center.php'],
    ];
    $navItems[] = [
        'label' => 'Fahrzeuge',
        'href' => $basePath . 'fahrzeuge.php',
        'activePaths' => ['fahrzeuge.php'],
    ];
    $navItems[] = [
        'label' => 'Wochenaufgaben',
        'href' => $basePath . 'wochenaufgaben.php',
        'activePaths' => ['wochenaufgaben.php'],
    ];
    $navItems[] = [
        'label' => 'Kühlschrank',
        'href' => $basePath . 'kuehlschrank.php',
        'activePaths' => ['kuehlschrank.php'],
    ];

    if ($isAdmin || ($userRang && in_array($userRang, $hauptlagerErlaubteRollen, true))) {
        $navItems[] = [
            'label' => 'Hauptlager',
            'href' => $basePath . 'hauptlager.php',
            'activePaths' => ['hauptlager.php'],
        ];
    }

    if ($isAdmin || ($userRang && in_array($userRang, $azubiErlaubteRollen, true))) {
        $navItems[] = [
            'label' => 'Azubilager',
            'href' => $basePath . 'azubilager.php',
            'activePaths' => ['azubilager.php'],
        ];
    }

    if ($isAdmin || ($userRang && in_array($userRang, $bueroErlaubteRollen, true))) {
        $navItems[] = [
            'label' => 'Bürolager',
            'href' => $basePath . 'buerolager.php',
            'activePaths' => ['buerolager.php'],
        ];
    }

    if (!empty($_SESSION['user_id'])) {
        $navItems[] = [
            'label' => 'Nachrichten',
            'href' => $basePath . 'admin/messages.php',
            'activePaths' => ['messages.php'],
            'badge' => $unreadMessages,
        ];
    }
}

if ($isAdmin || ($userRang && in_array($userRang, $adminErlaubteRollen, true))) {
    $navItems[] = [
        'label' => 'Admin',
        'href' => $basePath . 'admin/dashboard.php',
        'activePaths' => ['dashboard.php'],
    ];
    $navItems[] = [
        'label' => 'News verwalten',
        'href' => $basePath . 'admin/news_manage.php',
        'activePaths' => ['news_manage.php'],
    ];
}

if (!empty($_SESSION['user_id'])) {
    $navItems[] = [
        'label' => 'Profil',
        'href' => $basePath . 'profile.php',
        'activePaths' => ['profile.php', 'profile_edit.php'],
    ];
}

if (!empty($_SESSION['user_role']) || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)) {
    $navItems[] = [
        'label' => 'Abmelden',
        'href' => $basePath . 'admin/logout.php',
        'variant' => 'action',
    ];
} else {
    $navItems[] = [
        'label' => 'Login',
        'href' => $basePath . 'admin/login.php',
        'variant' => 'action',
    ];
}
?>

<header>
  <div class="header-top">
    <a href="<?= $basePath ?>index.php" class="brand">
      <img src="<?= $basePath ?>pics/header_logo.png" alt="Benny’s Original Motor Works" class="brand-banner">
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mainMenu">
      <span class="menu-toggle__icon" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
      </span>
      <span class="menu-toggle__label">Menü</span>
    </button>
  </div>

    <div class="menu-bar">
    <nav class="main-nav" id="mainMenu" aria-label="Hauptnavigation">
      <ul class="nav-list">
        <?php foreach ($navItems as $item):
            $paths = $item['activePaths'] ?? [];
            $isActive = in_array($currentPath, $paths, true);
            $classes = ['nav-link'];
            if ($isActive) {
                $classes[] = 'is-active';
            }
            if (!empty($item['variant'])) {
                $classes[] = 'nav-link--' . $item['variant'];
            }
        ?>
          <li class="nav-item">
            <a class="<?= implode(' ', $classes) ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="nav-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (!empty($item['badge'])): ?>
                <span class="msg-count"><?= (int) $item['badge'] ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('header');
  const toggle = header ? header.querySelector('.menu-toggle') : null;
  const nav = header ? header.querySelector('.main-nav') : null;

  if (header && toggle && nav) {
    toggle.addEventListener('click', () => {
      const isOpen = header.classList.toggle('menu-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (header.classList.contains('menu-open')) {
          header.classList.remove('menu-open');
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    });
  }

  const refreshMessages = () => {
    fetch('<?= $basePath ?>admin/check_unread_messages.php')
      .then(res => res.text())
      .then(count => {
        const link = document.querySelector('a[href$="admin/messages.php"]');
        if (!link) return;

        let badge = link.querySelector('.msg-count');
        const num = parseInt(count, 10) || 0;

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
  };

  refreshMessages();
  setInterval(refreshMessages, 30000);
});
</script>
<?php include $_SERVER['DOCUMENT_ROOT'].'/bennys/chat/chat.php'; ?>
