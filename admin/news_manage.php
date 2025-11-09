<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

// === NEWS HINZUFÜGEN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_news'])) {
    $titel = trim($_POST['titel']);
    $icon  = trim($_POST['icon']);
    $text  = trim($_POST['text']);
    $sichtbar = $_POST['sichtbar_fuer'] ?? 'oeffentlich';

    if ($titel && $text) {
        $stmt = $pdo->prepare("INSERT INTO news (titel, icon, text, sichtbar_fuer, erstellt_am) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$titel, $icon, $text, $sichtbar]);
        header("Location: news_manage.php?success=1");
        exit;
    }
}

// === NEWS AKTUALISIEREN ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_news'])) {
    $id = (int)$_POST['news_id'];
    $titel = trim($_POST['edit_titel']);
    $icon  = trim($_POST['edit_icon']);
    $text  = trim($_POST['edit_text']);
    $sichtbar = $_POST['edit_sichtbar_fuer'] ?? 'oeffentlich';

    if ($id && $titel && $text) {
        $stmt = $pdo->prepare("UPDATE news SET titel = ?, icon = ?, text = ?, sichtbar_fuer = ? WHERE id = ?");
        $stmt->execute([$titel, $icon, $text, $sichtbar, $id]);
        header("Location: news_manage.php?updated=1");
        exit;
    }
}

// === NEWS LÖSCHEN ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([$id]);
    header("Location: news_manage.php?deleted=1");
    exit;
}

