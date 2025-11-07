<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/includes/forum_helpers.php';
forum_require_login();
$me = forum_get_user_summary($pdo, (int)$_SESSION['user_id']);

$thread_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT t.id, t.title, t.created_at, t.room_id, 
                              r.title AS room_title, r.icon AS room_icon
                       FROM forum_threads t
                       JOIN forum_rooms r ON r.id = t.room_id
                       WHERE t.id=?");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$thread) { die('Thema nicht gefunden'); }

// Antwort
if (isset($_POST['reply'])) {
    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
        $pdo->prepare("INSERT INTO forum_posts (thread_id, author_id, content) VALUES (?, ?, ?)")
            ->execute([$thread_id, $me['mid'], $content]);
    }
    header("Location: forum_thread.php?id=".$thread_id); exit;
}

// Speichern Bearbeitung
if (isset($_POST['save_edit'])) {
    $pid = (int)$_POST['post_id'];
    $content = trim($_POST['content'] ?? '');

    $p = $pdo->prepare("SELECT author_id FROM forum_posts WHERE id=?");
    $p->execute([$pid]);
    $post = $p->fetch(PDO::FETCH_ASSOC);

    if ($post && ($post['author_id'] == $me['mid'] || forum_is_admin($me))) {
        $pdo->prepare("UPDATE forum_posts SET content=? WHERE id=?")
            ->execute([$content, $pid]);
    }
    header("Location: forum_thread.php?id=".$thread_id); exit;
}

// Löschen
if (isset($_POST['delete_post'])) {
    $pid = (int)$_POST['post_id'];
    $p = $pdo->prepare("SELECT author_id FROM forum_posts WHERE id=?");
    $p->execute([$pid]);
    $post = $p->fetch(PDO::FETCH_ASSOC);

    if ($post && ($post['author_id'] == $me['mid'] || forum_is_admin($me))) {
        $pdo->prepare("DELETE FROM forum_posts WHERE id=?")->execute([$pid]);
    }
    header("Location: forum_thread.php?id=".$thread_id); exit;
}

