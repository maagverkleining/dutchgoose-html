(function() {
  function getAdvertiser(href) {
    if (href.includes('vitaminenspecialist.nl')) {
      return { advertiser: 'vitaminenspecialist', type: href.includes('dgproefpakket') ? 'proefpakket' : 'regulier', network: 'direct' };
    }
    if (href.includes('serv.linkster.co')) {
      return { advertiser: 'ahead', type: 'snacks', network: 'linkster' };
    }
    if (href.includes('fr135.net')) {
      if (href.includes('si=3366'))  return { advertiser: 'foliactive', type: 'capsules', network: 'daisycon' };
      if (href.includes('si=15237')) return { advertiser: 'nutribites', type: 'supplementen', network: 'daisycon' };
    }
    if (href.includes('bdt9.net') && href.includes('si=18019')) {
      return { advertiser: 'dropwinkel', type: 'suikervrij', network: 'daisycon' };
    }
    if (href.includes('glp8.net')) {
      if (href.includes('si=19859')) return { advertiser: 'de_goedkoopste_outlet', type: 'kleding', network: 'daisycon' };
      if (href.includes('si=20150')) return { advertiser: 'internetslagerij', type: 'vlees', network: 'daisycon' };
    }
    if (href.includes('jdt8.net') && href.includes('si=17660')) {
      return { advertiser: 'the_butchery', type: 'vlees', network: 'daisycon' };
    }
    if (href.includes('awin1.com') && href.includes('awinmid=117963')) {
      return { advertiser: 'fitmeals', type: 'maaltijden', network: 'awin' };
    }
    if (href.includes('nutribites.nl'))        return { advertiser: 'nutribites', type: 'supplementen', network: 'direct' };
    if (href.includes('butchery.nl'))          return { advertiser: 'the_butchery', type: 'vlees', network: 'direct' };
    if (href.includes('internetslagerij.nl'))  return { advertiser: 'internetslagerij', type: 'vlees', network: 'direct' };
    if (href.includes('fitmeals.nl'))          return { advertiser: 'fitmeals', type: 'maaltijden', network: 'direct' };
    if (href.includes('dropwinkel.eu'))        return { advertiser: 'dropwinkel', type: 'suikervrij', network: 'direct' };
    if (href.includes('plein.nl'))             return { advertiser: 'plein', type: 'supplementen', network: 'direct' };
    return null;
  }

  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    var href = link.href || '';
    if (!href.startsWith('http')) return;
    if (typeof gtag === 'undefined') return;

    var affiliate = getAdvertiser(href);
    if (affiliate) {
      gtag('event', 'affiliate_click', {
        affiliate_advertiser: affiliate.advertiser,
        affiliate_type:       affiliate.type,
        affiliate_network:    affiliate.network,
        affiliate_url:        href,
        page_location:        window.location.href,
        page_title:           document.title
      });
      return;
    }

    if (href.includes('chat.whatsapp.com') || href.includes('wa.me')) {
      gtag('event', 'community_click', { platform: 'whatsapp', link_url: href });
      return;
    }
    if (href.includes('facebook.com/groups')) {
      gtag('event', 'community_click', { platform: 'facebook', link_url: href });
      return;
    }

    if (!href.includes('dutchgoose.nl')) {
      gtag('event', 'outbound_click', { link_url: href, page_location: window.location.href });
    }
  }, true);
})();