// === NEWS LADEN ===
$stmt = $pdo->query("SELECT * FROM news ORDER BY erstellt_am DESC");
$newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalNews = count($newsList);
$publicNews = 0;
foreach ($newsList as $entry) {
    if (($entry['sichtbar_fuer'] ?? '') === 'oeffentlich') {
        $publicNews++;
    }
}
$internalNews = $totalNews - $publicNews;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📰 News-Verwaltung | Admin</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page news-manage-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📰 News &amp; Ankündigungen verwalten</h1>
    <p class="inventory-description">
      Pflege die Startseiten-News im selben Look &amp; Feel wie unsere Lagerübersichten.
    </p>
    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesamt</span>
        <span class="inventory-metric__value"><?= number_format($totalNews, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Einträge im System</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Öffentlich</span>
        <span class="inventory-metric__value"><?= number_format($publicNews, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Für alle sichtbar</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Intern</span>
        <span class="inventory-metric__value"><?= number_format($internalNews, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Nur Team-Mitglieder</span>
      </article>
    </div>
  </header>

  <section class="inventory-section news-create">
    <div>
      <h2>Neue News veröffentlichen</h2>
      <p class="inventory-section__intro">
        Nutze Editor, Emoji-Picker und Live-Vorschau, um frische Informationen im Lager-Stil zu gestalten.
      </p>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <p class="inventory-note inventory-note--success">News erfolgreich angelegt.</p>
    <?php elseif (isset($_GET['updated'])): ?>
      <p class="inventory-note inventory-note--success">News erfolgreich aktualisiert.</p>
    <?php elseif (isset($_GET['deleted'])): ?>
      <p class="inventory-note inventory-note--success">News entfernt.</p>
    <?php endif; ?>

    <form method="post" class="inventory-form news-form" id="newsForm" onsubmit="syncContent()">
      <input type="hidden" name="add_news" value="1">
      <input type="hidden" name="icon" id="selectedIcon">
      <input type="hidden" name="text" id="hiddenText">

      <div class="form-grid two-column">
        <div class="input-control">
          <label for="titel">Titel</label>
          <input type="text" class="input-field" name="titel" id="titel" placeholder="Titel der News" required>
        </div>
        <div class="input-control">
          <label for="sichtbar_fuer">Sichtbarkeit</label>
          <select name="sichtbar_fuer" id="sichtbar_fuer" class="inventory-select" required>
            <option value="oeffentlich">🌍 Öffentlich</option>
            <option value="intern">🔒 Intern</option>
          </select>
        </div>
      </div>

      <div class="news-icon-picker">
        <div class="news-icon-picker__preview" id="selectedIconPreview" aria-live="polite">📰</div>
        <div class="news-icon-picker__actions">
          <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" id="toggleEmojiList">😊 Emoji / Icon wählen</button>
          <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" id="clearIcon">🧹 Icon zurücksetzen</button>
        </div>
      </div>
      <div class="news-emoji-grid" id="emojiList"></div>

      <div class="news-editor-card">
        <div class="news-toolbar" role="toolbar" aria-label="Formatierungen">
          <button type="button" class="news-toolbar__btn" data-cmd="bold" title="Fett"><strong>B</strong></button>
          <button type="button" class="news-toolbar__btn" data-cmd="italic" title="Kursiv"><em>I</em></button>
          <button type="button" class="news-toolbar__btn" data-cmd="underline" title="Unterstrichen"><span class="news-toolbar__underline">U</span></button>
          <button type="button" class="news-toolbar__btn" data-cmd="insertUnorderedList" title="Aufzählung">• Liste</button>
          <button type="button" class="news-toolbar__btn" data-cmd="createLink" title="Link">🔗 Link</button>
        </div>
        <div class="news-editor" id="editor" contenteditable="true" aria-label="News-Inhalt"></div>
      </div>

      <div class="news-preview" id="preview">
        <div class="news-preview__header">
          <span class="news-preview__icon" id="preview-icon">📰</span>
          <div class="news-preview__meta">
            <strong class="news-preview__title" id="preview-title">Vorschau-Titel</strong>
            <span class="news-preview__hint">Live-Vorschau für Mitarbeitende</span>
          </div>
        </div>
        <div id="preview-content" class="news-preview__content">Hier erscheint deine News-Vorschau.</div>
      </div>

      <div class="form-actions">
        <button class="inventory-submit" type="submit">💾 News speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <div>
      <h2>Bestehende News</h2>
      <p class="inventory-section__intro">
        Bearbeite oder entferne vorhandene Meldungen – natürlich in derselben Optik wie unsere Lagerlisten.
      </p>
    </div>

    <?php if (empty($newsList)): ?>
      <p class="empty-state">Aktuell sind keine News hinterlegt.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Icon</th>
              <th>Titel</th>
              <th>Sichtbarkeit</th>
              <th>Datum</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($newsList as $n): ?>
              <tr>
                <td class="news-table__icon"><?= htmlspecialchars($n['icon']) ?></td>
                <td><?= htmlspecialchars($n['titel']) ?></td>
                <td><?= $n['sichtbar_fuer'] === 'intern' ? '🔒 Intern' : '🌍 Öffentlich' ?></td>
                <td><?= date('d.m.Y H:i', strtotime($n['erstellt_am'])) ?></td>
                <td>
                  <div class="news-table__actions">
                    <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" onclick='openEditPopup(<?= json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Bearbeiten</button>
                    <a href="?delete=<?= $n['id'] ?>" class="inventory-submit inventory-submit--danger inventory-submit--small" onclick="return confirm('⚠️ Diese News wirklich löschen?')">🗑️ Löschen</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<!-- Bearbeiten Popup -->
<div class="news-modal-overlay" id="editPopup">
  <div class="news-modal">
    <h3>✏️ News bearbeiten</h3>
    <form method="post" class="inventory-form" onsubmit="return confirm('Änderungen speichern?')">
      <input type="hidden" name="update_news" value="1">
      <input type="hidden" name="news_id" id="edit_id">

      <div class="input-control">
        <label for="edit_titel">Titel</label>
        <input type="text" class="input-field" name="edit_titel" id="edit_titel" required>
      </div>

      <div class="input-control">
        <label for="edit_sichtbar_fuer">Sichtbarkeit</label>
        <select name="edit_sichtbar_fuer" id="edit_sichtbar_fuer" class="inventory-select">
          <option value="oeffentlich">🌍 Öffentlich</option>
          <option value="intern">🔒 Intern</option>
        </select>
      </div>

      <div class="news-icon-picker news-icon-picker--compact">
        <div class="news-icon-picker__preview" id="selectedIconPreviewEdit">📰</div>
        <div class="news-icon-picker__actions">
          <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" id="toggleEmojiListEdit">😊 Emoji wählen</button>
          <button type="button" class="inventory-submit inventory-submit--ghost inventory-submit--small" id="clearIconEdit">🧹 Icon zurücksetzen</button>
        </div>
      </div>
      <input type="text" class="input-field" name="edit_icon" id="edit_icon" placeholder="oder Emoji hier einfügen">
      <div class="news-emoji-grid news-emoji-grid--popup" id="emojiListEdit"></div>

      <div class="input-control">
        <label for="edit_text">Text</label>
        <textarea name="edit_text" id="edit_text" class="input-field" rows="6"></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">💾 Speichern</button>
        <button type="button" class="inventory-submit inventory-submit--ghost" onclick="closeEditPopup()">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<script>
// === Toolbar ===
document.querySelectorAll('.news-toolbar__btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const cmd = btn.dataset.cmd;
    if (cmd === 'createLink') {
      const url = prompt('Link-Adresse:');
      if (url) {
        document.execCommand(cmd, false, url);
      }
    } else {
      document.execCommand(cmd, false, null);
    }
    updatePreview();
  });
});

