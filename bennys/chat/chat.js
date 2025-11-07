(function(){
  const root = document.getElementById('bc-root'); if(!root) return;
  const $ = s => root.querySelector(s);

  const bubble = $('#bc-bubble');
  const popup  = $('#bc-popup');
  const close  = $('#bc-close');
  const soundBtn = $('#bc-sound');
  const list   = $('#bc-messages');
  const form   = $('#bc-form');
  const input  = $('#bc-input');
  const emojiBtn = $('#bc-emoji');
  const picker = $('#bc-emoji-picker');
  const file   = $('#bc-image');
  const pttIndicator = $('#bc-ptt-indicator');

  let selfId = 0;
  let sinceId = 0;
  let timer = null;
  let initialized = false;
  let autoScroll = true;
  let selfAliases = new Set();
  const mentionPattern = /@([A-Za-zÀ-ÖØ-öø-ÿ0-9_.-]+)/g;
  const mentionCleanupPattern = /[^A-Za-zÀ-ÖØ-öø-ÿ0-9_.-]+/g;
  const mentionCharPattern = /[A-Za-zÀ-ÖØ-öø-ÿ0-9_.-]/;

  const mentionBox = document.createElement('div');
  mentionBox.id = 'bc-mention';
  mentionBox.hidden = true;
  if(form){ form.appendChild(mentionBox); }

  let mentionCatalog = [];
  let mentionMatches = [];
  let mentionActiveIndex = -1;
  let mentionStartIndex = -1;
  let mentionEndIndex = -1;

  const PTT_DEFAULT = 'Funk: # gedrückt halten';
  const VOICE_MIN_DURATION = 400; // ms
  const VOICE_MAX_DURATION = 20000; // ms

  const voiceSupported = !!(window.MediaRecorder && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  const preferredMime = (function(){
    if(!window.MediaRecorder) return '';
    const candidates = [
      'audio/webm;codecs=opus',
      'audio/ogg;codecs=opus',
      'audio/webm',
      'audio/ogg'
    ];
    return candidates.find(type => window.MediaRecorder.isTypeSupported(type)) || '';
  })();

  let voiceStream = null;
  let mediaRecorder = null;
  let voiceChunks = [];
  let voiceRecording = false;
  let voiceSendAfterStop = false;
  let voiceTimeout = null;
  let voiceStart = 0;
  let lastVoiceDuration = 0;
  let pttResetTimer = null;
  let activeVoiceKeyCode = null;

  // ✅ Sound-Status speichern (Standard: ON)
  let soundEnabled = (localStorage.getItem('chat_sound') !== 'off');
  updateSoundIcon();

  function updateSoundIcon(){
    if(!soundBtn) return;

    // Icon bleibt gleich
    soundBtn.textContent = '🔊';

    // Visueller Lautlos-Modus
    soundBtn.classList.toggle('muted', !soundEnabled);

    // kleiner Aktivierungs-Ping
    soundBtn.classList.add('bc-ping');
    setTimeout(() => soundBtn.classList.remove('bc-ping'), 450);
  }

  function normalizeMention(value){
    if(!value) return '';
    let str = value.toString();
    if(typeof str.normalize === 'function'){
      try { str = str.normalize('NFKC'); }
      catch(e){}
    }
    str = str.toLocaleLowerCase('de-DE');
    return str.replace(mentionCleanupPattern, '');
  }

  function sanitizeMentionInsert(value){
    if(!value) return '';
    let str = value.toString();
    if(typeof str.normalize === 'function'){
      try { str = str.normalize('NFKC'); }
      catch(e){}
    }
    return str.replace(mentionCleanupPattern, '');
  }

  function setSelfIdentity(info = {}){
    const aliases = new Set();
    const add = val => {
      const norm = normalizeMention(val);
      if(norm) aliases.add(norm);
    };

    add(info.self_username || info.username);

    const display = info.self_display_name || info.display_name;
    if(display){
      add(display);
      display.split(/[\s\-()']+/).forEach(add);
    }

    selfAliases = aliases;
  }

  function setMentionCatalog(list){
    mentionCatalog = [];
    const rows = Array.isArray(list) ? list : [];
    rows.forEach(item => {
      if(!item) return;
      const rawUsername = item.username != null ? String(item.username).trim() : '';
      const rawDisplay = item.display_name != null ? String(item.display_name).trim() : '';
      const display = rawDisplay || rawUsername;
      const insertSource = rawUsername || rawDisplay;
      const insertValue = sanitizeMentionInsert(insertSource);
      if(!insertValue) return;

      const tokens = new Set();
      const pushToken = val => {
        const norm = normalizeMention(val);
        if(norm) tokens.add(norm);
      };

      if(rawUsername){ pushToken(rawUsername); }
      if(rawDisplay){
        pushToken(rawDisplay);
        rawDisplay.split(/[\s\-()']+/).forEach(pushToken);
      }

      mentionCatalog.push({
        username: rawUsername,
        display,
        insert: insertValue,
        tokens: Array.from(tokens)
      });
    });
    handleMentionInputEvent();
  }

  function computeMentionMatches(query){
    if(!mentionCatalog.length) return [];
    const normalized = normalizeMention(query);
    let matches;
    if(!normalized){
      matches = mentionCatalog.slice();
    } else {
      matches = mentionCatalog.filter(item => item.tokens.some(token => token.includes(normalized)));
      if(!matches.length){
        const lowQuery = query.toLocaleLowerCase('de-DE');
        matches = mentionCatalog.filter(item => {
          const disp = (item.display || '').toLocaleLowerCase('de-DE');
          const user = (item.username || '').toLocaleLowerCase('de-DE');
          return disp.includes(lowQuery) || user.includes(lowQuery);
        });
      }
    }
    return matches.slice(0, 20);
  }

  function hideMentionList(){
    mentionMatches = [];
    mentionActiveIndex = -1;
    mentionStartIndex = -1;
    mentionEndIndex = -1;
    if(!mentionBox.hidden){
      mentionBox.hidden = true;
      mentionBox.innerHTML = '';
    } else {
      mentionBox.innerHTML = '';
    }
    delete mentionBox.dataset.position;
  }

  function renderMentionList(){
    if(!mentionMatches.length){
      hideMentionList();
      return;
    }
    mentionBox.innerHTML = '';
    const frag = document.createDocumentFragment();
    mentionMatches.forEach((item, idx) => {
      const div = document.createElement('div');
      div.className = 'item' + (idx === mentionActiveIndex ? ' active' : '');
      div.dataset.index = String(idx);

      const display = item.display || '';
      const username = item.username || '';
      const same = display && username && display.toLocaleLowerCase('de-DE') === username.toLocaleLowerCase('de-DE');

      if(username && (!display || same)){
        div.innerHTML = '@' + escapeHtml(username);
      } else if(display && username){
        div.innerHTML = `${escapeHtml(display)} <span class="handle">@${escapeHtml(username)}</span>`;
      } else if(display){
        div.innerHTML = escapeHtml(display);
      } else {
        div.innerHTML = '@' + escapeHtml(item.insert || username);
      }

      frag.appendChild(div);
    });
    mentionBox.appendChild(frag);
    mentionBox.hidden = false;
    updateMentionPosition();
  }

  function updateMentionPosition(){
    if(mentionBox.hidden || !form || !input) return;
    const inputRect = input.getBoundingClientRect();
    const formRect = form.getBoundingClientRect();
    const left = Math.max(0, inputRect.left - formRect.left);
    const gap = 8;
    mentionBox.style.left = left + 'px';
    mentionBox.style.minWidth = inputRect.width + 'px';

    const bottom = Math.max(0, formRect.bottom - inputRect.top + gap);
    mentionBox.style.bottom = bottom + 'px';
    mentionBox.style.top = 'auto';
    mentionBox.dataset.position = 'above';
  }

  function handleMentionInputEvent(){
    if(!input) return;
    const value = input.value || '';
    const caret = (typeof input.selectionStart === 'number') ? input.selectionStart : value.length;
    const before = value.slice(0, caret);
    const atIndex = before.lastIndexOf('@');
    if(atIndex === -1){
      hideMentionList();
      return;
    }
    if(atIndex > 0 && mentionCharPattern.test(before.charAt(atIndex - 1))){
      hideMentionList();
      return;
    }

    let end = atIndex + 1;
    while(end < value.length && mentionCharPattern.test(value.charAt(end))){
      end++;
    }
    if(caret < atIndex + 1 || caret > end){
      hideMentionList();
      return;
    }

    const query = value.slice(atIndex + 1, caret);
    const matches = computeMentionMatches(query);
    if(!matches.length){
      hideMentionList();
      return;
    }

    mentionMatches = matches;
    mentionStartIndex = atIndex;
    mentionEndIndex = end;
    if(mentionActiveIndex < 0 || mentionActiveIndex >= mentionMatches.length){
      mentionActiveIndex = 0;
    }
    renderMentionList();
  }

  function pickMention(index){
    if(index == null || index < 0 || index >= mentionMatches.length) return;
    if(!input) return;
    if(mentionStartIndex < 0){
      hideMentionList();
      return;
    }
    const item = mentionMatches[index];
    const insertValue = sanitizeMentionInsert(item.insert || item.username || item.display);
    if(!insertValue) {
      hideMentionList();
      return;
    }

    const value = input.value || '';
    const before = value.slice(0, mentionStartIndex);
    const afterIndex = (mentionEndIndex >= mentionStartIndex) ? mentionEndIndex : ((typeof input.selectionEnd === 'number') ? input.selectionEnd : value.length);
    const after = value.slice(afterIndex);

    const mentionText = '@' + insertValue;
    let needsSpace = false;
    if(after.length === 0){
      needsSpace = true;
    } else if(!/^[\s.,;:!?)]/.test(after)){
      needsSpace = true;
    }

    const spacer = needsSpace ? ' ' : '';
    const newValue = before + mentionText + spacer + after;
    input.value = newValue;
    const newCaret = before.length + mentionText.length + spacer.length;
    input.setSelectionRange(newCaret, newCaret);
    hideMentionList();
    input.focus();
    handleMentionInputEvent();
  }

  function handleMentionKeyDown(ev){
    if(mentionBox.hidden || !mentionMatches.length) return;
    if(ev.key === 'ArrowDown'){
      ev.preventDefault();
      mentionActiveIndex = (mentionActiveIndex + 1) % mentionMatches.length;
      renderMentionList();
    } else if(ev.key === 'ArrowUp'){
      ev.preventDefault();
      mentionActiveIndex = (mentionActiveIndex - 1 + mentionMatches.length) % mentionMatches.length;
      renderMentionList();
    } else if(ev.key === 'Enter' || ev.key === 'Tab'){
      ev.preventDefault();
      pickMention(mentionActiveIndex >= 0 ? mentionActiveIndex : 0);
    } else if(ev.key === 'Escape'){
      ev.preventDefault();
      hideMentionList();
    }
  }

  function handleMentionKeyNavigation(ev){
    if(ev.key === 'ArrowLeft' || ev.key === 'ArrowRight' || ev.key === 'Home' || ev.key === 'End'){
      handleMentionInputEvent();
    }
  }

  function isMentionForMe(tokens){
    if(!tokens || !tokens.length || !selfAliases.size) return false;
    return tokens.some(token => selfAliases.has(normalizeMention(token)));
  }

  function escapeHtml(str){
    const text = (str == null) ? '' : String(str);
    return text.replace(/[&<>\"']/g, ch => {
      switch(ch){
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#39;';
        default: return ch;
      }
    });
  }

  function formatSegment(str){
    return escapeHtml(str).replace(/\r?\n/g, '<br>');
  }

  function renderBody(text){
    if(!text){
      return { html: '', mentions: [] };
    }

    const mentions = [];
    let html = '';
    let lastIndex = 0;
    mentionPattern.lastIndex = 0;
    let match;

    while((match = mentionPattern.exec(text))){
      const before = text.slice(lastIndex, match.index);
      if(before){ html += formatSegment(before); }

      const mentionRaw = match[0];
      html += `<span class="bc-mention">${formatSegment(mentionRaw)}</span>`;
      mentions.push(match[1]);
      lastIndex = mentionPattern.lastIndex;
    }

    const tail = text.slice(lastIndex);
    if(tail){
      html += formatSegment(tail);
    }

    if(html === ''){
      html = formatSegment(text);
    }

    return { html, mentions };
  }

  function setPttMessage(text, { mode = 'info', persistent = false, timeout = 2600 } = {}){
    if(!pttIndicator) return;
    pttIndicator.textContent = text;
    const isActive = (mode === 'recording' || mode === 'sending' || mode === 'success');
    pttIndicator.classList.toggle('is-active', isActive);
    pttIndicator.classList.toggle('is-error', mode === 'error');

    if(pttResetTimer){
      clearTimeout(pttResetTimer);
      pttResetTimer = null;
    }

    if(!persistent){
      pttResetTimer = setTimeout(() => {
        pttIndicator.textContent = PTT_DEFAULT;
        pttIndicator.classList.remove('is-active', 'is-error');
        pttResetTimer = null;
      }, timeout);
    }
  }

  async function ensureVoiceStream(){
    if(!voiceSupported) {
      setPttMessage('Funk nicht verfügbar (Browser unterstützt kein Mikro)', { mode: 'error' });
      return null;
    }
    if(voiceStream) return voiceStream;
    try {
      voiceStream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true
        }
      });
      voiceStream.getAudioTracks().forEach(track => {
        const handler = () => {
          cancelVoiceRecording('Mikrofon getrennt');
          voiceStream = null;
        };
        if(typeof track.addEventListener === 'function'){
          track.addEventListener('ended', handler);
        } else if(typeof track.onended === 'object' || typeof track.onended === 'function') {
          track.onended = handler;
        }
      });
      return voiceStream;
    } catch(err){
      console.warn('Funk Mikrofon verweigert', err);
      setPttMessage('Mikrofon blockiert – bitte Browser-Zugriff erlauben', { mode: 'error' });
      return null;
    }
  }

  async function startVoiceRecording(){
    if(voiceRecording) return;
    const stream = await ensureVoiceStream();
    if(!stream) return;

    voiceChunks = [];
    voiceSendAfterStop = false;
    voiceStart = Date.now();
    lastVoiceDuration = 0;

    try {
      mediaRecorder = preferredMime ? new MediaRecorder(stream, { mimeType: preferredMime }) : new MediaRecorder(stream);
    } catch(err){
      console.warn('Funk MediaRecorder Fehler', err);
      setPttMessage('Recorder konnte nicht gestartet werden', { mode: 'error' });
      return;
    }

    voiceRecording = true;
    setPttMessage('Funk läuft…', { mode: 'recording', persistent: true });
    root.classList.add('bc-recording');

    mediaRecorder.ondataavailable = ev => {
      if(ev.data && ev.data.size){ voiceChunks.push(ev.data); }
    };

    mediaRecorder.onstop = () => {
      if(voiceTimeout){ clearTimeout(voiceTimeout); voiceTimeout = null; }
      const chunks = voiceChunks.slice();
      voiceChunks = [];
      const recorderMime = (mediaRecorder && mediaRecorder.mimeType) || preferredMime || 'audio/webm';
      mediaRecorder = null;
      if(!voiceSendAfterStop || !chunks.length) return;
      const blob = new Blob(chunks, { type: recorderMime });
      if(blob.size < 2048){
        setPttMessage('Funk zu kurz', { mode: 'error' });
        return;
      }
      sendVoiceMessage(blob);
    };

    mediaRecorder.onerror = err => {
      console.warn('Funk Recorder Fehler', err);
      voiceRecording = false;
      voiceSendAfterStop = false;
      voiceChunks = [];
      root.classList.remove('bc-recording');
      setPttMessage('Recorder-Fehler – Aufnahme abgebrochen', { mode: 'error' });
      try {
        mediaRecorder.stop();
      } catch(e){}
      mediaRecorder = null;
    };

    try {
      mediaRecorder.start();
    } catch(err){
      voiceRecording = false;
      root.classList.remove('bc-recording');
      setPttMessage('Recorder konnte nicht starten', { mode: 'error' });
      return;
    }

    voiceTimeout = setTimeout(() => {
      stopVoiceRecording({ reason: 'Zeitlimit erreicht' });
    }, VOICE_MAX_DURATION);
  }

  function stopVoiceRecording({ reason = '', cancel = false } = {}){
    if(!voiceRecording) return;
    voiceRecording = false;
    activeVoiceKeyCode = null;
    if(voiceTimeout){ clearTimeout(voiceTimeout); voiceTimeout = null; }

    const duration = Date.now() - voiceStart;
    lastVoiceDuration = Math.max(1, Math.round(duration / 1000));
    root.classList.remove('bc-recording');

    if(cancel){
      voiceSendAfterStop = false;
      if(reason === 'Aufnahme beendet'){
        setPttMessage('Aufnahme beendet', { mode: 'info', timeout: 1800 });
      } else {
        setPttMessage(reason || 'Aufnahme abgebrochen', { mode: 'error' });
      }
    } else if(duration < VOICE_MIN_DURATION){
      voiceSendAfterStop = false;
      setPttMessage('Funk zu kurz', { mode: 'error' });
    } else {
      voiceSendAfterStop = true;
      if(reason === 'Zeitlimit erreicht'){
        setPttMessage('Zeitlimit erreicht – Funk wird gesendet…', { mode: 'sending', timeout: 2400 });
      } else {
        setPttMessage('Funk wird gesendet…', { mode: 'sending', timeout: 2200 });
      }
    }

    if(mediaRecorder && mediaRecorder.state !== 'inactive'){
      try { mediaRecorder.stop(); }
      catch(err){
        console.warn('Funk stop Fehler', err);
        voiceSendAfterStop = false;
        voiceChunks = [];
      }
    } else {
      voiceSendAfterStop = false;
      voiceChunks = [];
    }
  }

  function cancelVoiceRecording(reason){
    if(!voiceRecording){
      activeVoiceKeyCode = null;
      return;
    }
    activeVoiceKeyCode = null;
    stopVoiceRecording({ reason, cancel: true });
  }

  async function sendVoiceMessage(blob){
    const fd = new FormData();
    fd.append('body', '');
    fd.append('voice', blob, `Funk-${Date.now()}.webm`);
    fd.append('voice_duration', String(lastVoiceDuration || 0));

    try {
      await api('send.php', { method: 'POST', body: fd });
      setPttMessage('Funk gesendet ✅', { mode: 'success', timeout: 2400 });
    } catch(err){
      setPttMessage('Funk konnte nicht gesendet werden', { mode: 'error' });
      console.warn('Funk send Fehler', err);
    }
  }

  function handleVoiceKeyDown(ev){
    if(ev.repeat) return;
    if(ev.key !== '#') return;
    if(popup.dataset.open !== '1') return;
    if(!voiceSupported){
      setPttMessage('Funk nicht verfügbar (Browser)', { mode: 'error' });
      return;
    }
    ev.preventDefault();
    activeVoiceKeyCode = ev.code || '#';
    startVoiceRecording();
  }

  function handleVoiceKeyUp(ev){
    const matchesKey = ev.key === '#' || (activeVoiceKeyCode && ev.code === activeVoiceKeyCode);
    if(!matchesKey) return;
    activeVoiceKeyCode = null;
    if(voiceRecording){
      ev.preventDefault();
      stopVoiceRecording();
    }
  }

  // ✅ "Woop" Ton
  function playSound(kind = 'default'){
    if(!soundEnabled) return;

    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    if(kind === 'mention'){
      osc.type = 'triangle';
      osc.frequency.setValueAtTime(720, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(1020, ctx.currentTime + 0.18);
      gain.gain.setValueAtTime(0.42, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.32);
    } else {
      osc.type = 'sine';
      osc.frequency.setValueAtTime(480, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(190, ctx.currentTime + 0.25);
      gain.gain.setValueAtTime(0.35, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
    }

    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    const duration = kind === 'mention' ? 0.35 : 0.25;
    osc.stop(ctx.currentTime + duration);
  }

  // ✅ Sound Toggle Button
  soundBtn?.addEventListener('click', ()=>{
    soundEnabled = !soundEnabled;
    localStorage.setItem('chat_sound', soundEnabled ? 'on' : 'off');
    updateSoundIcon();
  });

  function bottom(){
    if(autoScroll) list.scrollTop = list.scrollHeight;
  }

  list.addEventListener("scroll", () => {
    const distance = list.scrollHeight - list.scrollTop - list.clientHeight;
    autoScroll = (distance < 30);
  });

  function open(){
    popup.hidden = false;
    popup.dataset.open = '1';
    if (!initialized) { init(); initialized = true; }
    cleanup();
    hideMentionList();
    if(voiceSupported){
      setPttMessage(PTT_DEFAULT, { persistent: true });
    } else {
      setPttMessage('Funk nicht verfügbar (Browser)', { mode: 'error', persistent: true });
    }
  }

  function hide(){
    popup.hidden = true;
    popup.dataset.open = '0';
    cancelVoiceRecording('Aufnahme beendet');
    hideMentionList();
  }

  async function api(p, opts){
    const r = await fetch('/bennys/chat/api/'+p, {credentials:'same-origin', ...opts});
    let j;
    try { j = await r.json(); }
    catch(e){ throw new Error("server_invalid_json"); }
    if(j.error === "empty_message"){ return { ok:false, empty:true }; }
    if(!j.ok) throw new Error(j.error || "unknown_error");
    return j;
  }

  function node(m){
    const isMe = Number(m.user_id) === Number(selfId);

    const el = document.createElement('div');
    el.className = 'bc-msg' + (isMe ? ' me' : '');
    el.dataset.id = m.id;

    const avatar = document.createElement('img');
    avatar.className = 'bc-avatar';
    avatar.src = m.avatar_url || 'pics/default-avatar.png';

    const content = document.createElement('div');
    content.className = 'bc-content';

    if(m.type==='image' && m.file_path){
      const img = document.createElement('img');
      img.src = m.file_path.startsWith('/') ? m.file_path : ('/bennys/chat/' + m.file_path.replace(/^\/?bennys\/chat\//,''));
      content.appendChild(img);
    }

    if(m.type==='voice' && m.file_path){
      el.classList.add('voice');
      const wrap = document.createElement('div');
      wrap.className = 'bc-voice';

      const label = document.createElement('div');
      label.className = 'bc-voice-label';
      const secs = Number(m.duration_seconds || 0);
      label.textContent = 'Funk' + (secs ? ` • ${secs}s` : '');

      const audio = document.createElement('audio');
      audio.controls = true;
      audio.preload = 'metadata';
      audio.controlsList = 'nodownload noplaybackrate';
      audio.src = m.file_path.startsWith('/') ? m.file_path : ('/bennys/chat/' + m.file_path.replace(/^\/?bennys\/chat\//,''));
      audio.addEventListener('loadedmetadata', () => {
        if(!secs && Number.isFinite(audio.duration) && audio.duration > 0){
          const dur = Math.max(1, Math.round(audio.duration));
          label.textContent = 'Funk • ' + dur + 's';
        }
      });

      wrap.appendChild(label);
      wrap.appendChild(audio);
      content.appendChild(wrap);
    }

    let mentionData = { html: '', mentions: [] };
    if(m.body){
      mentionData = renderBody(m.body);
      const t = document.createElement('div');
      t.className = 'bc-text';
      t.innerHTML = mentionData.html;
      content.appendChild(t);
    }

    const mentionsMe = !isMe && isMentionForMe(mentionData.mentions);
    if(mentionsMe){
      el.classList.add('mention-me');
      el.dataset.mentionMe = '1';
    }

    const meta = document.createElement('div');
    meta.className = 'bc-meta';
    const d = new Date(m.created_at);
    meta.textContent = (isMe ? 'Ich' : (m.display_name || ('User#' + m.user_id))) + ' • ' +
                       d.toLocaleString('de-DE', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    content.appendChild(meta);

    if(m.can_delete == 1){
      const del = document.createElement('button');
      del.className = 'bc-del';
      del.textContent = '🗑️';
      del.title = 'Nachricht löschen';
      del.onclick = () => deleteMessage(m.id, el);
      content.appendChild(del);
    }

    el.appendChild(avatar);
    el.appendChild(content);

    return el;
  }

  async function deleteMessage(id, el){
    if(!confirm("Nachricht wirklich löschen?")) return;
    try { await api('delete.php?id='+id, { method:'POST' }); }
    catch(e){ alert('Fehler beim Löschen: '+e.message); return; }
    el.remove();
  }

  async function cleanup(){
    const today = new Date().toISOString().slice(0,10);
    const lastRun = localStorage.getItem('chat_cleanup_last');

    if(lastRun === today) return;

    try {
      const r = await fetch('/bennys/chat/api/cleanup.php', { credentials:'same-origin' });
      const j = await r.json();

      if (j.deleted > 0) {
        list.innerHTML = '';
        sinceId = 0;
        init();
      }

      localStorage.setItem('chat_cleanup_last', today);

    } catch(e){
    }
  }

  async function init(){
    const r = await api('init.php');
    selfId = Number(r.self_id);
    setSelfIdentity(r);
    setMentionCatalog(r.mention_catalog);
    const h = await api('history.php?limit=120');
    list.innerHTML=''; sinceId=0;
    (h.messages||[]).forEach(m=>{
      m.user_id=Number(m.user_id);
      const el = node(m);
      list.appendChild(el);
      if(m.id>sinceId) sinceId=m.id;
    });
    bottom();
    start();
  }

  async function poll(){
    try{
      const j = await api('poll.php?since_id='+sinceId);
      if(j.messages && j.messages.length){

        let hasForeignMessage = false;
        let mentionPing = false;

        j.messages.forEach(m=>{
          m.user_id=Number(m.user_id);
          const el = node(m);
          list.appendChild(el);
          if(m.id>sinceId) sinceId=m.id;

          if(m.user_id !== selfId){
            hasForeignMessage = true;
            if(el.dataset.mentionMe === '1'){
              mentionPing = true;
            }
          }
        });

        if(mentionPing){
          playSound('mention');
        } else if(hasForeignMessage){
          playSound();
        }

        if(popup.dataset.open!=='1'){
          bubble.classList.add('bc-pulse');
          setTimeout(()=>bubble.classList.remove('bc-pulse'),800);
        }
        bottom();
      }
    }catch(e){ }
  }

  function start(){ if(timer) clearInterval(timer); timer=setInterval(poll, 1000); }

  if(input){
    input.addEventListener('input', handleMentionInputEvent);
    input.addEventListener('keydown', ev => {
      handleMentionKeyDown(ev);
      if(ev.key === 'Backspace' || ev.key === 'Delete'){
        setTimeout(handleMentionInputEvent, 0);
      }
    });
    input.addEventListener('keyup', handleMentionKeyNavigation);
    input.addEventListener('click', handleMentionInputEvent);
    input.addEventListener('focus', handleMentionInputEvent);
    input.addEventListener('blur', () => setTimeout(hideMentionList, 120));
  }

  if(mentionBox){
    mentionBox.addEventListener('mousedown', ev => ev.preventDefault());
    mentionBox.addEventListener('click', ev => {
      const target = ev.target.closest('.item');
      if(!target) return;
      ev.preventDefault();
      const idx = Number(target.dataset.index);
      if(!isNaN(idx)){
        pickMention(idx);
      }
    });
  }

  window.addEventListener('resize', updateMentionPosition);

  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const fd = new FormData(form);
    const text = (fd.get('body') || '').toString().trim();
    if(text.length === 0 && !file.files.length){ return; }
    try { await api('send.php', { method:'POST', body:fd }); }
    catch(e){ if(e.message!=='empty_message') alert('Senden fehlgeschlagen: '+e.message); return; }
    input.value=''; file.value='';
    hideMentionList();
  });

  bubble.addEventListener('click', () => popup.dataset.open === '1' ? hide() : open());
  close.addEventListener('click', hide);

  emojiBtn.addEventListener('click', () => {
  picker.hidden = !picker.hidden;
});

picker.addEventListener('click', (ev) => {
  if (ev.target.textContent) {
    input.value += ev.target.textContent;
    picker.hidden = true;
    input.focus();
  }
});

  document.addEventListener('keydown', handleVoiceKeyDown);
  document.addEventListener('keyup', handleVoiceKeyUp);
  window.addEventListener('blur', () => cancelVoiceRecording('Aufnahme beendet'));
  document.addEventListener('visibilitychange', () => {
    if(document.hidden) cancelVoiceRecording('Aufnahme beendet');
  });

  if(pttIndicator){
    if(voiceSupported){
      setPttMessage(PTT_DEFAULT, { persistent: true });
    } else {
      setPttMessage('Funk nicht verfügbar (Browser)', { mode: 'error', persistent: true });
    }
  }

})();
