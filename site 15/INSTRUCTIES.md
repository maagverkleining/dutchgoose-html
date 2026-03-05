# Dutch Goose — Instructieboek voor aanpassingen

Dit bestand is bedoeld voor Claude of Codex. Lees dit eerst voordat je iets aanpast.

---

## Sitestructuur

```
site/
  index.html          — Homepage
  kennisbank.html     — Kennisbank overzichtspagina
  deals.html          — Deals pagina
  vitamines.html      — Vitamines pagina
  tools.html          — Tools pagina
  community.html      — Community pagina
  over-david.html     — Over David
  samenwerken.html    — Samenwerken
  starter-kits.html   — Starter kits
  contact.html        — Contact
  privacy.html        — Privacy
  cookies.html        — Cookies
  disclosure.html     — Disclosure
  style.css           — ALLE gedeelde CSS (niet aanpassen tenzij gevraagd)
  sitemap.xml         — Sitemap (altijd updaten bij nieuwe pagina)
  robots.txt          — Robots
  kennisbank/
    _template.html    — Template voor nieuwe artikelen
    [slug].html       — Losse artikel pagina's
```

---

## Commands — zo geef je opdrachten

### 📝 Nieuw artikel
```
artikel: "[Titel van het artikel]"
categorie: [voeding / vitamines / sport / herstel / mentaal / medisch / bypass / sleeve]
tags: [tag1, tag2, tag3]
uitgelicht: [ja / nee]
samenvatting: "[Korte beschrijving voor de card op kennisbank.html]"
```

**Wat er dan gebeurt:**
1. Nieuw bestand `kennisbank/[slug].html` aangemaakt op basis van `_template.html`
2. Artikel card toegevoegd aan `kennisbank.html` (sectie "Recente artikelen")
3. Als `uitgelicht: ja` → ook featured artikel slot bijgewerkt
4. `sitemap.xml` bijgewerkt met nieuwe URL

**Voorbeeld:**
```
artikel: "Hoofdhonger vs buikhonger na maagverkleining"
categorie: voeding
tags: bypass, sleeve, mentaal, voeding
uitgelicht: nee
samenvatting: "Het verschil tussen echte honger en zin in eten na een maagverkleining. Hoe herken je het en wat doe je eraan?"
```

---

### 🏷️ Nieuwe deal toevoegen
```
deal: "[Naam bedrijf]"
korting: "[bijv. 15% korting]"
code: "[KORTINGSCODE]"
url: "[affiliate link]"
categorie: [supplementen / voeding / sport / kleding / overig]
beschrijving: "[Korte beschrijving]"
```

**Wat er dan gebeurt:**
1. Deal card toegevoegd aan `deals.html`
2. `sitemap.xml` niet gewijzigd (deals staan al in sitemap)

---

### ✏️ Tekst aanpassen op een pagina
```
pagina: [index / kennisbank / deals / vitamines / tools / community / over-david / samenwerken]
sectie: [bijv. "hero tekst" / "intro" / "FAQ vraag X"]
wijziging: "[Nieuwe tekst]"
```

---

### 🗑️ Artikel verplaatsen of verwijderen
```
artikel: "[slug of titel]"
actie: [verwijder van kennisbank / verplaats naar archief / zet featured]
```

---

### 🗺️ Sitemap handmatig updaten
Wordt automatisch gedaan bij nieuwe artikelen. Bij handmatige update:
```
sitemap: update
```

---

## Artikel card HTML (voor in kennisbank.html)

Kopieer dit blok en vul in bij nieuwe artikel card:

```html
<div class="art-card rv">
  <div class="art-top">EMOJI</div>
  <div class="art-body">
    <div class="art-cat">CATEGORIE</div>
    <h3>TITEL</h3>
    <p>SAMENVATTING</p>
    <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.5rem">
      <span class="art-tag">TAG1</span>
      <span class="art-tag">TAG2</span>
    </div>
    <a href="kennisbank/SLUG.html" class="read">Lees meer →</a>
  </div>
</div>
```

---

## Categorie emoji's

| Categorie | Emoji |
|-----------|-------|
| Voeding   | 🍽️ |
| Vitamines | 💊 |
| Sport     | 🏋️ |
| Herstel   | 🤗 |
| Mentaal   | 🧠 |
| Medisch   | 🔬 |
| Bypass    | 🫀 |
| Sleeve    | 🍌 |

---

## Deployment

Na elke aanpassing:
1. Bestanden opslaan
2. Push naar GitHub: `git add . && git commit -m "[beschrijving]" && git push`
3. GitHub webhook stuurt signaal naar Plesk
4. Plesk trekt nieuwe bestanden automatisch op → live op dutchgoose.nl

**Plesk Git URL:** `https://plesk01.dotpoint.nl:8443`
**GitHub repo:** `maagverkleining/dutchgoose-portal`
**Branch:** `master`

---

## Belangrijke regels

- **Nooit** `style.css` aanpassen tenzij expliciet gevraagd
- **Altijd** `sitemap.xml` updaten bij nieuwe artikel pagina's
- **Altijd** `_template.html` als basis gebruiken voor nieuwe artikelen
- Artikel slugs: alleen kleine letters, koppeltekens, geen spaties (bijv. `hoofdhonger-buikhonger.html`)
- Kennisbank artikelen linken altijd met `../style.css` (één map dieper)
