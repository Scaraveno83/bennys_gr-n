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
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>📰 News-Verwaltung | Admin</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main {
  max-width: 1000px;
  margin: 120px auto;
  padding: 20px;
  color: #fff;
}

/* === Formular === */
.news-form {
  background: rgba(25,25,25,0.85);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 25px;
  box-shadow: 0 0 20px rgba(57,255,20,0.3);
  margin-bottom: 50px;
}
.news-form input, .news-form select {
  width: 100%;
  background: rgba(20,20,20,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 10px;
  padding: 12px 14px;
  color: #fff;
  font-size: 1rem;
  margin-bottom: 16px;
}
.news-form select:focus, .news-form input:focus {
  outline: none;
  box-shadow: 0 0 15px rgba(57,255,20,0.6);
}

/* === Toolbar === */
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}
.toolbar button {
  background: rgba(57,255,20,0.2);
  border: 1px solid rgba(57,255,20,0.5);
  color: #fff;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
  transition: .2s;
}
.toolbar button:hover {
  background: rgba(57,255,20,0.4);
}

/* === Editor === */
.editor {
  background: rgba(20,20,20,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 10px;
  min-height: 200px;
  padding: 10px;
  color: #fff;
  overflow-y: auto;
}
.editor:focus {
  outline: none;
  box-shadow: 0 0 10px rgba(57,255,20,0.6);
}

/* === Icon / Emoji Picker === */
.icon-picker {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 12px;
}
.icon-preview {
  font-size: 2rem;
  text-shadow: 0 0 10px #76ff65;
}
.picker-actions {
  display: flex;
  gap: 8px;
}
.picker-actions button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(57,255,20,0.15);
  border: 2px solid rgba(57,255,20,0.6);
  color: #76ff65;
  font-weight: bold;
  border-radius: 50px;
  padding: 10px 16px;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 0 12px rgba(57,255,20,0.35);
  text-shadow: 0 0 10px rgba(57,255,20,0.6);
}
.picker-actions button:hover {
  background: linear-gradient(90deg, #39ff14, #76ff65);
  color: #fff;
  box-shadow: 0 0 22px rgba(57,255,20,0.7);
  transform: translateY(-1px) scale(1.03);
}

/* Emoji Grid */
.emoji-list {
  display: none;
  grid-template-columns: repeat(auto-fill, minmax(34px, 1fr));
  gap: 6px;
  max-height: 260px;
  overflow-y: auto;
  background: rgba(15,15,15,0.95);
  border: 1px solid rgba(57,255,20,0.35);
  border-radius: 12px;
  padding: 10px;
  margin-bottom: 10px;
  box-shadow: 0 0 16px rgba(57,255,20,0.25);
}
.emoji-list span {
  font-size: 1.3rem;
  cursor: pointer;
  text-align: center;
  padding: 6px 0;
  border-radius: 8px;
  transition: 0.15s;
}
.emoji-list span:hover {
  transform: scale(1.15);
  background: rgba(57,255,20,0.18);
}

/* === Vorschau & Save === */
.preview-card {
  background: rgba(25,25,25,0.7);
  border: 1px solid rgba(57,255,20,.4);
  border-radius: 15px;
  padding: 26px;
  box-shadow: 0 0 20px rgba(57,255,20,.25);
  backdrop-filter: blur(10px);
  max-width: 750px;
  margin-top: 30px;
}
.preview-card h3 {
  color: #76ff65;
  display: flex;
  align-items: center;
  gap: 8px;
  text-shadow: 0 0 10px #39ff14;
}
.preview-card .content {
  color: #e0e0e0;
  line-height: 1.6;
  margin-top: 10px;
}
button.save, .popup button {
  background: linear-gradient(90deg, #39ff14, #76ff65);
  border: 1px solid rgba(57,255,20,0.6);
  color: #fff;
  padding: 12px 28px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1.05rem;
  cursor: pointer;
  text-shadow: 0 0 6px rgba(255,255,255,0.3);
  box-shadow: 0 0 20px rgba(57,255,20,0.3);
  transition: all 0.25s ease;
}
button.save:hover, .popup button:hover {
  background: linear-gradient(90deg, #76ff65, #b8ffb0);
  transform: translateY(-2px) scale(1.04);
  box-shadow: 0 0 25px rgba(57,255,20,0.7);
}
button.save:active, .popup button:active {
  transform: scale(0.97);
  box-shadow: 0 0 15px rgba(57,255,20,0.6);
}

/* === Tabelle (Bestehende News) === */
.news-table {
  background: rgba(25,25,25,0.85);
  border: 1px solid rgba(57,255,20,0.3);
  border-radius: 15px;
  box-shadow: 0 0 25px rgba(57,255,20,0.25);
  overflow: hidden;
  margin-top: 20px;
}
.news-table table { width: 100%; border-collapse: collapse; }
.news-table th, .news-table td { padding: 14px 10px; text-align: center; }
.news-table th {
  background: rgba(57,255,20,0.15);
  color: #76ff65;
  text-shadow: 0 0 6px rgba(57,255,20,0.4);
  font-weight: bold; letter-spacing: .5px;
}
.news-table tr { transition: background .3s ease, transform .2s ease; }
.news-table tr:hover { background: rgba(57,255,20,0.08); transform: scale(1.01); }
.news-table td { border-bottom: 1px solid rgba(57,255,20,0.15); }
.news-table td button {
  background: rgba(57,255,20,0.2);
  border: 1px solid rgba(57,255,20,0.5);
  border-radius: 8px;
  color: #fff;
  padding: 6px 12px;
  cursor: pointer;
  transition: .25s;
}
.news-table td button:hover { background: rgba(57,255,20,0.4); }
.news-table a.delete {
  color: #76ff65; text-decoration: none; font-weight: bold; text-shadow:0 0 8px rgba(57,255,20,0.6);
}
.news-table a.delete:hover { text-decoration: underline; color: #aaff9a; }

/* === Popup === */
.popup-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8);
  display: none; justify-content: center; align-items: center; z-index: 1000;
}
.popup {
  background: rgba(20,20,20,0.95);
  border: 2px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 25px; width: 640px; max-width: 92%;
  color: #fff; box-shadow: 0 0 25px rgba(57,255,20,0.6);
  animation: fadeInUp .35s ease;
}
.popup h3 { text-align: center; color: #76ff65; margin-top: 0; }
.popup .btns { display: flex; justify-content: space-between; gap: 10px; margin-top: 15px; }
.popup button.cancel { background: rgba(57,255,20,0.1); border: 1px solid rgba(57,255,20,0.4); }

/* Mini Emoji Picker im Popup */
.emoji-list--popup { max-height: 220px; }
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <h2>📰 News / Ankündigungen verwalten</h2>

  <form method="post" class="news-form" id="newsForm" onsubmit="syncContent()">
    <input type="hidden" name="add_news" value="1">
    <input type="hidden" name="icon" id="selectedIcon">
    <input type="hidden" name="text" id="hiddenText">

    <label for="titel">Titel:</label>
    <input type="text" name="titel" id="titel" placeholder="Titel der News" required>

    <label for="sichtbar_fuer">Sichtbarkeit:</label>
    <select name="sichtbar_fuer" id="sichtbar_fuer" required>
      <option value="oeffentlich">🌍 Öffentlich</option>
      <option value="intern">🔒 Intern</option>
    </select>

    <div class="icon-picker">
      <div class="picker-actions">
        <button type="button" id="toggleEmojiList">😊 Emoji / Icon wählen</button>
        <button type="button" id="clearIcon">🧹 Icon leeren</button>
      </div>
      <div class="icon-preview" id="selectedIconPreview">📰</div>
    </div>
    <div class="emoji-list" id="emojiList"></div>

    <div class="toolbar">
      <button type="button" data-cmd="bold"><b>B</b></button>
      <button type="button" data-cmd="italic"><i>I</i></button>
      <button type="button" data-cmd="underline"><u>U</u></button>
      <button type="button" data-cmd="insertUnorderedList">• Liste</button>
      <button type="button" data-cmd="createLink">🔗 Link</button>
    </div>

    <div class="editor" id="editor" contenteditable="true"></div>
    <button class="save" type="submit">💾 News speichern</button>

    <div class="preview-card" id="preview">
      <h3><span id="preview-icon">📰</span> <span id="preview-title">Vorschau-Titel</span></h3>
      <div id="preview-content" class="content">Hier erscheint deine News-Vorschau.</div>
    </div>
  </form>

  <h3>📋 Bestehende News</h3>
  <div class="news-table">
    <table>
      <thead><tr><th>Icon</th><th>Titel</th><th>Typ</th><th>Datum</th><th>Aktionen</th></tr></thead>
      <tbody>
        <?php foreach ($newsList as $n): ?>
        <tr>
          <td style="font-size:1.5rem;"><?= htmlspecialchars($n['icon']) ?></td>
          <td><?= htmlspecialchars($n['titel']) ?></td>
          <td><?= $n['sichtbar_fuer']==='intern'?'🔒 Intern':'🌍 Öffentlich' ?></td>
          <td><?= date('d.m.Y H:i', strtotime($n['erstellt_am'])) ?></td>
          <td>
            <button type="button" onclick='openEditPopup(<?= json_encode($n, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️ Bearbeiten</button>
            <a href="?delete=<?= $n['id'] ?>" class="delete" onclick="return confirm('⚠️ Diese News wirklich löschen?')">🗑️ Löschen</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Bearbeiten Popup -->
<div class="popup-overlay" id="editPopup">
  <div class="popup">
    <h3>✏️ News bearbeiten</h3>
    <form method="post" onsubmit="return confirm('Änderungen speichern?')">
      <input type="hidden" name="update_news" value="1">
      <input type="hidden" name="news_id" id="edit_id">

      <label>Titel:</label>
      <input type="text" name="edit_titel" id="edit_titel" required>

      <label>Sichtbarkeit:</label>
      <select name="edit_sichtbar_fuer" id="edit_sichtbar_fuer">
        <option value="oeffentlich">🌍 Öffentlich</option>
        <option value="intern">🔒 Intern</option>
      </select>

      <label>Icon:</label>
      <div class="icon-picker" style="margin-bottom:8px;">
        <div class="picker-actions">
          <button type="button" id="toggleEmojiListEdit">😊 Emoji wählen</button>
          <button type="button" id="clearIconEdit">🧹 Icon leeren</button>
        </div>
        <div class="icon-preview" id="selectedIconPreviewEdit">📰</div>
      </div>
      <input type="text" name="edit_icon" id="edit_icon" placeholder="oder Emoji hier einfügen" style="width:100%;background:rgba(20,20,20,0.9);border:1px solid rgba(57,255,20,0.4);border-radius:10px;padding:10px;color:#fff;margin-bottom:10px;">
      <div class="emoji-list emoji-list--popup" id="emojiListEdit"></div>

      <label>Text:</label>
      <textarea name="edit_text" id="edit_text" rows="6" style="width:100%;border-radius:10px;padding:10px;background:rgba(20,20,20,0.9);color:#fff;border:1px solid rgba(57,255,20,0.4);"></textarea>

      <div class="btns">
        <button type="submit">💾 Speichern</button>
        <button type="button" class="cancel" onclick="closeEditPopup()">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<script>
// === Toolbar ===
document.querySelectorAll('.toolbar button').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const cmd=btn.dataset.cmd;
    if(cmd==='createLink'){
      const url=prompt('Link-Adresse:');
      if(url) document.execCommand(cmd,false,url);
    } else {
      document.execCommand(cmd,false,null);
    }
    updatePreview();
  });
});

