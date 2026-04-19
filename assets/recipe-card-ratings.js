(function () {
  'use strict';

  var BATCH_API = '/api/ratings-batch.php';

  function starsHTML(avg) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
      if (avg >= i) {
        out += '<span style="color:#f59e0b">\u2605</span>';
      } else if (avg >= i - 0.5) {
        out += '<span style="position:relative;display:inline-block;color:#ddd;">\u2605'
             + '<span style="position:absolute;left:0;top:0;width:50%;overflow:hidden;color:#f59e0b;">\u2605</span>'
             + '</span>';
      } else {
        out += '<span style="color:#ddd;">\u2605</span>';
      }
    }
    return out;
  }

  function resolveUrl(href) {
    // Use the browser's URL resolution -- handles relative, absolute, protocol-relative
    try {
      return new URL(href, window.location.href).pathname;
    } catch (e) {
      return href;
    }
  }

  function injectRating(card, data) {
    // Avoid double-inject
    if (card.querySelector('.recipe-card-rating')) return;

    var ratingEl = document.createElement('div');
    ratingEl.className = 'recipe-card-rating';

    var starsSpan = document.createElement('span');
    starsSpan.className = 'recipe-card-stars';
    starsSpan.innerHTML = starsHTML(data.average);

    var valSpan = document.createElement('span');
    valSpan.className = 'recipe-card-rating-value';
    valSpan.textContent = data.average.toFixed(1);

    var countSpan = document.createElement('span');
    countSpan.className = 'recipe-card-rating-count';
    countSpan.textContent = '(' + data.count + ')';

    ratingEl.appendChild(starsSpan);
    ratingEl.appendChild(valSpan);
    ratingEl.appendChild(countSpan);

    // .recipe-card: insert after .recipe-card-macros or .recipe-card-meta inside .recipe-card-body
    var body = card.querySelector('.recipe-card-body');
    if (body) {
      var anchor = body.querySelector('.recipe-card-macros') || body.querySelector('.recipe-card-meta');
      if (anchor && anchor.parentNode === body) {
        anchor.after(ratingEl);
      } else {
        body.appendChild(ratingEl);
      }
      return;
    }

    // .related-card: <a> is the card itself, insert after the last <p> child
    var lastP = null;
    Array.from(card.children).forEach(function (c) {
      if (c.tagName === 'P') lastP = c;
    });
    if (lastP) {
      lastP.after(ratingEl);
    } else {
      card.appendChild(ratingEl);
    }
  }

  function init() {
    var cards = Array.from(document.querySelectorAll('a.recipe-card[href], a.related-card[href]'));
    if (!cards.length) return;

    var urlMap = {}; // pathname -> [card, ...]
    cards.forEach(function (card) {
      var path = resolveUrl(card.getAttribute('href'));
      if (!path.match(/^\/recepten\/.+\.html$/)) return;
      if (!urlMap[path]) urlMap[path] = [];
      urlMap[path].push(card);
    });

    var urls = Object.keys(urlMap);
    if (!urls.length) return;

    fetch(BATCH_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ urls: urls })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var results = data.results || {};
        Object.keys(results).forEach(function (url) {
          var info = results[url];
          if (info.count < 1) return;
          var cardsForUrl = urlMap[url] || [];
          cardsForUrl.forEach(function (card) {
            injectRating(card, info);
          });
        });
      })
      .catch(function (err) {
        console.error('[recipe-card-ratings] fetch error:', err);
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
