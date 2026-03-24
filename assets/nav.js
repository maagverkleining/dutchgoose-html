(function() {
  const nav = `
<nav>
  <a href="/" class="logo">
    <img src="/assets/dutch-goose-logo-cropped.png" alt="Dutch Goose logo" width="46" height="46">
    <div class="logo-copy">
      <span class="lt">Dutch Goose</span>
      <span class="ls">JOUW GIDS VOOR MAAGVERKLEINING</span>
    </div>
  </a>
  <ul class="nl">
    <li><a href="/deals.html">🏷️ Deals</a></li>
    <li class="nd"><a href="/vitamines.html">💊 Vitamines ▾</a>
      <div class="nd-menu">
        <a href="/vitamines.html#verplicht">💊 Verplichte supplementen</a>
        <a href="/vitamines.html#timing">⏰ Timing &amp; inname</a>
        <a href="/vitamines.html#bloedwaarden">🩸 Bloedwaarden</a>
        <a href="/vitamines.html#merken">🏷️ Merken vergelijken</a>
        <a href="/vitamines.html#bypass">🥝 Bypass vs sleeve</a>
      </div>
    </li>
    <li class="nd"><a href="/blog.html">📚 Kennisbank ▾</a>
      <div class="nd-menu">
        <a href="/blog.html#voeding">🍽️ Voeding &amp; eten</a>
        <a href="/blog.html#vitamines">💊 Vitamines</a>
        <a href="/blog.html#herstel">🏥 Herstel</a>
        <a href="/blog.html#mentaal">🧠 Mentaal</a>
      </div>
    </li>
    <li class="nd"><a href="/recepten/">🍽️ Recepten ▾</a>
      <div class="nd-menu">
        <a href="/recepten/vloeibaar/">💧 Vloeibaar</a>
        <a href="/recepten/gepureerd/">🫘 Gepureerd</a>
        <a href="/recepten/vaste-voeding/">🥗 Vaste voeding</a>
      </div>
    </li>
    <li class="nd"><a href="/tools.html">🔧 Tools ▾</a>
      <div class="nd-menu">
        <a href="/tools.html#eiwit">🥙 Eiwit calculator</a>
        <a href="/tools.html#fase">🥘 Fase checker</a>
        <a href="/tools.html#timer">⏳ Eet timer</a>
        <a href="/tools/bypass-vs-sleeve.html">🥝 Bypass vs sleeve</a>
        <a href="/tools/bloedwaarden.html">📅 Bloedwaarden checklist</a>
        <a href="https://www.wlsvitaminen.nl">💊 Vitamines vergelijken</a>
      </div>
    </li>
    <li class="nd"><a href="/orienteren.html">🔍 Oriënteren ▾</a>
      <div class="nd-menu">
        <a href="/klinieken/">🏥 Klinieken vergelijker</a>
        <a href="/gastric-bypass.html">🥝 Gastric bypass</a>
        <a href="/gastric-sleeve.html">🍌 Gastric sleeve</a>
        <a href="/criteria.html">✅ Kom ik in aanmerking?</a>
        <a href="/vergoeding.html">💶 Vergoeding &amp; verzekering</a>
        <a href="/maagverkleining-buitenland.html">🌍 Maagverkleining buitenland</a>
      </div>
    </li>
    <li class="nd"><a href="/community.html">👥 Community ▾</a>
      <div class="nd-menu">
        <a href="/community.html#whatsapp">💬 WhatsApp</a>
        <a href="/community.html#facebook">👥 Facebook</a>
        <a href="/community.html#discord">🎮 Discord</a>
        <a href="/over-david.html">🦆 Over David</a>
      </div>
    </li>
  </ul>
  <div class="nav-actions">
    <a href="/community.html#join-baribuddies" class="nc">🥝 Join BariBuddies</a>
    <button class="ham" onclick="document.querySelector('nav .nl').classList.toggle('mob-open')" aria-label="Menu openen"><span></span><span></span><span></span><em>Menu</em></button>
  </div>
</nav>`;

  // Vervang de bestaande nav
  const existing = document.querySelector('nav');
  if (existing) {
    existing.outerHTML = nav;
  } else {
    document.body.insertAdjacentHTML('afterbegin', nav);
  }

  // Injecteer mobiele nav CSS
  const style = document.createElement('style');
  style.textContent = `
    @media(max-width:900px) {
      nav .nl.mob-open {
        display: flex !important;
        flex-direction: column !important;
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        background: white !important;
        border-bottom: 2px solid var(--tm) !important;
        padding: .5rem 5vw 1rem !important;
        box-shadow: 0 8px 24px rgba(26,124,107,.12) !important;
        z-index: 99 !important;
        gap: .15rem !important;
      }
      nav .nl.mob-open li {
        width: 100% !important;
      }
      nav .nl.mob-open li a {
        display: block !important;
        padding: .65rem .75rem !important;
        font-size: .95rem !important;
        border-radius: 8px !important;
      }
      nav .nl.mob-open .nd-menu {
        display: none !important;
      }
      nav .nl.mob-open .nd.open .nd-menu {
        display: flex !important;
        flex-direction: column !important;
        position: static !important;
        box-shadow: none !important;
        border: none !important;
        padding: 0 0 0 1rem !important;
        background: var(--tp) !important;
        border-radius: 8px !important;
        margin: .2rem 0 .4rem !important;
      }
      nav .nl.mob-open .nd-menu a {
        font-size: .85rem !important;
        padding: .45rem .75rem !important;
        color: var(--t) !important;
      }
    }
  `;
  document.head.appendChild(style);

  // Mobiele submenu toggle
  document.querySelector('nav').addEventListener('click', function(e) {
    if (window.innerWidth > 900) return;
    const nd = e.target.closest('.nd');
    if (!nd) return;
    const link = e.target.closest('a');
    if (link && link.parentElement === nd) {
      // Klik op het hoofditem — toggle submenu
      e.preventDefault();
      nd.classList.toggle('open');
    }
  });

  // Markeer actieve pagina
  const path = window.location.pathname;
  document.querySelectorAll('nav .nl a').forEach(a => {
    if (a.getAttribute('href') === path ||
        (a.getAttribute('href') !== '/' && path.startsWith(a.getAttribute('href')))) {
      a.classList.add('on');
    }
  });
})();
