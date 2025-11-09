<?php
// error_reporting(E_ALL); ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

// 🔹 Prüfen, ob Datei per AJAX geladen wird (z. B. für Popups)
$isAjax = isset($_GET['ajax']);

$isLoggedIn = !empty($_SESSION['user_role']) || !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? '';

// Sicht-Filter
$sicht = $_GET['sicht'] ?? ($isLoggedIn ? 'alle' : 'oeffentlich');
$filterSql = '';
$scopeLabels = [
  'alle'        => 'Alle News',
  'intern'      => 'Interne News',
  'oeffentlich' => 'Öffentliche News',
];
$scopeDescriptions = [
  'alle'        => 'Gesamtes Archiv',
  'intern'      => 'Nur für Mitarbeitende sichtbar',
  'oeffentlich' => 'Für alle Besucher sichtbar',
];
switch ($sicht) {
  case 'intern':      $filterSql = "WHERE sichtbar_fuer = 'intern'"; break;
  case 'oeffentlich': $filterSql = "WHERE sichtbar_fuer = 'oeffentlich'"; break;
  default:            $filterSql = $isLoggedIn ? "" : "WHERE sichtbar_fuer = 'oeffentlich'";
}

// Pagination
$proSeite = 10;
$seite = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($seite - 1) * $proSeite;
$countQuery = $pdo->query("SELECT COUNT(*) FROM news $filterSql");
$gesamt = (int)$countQuery->fetchColumn();
$seitenGesamt = max(1, (int)ceil($gesamt / $proSeite));
$aktuellerScopeName = $scopeLabels[$sicht] ?? 'News';
$aktuellerScopeBeschreibung = $scopeDescriptions[$sicht] ?? '';

