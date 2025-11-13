// === INITIALISIERUNG NACH DOM ===
document.addEventListener("DOMContentLoaded", () => {

  /* ------------------------------
     HEADER OFFSET SYNCHRONISIEREN
  ------------------------------- */
  const headerEl = document.querySelector(".site-header");
  const headerOffsetEl = document.querySelector(".header-offset");
  const syncHeaderOffset = () => {
    if (!headerEl || !headerOffsetEl) return;
    headerOffsetEl.style.height = `${headerEl.offsetHeight}px`;
  };

  if (headerEl && headerOffsetEl) {
    syncHeaderOffset();
    window.addEventListener("load", syncHeaderOffset);
    window.addEventListener("resize", syncHeaderOffset);

    const bannerImg = headerEl.querySelector(".brand-banner");
    if (bannerImg && !bannerImg.complete) {
      bannerImg.addEventListener("load", syncHeaderOffset, { once: true });
    }

    if (typeof ResizeObserver !== "undefined") {
      const resizeObserver = new ResizeObserver(() => syncHeaderOffset());
      resizeObserver.observe(headerEl);
    }
  }

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
     PARALLAX HERO
  ------------------------------- */
  const hero = document.querySelector('.hero');
  window.addEventListener('scroll', () => {
    if (hero) hero.style.backgroundPositionY = window.pageYOffset * 0.5 + 'px';
  });

  /* ------------------------------
     DROPDOWN MENÜ FUNKTION
  ------------------------------- */
  const dropdownToggles = Array.from(document.querySelectorAll('[data-dropdown-toggle]'));
  const dropdownPairs = dropdownToggles
    .map((toggle) => {
      const targetId = toggle.getAttribute('data-dropdown-toggle');
      if (!targetId) return null;
      const dropdownEl = document.getElementById(targetId);
      if (!dropdownEl) return null;
      toggle.setAttribute('aria-expanded', 'false');
      return { toggle, dropdown: dropdownEl };
    })
    .filter(Boolean);

  const setDropdownState = (pair, isOpen) => {
    pair.dropdown.classList.toggle('show', isOpen);
    pair.toggle.classList.toggle('is-open', isOpen);
    pair.toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  };

  const closeAllDropdowns = (except = null) => {
    dropdownPairs.forEach((pair) => {
      if (except && pair.dropdown === except) {
        return;
      }
      setDropdownState(pair, false);
    });
  };

  dropdownPairs.forEach((pair) => {
    pair.toggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const willOpen = !pair.dropdown.classList.contains('show');
      if (willOpen) {
        closeAllDropdowns(pair.dropdown);
      } else {
        closeAllDropdowns();
      }
      setDropdownState(pair, willOpen);
    });
  });

  if (dropdownPairs.length > 0) {
    document.addEventListener('click', (event) => {
      const clickedInside = dropdownPairs.some((pair) =>
        pair.dropdown.contains(event.target) || pair.toggle.contains(event.target)
      );
      if (!clickedInside) {
        closeAllDropdowns();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeAllDropdowns();
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
    .news-popup-inner {␊
      background: rgba(25,25,25,0.95);␊
      border: 2px solid var(--accent, #2ad977);
      border-radius: 15px;
      padding: 30px;
      text-align: center;
      color: #fff;
      max-width: 420px;
      width: 90%;
      box-shadow: 0 0 25px rgba(var(--accent-pop-rgb, 118,255,101),0.6);
    }
    .popup-buttons {
      margin-top: 20px;
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .btn-primary,
    .btn-ghost {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 22px;
      border-radius: 12px;
      border: 1px solid var(--button-border, rgba(var(--accent-pop-rgb, 118,255,101),0.35));
      background: var(--button-bg, rgba(var(--accent-pop-rgb, 118,255,101),0.1))
      color: var(--button-color, rgba(210,255,215,0.9));
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      transition: var(--transition, all 0.25s ease);
    }
    .btn-primary:hover,
    .btn-primary:focus-visible,
    .btn-ghost:hover,
    .btn-ghost:focus-visible {
      background: var(
        --button-hover-bg,
        linear-gradient(132deg, rgba(42,217,119,0.34), rgba(118,255,101,0.26))
      );
      color: var(--button-hover-color, #041104);
      box-shadow: var(
        --button-hover-shadow,
        0 18px 36px rgba(17,123,69,0.26), inset 0 0 22px rgba(118,255,101,0.24)
      );
      transform: var(--button-hover-transform, translateY(-3px) scale(1.02));
      border-color: var(--button-hover-border, rgba(42,217,119,0.6));
      outline: none;
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
