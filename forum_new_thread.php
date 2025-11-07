<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/includes/forum_helpers.php';
forum_require_login();
$me = forum_get_user_summary($pdo, (int)$_SESSION['user_id']); // enthält mitarbeiter_id (mid)

$room_id = (int)($_GET['room'] ?? 0);
$stmt = $pdo->prepare("SELECT id, title, icon FROM forum_rooms WHERE id=?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) { die("Raum nicht gefunden."); }
if (!forum_can_write($pdo, $me, $room_id)) { die("Keine Schreibrechte in diesem Raum."); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? '');
    $content = trim($_POST["content"] ?? '');
    if ($title !== "" && $content !== "" && !empty($me['mid'])) {
        $pdo->beginTransaction();
        // Autor = mitarbeiter.id
        $stmt = $pdo->prepare("INSERT INTO forum_threads (room_id, title, author_id) VALUES (?, ?, ?)");
        $stmt->execute([$room_id, $title, (int)$me['mid']]);
        $thread_id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO forum_posts (thread_id, author_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$thread_id, (int)$me['mid'], $content]);
        $pdo->commit();
        header("Location: /forum_thread.php?id=".$thread_id);
        exit;
    }
}
?>

<?php
// Deine Emoji-Liste unverändert
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
<title>Neues Thema</title>
<link rel="stylesheet" href="/header.css">
<link rel="stylesheet" href="/styles.css">
<link rel="stylesheet" href="/forum.css">
<style>
/* deine vorhandenen Klassen bleiben; nur ein kleiner Abstand unter dem Picker */
.emoji-picker{margin-top:8px;}
.emoji-toggle{margin-top:6px;background:#262626;border:1px solid rgba(57,255,20,.35);padding:6px 10px;border-radius:8px;color:#fff;cursor:pointer}
.emoji-btn{font-size:22px;cursor:pointer;padding:2px 4px}
.emoji-btn:hover{background:rgba(57,255,20,.2);border-radius:6px}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<div class="forum-wrap">
  <div class="forum-card">
    <h2 class="forum-title"><?= h(($room['icon'] ?? '').' Neues Thema in „'.($room['title'] ?? '').'“') ?></h2>
  </div>

  <form method="post" class="forum-form">

    <!-- TITEL -->
    <label style="display:flex;align-items:center;gap:10px;">
      Titel:
      <button type="button" class="emoji-toggle" style="margin:0" onclick="togglePickerById('picker-title')">😊 Emojis ▼</button>
    </label>
    <!-- Picker direkt UNTER dem Button, ÜBER dem Feld -->
    <div id="picker-title" class="emoji-picker" style="display:none;flex-wrap:wrap;gap:6px;padding:8px;background:#1a1a1a;border:1px solid rgba(57,255,20,.35);border-radius:10px;">
      <?php foreach($emoji_list as $em): ?>
        <span class="emoji-btn" onclick="addEmojiTo('input[name=title]', '<?= $em ?>')"><?= $em ?></span>
      <?php endforeach; ?>
    </div>
    <!-- Titel-Feld -->
    <input type="text" name="title" class="forum-input" placeholder="Kurzer, klarer Betreff…" required>

    <!-- BEITRAG -->
    <label style="display:flex;align-items:center;gap:10px;margin-top:12px;">
      Beitrag:
      <button type="button" class="emoji-toggle" style="margin:0" onclick="togglePickerById('picker-content')">😊 Emojis ▼</button>
    </label>
    <!-- Picker direkt UNTER dem Button, ÜBER dem Feld -->
    <div id="picker-content" class="emoji-picker" style="display:none;flex-wrap:wrap;gap:6px;padding:8px;background:#1a1a1a;border:1px solid rgba(57,255,20,.35);border-radius:10px;">
      <?php foreach($emoji_list as $em): ?>
        <span class="emoji-btn" onclick="addEmojiTo('textarea[name=content]', '<?= $em ?>')"><?= $em ?></span>
      <?php endforeach; ?>
    </div>
    <!-- Textarea -->
    <textarea name="content" rows="6" class="forum-input" required></textarea>

    <button class="button-main" style="margin-top:12px;">Thema erstellen</button>
  </form>
</div>

<script>
function togglePickerById(id){
  const box = document.getElementById(id);
  box.style.display = (box.style.display==='none' || !box.style.display) ? 'flex' : 'none';
}
function addEmojiTo(selector, e){
  const el = document.querySelector(selector);
  if(!el) return;
  const s = el.selectionStart ?? el.value.length;
  const t = el.selectionEnd ?? el.value.length;
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
    <a href="/forum_room.php?id=<?= $room_id ?>" class="footer-btn">← Zurück</a>
    <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
  </div>
</footer>



<script src="/script.js"></script>
</body>
</html>