// === Vorschau ===
const editor=document.getElementById('editor');
const titleInput=document.getElementById('titel');
editor.addEventListener('input',updatePreview);
titleInput.addEventListener('input',updatePreview);
function updatePreview(){
  document.getElementById('preview-title').textContent=titleInput.value||'Vorschau-Titel';
  document.getElementById('preview-icon').textContent=document.getElementById('selectedIcon').value||'📰';
  document.getElementById('preview-content').innerHTML=editor.innerHTML||'Hier erscheint deine News-Vorschau.';
}
function syncContent(){document.getElementById('hiddenText').value=editor.innerHTML;}

// === Emoji-Picker (Add) ===
const emojiList = document.getElementById("emojiList");
const toggleEmojiList = document.getElementById("toggleEmojiList");
const selectedIconInput = document.getElementById("selectedIcon");
const selectedIconPreview = document.getElementById("selectedIconPreview");
const clearIconBtn = document.getElementById("clearIcon");

// große Emoji-Auswahl generieren (ähnlich wie vorher)
(function buildEmojiGrid(){
  const emojis=[];
  for(let i=0x1F300;i<=0x1FAF0;i++){
    try{emojis.push(String.fromCodePoint(i));}catch(_){}
  }
  emojiList.innerHTML = emojis.map(e=>`<span>${e}</span>`).join("");
})();
toggleEmojiList.addEventListener("click",()=>{
  emojiList.style.display = (emojiList.style.display==="grid") ? "none" : "grid";
});
emojiList.addEventListener("click", e=>{
  if(e.target.tagName==="SPAN"){
    const icon=e.target.textContent;
    selectedIconInput.value=icon;
    selectedIconPreview.textContent=icon;
    document.getElementById('preview-icon').textContent=icon;
    emojiList.style.display="none";
  }
});
clearIconBtn.addEventListener("click",()=>{
  selectedIconInput.value="";
  selectedIconPreview.textContent="📰";
  document.getElementById('preview-icon').textContent="📰";
});

