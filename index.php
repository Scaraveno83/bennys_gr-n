<?php
// --- DEBUG optional (bei Livebetrieb ausschalten) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'includes/db.php';

/** Inhalte laden (content.section = 'about' | 'services' | 'team') */
function getContent(string $section) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM content WHERE section = ? LIMIT 1");
    $stmt->execute([$section]);
    return $stmt->fetch();
}

/**
 * Lade die neuesten Galerie-Bilder für die Startseiten-Vorschau.
 */
function getGalleryPreviewImages(int $limit = 3): array
{
    global $pdo;

    $limit = max(1, $limit);
    $hasMediaType = false;

    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM gallery LIKE 'media_type'");
        $hasMediaType = $colStmt && $colStmt->rowCount() > 0;
    } catch (PDOException $e) {
        $hasMediaType = false;
    }

    $columns = ['id', 'image_url', 'alt_text'];
    if ($hasMediaType) {
        $columns[] = 'media_type';
    }

    $rawLimit = $limit * 3; // etwas mehr laden, damit Videos herausgefiltert werden können

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM gallery ORDER BY id DESC LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $rawLimit, PDO::PARAM_INT);
    $stmt->execute();

    $images = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($hasMediaType && ($row['media_type'] ?? 'image') !== 'image') {
            continue;
        }

        $imageUrl = trim((string)($row['image_url'] ?? ''));
        if ($imageUrl === '') {
            continue;
        }

        $images[] = [
            'image_url' => $imageUrl,
            'alt_text'  => $row['alt_text'] ?? ''
        ];

        if (count($images) >= $limit) {
            break;
        }
    }

    return $images;
}

$about    = getContent('about');
$services = getContent('services');
$team     = getContent('team');
$galleryPreview = getGalleryPreviewImages(3);

/** NEWS laden (letzte 5) */
$newsStmt   = $pdo->query("SELECT * FROM news ORDER BY erstellt_am DESC LIMIT 5");
$latestNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

