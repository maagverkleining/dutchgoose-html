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

  function renderCard(phase, data) {
    var card = document.createElement('a');
    card.className = 'top-recipe-card';
    card.href = data.url;
    card.setAttribute('data-phase', phase.key);

    var badge = document.createElement('div');
    badge.className = 'top-recipe-badge';
    badge.textContent = phase.label;

    var title = document.createElement('h3');
    title.className = 'top-recipe-title';
    title.textContent = data.title;

    var ratingDiv = document.createElement('div');
    ratingDiv.className = 'top-recipe-rating';

    var starsSpan = document.createElement('span');
    starsSpan.className = 'top-recipe-stars';
    starsSpan.innerHTML = starsHTML(data.avg);

    var valSpan = document.createElement('span');
    valSpan.className = 'top-recipe-value';
    valSpan.textContent = data.avg.toFixed(1);

    var countSpan = document.createElement('span');
    countSpan.className = 'top-recipe-count';
    countSpan.textContent = '(' + data.count + ' beoordelingen)';

    ratingDiv.appendChild(starsSpan);
    ratingDiv.appendChild(valSpan);
    ratingDiv.appendChild(countSpan);

    var link = document.createElement('span');
    link.className = 'top-recipe-link';
    link.textContent = 'Bekijk recept \u2192';

    card.appendChild(badge);
    card.appendChild(title);
    card.appendChild(ratingDiv);
    card.appendChild(link);

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
