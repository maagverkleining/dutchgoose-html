import json
import re
from html import unescape
from pathlib import Path


blog_dir = Path("site/blog")


def clean_text(value: str) -> str:
    value = re.sub(r"<[^>]+>", " ", value)
    value = unescape(value)
    value = re.sub(r"\s+", " ", value)
    return value.strip()


faq_pattern = re.compile(
    r'<div[^>]*class=["\'][^"\']*\bfq\b[^"\']*["\'][^>]*>.*?'
    r"<button[^>]*>(.*?)<span[^>]*class=[\"'][^\"']*fqi[^\"']*[\"'][^>]*>.*?</button>.*?"
    r'<div[^>]*class=["\'][^"\']*\bfqa\b[^"\']*["\'][^>]*>.*?'
    r'<div[^>]*class=["\'][^"\']*fqa-inner[^"\']*["\'][^>]*>(.*?)</div>.*?</div>.*?</div>',
    re.DOTALL | re.IGNORECASE,
)


for html_file in blog_dir.glob("*.html"):
    content = html_file.read_text(encoding="utf-8")

    if '"@type": "Article"' in content or '"@type":"Article"' in content:
        print(f"Overgeslagen (al schema): {html_file.name}")
        continue

    title_match = re.search(r"<title>(.*?)</title>", content, re.IGNORECASE | re.DOTALL)
    title = title_match.group(1).strip() if title_match else html_file.stem.replace("-", " ").title()
    title = re.sub(r"\s*\|\s*Dutch Goose.*$", "", title).strip()

    desc_match = re.search(
        r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']',
        content,
        re.IGNORECASE | re.DOTALL,
    )
    description = clean_text(desc_match.group(1)) if desc_match else ""

    canonical_url = f"https://www.dutchgoose.nl/blog/{html_file.name}"

    faq_items = []
    for q_raw, a_raw in faq_pattern.findall(content):
        question = clean_text(q_raw)
        answer = clean_text(a_raw)
        if question and answer and len(question) > 10:
            faq_items.append(
                {
                    "@type": "Question",
                    "name": question,
                    "acceptedAnswer": {"@type": "Answer", "text": answer},
                }
            )
        if len(faq_items) >= 6:
            break

    schema = {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": title,
        "description": description,
        "url": canonical_url,
        "inLanguage": "nl-NL",
        "author": {
            "@type": "Person",
            "name": "David Gans",
            "url": "https://www.dutchgoose.nl/over-david.html",
            "description": "Ervaringsdeskundige bariatrie, afgevallen 105 kg na gastric bypass januari 2024",
        },
        "publisher": {
            "@type": "Organization",
            "name": "Dutch Goose",
            "url": "https://www.dutchgoose.nl",
        },
        "isPartOf": {
            "@type": "WebSite",
            "name": "Dutch Goose",
            "url": "https://www.dutchgoose.nl",
        },
    }

    schema_tag = (
        '<script type="application/ld+json">\n'
        f"{json.dumps(schema, ensure_ascii=False, indent=2)}\n"
        "</script>"
    )

    if faq_items:
        faq_schema = {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": faq_items,
        }
        faq_tag = (
            '<script type="application/ld+json">\n'
            f"{json.dumps(faq_schema, ensure_ascii=False, indent=2)}\n"
            "</script>"
        )
        schema_tag = schema_tag + "\n" + faq_tag

    if "</head>" in content:
        new_content = content.replace("</head>", schema_tag + "\n</head>", 1)
        html_file.write_text(new_content, encoding="utf-8")
        print(f"Schema toegevoegd: {html_file.name} (FAQ items: {len(faq_items)})")
    else:
        print(f"WAARSCHUWING: geen </head> gevonden in {html_file.name}")

print("\nKlaar!")