// === Vorschau ===
const editor = document.getElementById('editor');
const titleInput = document.getElementById('titel');
editor.addEventListener('input', updatePreview);
titleInput.addEventListener('input', updatePreview);
function updatePreview() {
  document.getElementById('preview-title').textContent = titleInput.value || 'Vorschau-Titel';
  document.getElementById('preview-icon').textContent = document.getElementById('selectedIcon').value || '📰';
  document.getElementById('preview-content').innerHTML = editor.innerHTML || 'Hier erscheint deine News-Vorschau.';
}
function syncContent() {
  document.getElementById('hiddenText').value = editor.innerHTML;
}

// === Emoji-Picker (Add) ===
const emojiList = document.getElementById('emojiList');
const toggleEmojiList = document.getElementById('toggleEmojiList');
const selectedIconInput = document.getElementById('selectedIcon');
const selectedIconPreview = document.getElementById('selectedIconPreview');
const clearIconBtn = document.getElementById('clearIcon');

(function buildEmojiGrid() {
  const emojis = [];
  for (let i = 0x1f300; i <= 0x1faf0; i++) {
    try {
      emojis.push(String.fromCodePoint(i));
    } catch (_) {
      // ignore invalid code points
    }
  }
  emojiList.innerHTML = emojis.map(e => `<span>${e}</span>`).join('');
})();

toggleEmojiList.addEventListener('click', () => {
  emojiList.style.display = (emojiList.style.display === 'grid') ? 'none' : 'grid';
});
emojiList.addEventListener('click', e => {
  if (e.target.tagName === 'SPAN') {
    const icon = e.target.textContent;
    selectedIconInput.value = icon;
    selectedIconPreview.textContent = icon;
    document.getElementById('preview-icon').textContent = icon;
    emojiList.style.display = 'none';
  }
});
clearIconBtn.addEventListener('click', () => {
  selectedIconInput.value = '';
  selectedIconPreview.textContent = '📰';
  document.getElementById('preview-icon').textContent = '📰';
});

// === Popup bearbeiten ===
function openEditPopup(news) {
  document.getElementById('edit_id').value = news.id;
  document.getElementById('edit_titel').value = news.titel;
  document.getElementById('edit_sichtbar_fuer').value = news.sichtbar_fuer;
  document.getElementById('edit_icon').value = news.icon || '';
  document.getElementById('selectedIconPreviewEdit').textContent = news.icon || '📰';
  document.getElementById('edit_text').value = news.text.replace(/<br\s*\/?>/g, '\n');
  document.getElementById('editPopup').style.display = 'flex';
}
function closeEditPopup() {
  document.getElementById('editPopup').style.display = 'none';
}
window.openEditPopup = openEditPopup;
window.closeEditPopup = closeEditPopup;

// Emoji-Picker im Popup
const emojiListEdit = document.getElementById('emojiListEdit');
const toggleEmojiListEdit = document.getElementById('toggleEmojiListEdit');
const clearIconEdit = document.getElementById('clearIconEdit');
const editIconInput = document.getElementById('edit_icon');
const selectedIconPreviewEdit = document.getElementById('selectedIconPreviewEdit');

(function buildEmojiGridEdit() {
  emojiListEdit.innerHTML = emojiList.innerHTML;
})();

toggleEmojiListEdit.addEventListener('click', () => {
  emojiListEdit.style.display = (emojiListEdit.style.display === 'grid') ? 'none' : 'grid';
});
emojiListEdit.addEventListener('click', e => {
  if (e.target.tagName === 'SPAN') {
    const icon = e.target.textContent;
    editIconInput.value = icon;
    selectedIconPreviewEdit.textContent = icon;
    emojiListEdit.style.display = 'none';
  }
});
clearIconEdit.addEventListener('click', () => {
  editIconInput.value = '';
  selectedIconPreviewEdit.textContent = '📰';
});
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>