// News abrufen
$stmt = $pdo->prepare("SELECT * FROM news $filterSql ORDER BY erstellt_am DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $proSeite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alleNews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$anzahlAufSeite = count($alleNews);

// 🔹 Nur Header laden, wenn kein AJAX-Aufruf
if (!$isAjax):
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📚 News-Archiv | Benny’s Werkstatt</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<?php endif; ?>
<main class="inventory-page news-archive-page">
  <header class="inventory-header news-archive-header">
    <div class="news-archive-header__intro">
      <h1 class="inventory-title">📚 News &amp; Ankündigungen Archiv</h1>
      <p class="inventory-description">
        Alle Updates aus Benny’s Werkstatt – transparent, strukturiert und im selben Look wie unser Lager.
      </p>
    </div>
    <div class="news-archive-meta">
      <div class="news-archive-meta__item">
        <span class="news-archive-meta__label">Aktuelle Ansicht</span>
        <span class="news-archive-meta__value"><?= htmlspecialchars($aktuellerScopeName) ?></span>
        <?php if ($aktuellerScopeBeschreibung): ?>
          <span class="news-archive-meta__hint"><?= htmlspecialchars($aktuellerScopeBeschreibung) ?></span>
        <?php endif; ?>
      </div>
      <div class="news-archive-meta__item">
        <span class="news-archive-meta__label">Beiträge im Archiv</span>
        <span class="news-archive-meta__value"><?= $gesamt ?></span>
        <span class="news-archive-meta__hint">Gefiltert nach Auswahl</span>
      </div>
      <div class="news-archive-meta__item">
        <span class="news-archive-meta__label">Auf dieser Seite</span>
        <span class="news-archive-meta__value"><?= $anzahlAufSeite ?></span>
        <span class="news-archive-meta__hint">Seite <?= $seite ?> von <?= $seitenGesamt ?></span>
      </div>
    </div>
  </header>

  <section class="inventory-section news-archive-section">
    <div class="news-archive-tabs">
      <div class="news-archive-tabs__group">
        <a href="?sicht=oeffentlich" class="news-archive-tab <?= $sicht==='oeffentlich'?'is-active':'' ?>">🌍 Öffentliche News</a>
        <?php if ($isLoggedIn): ?>
          <a href="?sicht=intern" class="news-archive-tab <?= $sicht==='intern'?'is-active':'' ?>">🔒 Interne News</a>
          <a href="?sicht=alle" class="news-archive-tab <?= $sicht==='alle'?'is-active':'' ?>">📋 Alle anzeigen</a>
        <?php endif; ?>
      </div>
      <span class="news-archive-tabs__info">
        <?= $anzahlAufSeite ?> Einträge · <?= htmlspecialchars($aktuellerScopeName) ?>
      </span>
    </div>

    <?php if ($alleNews): ?>
      <div class="news-archive-list">
       <?php foreach ($alleNews as $n):
          $visibility = $n['sichtbar_fuer'] ?? 'oeffentlich';
          if ($visibility === 'intern' && !$isLoggedIn) continue;

          $icon = trim($n['icon'] ?? '') ?: '📰';
          $createdAt = new DateTime($n['erstellt_am']);
          $createdAtFormatted = $createdAt->format('d.m.Y H:i');
          $createdAtIso = $createdAt->format(DateTime::ATOM);
          $isInternNews = $visibility === 'intern';

          $rStmt=$pdo->prepare("SELECT reaction_type,count FROM news_reactions WHERE news_id=?");
          $rStmt->execute([$n['id']]);
          $reactions=$rStmt->fetchAll(PDO::FETCH_KEY_PAIR);

          $cStmt=$pdo->prepare("SELECT id,user_id,name,text,created_at FROM news_comments WHERE news_id=? ORDER BY created_at ASC");
          $cStmt->execute([$n['id']]);
          $countComments=$cStmt->rowCount();
        ?>
        <article class="news-card news-archive-card" id="news-<?= (int)$n['id'] ?>">
          <header class="news-card__header">
            <div class="news-card__identity">
              <span class="news-card__icon" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
              <div class="news-card__titles">
                <h3 class="news-card__title"><?= htmlspecialchars($n['titel']) ?></h3>
                <div class="news-card__meta">
                  <span class="news-card__meta-item">
                    📅 <time datetime="<?= $createdAtIso ?>"><?= $createdAtFormatted ?></time>
                  </span>
                  <span class="news-card__meta-divider" aria-hidden="true"></span>
                  <span class="news-card__meta-item" title="Kommentare">💬 <?= $countComments ?></span>
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

          <footer class="news-card__footer news-card__footer--archive">
            <form method="POST" action="add_reaction.php" class="news-card__reactions">
              <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
              <button name="reaction" value="like"  class="reaction-btn">
                <span class="reaction-emoji" aria-hidden="true">👍</span>
                <span class="reaction-count"><?= (int)($reactions['like']??0) ?></span>
              </button>
              <button name="reaction" value="love"  class="reaction-btn">
                <span class="reaction-emoji" aria-hidden="true">❤️</span>
                <span class="reaction-count"><?= (int)($reactions['love']??0) ?></span>
              </button>
              <button name="reaction" value="fire"  class="reaction-btn">
                <span class="reaction-emoji" aria-hidden="true">🔥</span>
                <span class="reaction-count"><?= (int)($reactions['fire']??0) ?></span>
              </button>
              <button name="reaction" value="angry" class="reaction-btn">
                <span class="reaction-emoji" aria-hidden="true">😡</span>
                <span class="reaction-count"><?= (int)($reactions['angry']??0) ?></span>
              </button>
            </form>

            <button
              type="button"
              class="toggle-comments-btn"
              data-open-text="💬 Kommentare anzeigen (<?= $countComments ?>)"
              data-close-text="🙈 Kommentare ausblenden"
              aria-expanded="false"
              aria-controls="archive-comments-<?= (int)$n['id'] ?>"
              onclick="toggleComments(this)"
            >
              💬 Kommentare anzeigen (<?= $countComments ?>)
            </button>
          </footer>

          <div class="comments" id="archive-comments-<?= (int)$n['id'] ?>" hidden>
            <h4>Kommentare</h4>
            <?php if ($countComments>0): while($c=$cStmt->fetch(PDO::FETCH_ASSOC)): ?>
              <div class="comment">
                <div class="comment-header">
                  <strong><?= htmlspecialchars($c['name']) ?></strong>
                  <span class="comment-date"><?= date('d.m.Y H:i',strtotime($c['created_at'])) ?></span>
                </div>
                <p><?= nl2br(htmlspecialchars($c['text'])) ?></p>
                <?php if(($isLoggedIn&&($c['user_id']??null)==$userId)||$userRole==='admin'): ?>
                  <button type="button" class="edit-btn" onclick="toggleEditForm(this)">✏️ Bearbeiten</button>
                  <form method="POST" action="edit_comment.php" class="edit-comment-form">
                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                    <textarea name="new_text" rows="2" required><?= htmlspecialchars($c['text']) ?></textarea>
                    <button type="submit">💾 Speichern</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endwhile; else: ?>
              <p class="no-comments">Noch keine Kommentare.</p>
            <?php endif; ?>

            <?php if ($n['sichtbar_fuer']==='oeffentlich'||$isLoggedIn): ?>
              <form method="POST" action="add_comment.php" class="comment-form">
                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                <?php if(!$isLoggedIn): ?><input type="text" name="name" placeholder="Dein Name" required><?php endif; ?>
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
    <?php else: ?>
      <div class="news-archive-empty">
        <p>Keine News in dieser Kategorie vorhanden.</p>
      </div>
    <?php endif; ?>

    <?php if ($seitenGesamt>1): ?>
      <nav class="news-archive-pagination" aria-label="News Seiten">
        <?php for($i=1;$i<=$seitenGesamt;$i++): ?>
          <a href="?sicht=<?= htmlspecialchars($sicht) ?>&amp;page=<?= $i ?>" class="news-archive-page-link <?= $i===$seite?'is-active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>

    <div class="news-archive-back">
      <a href="index.php#news" class="btn btn-ghost">← Zurück zu den aktuellen News</a>
    </div>
  </section>
</main>

<script>
function toggleComments(button){
  const card=button.closest('.news-card');
  if(!card)return;

  const targetId=button.getAttribute('aria-controls');
  const comments=targetId?card.querySelector(`#${targetId}`):card.querySelector('.comments');
  if(!comments)return;

  const isHidden=comments.hasAttribute('hidden');
  if(isHidden){
    comments.removeAttribute('hidden');
    comments.classList.add('is-visible');
  }else{
    comments.setAttribute('hidden','');
    comments.classList.remove('is-visible');
  }

  const openText=button.dataset.openText||button.textContent;
  const closeText=button.dataset.closeText||button.textContent;
  const expanded=comments.hasAttribute('hidden')?'false':'true';
  button.innerHTML=expanded==='true'?closeText:openText;
  button.setAttribute('aria-expanded',expanded);
}
function toggleEditForm(button){
  const form=button.nextElementSibling;
  if(!form)return;
  const isVisible=form.style.display==='block';
  form.style.display=isVisible?'none':'block';
  button.textContent=isVisible?'✏️ Bearbeiten':'❌ Abbrechen';
}

// --- Reaktionen per AJAX ---
document.addEventListener('click',async e=>{
  const btn=e.target.closest('.reaction-btn');
  if(!btn)return;
  e.preventDefault();
  const form=btn.closest('form');
  const newsId=form.querySelector('input[name="news_id"]').value;
  const reaction=btn.value;
  try{
    const res=await fetch('add_reaction.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`news_id=${encodeURIComponent(newsId)}&reaction=${encodeURIComponent(reaction)}`
    });
    const data=await res.json();
    if(data.status==='success'){
      for(const [type,count]of Object.entries(data.reactions)){
        const targetBtn=form.querySelector(`button[value="${type}"]`);
        if(!targetBtn)continue;
        const countEl=targetBtn.querySelector('.reaction-count');
        if(countEl)countEl.textContent=count;
      }
    }
  }catch(err){console.error('Fehler bei Reaktion:',err);}
});
</script>

<?php if(!$isAjax): ?>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
</body>
</html>
<?php endif; ?>