// Login-Infos
$isLoggedIn = !empty($_SESSION['user_role']) || !empty($_SESSION['user_id']);
$userId     = $_SESSION['user_id']   ?? null;
$userRole   = $_SESSION['user_role'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Benny's Werkstatt – Tuning & Reparaturen</title>

<meta name="description" content="Benny’s Werkstatt – dein Partner für Fahrzeugtuning, Reparaturen und Aufbereitung." />
<meta name="theme-color" content="#39ff14" />
<meta property="og:title" content="Benny's Werkstatt – Tuning & Reparaturen" />
<meta property="og:description" content="Offizielle Seite von Benny’s Werkstatt – Qualität, Power und Design für dein Fahrzeug." />
<meta property="og:type" content="website" />
<meta property="og:image" content="https://images.unsplash.com/photo-1592194996308-7b43878e84a6?auto=format&fit=crop&w=1600&q=80" />

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="header.css" />
<link rel="stylesheet" href="styles.css" />
</head>

<body id="top">
<?php include 'header.php'; ?>

<main class="page-shell">

  <!-- 📰 NEWS / ANKÜNDIGUNGEN -->
  <section id="news" class="cards-section surface-panel">
    <header class="section-head section-head--center section-head--narrow">
      <h2 class="section-title">📰 Aktuelle News &amp; Ankündigungen</h2>
      <p class="section-intro">
        Alle internen und öffentlichen Meldungen laufen hier zusammen – sortiert wie in den Lager-Übersichten.
      </p>
    </header>

           <?php if (!empty($latestNews)): ?>
      <div class="card-grid news-list">
        <?php foreach ($latestNews as $n): ?>
          <?php
          $visibility = $n['sichtbar_fuer'] ?? 'oeffentlich';

            // Interne News nur für eingeloggte Nutzer anzeigen:
            if ($visibility === 'intern' && !$isLoggedIn) continue;

            // Icon & Datum vorbereiten
            $icon = trim($n['icon'] ?? '') ?: '📰';
            $createdAt = new DateTime($n['erstellt_am']);
            $createdAtFormatted = $createdAt->format('d.m.Y H:i');
            $createdAtIso = $createdAt->format(DateTime::ATOM);
            $isInternNews = $visibility === 'intern';

            // Reaktionen laden (Zähler)
            $rStmt = $pdo->prepare("SELECT reaction_type, count FROM news_reactions WHERE news_id = ?");
            $rStmt->execute([$n['id']]);
            $reactions = $rStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Kommentare laden
            $cStmt = $pdo->prepare("SELECT id, user_id, name, text, created_at FROM news_comments WHERE news_id = ? ORDER BY created_at ASC");
            $cStmt->execute([$n['id']]);
            $countComments = $cStmt->rowCount();
          ?>
          <article class="card glass news-card" id="news-<?= (int)$n['id'] ?>">
            <header class="news-card__header">
              <div class="news-card__identity">
                <span class="news-card__icon" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                <div class="news-card__titles">
                  <h3 class="news-card__title">
                    <?= htmlspecialchars($n['titel']) ?>
                  </h3>
                  <div class="news-card__meta">
                    <span class="news-card__meta-item">
                      📅 <time datetime="<?= $createdAtIso ?>"><?= $createdAtFormatted ?></time>
                    </span>
                    <span class="news-card__meta-divider" aria-hidden="true"></span>
                    <span class="news-card__meta-item" title="Kommentare">
                      💬 <?= $countComments ?>
                    </span>
                    <span class="news-card__meta-divider" aria-hidden="true"></span>
                    <span class="news-card__badge <?= $isInternNews ? 'news-card__badge--intern' : 'news-card__badge--public' ?>">
                      <?= $isInternNews ? '🔒 Intern' : '🌍 Öffentlich' ?>
                    </span>
                  </div>
                </div>
              </div>
            </header>

            <div class="news-card__body">
              <div class="news-text"><?= $n['text'] ?></div>
            </div>

            <footer class="news-card__footer">
              <form method="POST" action="add_reaction.php" class="news-card__reactions">
                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                <button name="reaction" value="like"  class="reaction-btn">
                  <span class="reaction-emoji" aria-hidden="true">👍</span>
                  <span class="reaction-count"><?= (int)($reactions['like']  ?? 0) ?></span>
                </button>
                <button name="reaction" value="love"  class="reaction-btn">
                  <span class="reaction-emoji" aria-hidden="true">❤️</span>
                  <span class="reaction-count"><?= (int)($reactions['love']  ?? 0) ?></span>
                </button>
                <button name="reaction" value="fire"  class="reaction-btn">
                  <span class="reaction-emoji" aria-hidden="true">🔥</span>
                  <span class="reaction-count"><?= (int)($reactions['fire']  ?? 0) ?></span>
                </button>
                <button name="reaction" value="angry" class="reaction-btn">
                  <span class="reaction-emoji" aria-hidden="true">😡</span>
                  <span class="reaction-count"><?= (int)($reactions['angry'] ?? 0) ?></span>
                </button>
              </form>

            <button
                type="button"
                class="toggle-comments-btn"
                data-open-text="💬 Kommentare anzeigen (<?= $countComments ?>)"
                data-close-text="🙈 Kommentare ausblenden"
                aria-expanded="false"
                aria-controls="comments-<?= (int)$n['id'] ?>"
                onclick="toggleComments(this)"
              >
                💬 Kommentare anzeigen (<?= $countComments ?>)
              </button>
            </footer>

            <div class="comments" id="comments-<?= (int)$n['id'] ?>" hidden>
              <h4>Kommentare</h4>

              <?php if ($countComments > 0): ?>
                <?php while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)): ?>
                  <div class="comment">
                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                    <span class="comment-date"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></span>
                    <p><?= nl2br(htmlspecialchars($c['text'])) ?></p>

                    <?php if (($isLoggedIn && ($c['user_id'] ?? null) == $userId) || $userRole === 'admin'): ?>
                      <button type="button" class="edit-btn" onclick="toggleEditForm(this)">✏️ Bearbeiten</button>
                      <form method="POST" action="edit_comment.php" class="edit-comment-form">
                        <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                        <textarea name="new_text" rows="2" required><?= htmlspecialchars($c['text']) ?></textarea>
                        <button type="submit">💾 Speichern</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <p class="no-comments">Noch keine Kommentare.</p>
              <?php endif; ?>

              <?php if (($n['sichtbar_fuer'] ?? 'oeffentlich') === 'oeffentlich' || $isLoggedIn): ?>
                <form method="POST" action="add_comment.php" class="comment-form">
                  <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                  <?php if (!$isLoggedIn): ?>
                    <input type="text" name="name" placeholder="Dein Name" required>
                  <?php endif; ?>
                  <textarea name="comment_text" rows="3" placeholder="Kommentar schreiben..." required></textarea>
                  <button type="submit" class="btn btn-primary">Absenden</button>
                </form>
              <?php else: ?>
                <p class="comment-login-hint">Nur eingeloggte Benutzer können hier kommentieren.</p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="section-footer">
        <a href="news_archiv.php" class="footer-btn">📚 Alle News ansehen</a>
      </div>
    <?php else: ?>
      <div class="card glass"><p>Zurzeit gibt es keine News oder Ankündigungen.</p></div>
    <?php endif; ?>
  </section>

  <!-- ÜBER UNS -->
  <section id="about" class="cards-section surface-panel info-section">
    <header class="section-head section-head--center section-head--narrow">
      <span class="section-kicker">Werkstatt-DNA</span>
      <h2 class="section-title"><?= htmlspecialchars($about['title'] ?? 'Über uns') ?></h2>
    </header>
    <div class="info-grid">
      <article class="card glass info-card">
        <div class="info-card__text">
          <?= nl2br(htmlspecialchars($about['text'] ?? 'Hier steht bald mehr über Benny’s Werkstatt...')) ?>
        </div>
      </article>
    </div>
  </section>

  <!-- SERVICES -->
  <section id="services" class="cards-section surface-panel info-section">
    <header class="section-head section-head--center section-head--narrow">
      <span class="section-kicker">Leistungen</span>
      <h2 class="section-title"><?= htmlspecialchars($services['title'] ?? 'Unsere Services') ?></h2>
    </header>
    <div class="info-grid">
      <article class="card glass info-card">
        <div class="info-card__text">
          <?= nl2br(htmlspecialchars($services['text'] ?? 'Unsere Servicebeschreibungen folgen bald...')) ?>
        </div>
      </article>
    </div>
  </section>

  <!-- TEAM -->
  <section id="team" class="cards-section surface-panel info-section">
    <header class="section-head section-head--center section-head--narrow">
      <span class="section-kicker">Crew</span>
      <h2 class="section-title"><?= htmlspecialchars($team['title'] ?? 'Unser Team') ?></h2>
    </header>
    <div class="info-grid">
      <article class="card glass info-card">
        <div class="info-card__text">
          <?= nl2br(htmlspecialchars($team['text'] ?? 'Unser Team stellt sich bald vor...')) ?>
        </div>
      </article>
    </div>
   </section>

  <!-- GALERIE TEASER -->
  <section id="gallery-teaser" class="cards-section surface-panel gallery-section">
    <header class="section-head section-head--center section-head--narrow">
      <span class="section-kicker">Einblicke</span>
      <h2 class="section-title">Galerie</h2>
    </header>
    <div class="gallery-teaser-grid">
      <article class="card glass gallery-teaser-card">
        <div class="gallery-teaser-card__content">
          <?php if (!empty($galleryPreview)): ?>
            <div class="gallery-teaser-preview" aria-label="Neueste Eindrücke aus der Galerie">
              <?php foreach ($galleryPreview as $preview): ?>
                <figure class="gallery-teaser-thumb">
                  <img src="<?= htmlspecialchars($preview['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($preview['alt_text'] !== '' ? $preview['alt_text'] : 'Galerie-Vorschau aus Benny\'s Werkstatt', ENT_QUOTES, 'UTF-8') ?>">
                </figure>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="gallery-teaser-placeholder">
              <span>Schon bald findest du hier frische Eindrücke aus der Werkstatt.</span>
            </div>
          <?php endif; ?>

          <div class="gallery-teaser-card__text">
            <p>Entdecke eindrucksvolle Bilder und Videos aus Benny's Werkstatt in unserer neuen Mediengalerie.</p>
            <a class="btn btn-primary gallery-btn" href="gallery.php">Zur Galerie</a>
          </div>
        </div>
      </article>
    </div>
  </section>
