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

$about    = getContent('about');
$services = getContent('services');
$team     = getContent('team');

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
    <div class="section-head">
      <h2 class="section-title">📰 Aktuelle News &amp; Ankündigungen</h2>
      <p class="section-intro">
        Alle internen und öffentlichen Meldungen laufen hier zusammen – sortiert wie in den Lager-Übersichten.
      </p>
    </div>

           <?php if (!empty($latestNews)): ?>
      <div class="card-grid news-list">
        <?php foreach ($latestNews as $n): ?>
          <?php
            // Interne News nur für eingeloggte Nutzer anzeigen:
            if (($n['sichtbar_fuer'] ?? 'oeffentlich') === 'intern' && !$isLoggedIn) continue;

            // Reaktionen laden (Zähler)
            $rStmt = $pdo->prepare("SELECT reaction_type, count FROM news_reactions WHERE news_id = ?");
            $rStmt->execute([$n['id']]);
            $reactions = $rStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Kommentare laden
            $cStmt = $pdo->prepare("SELECT id, user_id, name, text, created_at FROM news_comments WHERE news_id = ? ORDER BY created_at ASC");
            $cStmt->execute([$n['id']]);
            $countComments = $cStmt->rowCount();
          ?>
          <div class="card glass news-card" id="news-<?= (int)$n['id'] ?>">
            <h3>
              <?= htmlspecialchars($n['titel']) ?>
              <?php if (($n['sichtbar_fuer'] ?? 'oeffentlich') === 'intern'): ?>
                <span class="intern-label">🔒 Intern</span>
              <?php endif; ?>
            </h3>
            <span class="news-date">📅 <?= date('d.m.Y H:i', strtotime($n['erstellt_am'])) ?></span>
            <div class="news-text"><?= $n['text'] ?></div>

            <!-- Reaktionen -->
            <div class="reactions">
              <form method="POST" action="add_reaction.php">
                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                <button name="reaction" value="like"  class="reaction-btn">👍 <?= (int)($reactions['like']  ?? 0) ?></button>
                <button name="reaction" value="love"  class="reaction-btn">❤️ <?= (int)($reactions['love']  ?? 0) ?></button>
                <button name="reaction" value="fire"  class="reaction-btn">🔥 <?= (int)($reactions['fire']  ?? 0) ?></button>
                <button name="reaction" value="angry" class="reaction-btn">😡 <?= (int)($reactions['angry'] ?? 0) ?></button>
              </form>
            </div>

            <!-- Kommentar-Toggle -->
            <button type="button" class="toggle-comments-btn" onclick="toggleComments(this)">
              💬 Kommentare anzeigen (<?= $countComments ?>)
            </button>

            <!-- Kommentare -->
            <div class="comments">
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
          </div>
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
  <section id="about" class="cards-section surface-panel">
    <h2 class="section-title"><?= htmlspecialchars($about['title'] ?? 'Über uns') ?></h2>
    <div class="card-grid">
      <div class="card glass">
        <p><?= nl2br(htmlspecialchars($about['text'] ?? 'Hier steht bald mehr über Benny’s Werkstatt...')) ?></p>
      </div>
    </div>
  <</section>

  <!-- SERVICES -->
  <section id="services" class="cards-section surface-panel">
    <h2 class="section-title"><?= htmlspecialchars($services['title'] ?? 'Unsere Services') ?></h2>
    <div class="card-grid">
      <div class="card glass">
        <p><?= nl2br(htmlspecialchars($services['text'] ?? 'Unsere Servicebeschreibungen folgen bald...')) ?></p>
      </div>
    </div>
  </section>

  <!-- TEAM -->
  <section id="team" class="cards-section surface-panel">
    <h2 class="section-title"><?= htmlspecialchars($team['title'] ?? 'Unser Team') ?></h2>
    <div class="card-grid">
      <div class="card glass">
        <p><?= nl2br(htmlspecialchars($team['text'] ?? 'Unser Team stellt sich bald vor...')) ?></p>
      </div>
    </div>
   </section>

  <!-- GALERIE TEASER -->
  <section id="gallery-teaser" class="cards-section surface-panel">
    <h2 class="section-title">Galerie</h2>
    <div class="card-grid">
      <div class="card glass gallery-teaser-card">
        <p>Entdecke eindrucksvolle Bilder und Videos aus Benny's Werkstatt in unserer neuen Mediengalerie.</p>
        <a class="btn btn-primary gallery-btn" href="gallery.php">Zur Galerie</a>
      </div>
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
  const comments = button.nextElementSibling;
  if (!comments) return;
  const isVisible = comments.style.display === 'block';
  comments.style.display = isVisible ? 'none' : 'block';
  button.textContent = isVisible
    ? button.textContent.replace('ausblenden', 'anzeigen')
    : button.textContent.replace('anzeigen', 'ausblenden');
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
  if (!e.target.matches('.reaction-btn')) return;
  e.preventDefault();

  const btn = e.target;
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
        if (targetBtn) targetBtn.innerHTML = targetBtn.innerHTML.replace(/\d+$/, count);
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
