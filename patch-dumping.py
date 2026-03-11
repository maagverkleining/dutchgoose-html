"""
Sessie 3 patch - dumping artikel answer-first
Uitvoeren in de root van de repo: python3 patch-dumping.py
"""

path = "site/blog/dumping-vroeg-en-laat-zo-herken-je-het-na-maagverkleining.html"

with open(path, "r", encoding="utf-8") as f:
    html = f.read()

# 1. Answer-first intro
html = html.replace(
    '<p>Je hebt net lekker gegeten. En dan, tien minuten later: hartkloppingen, zweten, een duizelig gevoel. Of het tegenovergestelde: anderhalf uur na je maaltijd zak je ineens in, trillerig en leeg. Dit zijn de twee gezichten van dumping, en als je een gastric bypass of sleeve hebt gehad, is de kans groot dat je het op een gegeven moment meemaakt.</p>',
    '<p>Dumping is een reactie waarbij voedsel te snel vanuit je maag naar de dunne darm stroomt. Na een gastric bypass of sleeve is de kans hierop verhoogd, zeker bij suiker, vet eten of te grote porties. Er zijn twee soorten: vroege dumping (10-30 minuten na het eten, met hartkloppingen en zweten) en late dumping (1-3 uur later, met trillen en een bloedsuikerdip). De meeste gevallen zijn te voorkomen.</p>'
)

# 2. H2 titels naar vraagvorm
html = html.replace(
    '>Late dumping: de verraderlijke variant<',
    '>Wat is late dumping en waarom voelt het anders?<'
)
html = html.replace(
    '>Dumping voorkomen: praktische regels<',
    '>Hoe voorkom je dumping?<'
)
html = html.replace(
    '>Wanneer naar de dokter?<',
    '>Wanneer ga je naar de dokter?<'
)

# 3. Twee extra FAQ vragen toevoegen voor de afsluitende </div> van de faq-section
extra_faq = (
    '<div class="fq"><button class="fqb" onclick="tf(this)">'
    'Verschilt dumping bij gastric bypass en gastric sleeve?'
    '<span class="fqi">\u25be</span></button>'
    '<div class="fqa"><p>Ja, licht. Na een gastric bypass is de kans op late dumping groter, '
    'omdat voedsel de eerste maag volledig omzeilt. Na een sleeve is vroege dumping vaker het geval. '
    'Beide varianten zijn met dezelfde aanpassingen goed te beheersen.</p></div></div>'
    '<div class="fq"><button class="fqb" onclick="tf(this)">'
    'Wat is het verschil tussen dumping en een vastloper?'
    '<span class="fqi">\u25be</span></button>'
    '<div class="fqa"><p>Bij een vastloper blijft voedsel hangen in je maag of slokdarm, '
    'met druk, speeksel en ongemak als gevolg. Bij dumping komt voedsel juist te snel door. '
    'Beide voelen onprettig maar vragen een andere aanpak.</p></div></div>'
)

# Voeg in voor de laatste </div> van de faq-section (na de 4e fq)
# Zoek het sluitende </div> dat direct na de laatste fq staat
old_faq_end = (
    '<div class="fq"><button class="fqb" onclick="tf(this)">Kan ik ooit weer suiker eten?'
    '<span class="fqi">\u25be</span></button>'
    '<div class="fqa"><p>Sommige mensen tolereren kleine hoeveelheden suiker later in het proces, '
    'anderen blijven er gevoelig voor. Test voorzichtig, in kleine hoeveelheden en nooit op een lege maag.'
    '</p></div></div></div>'
)
new_faq_end = (
    '<div class="fq"><button class="fqb" onclick="tf(this)">Kan ik ooit weer suiker eten?'
    '<span class="fqi">\u25be</span></button>'
    '<div class="fqa"><p>Sommige mensen tolereren kleine hoeveelheden suiker later in het proces, '
    'anderen blijven er gevoelig voor. Test voorzichtig, in kleine hoeveelheden en nooit op een lege maag.'
    '</p></div></div>'
    + extra_faq
    + '</div>'
)
html = html.replace(old_faq_end, new_faq_end)

# 4. FAQPage JSON-LD schema toevoegen voor </head>
faqpage_schema = '''<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {"@type": "Question","name": "Heb ik altijd dumping na een maagverkleining?","acceptedAnswer": {"@type": "Answer","text": "Nee. Niet iedereen krijgt dumping. Sommige mensen ervaren het nooit, anderen alleen in de eerste maanden. Het hangt af van het type operatie, je eetgewoontes en persoonlijke gevoeligheid."}},
    {"@type": "Question","name": "Gaat dumping ooit weg?","acceptedAnswer": {"@type": "Answer","text": "Voor veel mensen vermindert dumping in het eerste jaar naarmate je leert wat werkt en wat niet. Het gaat zelden volledig weg, maar wordt wel veel beter beheersbaar."}},
    {"@type": "Question","name": "Is dumping gevaarlijk?","acceptedAnswer": {"@type": "Answer","text": "Incidentele dumping is niet gevaarlijk maar wel erg onprettig. Structurele late dumping met lage bloedsuikers vraagt wel aandacht van je bariatrisch team."}},
    {"@type": "Question","name": "Kan ik ooit weer suiker eten?","acceptedAnswer": {"@type": "Answer","text": "Sommige mensen tolereren kleine hoeveelheden suiker later in het proces, anderen blijven er gevoelig voor. Test voorzichtig, in kleine hoeveelheden en nooit op een lege maag."}},
    {"@type": "Question","name": "Verschilt dumping bij gastric bypass en gastric sleeve?","acceptedAnswer": {"@type": "Answer","text": "Ja, licht. Na een gastric bypass is de kans op late dumping groter, omdat voedsel de eerste maag volledig omzeilt. Na een sleeve is vroege dumping vaker het geval. Beide varianten zijn met dezelfde aanpassingen goed te beheersen."}},
    {"@type": "Question","name": "Wat is het verschil tussen dumping en een vastloper?","acceptedAnswer": {"@type": "Answer","text": "Bij een vastloper blijft voedsel hangen in je maag of slokdarm, met druk, speeksel en ongemak als gevolg. Bij dumping komt voedsel juist te snel door. Beide voelen onprettig maar vragen een andere aanpak."}}
  ]
}
</script>
'''
html = html.replace('</head>', faqpage_schema + '</head>')

# 5. Em-dashes verwijderen
html = html.replace(' \u2014 ', ', ')
html = html.replace('\u2014 ', ', ')
html = html.replace(' \u2014', ',')

with open(path, "w", encoding="utf-8") as f:
    f.write(html)

print("Klaar. Controleer de wijzigingen en push naar main.")
