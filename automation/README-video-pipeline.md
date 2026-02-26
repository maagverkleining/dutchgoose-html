# Ultra Automation Video Pipeline (lokale versie)

Bestanden:
- `automation/video_pipeline.py` (engine)
- `automation/video_app.py` (simpele lokale app/UI)

## Wat dit nu doet

- Watch folder: `~/Videos/CapCut/Exports/INBOX`
- Pakt nieuwe video’s op (`.mp4`, `.mov`, `.m4v`, `.hevc`) en verplaatst naar:
  - `~/Videos/CapCut/Exports/OUTBOX/<bestandsnaam>/source.mp4`
- Maakt alle gevraagde outputbestanden (incl. review pack, captions, metadata, exports, approval.json, dashboard.json)
- Dynamische YouTube-keuze:
  - `<= 60s` → `SHORTS`
  - `> 60s` → `LONG`
- Veiligheidsklep:
  - post-flow start **alleen** als `approval.json` op `"approved": true` staat
- Compliance-check blokkeert bij issues (`NEEDS_EDIT`)

## Starten (aanbevolen: via app)

```bash
cd /Users/davidgans/.openclaw/workspace
python3 automation/video_app.py
```

Open daarna in je browser:
- `http://127.0.0.1:8765`

In de app klik je op **Start pipeline**.

Optionele env vars:

- `CAPCUT_INBOX`
- `CAPCUT_OUTBOX`
- `CAPCUT_DASHBOARD`
- `CAPCUT_POLL_SECONDS`
- `WHISPER_MODEL` (default: `base`)

## Vereisten

CLI tools op systeempad:

- `ffmpeg`
- `ffprobe`
- `whisper` (voor transcript)
- `tesseract` (optioneel, voor OCR)

## Belangrijke noot

Upload naar TikTok/Instagram/YouTube staat als veilige placeholder in `try_post()`:
- volgorde + gating zijn al ingebouwd
- echte platform-connectors/API calls kun je daar direct inhaken

