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

// News abrufen
$stmt = $pdo->prepare("SELECT * FROM news $filterSql ORDER BY erstellt_am DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $proSeite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alleNews = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<style>
main { max-width:1100px; margin:120px auto; padding:0 20px; }
.tabs { display:flex; gap:10px; justify-content:center; margin-bottom:30px; flex-wrap:wrap; }
.tabs a {
  border:2px solid #39ff14; border-radius:8px; padding:10px 20px;
  color:#39ff14; text-decoration:none; font-weight:bold;
  box-shadow:0 0 10px rgba(57,255,20,0.4); transition:all .3s ease;
}
.tabs a:hover { background:linear-gradient(90deg,#39ff14,#76ff65); color:#fff; }
.tabs .active { background:linear-gradient(90deg,#39ff14,#76ff65); color:#fff; }

.news-card {
  background:rgba(20,20,20,0.6);
  border:1px solid rgba(57,255,20,0.3);
  border-radius:15px;
  padding:20px;
  margin-bottom:25px;
  box-shadow:0 0 15px rgba(57,255,20,0.2);
}
.news-card h3 { display:flex; align-items:center; gap:10px; margin-top:0; color:#fff; }
.news-card .intern-label { font-size:0.9rem; color:#76ff65; text-shadow:0 0 8px #39ff14; }
.news-date { font-size:0.9rem; color:#bbb; }
.news-text { margin:15px 0; color:#eee; line-height:1.5; }

.reactions {
  display:flex; gap:10px; align-items:center; flex-wrap:wrap;
  margin-top:15px; border-top:1px solid rgba(57,255,20,0.3); padding-top:10px;
}
.reaction-btn {
  background:rgba(57,255,20,0.1);
  border:1px solid rgba(57,255,20,0.4);
  color:#a8ffba;
  border-radius:8px;
  padding:6px 10px;
  cursor:pointer;
  transition:all 0.25s ease;
  font-size:1rem;
}
.reaction-btn:hover {
  background:linear-gradient(90deg,#39ff14,#76ff65);
  color:#fff;
  transform:scale(1.05);
}

.comments { margin-top:25px; border-top:1px solid rgba(57,255,20,0.3); padding-top:15px; display:none; }
.comment {
  background:rgba(20,20,20,0.7);
  border:1px solid rgba(57,255,20,0.25);
  border-radius:10px;
  padding:10px 14px;
  margin-bottom:10px;
  box-shadow:inset 0 0 10px rgba(57,255,20,0.1);
}
.comment strong { color:#76ff65; margin-right:8px; }
.comment-date { font-size:0.8rem; color:#aaa; }

.comment-form textarea, .comment-form input {
  width:100%; background:rgba(25,25,25,0.9); color:#fff;
  border:1px solid rgba(57,255,20,0.3); border-radius:6px;
  padding:8px; margin-bottom:8px;
}

.edit-btn {
  background:none; border:none; color:#76ff65;
  cursor:pointer; font-size:0.9rem; margin-top:4px;
  transition:all 0.2s ease;
}
.edit-btn:hover { text-decoration:underline; color:#a8ffba; }

.edit-comment-form { display:none; margin-top:8px; }
.edit-comment-form textarea {
  width:100%; background:rgba(30,30,30,0.9);
  border:1px solid rgba(57,255,20,0.3); color:#fff;
  border-radius:6px; padding:6px;
}
.edit-comment-form button {
  margin-top:4px; background:linear-gradient(90deg,#39ff14,#76ff65);
  border:none; border-radius:6px; color:#fff; padding:5px 10px; cursor:pointer;
}
.edit-comment-form button:hover { box-shadow:0 0 10px rgba(57,255,20,0.8); }

.toggle-comments-btn {
  background:rgba(57,255,20,0.1);
  border:1px solid rgba(57,255,20,0.4);
  color:#a8ffba;
  border-radius:8px;
  padding:6px 12px;
  cursor:pointer;
  margin-top:15px;
  transition:all 0.25s ease;
}
.toggle-comments-btn:hover {
  background:linear-gradient(90deg,#39ff14,#76ff65);
  color:#fff;
  transform:scale(1.05);
}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<?php endif; ?>
<main>
  <h2 class="section-title">📚 News & Ankündigungen Archiv</h2>

  <div class="tabs">
    <a href="?sicht=oeffentlich" class="<?= $sicht==='oeffentlich'?'active':'' ?>">🌍 Öffentliche News</a>
    <?php if ($isLoggedIn): ?>
      <a href="?sicht=intern" class="<?= $sicht==='intern'?'active':'' ?>">🔒 Interne News</a>
      <a href="?sicht=alle" class="<?= $sicht==='alle'?'active':'' ?>">📋 Alle anzeigen</a>
    <?php endif; ?>
  </div>

  <?php if ($alleNews): ?>
  <div class="card-grid news-list">
  <?php foreach ($alleNews as $n): if ($n['sichtbar_fuer']==='intern' && !$isLoggedIn) continue; ?>
    <div class="card glass news-card" id="news-<?= (int)$n['id'] ?>">
      <h3><?= htmlspecialchars($n['icon'] ?: '📰') ?> <?= htmlspecialchars($n['titel']) ?></h3>
      <span class="news-date">📅 <?= date('d.m.Y H:i', strtotime($n['erstellt_am'])) ?></span>
      <div class="news-text"><?= $n['text'] ?></div>

      <?php
        $rStmt=$pdo->prepare("SELECT reaction_type,count FROM news_reactions WHERE news_id=?");
        $rStmt->execute([$n['id']]);
        $reactions=$rStmt->fetchAll(PDO::FETCH_KEY_PAIR);
      ?>
      <div class="reactions">
        <form method="POST" action="add_reaction.php">
          <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
          <button name="reaction" value="like"  class="reaction-btn">👍 <?= (int)($reactions['like']??0) ?></button>
          <button name="reaction" value="love"  class="reaction-btn">❤️ <?= (int)($reactions['love']??0) ?></button>
          <button name="reaction" value="fire"  class="reaction-btn">🔥 <?= (int)($reactions['fire']??0) ?></button>
          <button name="reaction" value="angry" class="reaction-btn">😡 <?= (int)($reactions['angry']??0) ?></button>
        </form>
      </div>

      <?php
        $cStmt=$pdo->prepare("SELECT id,user_id,name,text,created_at FROM news_comments WHERE news_id=? ORDER BY created_at ASC");
        $cStmt->execute([$n['id']]);
        $countComments=$cStmt->rowCount();
      ?>
      <button type="button" class="toggle-comments-btn" onclick="toggleComments(this)">💬 Kommentare anzeigen (<?= $countComments ?>)</button>

      <div class="comments">
        <h4>Kommentare</h4>
        <?php if ($countComments>0): while($c=$cStmt->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="comment">
          <strong><?= htmlspecialchars($c['name']) ?></strong>
          <span class="comment-date"><?= date('d.m.Y H:i',strtotime($c['created_at'])) ?></span>
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
    </div>
  <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="card glass"><p>Keine News in dieser Kategorie vorhanden.</p></div>
  <?php endif; ?>

  <?php if ($seitenGesamt>1): ?>
  <div class="pagination">
    <?php for($i=1;$i<=$seitenGesamt;$i++): ?>
      <a href="?sicht=<?= htmlspecialchars($sicht) ?>&page=<?= $i ?>" class="<?= $i===$seite?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <div style="margin-top:40px;text-align:center;">
    <a href="index.php#news" class="btn btn-ghost">← Zurück zu den aktuellen News</a>
  </div>
</main>

<script>
function toggleComments(button){
  const comments=button.nextElementSibling;
  if(!comments)return;
  const isVisible=comments.style.display==='block';
  comments.style.display=isVisible?'none':'block';
  button.textContent=isVisible
    ?button.textContent.replace('ausblenden','anzeigen')
    :button.textContent.replace('anzeigen','ausblenden');
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
  if(!e.target.matches('.reaction-btn'))return;
  e.preventDefault();
  const btn=e.target;
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
        if(targetBtn)targetBtn.innerHTML=targetBtn.innerHTML.replace(/\d+$/,count);
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
