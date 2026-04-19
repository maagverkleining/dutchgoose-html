(function () {
  'use strict';

  var API = '/api/ratings.php';

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'cls') node.className = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else if (k === 'text') node.textContent = attrs[k];
        else node.setAttribute(k, attrs[k]);
      });
    }
    if (children) children.forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function starsDisplay(n, total) {
    total = total || 5;
    var out = '';
    for (var i = 1; i <= total; i++) {
      out += i <= Math.round(n) ? '\u2605' : '\u2606';
    }
    return out;
  }

  function starsHalf(n) {
    // fractional display with filled/half/empty
    var out = '';
    for (var i = 1; i <= 5; i++) {
      if (n >= i) out += '\u2605';
      else if (n >= i - 0.5) out += '\u2BD0';
      else out += '\u2606';
    }
    return out;
  }

  function updateSchema(average, count) {
    if (count < 3) return;
    var schemas = document.querySelectorAll('script[type="application/ld+json"]');
    schemas.forEach(function (s) {
      try {
        var data = JSON.parse(s.textContent);
        var recipeNode = null;
        if (data['@type'] === 'Recipe') {
          recipeNode = data;
        } else if (Array.isArray(data['@graph'])) {
          recipeNode = data['@graph'].find(function (x) { return x['@type'] === 'Recipe'; });
        }
        if (recipeNode) {
          recipeNode.aggregateRating = {
            '@type': 'AggregateRating',
            ratingValue: average.toFixed(1),
            reviewCount: count,
            bestRating: '5',
            worstRating: '1'
          };
          s.textContent = JSON.stringify(data);
        }
      } catch (e) { /* malformed schema, ignore */ }
    });
  }

  function buildStarInput(onSelect) {
    var selected = 0;
    var buttons = [];

    var group = el('div', {
      cls: 'rw-stars-input',
      role: 'radiogroup',
      'aria-label': 'Geef een beoordeling van 1 tot 5 sterren'
    });

    for (var i = 1; i <= 5; i++) {
      (function (n) {
        var btn = el('button', {
          cls: 'rw-star',
          type: 'button',
          'aria-label': n + ' ster' + (n > 1 ? 'ren' : ''),
          'data-val': n,
          text: '\u2606'
        });

        btn.addEventListener('mouseenter', function () {
          if (selected) return;
          buttons.forEach(function (b, idx) {
            b.textContent = idx < n ? '\u2605' : '\u2606';
            b.classList.toggle('rw-star--lit', idx < n);
          });
        });

        btn.addEventListener('mouseleave', function () {
          if (selected) return;
          buttons.forEach(function (b) {
            b.textContent = '\u2606';
            b.classList.remove('rw-star--lit');
          });
        });

        btn.addEventListener('click', function () {
          selected = n;
          buttons.forEach(function (b, idx) {
            b.textContent = idx < n ? '\u2605' : '\u2606';
            b.classList.toggle('rw-star--lit', idx < n);
            b.setAttribute('aria-pressed', idx < n ? 'true' : 'false');
          });
          onSelect(n);
        });

        buttons.push(btn);
        group.appendChild(btn);
      }(i));
    }

    group.reset = function () {
      selected = 0;
      buttons.forEach(function (b) {
        b.textContent = '\u2606';
        b.classList.remove('rw-star--lit');
        b.setAttribute('aria-pressed', 'false');
      });
    };

    return group;
  }

  function buildForm(recipeUrl, recipeTitle, onSuccess) {
    var chosenStars = 0;

    var wrap = el('div', { cls: 'rw-form' });
    var heading = el('h3', { cls: 'rw-form-heading', text: 'Schrijf een beoordeling' });
    wrap.appendChild(heading);

    var starLabel = el('p', { cls: 'rw-form-label', text: 'Jouw beoordeling' });
    wrap.appendChild(starLabel);

    var starInput = buildStarInput(function (n) {
      chosenStars = n;
      submitBtn.disabled = false;
    });
    wrap.appendChild(starInput);

    var nameInput = el('input', {
      type: 'text',
      name: 'name',
      placeholder: 'Voornaam (optioneel)',
      maxlength: '50',
      autocomplete: 'given-name'
    });
    wrap.appendChild(nameInput);

    var commentInput = el('textarea', {
      name: 'comment',
      placeholder: 'Jouw ervaring met dit recept (optioneel)',
      maxlength: '1000',
      rows: '3'
    });
    wrap.appendChild(commentInput);

    // Honeypot
    var hpWrap = el('div', { cls: 'rw-hp' });
    var hpInput = el('input', {
      type: 'text',
      name: 'website',
      tabindex: '-1',
      autocomplete: 'off'
    });
    hpWrap.appendChild(hpInput);
    wrap.appendChild(hpWrap);

    var submitBtn = el('button', {
      cls: 'rw-submit',
      type: 'button',
      text: 'Beoordeling plaatsen'
    });
    submitBtn.disabled = true;
    wrap.appendChild(submitBtn);

    var errorBox = el('div', { cls: 'rw-error', role: 'alert' });
    errorBox.style.display = 'none';
    wrap.insertBefore(errorBox, heading.nextSibling);

    submitBtn.addEventListener('click', function () {
      if (!chosenStars) return;
      errorBox.style.display = 'none';
      submitBtn.disabled = true;
      submitBtn.textContent = 'Bezig...';

      var body = {
        recipe_url: recipeUrl,
        recipe_title: recipeTitle,
        stars: chosenStars,
        name: nameInput.value.trim(),
        comment: commentInput.value.trim(),
        website: hpInput.value
      };

      fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            onSuccess(data.rating);
          } else {
            errorBox.textContent = data.error || 'Er ging iets mis. Probeer het opnieuw.';
            errorBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Beoordeling plaatsen';
          }
        })
        .catch(function (err) {
          console.error('[rating-widget] POST error:', err);
          errorBox.textContent = 'Kon de beoordeling niet versturen. Controleer je internetverbinding.';
          errorBox.style.display = 'block';
          submitBtn.disabled = false;
          submitBtn.textContent = 'Beoordeling plaatsen';
        });
    });

    return wrap;
  }

  function buildRatingItem(r) {
    var isFounder = r.name && r.name.indexOf('oprichter') !== -1;

    var item = el('div', { cls: 'rw-item' });

    var stars = el('div', {
      cls: 'rw-item-stars',
      text: starsDisplay(r.stars),
      'aria-label': r.stars + ' van de 5 sterren'
    });
    item.appendChild(stars);

    var meta = el('div', { cls: 'rw-item-meta' });
    var nameSpan = el('span', { cls: 'rw-item-name', text: r.name || 'Anoniem' });
    meta.appendChild(nameSpan);

    if (isFounder) {
      var badge = el('span', { cls: 'rw-founder-badge', text: 'oprichter' });
      meta.appendChild(badge);
    }

    var dateSpan = el('span', { cls: 'rw-item-date', text: ' \u00b7 ' + (r.created_at_human || '') });
    meta.appendChild(dateSpan);
    item.appendChild(meta);

    if (r.comment) {
      var comment = el('p', { cls: 'rw-item-comment', text: r.comment });
      item.appendChild(comment);
    }

    return item;
  }

  function buildList(ratings) {
    var wrap = el('div', { cls: 'rw-list' });
    var heading = el('h3', { cls: 'rw-list-heading', text: 'Beoordelingen' });
    wrap.appendChild(heading);

    if (!ratings || ratings.length === 0) {
      var empty = el('p', { cls: 'rw-empty', text: 'Nog geen beoordelingen. Wees de eerste!' });
      wrap.appendChild(empty);
      return wrap;
    }

    var INITIAL = 5;
    var listEl = el('div', { cls: 'rw-items' });

    ratings.forEach(function (r, idx) {
      var item = buildRatingItem(r);
      if (idx >= INITIAL) item.classList.add('rw-item--hidden');
      listEl.appendChild(item);
    });
    wrap.appendChild(listEl);

    if (ratings.length > INITIAL) {
      var moreBtn = el('button', {
        cls: 'rw-show-more',
        text: 'Toon alle ' + ratings.length + ' beoordelingen'
      });
      moreBtn.addEventListener('click', function () {
        listEl.querySelectorAll('.rw-item--hidden').forEach(function (i) {
          i.classList.remove('rw-item--hidden');
        });
        moreBtn.remove();
      });
      wrap.appendChild(moreBtn);
    }

    return wrap;
  }

  function buildHeader(average, count) {
    var wrap = el('div', { cls: 'rw-header' });

    if (count === 0) {
      var noRatings = el('p', { cls: 'rw-header-none', text: 'Nog geen beoordelingen voor dit recept.' });
      wrap.appendChild(noRatings);
      return wrap;
    }

    var avgEl = el('div', { cls: 'rw-avg', text: average.toFixed(1) });
    wrap.appendChild(avgEl);

    var starsEl = el('div', {
      cls: 'rw-avg-stars',
      text: starsHalf(average),
      'aria-label': average.toFixed(1) + ' gemiddeld'
    });
    wrap.appendChild(starsEl);

    var countEl = el('div', { cls: 'rw-count', text: count + ' beoordeling' + (count !== 1 ? 'en' : '') });
    wrap.appendChild(countEl);

    return wrap;
  }

  function initWidget(container) {
    var recipeUrl = container.getAttribute('data-recipe-url');
    var recipeTitle = container.getAttribute('data-recipe-title');

    if (!recipeUrl) return;

    container.classList.add('rw-container');

    var loading = el('p', { cls: 'rw-loading', text: 'Beoordelingen laden...' });
    container.appendChild(loading);

    fetch(API + '?recipe=' + encodeURIComponent(recipeUrl))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        container.removeChild(loading);

        var average = data.average || 0;
        var count = data.count || 0;
        var ratings = data.ratings || [];

        updateSchema(average, count);

        // Header
        container.appendChild(buildHeader(average, count));

        // Divider
        container.appendChild(el('hr', { cls: 'rw-divider' }));

        // Form
        var formWrap = el('div', { cls: 'rw-form-wrap' });

        var form = buildForm(recipeUrl, recipeTitle, function (newRating) {
          // Success
          formWrap.innerHTML = '';
          var successBox = el('div', {
            cls: 'rw-success',
            text: 'Bedankt voor je beoordeling! Hij staat direct in de lijst hieronder.'
          });
          formWrap.appendChild(successBox);

          // Prepend new rating to list
          var listEl = container.querySelector('.rw-items');
          if (listEl && newRating) {
            var newItem = buildRatingItem(newRating);
            listEl.insertBefore(newItem, listEl.firstChild);
          } else if (newRating) {
            // List didn't exist yet (was at 0), rebuild
            var oldList = container.querySelector('.rw-list');
            if (oldList) oldList.remove();
            ratings.unshift(newRating);
            container.appendChild(buildList(ratings));
          }

          // Update header
          var oldHeader = container.querySelector('.rw-header');
          if (oldHeader) {
            count += 1;
            average = ((average * (count - 1)) + newRating.stars) / count;
            var newHeader = buildHeader(average, count);
            container.replaceChild(newHeader, oldHeader);
            updateSchema(average, count);
          }
        });

        formWrap.appendChild(form);
        container.appendChild(formWrap);

        // Divider
        container.appendChild(el('hr', { cls: 'rw-divider' }));

        // List
        container.appendChild(buildList(ratings));
      })
      .catch(function (err) {
        console.error('[rating-widget] fetch error:', err);
        container.removeChild(loading);
        var errEl = el('p', {
          cls: 'rw-error',
          text: 'Beoordelingen konden niet worden geladen.'
        });
        container.appendChild(errEl);
      });
  }

  function init() {
    document.querySelectorAll('[id="rating-widget"]').forEach(initWidget);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
