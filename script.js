// === INITIALISIERUNG NACH DOM ===
document.addEventListener("DOMContentLoaded", () => {

  /* ------------------------------
     SECTION FADE-IN BEIM SCROLLEN
  ------------------------------- */
  const sections = document.querySelectorAll('section');
  if (sections.length > 0) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.1 });
    sections.forEach(s => observer.observe(s));
  }

  /* ------------------------------
     INVENTAR-TABELLEN: SUCHE & FILTER
  ------------------------------- */
  const inventoryPanels = document.querySelectorAll('[data-inventory]');
  inventoryPanels.forEach(panel => {
    const table = panel.querySelector('[data-inventory-table]');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = panel.querySelector('[data-table-search]');
    const filterButton = panel.querySelector('[data-table-filter="low-stock"]');
    const emptyState = panel.querySelector('[data-empty-state]');

    const applyFilters = () => {
      const query = (searchInput?.value || '').trim().toLowerCase();
      const onlyLowStock = filterButton?.classList.contains('is-active');
      let visibleRows = 0;

      rows.forEach(row => {
        const productCell = row.querySelector('td');
        const amountCell = row.querySelector('td:nth-child(2)');
        const matchesSearch = !query || (productCell && productCell.textContent.toLowerCase().includes(query));
        const matchesLowStock = !onlyLowStock || (amountCell && amountCell.classList.contains('low-stock'));
        const shouldShow = matchesSearch && matchesLowStock;
        row.style.display = shouldShow ? '' : 'none';
        if (shouldShow) visibleRows++;
      });

      if (emptyState) {
        emptyState.hidden = visibleRows !== 0;
      }
    };

    searchInput?.addEventListener('input', applyFilters);
    filterButton?.addEventListener('click', () => {
      filterButton.classList.toggle('is-active');
      applyFilters();
    });

    applyFilters();
  });

  /* ------------------------------
     PARALLAX HERO
  ------------------------------- */
  const hero = document.querySelector('.hero');
  window.addEventListener('scroll', () => {
    if (hero) hero.style.backgroundPositionY = window.pageYOffset * 0.5 + 'px';
  });

  /* ------------------------------
     DROPDOWN MENÜ FUNKTION
  ------------------------------- */
  const menuToggle = document.querySelector('.menu-toggle');
  const dropdown = document.getElementById('mainMenu');
  if (menuToggle && dropdown) {
    menuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && !menuToggle.contains(e.target)) {
        dropdown.classList.remove('show');
      }
    });
  }

  /* ------------------------------
     SCROLL TO TOP BUTTON
  ------------------------------- */
  const toTop = document.getElementById('toTop');
  if (toTop) {
    toTop.addEventListener('click', (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ------------------------------
     NEWS POPUP (mit Sound + Auto)
  ------------------------------- */
  // --- Pfadlogik für alle Bereiche ---
const path = window.location.pathname.toLowerCase();

// Prüfen, wo wir uns befinden
const isAdmin = path.includes("/admin/");
const isArcade = path.includes("/arcade/");
const isCalendar = path.includes("/calendar")
// Richtigen Basis-Pfad wählen
let base = "includes/";

if (isAdmin) {
  base = "../includes/"; // Admin liegt 1 Ordner tiefer
} else if (isArcade) {
  base = "../includes/"; // Arcade liegt auch 1 Ordner tiefer
}else if (isCalendar) {
  base = "../includes/"; // Calendar liegt auch 1 Ordner tiefer
}
;

  // --- dezenter Popup-Sound via Web Audio ---
  let audioCtx;
  function playPopupSound() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();

      const o = audioCtx.createOscillator();
      const g = audioCtx.createGain();

      o.type = 'sine';
      o.frequency.setValueAtTime(880, audioCtx.currentTime);
      o.frequency.exponentialRampToValueAtTime(1760, audioCtx.currentTime + 0.12);
      g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.08, audioCtx.currentTime + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.22);

      o.connect(g);
      g.connect(audioCtx.destination);
      o.start();
      o.stop(audioCtx.currentTime + 0.25);
    } catch (e) {
      console.warn("Sound konnte nicht abgespielt werden:", e);
    }
  }

  async function checkNews() {
    try {
      const res = await fetch(base + "check_news_popup.php", { cache: "no-store" });
      if (!res.ok) return;
      const data = await res.json();
      if (data.new_news && data.new_news > 0) showPopup(data.new_news);
    } catch (err) {
      console.warn("News check failed:", err);
    }
  }

  function showPopup(count) {
    if (document.getElementById("news-popup")) return;

    playPopupSound();

    const overlay = document.createElement("div");
    overlay.id = "news-popup";
    overlay.innerHTML = `
      <div class="news-popup-inner">
        <h3>📰 Neue News verfügbar!</h3>
        <p>Es gibt <b>${count}</b> neue Ankündigung${count > 1 ? "en" : ""}.</p>
        <div class="popup-buttons">
          <a href="#" id="showNewsBtn" class="btn-primary">➡️ Anzeigen</a>
          <button id="closeNewsPopup" class="btn-ghost">Schließen</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const closeAndSeen = () => {
      overlay.remove();
      fetch(base + "update_news_seen.php");
    };

    // ✅ Nur einfacher Redirect (kein AJAX)
    document.getElementById("showNewsBtn").addEventListener("click", (e) => {
     e.preventDefault();
     closeAndSeen();

      const path = window.location.pathname.toLowerCase();
     const isAdmin = path.includes("/admin/");
      const isArcade = path.includes("/arcade/");

    // Ziel immer das Hauptverzeichnis
       let target = "index.php#news";

        if (isAdmin) target = "../" + target;
        if (isArcade) target = "../" + target; // <-- Arcade liegt 1 Ordner tiefer

       window.location.href = target;
    });

    document.getElementById("closeNewsPopup").addEventListener("click", closeAndSeen);
  }

  // --- Styling ---
  const css = document.createElement("style");
  css.textContent = `
    #news-popup {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.65);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease forwards;
    }
    @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }
    .news-popup-inner {
      background: rgba(25,25,25,0.95);
      border: 2px solid #39ff14;
      border-radius: 15px;
      padding: 30px;
      text-align: center;
      color: #fff;
      max-width: 420px;
      width: 90%;
      box-shadow: 0 0 25px rgba(57,255,20,0.6);
    }
    .popup-buttons {
      margin-top: 20px;
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .btn-primary {
      background: linear-gradient(90deg,#39ff14,#76ff65);
      color: #fff;
      border: none;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: bold;
      text-decoration: none;
      cursor: pointer;
      box-shadow: 0 0 12px rgba(57,255,20,.45);
      transition: transform .2s ease;
    }
    .btn-ghost {
      background: rgba(57,255,20,0.1);
      border: 1px solid rgba(57,255,20,0.4);
      color: #a8ffba;
      border-radius: 10px;
      padding: 10px 18px;
      cursor: pointer;
      font-weight: bold;
      transition: transform .2s ease;
    }
    .btn-primary:hover, .btn-ghost:hover {
      transform: scale(1.05);
    }
  `;
  document.head.appendChild(css);

  // --- Intervall für automatische Prüfung ---
  checkNews();
  setInterval(checkNews, 30000);
  if (window.location.search.includes("success=1")) {
    setTimeout(() => checkNews(), 1200);
  }
});