</main>

<!-- FOOTER -->
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<!-- Popup-Style-Override: zentriert & größer (übersteuert styles aus script.js) -->
<style>
/* Container zentrieren */
#news-popup {
  inset: 0 !important;           /* statt bottom/right */
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;

  position: fixed !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;

  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  box-shadow: none !important;
  z-index: 9998 !important; /* unter dem Dropdown */
}

/* Inhalt größer & im Benny-Style */
#news-popup .news-popup-inner {
  width: min(560px, 92vw) !important;
  background: rgba(25,25,25,0.96) !important;
  border: 2px solid #39ff14 !important;
  border-radius: 16px !important;
  padding: 24px 26px !important;
  color: #fff !important;
  box-shadow: 0 0 35px rgba(57,255,20,0.55) !important;
  text-align: center !important;
}

#news-popup .news-popup-inner h3 {
  color: #76ff65 !important;
  text-shadow: 0 0 14px #39ff14 !important;
  margin-top: 0 !important;
  margin-bottom: 8px !important;
}

#news-popup .popup-buttons {
  display: flex !important;
  gap: 12px !important;
  justify-content: center !important;
  margin-top: 16px !important;
}

#news-popup .btn-primary,
#news-popup .btn-ghost {
  padding: 10px 18px !important;
  border-radius: 10px !important;
  font-weight: 700 !important;
}