// Beiträge laden
$q = $pdo->prepare("SELECT p.id, p.author_id, p.content, p.created_at,
                           m.name, m.rang, m.bild_url, m.id AS mid
                    FROM forum_posts p
                    LEFT JOIN mitarbeiter m ON m.id = p.author_id
                    WHERE p.thread_id=?
                    ORDER BY p.created_at ASC");
$q->execute([$thread_id]);
$posts = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
// DEINE LISTE – unverändert lassen
$emoji_list = [
"😀","😁","😂","🤣","😃","😄","😅","😆","😉","😊","😍","😘","😗","😙","😚","🙂","🤗","🤩","😎",
"😏","😒","😞","😔","😟","😕","🙁","☹️","😭","😢","😤","😠","😡","🤬","🤯","😳","🥵","🥶",
"😱","😨","😰","😥","😓","🤔","🤨","😐","😑","😶","🙄","😬","😮","😯","😲","😴","🤤",
"😪","😵","🤐","🥴","🤢","🤮","🤧","😷","🤒","🤕",
"👍","👎","👌","✌️","🤞","🤟","🤘","🤙","👏","🙌","🙏","🤝","💪","🫶",
"❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❤️‍🔥","❤️‍🩹","💕","💞","💓","💗","💖","💘","💝",
"🔥","✨","⭐","🌟","💫","⚡","💥","🌈","❄️","💧","💦","☔","☀️","🌙",
"🎉","🎊","🎈","🥳","🍻","🍺","🍷","🥃","🍾","🍔","🍟","🌭","🍕","🍗","🥩",
"🚗","🏎️","🛠️","🔧","🔩","⚙️","🧰","🛞","🛢️","⛽","💨","🔧","🚦","🏁",
"👀","👁️","🧠","💀","☠️","🤡","👻","👽","🤖","😺","😸","😹","😻","😼","😽",
"📝","📌","📍","📎","📢","📣","💬","🗨️","🗯️","🔊","🔒","🔓"
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= h($thread['title']) ?></title>
<link rel="stylesheet" href="/header.css">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/forum.css">
<style>
/* Nur Dropdown-UI, Rest bleibt unberührt */
.emoji-toggle{margin-top:6px;background:#262626;border:1px solid rgba(57,255,20,.35);padding:6px 10px;border-radius:8px;color:#fff;cursor:pointer}
.emoji-picker{margin-top:8px;padding:8px;background:#1a1a1a;border:1px solid rgba(57,255,20,.35);border-radius:10px;max-height:160px;overflow-y:auto;display:none;flex-wrap:wrap;gap:6px}
.emoji-btn{font-size:22px;cursor:pointer;padding:2px 4px}
.emoji-btn:hover{background:rgba(57,255,20,.2);border-radius:6px}
.edit-box { display:none; margin-top:10px; }
.show { display:block!important; }
.post-buttons { margin-top:8px; display:flex; gap:10px; }
.post-content { margin:6px 0 10px 0; }
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<div class="forum-wrap">
  <div class="forum-card">
    <h2><?= $thread['room_icon'].' '.h($thread['title']) ?></h2>
    <p class="forum-muted"><?= h($thread['room_title']) ?></p>
  </div>

  <?php foreach($posts as $p):
    $bild_url = $p['bild_url'] ?? '';

if (!empty($bild_url)) {
    // Wenn kein Slash vorne → ergänzen
    if ($bild_url[0] !== '/') {
        $bild_url = '/' . $bild_url;
    }

    // Absoluten Pfad prüfen
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $bild_url;

    if (file_exists($serverPath)) {
        $avatar = $bild_url;
    } else {
        $avatar = '/pics/default-avatar.png';
    }
} else {
    $avatar = '/pics/default-avatar.png';
}

$erlaubteRollen = [
    'Geschäftsführung',
    'Stv. Geschäftsleitung',
    'Personalleitung',
    'Administrator'
];

$canEdit = (
    $p['author_id'] == $me['mid'] 
    || !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'
    || in_array($me['rang'], $erlaubteRollen)
);

  ?>
  <div class="post">
    <img class="avatar" src="<?= h($avatar) ?>">
    <div class="post-body">
      <strong><?= h($p['name']) ?></strong>
      <?php if ($p['rang']): ?><span class="badge"><?= h($p['rang']) ?></span><?php endif; ?>
      <div class="forum-muted"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></div>

      <div class="post-content" id="content_<?= $p['id'] ?>">
        <?= nl2br(h($p['content'])) ?>
      </div>

      <?php if ($canEdit): ?>
      <div class="post-buttons">
        <button class="btn-danger" onclick="toggleEdit(<?= $p['id'] ?>)">Bearbeiten</button>
        <form method="post">
          <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
          <button class="btn-danger" name="delete_post">Löschen</button>
        </form>
      </div>

      <form method="post" class="edit-box" id="edit_<?= $p['id'] ?>">
        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
        <textarea name="content" rows="4" class="forum-input"><?= h($p['content']) ?></textarea>

        <!-- DROPDOWN -->
        <button type="button" class="emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
        <div class="emoji-picker">
          <?php foreach($emoji_list as $em): ?>
            <span class="emoji-btn" onclick="addEmojiTo('#edit_<?= $p['id'] ?> textarea[name=content]', '<?= $em ?>')"><?= $em ?></span>
          <?php endforeach; ?>
        </div>

        <button class="button-main" name="save_edit" style="margin-top:8px;">Speichern</button>
      </form>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; ?>

  <div class="forum-card" style="margin-top:16px;">
    <form method="post">
      <textarea name="content" rows="4" class="forum-input" placeholder="Antwort schreiben..." required></textarea>

      <!-- DROPDOWN -->
      <button type="button" class="emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
      <div class="emoji-picker">
        <?php foreach($emoji_list as $em): ?>
          <span class="emoji-btn" onclick="addEmojiTo('textarea[name=content]', '<?= $em ?>')"><?= $em ?></span>
        <?php endforeach; ?>
      </div>

      <button name="reply" class="button-main" style="margin-top:8px;">Antwort posten</button>
    </form>
  </div>
</div>

<script>
function toggleEdit(id){
  document.getElementById("edit_"+id).classList.toggle("show");
  document.getElementById("content_"+id).classList.toggle("show");
}
function togglePicker(btn){
  const box = btn.nextElementSibling;
  box.style.display = (box.style.display==='none'||!box.style.display) ? 'flex' : 'none';
}
function addEmojiTo(selector, e){
  const el = document.querySelector(selector);
  if(!el) return;
  const s = el.selectionStart ?? el.value.length, t = el.selectionEnd ?? el.value.length;
  el.value = el.value.slice(0,s) + e + el.value.slice(t);
  el.selectionStart = el.selectionEnd = s + e.length;
  el.focus();
}
document.addEventListener('click',e=>{
  if(!e.target.closest('.emoji-toggle') && !e.target.closest('.emoji-picker')){
    document.querySelectorAll('.emoji-picker').forEach(b=>b.style.display='none');
  }
});
</script>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>

  <div class="footer-buttons">
    <a href="/forum_room.php?id=<?= $thread['room_id'] ?>" class="footer-btn">← Zurück</a>
    <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
  </div>
</footer>


<script src="/script.js"></script>
</body>
</html>
