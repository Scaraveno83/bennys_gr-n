<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wenn kein Login → Chat nicht anzeigen
if (empty($_SESSION['user_id'])) {
    return; // KEIN exit → Seite lädt normal weiter
}
?>

<link rel="stylesheet" href="https://onev-bennys-rp.4lima.de/bennys/chat/chat.css">

<div id="bc-root">
  <button id="bc-bubble" aria-label="Chat öffnen">💬</button>

  <section id="bc-popup" aria-label="Bennys Livechat" data-open="0" hidden>
    <header class="bc-head">
      <span class="bc-title">Benny’s Chat</span>

      <div class="bc-ptt-status" role="status" aria-live="polite">
        <span id="bc-ptt-indicator" class="bc-ptt-indicator">Funk: # gedrückt halten</span>
      </div>

      <div class="bc-head-actions">
        <button id="bc-sound" class="bc-sound-btn" aria-label="Sound umschalten">🔊</button>
        <button id="bc-close" aria-label="Schließen" class="bc-close-btn">×</button>
      </div>
    </header>

    <main class="bc-main">
      <div id="bc-messages"></div>

      <!-- ✅ EMOJI PICKER (wird von JS sichtbar/unsichtbar gemacht) -->
      <div id="bc-emoji-picker" class="bc-emoji-picker" hidden>

  <!-- 😀 Gesichter -->
  <span>😀</span><span>😃</span><span>😄</span><span>😁</span><span>😆</span><span>😅</span><span>😂</span><span>🤣</span>
  <span>😊</span><span>😇</span><span>🙂</span><span>🙃</span><span>😉</span><span>😌</span><span>😍</span><span>🥰</span>
  <span>😘</span><span>😗</span><span>😙</span><span>😚</span><span>😋</span><span>😛</span><span>😜</span><span>🤪</span>
  <span>😝</span><span>🤑</span><span>🤗</span><span>🤭</span><span>🤫</span><span>🤔</span><span>🤐</span>
  <span>😐</span><span>😑</span><span>😶</span><span>🙄</span><span>😏</span><span>😣</span><span>😥</span><span>😮‍💨</span>
  <span>😪</span><span>😴</span><span>😓</span><span>😩</span><span>😫</span><span>🥱</span><span>😤</span><span>😠</span>
  <span>😡</span><span>🤬</span><span>😰</span><span>😨</span><span>😱</span><span>😢</span><span>😭</span><span>😳</span>

  <!-- 🤝 Handzeichen -->
  <span>👍</span><span>👎</span><span>👌</span><span>🤌</span><span>🤏</span>
  <span>✌️</span><span>🤞</span><span>🤟</span><span>🤘</span>
  <span>🤙</span><span>👋</span><span>🤝</span><span>🙏</span>
  <span>👏</span><span>🙌</span><span>💪</span><span>🫶</span>

  <!-- ❤️ Liebe & Symbole -->
  <span>❤️</span><span>🧡</span><span>💛</span><span>💚</span><span>💙</span><span>💜</span><span>🖤</span><span>🤍</span>
  <span>💔</span><span>❣️</span><span>💕</span><span>💞</span><span>💓</span><span>💗</span><span>💖</span><span>💘</span>

  <!-- 💬 Kommunikation -->
  <span>💬</span><span>🗨️</span><span>💭</span><span>📢</span><span>📣</span><span>🤙</span>

  <!-- 🚗 Fahrzeuge (Benny’s passend 😎) -->
  <span>🚗</span><span>🚙</span><span>🏎️</span><span>🚓</span><span>🛠️</span><span>⚙️</span><span>🔧</span><span>🔩</span>

</div>



      <form id="bc-form" enctype="multipart/form-data" autocomplete="off">

        <!-- ✅ EMOJI BUTTON -->
        <button type="button" id="bc-emoji" class="bc-emoji-btn">😊</button>

        <input type="text" id="bc-input" name="body" placeholder="Nachricht schreiben…" maxlength="2000">

        <label class="bc-attach" for="bc-image">📎</label>
        <input type="file" id="bc-image" name="image" accept="image/*" hidden>

        <button type="submit" class="bc-send">Senden</button>
      </form>
    </main>
  </section>
</div>

<script src="https://onev-bennys-rp.4lima.de/bennys/chat/chat.js"></script>

<script>
(function () {
  var root = document.getElementById('bc-root');
  if (!root) return;

  if (root.parentElement && root.parentElement.tagName.toLowerCase() === 'body') return;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', move);
  } else {
    move();
  }

  function move() {
    try { document.body.appendChild(root); } catch (e) {}
  }
})();
</script>