// === Popup bearbeiten ===
function openEditPopup(news){
  document.getElementById("edit_id").value=news.id;
  document.getElementById("edit_titel").value=news.titel;
  document.getElementById("edit_sichtbar_fuer").value=news.sichtbar_fuer;
  document.getElementById("edit_icon").value=news.icon||"";
  document.getElementById("selectedIconPreviewEdit").textContent=news.icon||"📰";
  document.getElementById("edit_text").value=news.text.replace(/<br\s*\/?>/g,"\n");
  document.getElementById("editPopup").style.display="flex";
}
function closeEditPopup(){document.getElementById("editPopup").style.display="none";}

// Emoji-Picker im Popup
const emojiListEdit = document.getElementById("emojiListEdit");
const toggleEmojiListEdit = document.getElementById("toggleEmojiListEdit");
const clearIconEdit = document.getElementById("clearIconEdit");
const editIconInput = document.getElementById("edit_icon");
const selectedIconPreviewEdit = document.getElementById("selectedIconPreviewEdit");

(function buildEmojiGridEdit(){
  // gleiche Liste recyceln (Performance: kleinere Auswahl möglich, hier komplett wie gewünscht)
  emojiListEdit.innerHTML = emojiList.innerHTML;
})();
toggleEmojiListEdit.addEventListener("click",()=>{
  emojiListEdit.style.display = (emojiListEdit.style.display==="grid") ? "none" : "grid";
});
emojiListEdit.addEventListener("click", e=>{
  if(e.target.tagName==="SPAN"){
    const icon=e.target.textContent;
    editIconInput.value=icon;
    selectedIconPreviewEdit.textContent=icon;
    emojiListEdit.style.display="none";
  }
});
clearIconEdit.addEventListener("click",()=>{
  editIconInput.value="";
  selectedIconPreviewEdit.textContent="📰";
});
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
