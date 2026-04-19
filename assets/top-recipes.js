(function () {
  'use strict';

  var PHASES = [
    { key: 'vloeibaar',     label: '🥣 Vloeibare fase' },
    { key: 'gepureerd',     label: '🥘 Gepureerde fase' },
    { key: 'vaste-voeding', label: '🍽️ Vaste voeding fase' }
  ];

  function starsHTML(avg) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
      if (avg >= i) {
        out += '<span style="color:#f5a623">\u2605</span>';
      } else if (avg >= i - 0.5) {
        out += '<span style="position:relative;display:inline-block;color:#ddd;">\u2605'
             + '<span style="position:absolute;left:0;top:0;width:50%;overflow:hidden;color:#f5a623;">\u2605</span>'
             + '</span>';
      } else {
        out += '<span style="color:#ddd;">\u2605</span>';
      }
    }
    return out;
  }

  function el(tag, cls) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    return e;
  }

  function renderCard(phase, data) {
    var card = el('a', 'top-recipe-card');
    card.href = data.url;
    card.setAttribute('data-phase', phase.key);

    // Image
    if (data.image) {
      var img = document.createElement('img');
      img.className = 'top-recipe-img';
      img.src = data.image;
      img.alt = data.short_title || data.title || '';
      img.loading = 'lazy';
      card.appendChild(img);
    }

    var body = el('div', 'top-recipe-body');

    // Category line (reuses badge styling from hub)
    var cat = el('div', 'top-recipe-cat');
    cat.textContent = data.cat || phase.label;
    body.appendChild(cat);

    // Title
    var title = el('h3', 'top-recipe-title');
    title.textContent = data.short_title || data.title || '';
    body.appendChild(title);

    // Description
    if (data.description) {
      var desc = el('p', 'top-recipe-desc');
      desc.textContent = data.description;
      body.appendChild(desc);
    }

    // Macros
    if (data.macros && data.macros.length) {
      var macrosDiv = el('div', 'top-recipe-macros');
      data.macros.forEach(function (m) {
        var pill = el('span', 'macro-pill');
        pill.textContent = m;
        macrosDiv.appendChild(pill);
      });
      body.appendChild(macrosDiv);
    }

    // Rating row
    var ratingDiv = el('div', 'top-recipe-rating');

    var starsSpan = el('span', 'top-recipe-stars');
    starsSpan.innerHTML = starsHTML(data.avg);

    var valSpan = el('span', 'top-recipe-value');
    valSpan.textContent = data.avg.toFixed(1);

    var countSpan = el('span', 'top-recipe-count');
    countSpan.textContent = '(' + data.count + ' beoordelingen)';

    ratingDiv.appendChild(starsSpan);
    ratingDiv.appendChild(valSpan);
    ratingDiv.appendChild(countSpan);
    body.appendChild(ratingDiv);

    // Link label
    var link = el('span', 'top-recipe-link');
    link.textContent = 'Bekijk recept \u2192';
    body.appendChild(link);

    card.appendChild(body);
    return card;
  }

  function init() {
    var grid = document.getElementById('top-recipes-grid');
    if (!grid) return;

    fetch('/api/top-recipes.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        grid.innerHTML = '';

        var hasAny = false;
        PHASES.forEach(function (phase) {
          var info = data[phase.key];
          if (!info) return;
          hasAny = true;
          grid.appendChild(renderCard(phase, info));
        });

        if (!hasAny) {
          grid.innerHTML = '<p class="top-recipes-loading">Nog geen beoordelingen beschikbaar.</p>';
        }
      })
      .catch(function () {
        grid.innerHTML = '';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
