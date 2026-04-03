document.addEventListener('DOMContentLoaded', function() {

  // Track alle outbound link klikken
  document.addEventListener('click', function(e) {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.href || '';

    // BariBuddies / Community links
    if (href.includes('chat.whatsapp.com') || href.includes('wa.me')) {
      gtag('event', 'outbound_click', { link_category: 'baribuddies', link_type: 'whatsapp', link_url: href });
    } else if (href.includes('facebook.com/groups')) {
      gtag('event', 'outbound_click', { link_category: 'baribuddies', link_type: 'facebook', link_url: href });
    } else if (href.includes('discord.gg') || href.includes('discord.com')) {
      gtag('event', 'outbound_click', { link_category: 'baribuddies', link_type: 'discord', link_url: href });
    }

    // Affiliate links
    else if (href.includes('vitaminenspecialist.nl') || href.includes('linkster.co')) {
      const label = href.includes('dgproefpakket') ? 'proefpakket' : 'regulier';
      gtag('event', 'outbound_click', { link_category: 'affiliate', link_advertiser: 'vitaminenspecialist', link_type: label, link_url: href });
    } else if (href.includes('linkster.co')) {
      gtag('event', 'outbound_click', { link_category: 'affiliate', link_advertiser: 'ahead', link_url: href });
    }

    // Overige externe links
    else if (href.startsWith('http') && !href.includes('dutchgoose.nl')) {
      gtag('event', 'outbound_click', { link_category: 'external', link_url: href });
    }
  });
});