/* leichte Einblend-Animation */
@keyframes fadeInUpCenter {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
#news-popup .news-popup-inner { animation: fadeInUpCenter .35s ease; }
</style>

<script>
function toggleComments(button) {
 const card = button.closest('.news-card');
  if (!card) return;

  const targetId = button.getAttribute('aria-controls');
  const comments = targetId ? card.querySelector(`#${targetId}`) : card.querySelector('.comments');
  if (!comments) return;
  
  const isHidden = comments.hasAttribute('hidden');
  if (isHidden) {
    comments.removeAttribute('hidden');
    comments.classList.add('is-visible');
  } else {
    comments.setAttribute('hidden', '');
    comments.classList.remove('is-visible');
  }

  const openText = button.dataset.openText || button.textContent;
  const closeText = button.dataset.closeText || button.textContent;
  const expanded = comments.hasAttribute('hidden') ? 'false' : 'true';
  button.innerHTML = expanded === 'true' ? closeText : openText;
  button.setAttribute('aria-expanded', expanded);
}
function toggleEditForm(button) {
  const form = button.nextElementSibling;
  if (!form) return;
  const isVisible = form.style.display === 'block';
  form.style.display = isVisible ? 'none' : 'block';
  button.textContent = isVisible ? '✏️ Bearbeiten' : '❌ Abbrechen';
}
</script>
<script>
// --- Reaktionen per AJAX ---
document.addEventListener('click', async e => {
  const btn = e.target.closest('.reaction-btn');
  if (!btn) return;
  e.preventDefault();

  const form = btn.closest('form');
  const newsId = form.querySelector('input[name="news_id"]').value;
  const reaction = btn.value;

  try {
    const res = await fetch('add_reaction.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `news_id=${encodeURIComponent(newsId)}&reaction=${encodeURIComponent(reaction)}`
    });

    const data = await res.json();
    if (data.status === 'success') {
      // Zähler aktualisieren
      for (const [type, count] of Object.entries(data.reactions)) {
        const targetBtn = form.querySelector(`button[value="${type}"]`);
        if (!targetBtn) continue;
        const countEl = targetBtn.querySelector('.reaction-count');
        if (countEl) countEl.textContent = count;
      }
    }
  } catch (err) {
    console.error('Fehler bei Reaktion:', err);
  }
});
</script>

<script>
// Smooth-Scroll, wenn die Seite mit #news geöffnet wurde (z.B. aus dem Popup "Anzeigen")
document.addEventListener('DOMContentLoaded', () => {
  if (location.hash === '#news') {
    const el = document.getElementById('news');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
});
</script>

<script src="script.js"></script>
</body>
</html>
