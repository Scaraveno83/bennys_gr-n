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

$threadCountStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_threads WHERE room_id = ?");
$threadCountStmt->execute([$room_id]);
$threadCount = (int)$threadCountStmt->fetchColumn();

$activityStmt = $pdo->prepare("SELECT MAX(p.created_at) FROM forum_posts p JOIN forum_threads t ON t.id = p.thread_id WHERE t.room_id = ?");
$activityStmt->execute([$room_id]);
$latestActivity = $activityStmt->fetchColumn();

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
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="inventory-page forum-page">
  <header class="inventory-header forum-header">
    <div>
      <h1 class="inventory-title"><?= h(($room['icon'] ?? '') . ' Neues Thema in „' . ($room['title'] ?? '') . '“') ?></h1>
      <p class="inventory-description">Starte eine neue Diskussion, um Aufgaben, Einsätze oder Ideen für diesen Bereich festzuhalten.</p>
    </div>
   <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Bestehende Themen</span>
        <span class="inventory-metric__value"><?= $threadCount ?></span>
        <span class="inventory-metric__hint">im ausgewählten Raum</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Letzte Aktivität</span>
        <span class="inventory-metric__value"><?= $latestActivity ? date('d.m.Y', strtotime($latestActivity)) : '—' ?></span>
        <span class="inventory-metric__hint"><?= $latestActivity ? date('H:i \U\h\r', strtotime($latestActivity)) : 'Noch keine Beiträge' ?></span>
      </div>
    </div>
    </header>

  <section class="inventory-section forum-section">
    <div>
      <h2 class="forum-section__title">Thread anlegen</h2>
      <p class="inventory-section__intro">Wähle einen prägnanten Titel und beschreibe dein Anliegen ausführlich, damit das Team schnell reagieren kann.</p>
    </div>

    <form method="post" class="inventory-form forum-form">
      <div class="input-control">
        <label for="thread_title">Titel</label>
        <input type="text" id="thread_title" name="title" class="input-field" placeholder="Kurzer, klarer Betreff…" required>
        <div class="forum-emoji-toolbar">
          <button type="button" class="forum-emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
          <div class="forum-emoji-picker">
            <?php foreach($emoji_list as $em): ?>
              <span class="forum-emoji" onclick="addEmojiTo('#thread_title', '<?= $em ?>')"><?= $em ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

   <div class="input-control">
        <label for="thread_content">Beitrag</label>
        <textarea id="thread_content" name="content" rows="6" class="input-field" placeholder="Beschreibe dein Anliegen oder deine Frage…" required></textarea>
        <div class="forum-emoji-toolbar">
          <button type="button" class="forum-emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
          <div class="forum-emoji-picker">
            <?php foreach($emoji_list as $em): ?>
              <span class="forum-emoji" onclick="addEmojiTo('#thread_content', '<?= $em ?>')"><?= $em ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button class="inventory-submit">Thema erstellen</button>
      </div>
    </form>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>

  <div class="footer-buttons">
    <a href="/forum_room.php?id=<?= $room_id ?>" class="footer-btn">← Zurück</a>
    <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
  </div>
</footer>

<script>
function togglePicker(btn){
  const box = btn.nextElementSibling;
  if (!box) return;
  box.style.display = (box.style.display==='none'||!box.style.display) ? 'flex' : 'none';
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
window.addEventListener('click',e=>{
  if(!e.target.closest('.forum-emoji-toggle') && !e.target.closest('.forum-emoji-picker')){
    document.querySelectorAll('.forum-emoji-picker').forEach(b=>b.style.display='none');
  }
});
</script>


<script src="/script.js"></script>
</body>
</html>
