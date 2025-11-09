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

$postCount = count($posts);
$replyCount = max($postCount - 1, 0);
$lastPostAt = $postCount ? $posts[$postCount - 1]['created_at'] : null;
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
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="inventory-page forum-page">
  <header class="inventory-header forum-header">
    <div>
      <h1 class="inventory-title"><?= $thread['room_icon'] . ' ' . h($thread['title']) ?></h1>
      <p class="inventory-description">Thema im Bereich „<?= h($thread['room_title']) ?>“</p>
    </div>
    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Beiträge</span>
        <span class="inventory-metric__value"><?= $postCount ?></span>
        <span class="inventory-metric__hint">inkl. Startpost</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Antworten</span>
        <span class="inventory-metric__value"><?= $replyCount ?></span>
        <span class="inventory-metric__hint">Teamreaktionen</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Letzte Aktivität</span>
        <span class="inventory-metric__value"><?= $lastPostAt ? date('d.m.Y', strtotime($lastPostAt)) : '—' ?></span>
        <span class="inventory-metric__hint"><?= $lastPostAt ? date('H:i \U\h\r', strtotime($lastPostAt)) : 'Noch keine Antworten' ?></span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Gestartet</span>
        <span class="inventory-metric__value"><?= date('d.m.Y', strtotime($thread['created_at'])) ?></span>
        <span class="inventory-metric__hint"><?= date('H:i \U\h\r', strtotime($thread['created_at'])) ?></span>
      </div>
    </div>
  </header>

  <section class="inventory-section forum-section">
    <div>
      <h2 class="forum-section__title">Beitragsverlauf</h2>
      <p class="inventory-section__intro">Alle Nachrichten werden in zeitlicher Reihenfolge angezeigt.</p>
    </div>

<div class="forum-post-list">
      <?php foreach ($posts as $p):
        $bild_url = $p['bild_url'] ?? '';

    if (!empty($bild_url)) {
          if ($bild_url[0] !== '/') {
              $bild_url = '/' . $bild_url;
          }

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
      <article class="forum-post">
        <img class="forum-post__avatar" src="<?= h($avatar) ?>" alt="Avatar von <?= h($p['name'] ?? 'Unbekannt') ?>">
        <div class="forum-post__body">
          <header class="forum-post__header">
            <div class="forum-post__title">
              <span class="forum-post__author"><?= h($p['name'] ?? 'Unbekannt') ?></span>
              <?php if ($p['rang']): ?><span class="badge"><?= h($p['rang']) ?></span><?php endif; ?>
            </div>
            <time class="forum-post__time" datetime="<?= date('c', strtotime($p['created_at'])) ?>">
              <?= date('d.m.Y H:i \U\h\r', strtotime($p['created_at'])) ?>
            </time>
          </header>

      <div class="forum-post__content" id="content_<?= $p['id'] ?>">
            <?= nl2br(h($p['content'])) ?>
          </div>

        <?php if ($canEdit): ?>
          <div class="forum-post__actions">
            <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" onclick="toggleEdit(<?= $p['id'] ?>)">Bearbeiten</button>
            <form method="post">
              <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
              <button class="inventory-submit inventory-submit--danger inventory-submit--small" name="delete_post">Löschen</button>
            </form>
          </div>

        <form method="post" class="forum-edit-form" id="edit_<?= $p['id'] ?>">
            <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
            <div class="input-control">
              <label class="sr-only" for="edit_content_<?= $p['id'] ?>">Beitrag bearbeiten</label>
              <textarea id="edit_content_<?= $p['id'] ?>" name="content" rows="4" class="input-field"><?= h($p['content']) ?></textarea>
            </div>
            <div class="forum-emoji-toolbar">
              <button type="button" class="forum-emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
              <div class="forum-emoji-picker">
                <?php foreach($emoji_list as $em): ?>
                  <span class="forum-emoji" onclick="addEmojiTo('#edit_content_<?= $p['id'] ?>', '<?= $em ?>')"><?= $em ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-actions">
              <button class="inventory-submit inventory-submit--small" name="save_edit">Speichern</button>
            </div>
          </form>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="inventory-section forum-section forum-reply">
    <div>
      <h2 class="forum-section__title">Antwort verfassen</h2>
      <p class="inventory-section__intro">Formuliere deine Rückmeldung oder ergänze weitere Informationen.</p>
    </div>

  <form method="post" class="inventory-form forum-form">
      <div class="input-control">
        <label for="reply_content">Antwort</label>
        <textarea id="reply_content" name="content" rows="4" class="input-field" placeholder="Antwort schreiben..." required></textarea>
      </div>
      <div class="forum-emoji-toolbar">
        <button type="button" class="forum-emoji-toggle" onclick="togglePicker(this)">😊 Emojis ▼</button>
        <div class="forum-emoji-picker">
          <?php foreach($emoji_list as $em): ?>
            <span class="forum-emoji" onclick="addEmojiTo('#reply_content', '<?= $em ?>')"><?= $em ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-actions">
        <button name="reply" class="inventory-submit">Antwort posten</button>
      </div>
    </form>
    </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>

  <div class="footer-buttons">
    <a href="/forum_room.php?id=<?= $thread['room_id'] ?>" class="footer-btn">← Zurück</a>
    <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
  </div>
</footer>

<script>
function toggleEdit(id){
  const form = document.getElementById('edit_'+id);
  const content = document.getElementById('content_'+id);
  if (form) { form.classList.toggle('is-open'); }
  if (content) { content.classList.toggle('is-hidden'); }
}
function togglePicker(btn){
  const box = btn.nextElementSibling;
  if (!box) return;
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
window.addEventListener('click',e=>{
  if(!e.target.closest('.forum-emoji-toggle') && !e.target.closest('.forum-emoji-picker')){
    document.querySelectorAll('.forum-emoji-picker').forEach(b=>b.style.display='none');
  }
});
</script>

<script src="/script.js"></script>
</body>
</html>
