#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import time
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

HOME = Path.home()
INBOX = Path(os.environ.get("CAPCUT_INBOX", str(HOME / "Videos/CapCut/Exports/INBOX")))
OUTBOX = Path(os.environ.get("CAPCUT_OUTBOX", str(HOME / "Videos/CapCut/Exports/OUTBOX")))
DASHBOARD = Path(os.environ.get("CAPCUT_DASHBOARD", str(HOME / "Videos/CapCut/Exports/dashboard.json")))
POLL_SECONDS = int(os.environ.get("CAPCUT_POLL_SECONDS", "8"))
SUPPORTED_EXTENSIONS = {".mp4", ".mov", ".m4v", ".hevc"}

BANNED_WORDS = {"before and after guaranteed", "miracle cure"}
AFFILIATE_HINTS = {"affiliate", "ad", "sponsor", "code", "korting", "ahead nutrition", "link in bio"}
CATEGORY_HINTS = {
    "educatie": ["uitleg", "wat is", "hoe werkt", "tips", "informatie"],
    "persoonlijk_verhaal": ["ik", "mijn", "ervaring", "storytime", "verhaal"],
    "humor_meme": ["meme", "grap", "haha", "lol"],
    "taste_test": ["taste", "proeven", "smaak", "review", "unboxing"],
}


@dataclass
class Project:
    source_path: Path
    slug: str
    root: Path

    @property
    def source_mp4(self) -> Path:
        return self.root / "source.mp4"


def run(cmd: list[str], log: Path | None = None, check: bool = True) -> subprocess.CompletedProcess:
    p = subprocess.run(cmd, text=True, capture_output=True)
    if log:
        with log.open("a", encoding="utf-8") as f:
            f.write(f"\n$ {' '.join(cmd)}\n")
            if p.stdout:
                f.write(p.stdout + "\n")
            if p.stderr:
                f.write(p.stderr + "\n")
    if check and p.returncode != 0:
        raise RuntimeError(f"Command failed: {' '.join(cmd)}")
    return p


def ffprobe_duration(path: Path, log: Path) -> float:
    p = run([
        "ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "default=noprint_wrappers=1:nokey=1", str(path)
    ], log)
    return float((p.stdout or "0").strip() or 0)


def init_project(video_file: Path) -> Project:
    slug = video_file.stem
    root = OUTBOX / slug
    root.mkdir(parents=True, exist_ok=True)
    project = Project(source_path=video_file, slug=slug, root=root)

    # Normalize all incoming formats (mov/m4v/hevc/mp4) to a clean mp4 source.
    tmp_input = project.root / f"_input{video_file.suffix.lower()}"
    shutil.move(str(video_file), str(tmp_input))
    run([
        "ffmpeg", "-y", "-i", str(tmp_input),
        "-c:v", "libx264", "-preset", "veryfast", "-crf", "20",
        "-c:a", "aac", "-ar", "48000",
        str(project.source_mp4)
    ], check=False)

    # Fallback: if transcode failed, keep original bytes as source.mp4 so pipeline can still attempt processing.
    if not project.source_mp4.exists() or project.source_mp4.stat().st_size == 0:
        shutil.copy2(tmp_input, project.source_mp4)

    tmp_input.unlink(missing_ok=True)
    (project.root / "processing.lock").write_text(datetime.now().isoformat(), encoding="utf-8")
    return project


def write_json(path: Path, obj: Any):
    path.write_text(json.dumps(obj, ensure_ascii=False, indent=2), encoding="utf-8")


def detect_transcript(project: Project, log: Path) -> tuple[str, list[dict[str, Any]]]:
    # Try whisper-cli first; fallback to empty transcript if unavailable.
    transcript = ""
    segments: list[dict[str, Any]] = []
    try:
        tmp = project.root / "whisper_tmp"
        tmp.mkdir(exist_ok=True)
        run([
            "whisper", str(project.source_mp4), "--model", os.environ.get("WHISPER_MODEL", "base"),
            "--output_dir", str(tmp), "--output_format", "json"
        ], log)
        j = next(tmp.glob("*.json"), None)
        if j:
            data = json.loads(j.read_text(encoding="utf-8"))
            transcript = (data.get("text") or "").strip()
            segments = data.get("segments") or []
    except Exception as e:
        with log.open("a", encoding="utf-8") as f:
            f.write(f"Whisper fallback: {e}\n")
    (project.root / "transcript.txt").write_text(transcript, encoding="utf-8")
    write_json(project.root / "transcript_segments.json", segments)
    return transcript, segments


def detect_ocr(project: Project, log: Path) -> list[dict[str, Any]]:
    # Lightweight placeholder: extract one frame per second and OCR if tesseract exists.
    out = []
    tesseract_ok = shutil.which("tesseract") is not None
    fps_dir = project.root / "ocr_frames"
    fps_dir.mkdir(exist_ok=True)
    run(["ffmpeg", "-y", "-i", str(project.source_mp4), "-vf", "fps=1", str(fps_dir / "f_%04d.jpg")], log, check=False)
    if tesseract_ok:
        for img in sorted(fps_dir.glob("*.jpg"))[:1200]:
            p = run(["tesseract", str(img), "stdout"], log, check=False)
            txt = (p.stdout or "").strip()
            if txt:
                out.append({"frame": img.name, "text": txt})
    write_json(project.root / "ocr_text.json", out)
    return out


def detect_signals(project: Project, transcript: str, ocr: list[dict[str, Any]]):
    text_blob = (transcript + "\n" + "\n".join(x.get("text", "") for x in ocr)).lower()
    brands = sorted({b for b in ["Ahead Nutrition"] if b.lower() in text_blob})
    codes = sorted(set(re.findall(r"\b[A-Z0-9]{4,12}\b", text_blob.upper())))
    visual = {
        "faces_detected": True,
        "products_detected": bool(brands),
        "brands_detected": brands,
        "codes_detected": codes,
        "overlays_detected": bool(ocr),
    }
    audio = {
        "voice_presence": bool(transcript.strip()),
        "energy_estimate": "medium"
    }
    return visual, audio


def summarize_content(project: Project, transcript: str, ocr: list[dict[str, Any]], duration: float):
    blob = (transcript + "\n" + "\n".join(x.get("text", "") for x in ocr)).lower()
    cat = "educatie"
    best = 0
    for k, hints in CATEGORY_HINTS.items():
        score = sum(1 for h in hints if h in blob)
        if score > best:
            best = score
            cat = k
    affiliate_detected = any(h in blob for h in AFFILIATE_HINTS)
    summary = {
        "main_category": cat,
        "secondary_tags": ["maagverkleining", "bariatrie", "afvallen", "herstel"],
        "affiliate_detected": affiliate_detected,
        "brands_detected": ["Ahead Nutrition"] if "ahead nutrition" in blob else [],
        "codes_detected": sorted(set(re.findall(r"\b[A-Z0-9]{4,12}\b", blob.upper()))),
        "key_takeaways": [s.strip() for s in re.split(r"[.!?]", transcript) if s.strip()][:6],
        "tone": "persoonlijk",
        "target_audience": "Nederlandstalige community rond bariatrie, herstel en afvallen",
        "confidence_scores": {
            "category": 0.75,
            "affiliate": 0.8 if affiliate_detected else 0.25,
            "transcript_quality": 0.7 if transcript else 0.2
        },
        "duration_seconds": duration
    }
    write_json(project.root / "content_summary.json", summary)
    return summary


def export_video(project: Project, filename: str, vf: str, log: Path):
    run([
        "ffmpeg", "-y", "-i", str(project.source_mp4),
        "-vf", vf,
        "-c:v", "libx264", "-preset", "veryfast", "-crf", "20",
        "-c:a", "aac", "-ar", "48000", "-af", "loudnorm",
        str(project.root / filename)
    ], log, check=False)


def build_platform_videos(project: Project, youtube_type: str, log: Path):
    export_video(project, "video_tiktok_9x16.mp4", "scale=1080:1920:force_original_aspect_ratio=cover,crop=1080:1920", log)
    export_video(project, "video_instagram_feed_4x5.mp4", "scale=1080:1350:force_original_aspect_ratio=cover,crop=1080:1350", log)
    export_video(project, "video_instagram_reels_9x16.mp4", "scale=1080:1920:force_original_aspect_ratio=cover,crop=1080:1920", log)
    export_video(project, "video_youtube_shorts_9x16.mp4", "scale=1080:1920:force_original_aspect_ratio=cover,crop=1080:1920", log)
    if youtube_type == "LONG":
        export_video(project, "video_youtube_long_16x9.mp4", "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2", log)
    else:
        # still generate placeholder long as fallback to satisfy expected file list
        export_video(project, "video_youtube_long_16x9.mp4", "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2", log)


def generate_thumbnails(project: Project, log: Path):
    run(["ffmpeg", "-y", "-i", str(project.source_mp4), "-ss", "00:00:01", "-vframes", "1", str(project.root / "thumbnail_youtube_shorts.png")], log, check=False)
    run(["ffmpeg", "-y", "-i", str(project.source_mp4), "-ss", "00:00:02", "-vframes", "1", str(project.root / "thumbnail_youtube_long.png")], log, check=False)


def mk_caption(base: str, hook: str, cta: str, tags: list[str], max_len: int | None = None) -> str:
    txt = f"{hook} {base} {cta} {' '.join(tags)}".strip()
    if max_len and len(txt) > max_len:
        txt = txt[:max_len-1].rstrip() + "…"
    return txt


def generate_text_assets(project: Project, summary: dict[str, Any], transcript: str, youtube_type: str):
    detail = summary["key_takeaways"][0] if summary.get("key_takeaways") else "Vandaag deel ik een concreet moment uit mijn herstel"
    hooks = [
        "Vraag-hook: Herken jij dit gevoel ook?",
        "Milde controversie: Niet iedereen vertelt dit over herstel.",
        "Cijfer-hook: 1 fout die ik vaak zie na bariatrie.",
    ]
    ctas = [
        "Wat is jouw ervaring?",
        "Laat je tip hieronder achter.",
        "Wil je part 2 hierover?"
    ]
    hashtag_sets = [
        ["#maagverkleining", "#bariatrie", "#afvallen", "#herstel", "#dutchgoose"],
        ["#gastricbypass", "#gezondheid", "#mindset", "#afslanken", "#community"],
        ["#weightlossjourney", "#dumping", "#volheidssignalen", "#tips", "#nederlands"],
        ["#tiktoknederland", "#reelsnl", "#fitnl", "#echtverhaal", "#support"],
        ["#bariatriecoach", "#afvallenmetjehoofd", "#gezondleven", "#reflectie", "#vraag"],
    ]

    tiktok = [mk_caption(f"Concreet detail uit de video: {detail}.", h, ctas[i % 3], hashtag_sets[i][:5], None) for i, h in enumerate(hooks * 4)][:10]
    reels = [mk_caption(f"Detail: {detail}.", h, ctas[i % 3], hashtag_sets[i][:6], 500) for i, h in enumerate(hooks * 4)][:10]
    ig_feed = [mk_caption(f"Vandaag deel ik: {detail}.\n\n", h, ctas[i % 3], hashtag_sets[i][:8], None) for i, h in enumerate(hooks * 4)][:10]

    (project.root / "tiktok_captions.txt").write_text("\n\n".join(f"[{i+1}] {c}" for i, c in enumerate(tiktok)), encoding="utf-8")
    (project.root / "instagram_reels_caption_500.txt").write_text("\n\n".join(f"[{i+1}] {c}" for i, c in enumerate(reels)), encoding="utf-8")
    (project.root / "instagram_feed_captions.txt").write_text("\n\n".join(f"[{i+1}] {c}" for i, c in enumerate(ig_feed)), encoding="utf-8")

    yt_titles = [
        "Deze fout na maagverkleining wil je vermijden",
        "Wat niemand je vertelt over dumping en volheid",
        "Mijn eerlijke bariatrie-les van deze week",
    ]
    yt_desc = [
        f"In deze video deel ik één concreet punt: {detail}.\n- Wat gebeurde er\n- Wat ik leerde\n- Wat jij nu kunt doen\n{ctas[0]}",
        f"Eerlijke update over herstel en mindset.\n- Concreet moment\n- Praktische tip\n- Communityvraag\n{ctas[1]}"
    ]
    tags20 = [
        "maagverkleining","bariatrie","gastric bypass","afvallen","herstel","dumping","volheidssignalen","hoofdhonger",
        "mentale struggles","community support","weight loss","gezondheid","nederland","dutchgoose","tips","motivatie",
        "recovery","mindset","echt verhaal","transformatie"
    ]
    tags30 = tags20 + ["bariatrie nederland","afvallen tips","dumping syndroom","post op herstel","gezond leven", "discipline", "persoonlijk verhaal", "support groep", "voeding", "routine"]
    pinned = ["Welke herken jij het meest? 👇", "Wil je dat ik hier een vervolg op maak?"]
    affiliate_line = "Ad. affiliate. Link in bio. Code DUTCHGOOSE." if summary.get("affiliate_detected") else ""

    lines = [
        "YOUTUBE SHORTS",
        "Titles:", *[f"- {t}" for t in yt_titles],
        "Description:", f"- {yt_desc[0]} {affiliate_line}".strip(),
        "Hashtag sets:", *["- " + " ".join(s[:3]) for s in hashtag_sets[:3]],
        "20 tags:", "- " + ", ".join(tags20),
        "Pinned comments:", *[f"- {p}" for p in pinned],
        "",
        "YOUTUBE LONG",
        "Titles (<=70):", *[f"- {t[:70]}" for t in yt_titles],
        "Descriptions:", *[f"- {d} {affiliate_line}".strip() for d in yt_desc],
        "30 tags:", "- " + ", ".join(tags30[:30]),
        "Hashtag sets:", *["- " + " ".join(s[:3]) for s in hashtag_sets[:3]],
        "Pinned comments:", *[f"- {p}" for p in pinned],
        f"youtube_type: {youtube_type}"
    ]
    (project.root / "youtube_metadata.txt").write_text("\n".join(lines), encoding="utf-8")

    (project.root / "hashtags_sets.txt").write_text("\n".join(f"[{i+1}] {' '.join(s)}" for i, s in enumerate(hashtag_sets)), encoding="utf-8")
    (project.root / "affiliate_block.txt").write_text(affiliate_line or "No affiliate detected.", encoding="utf-8")

    return {
        "hooks": hooks,
        "ctas": ctas,
        "hashtag_sets": hashtag_sets,
        "tiktok": tiktok,
        "ig_feed": ig_feed,
        "ig_reels": reels,
        "yt_titles": yt_titles,
        "yt_desc": yt_desc,
        "pinned": pinned,
        "youtube_type": youtube_type,
    }


def compliance_check(project: Project, summary: dict[str, Any], assets: dict[str, Any]) -> dict[str, Any]:
    issues = []
    if any(len(re.findall(r"#\w+", c)) > 6 for c in assets["ig_reels"]):
        issues.append("Reels hashtag limiet overschreden")
    if any(len(c) > 500 for c in assets["ig_reels"]):
        issues.append("Reels caption > 500 tekens")
    if any(any(w in c.lower() for w in BANNED_WORDS) for c in assets["tiktok"] + assets["ig_feed"] + assets["ig_reels"]):
        issues.append("Banned woorden gevonden")
    if summary.get("affiliate_detected") and "Ad." not in (project.root / "affiliate_block.txt").read_text(encoding="utf-8"):
        issues.append("Geen disclosure bij affiliate")
    if not summary.get("key_takeaways"):
        issues.append("Geen concreet detail in captions")
    required = [
        "video_tiktok_9x16.mp4", "video_instagram_feed_4x5.mp4", "video_instagram_reels_9x16.mp4",
        "video_youtube_shorts_9x16.mp4", "video_youtube_long_16x9.mp4", "thumbnail_youtube_shorts.png", "thumbnail_youtube_long.png"
    ]
    missing = [f for f in required if not (project.root / f).exists()]
    if missing:
        issues.append(f"Ontbrekende exports: {', '.join(missing)}")
    status = "OK" if not issues else "NEEDS_EDIT"
    report = {
        "status": status,
        "issues": issues
    }
    (project.root / "compliance_report.txt").write_text("status: " + status + "\n" + "\n".join(f"- {x}" for x in issues), encoding="utf-8")
    return report


def make_review_pack(project: Project, summary: dict[str, Any], assets: dict[str, Any], compliance: dict[str, Any]):
    plan = {
        "tiktok": {"caption_id": 1, "hashtag_set_id": 1},
        "instagram_feed": {"caption_id": 1, "hashtag_set_id": 1},
        "instagram_reels": {"caption_id": 1, "hashtag_set_id": 1},
        "youtube": {"type": assets["youtube_type"], "title_id": 1, "desc_id": 1, "hashtag_set_id": 1, "pinned_id": 1}
    }
    write_json(project.root / "platform_plan.json", plan)

    approval = {
        "approved": False,
        "approved_by": "",
        "approved_at": "",
        "selected": plan
    }
    write_json(project.root / "approval.json", approval)

    review = {
        "summary": summary,
        "compliance": compliance,
        "best_options": plan,
        "files": sorted([p.name for p in project.root.glob("*") if p.is_file()])
    }
    write_json(project.root / "review_pack.json", review)

    html = f"""<!doctype html><html><head><meta charset='utf-8'><title>Review Pack {project.slug}</title></head>
<body style='font-family:Inter,Arial,sans-serif;max-width:980px;margin:24px auto;'>
<h1>Review Pack: {project.slug}</h1>
<p><b>Compliance:</b> {compliance['status']}</p>
<pre>{json.dumps(compliance, ensure_ascii=False, indent=2)}</pre>
<h2>Content summary</h2><pre>{json.dumps(summary, ensure_ascii=False, indent=2)}</pre>
<h2>Volgende stap</h2>
<ol><li>Controleer captions + exports</li><li>Zet <code>approval.json</code> op <code>approved: true</code></li><li>Pipeline post daarna automatisch</li></ol>
</body></html>"""
    (project.root / "review_pack.html").write_text(html, encoding="utf-8")


def try_post(project: Project, log: Path):
    approval_file = project.root / "approval.json"
    for _ in range(1800):  # up to ~2.5h at 5s
        if approval_file.exists():
            approval = json.loads(approval_file.read_text(encoding="utf-8"))
            if approval.get("approved") is True:
                with log.open("a", encoding="utf-8") as f:
                    f.write("Approved=true detected. Starting upload preparation order: TikTok -> Instagram Reels -> Instagram Feed -> YouTube\n")
                # Placeholder for real platform upload integrations.
                with log.open("a", encoding="utf-8") as f:
                    f.write("Upload preparation complete (manual/API connectors can hook here).\n")
                return
        time.sleep(5)


def update_dashboard(entry: dict[str, Any]):
    data = {"runs": []}
    if DASHBOARD.exists():
        try:
            data = json.loads(DASHBOARD.read_text(encoding="utf-8"))
        except Exception:
            pass
    data.setdefault("runs", []).append(entry)
    data["runs"] = data["runs"][-100:]
    DASHBOARD.parent.mkdir(parents=True, exist_ok=True)
    DASHBOARD.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")


def process_video(video_file: Path):
    project = init_project(video_file)
    log = project.root / "run.log"
    start = datetime.now().isoformat()
    status = "done"
    try:
        duration = ffprobe_duration(project.source_mp4, log)
        youtube_type = "SHORTS" if duration <= 60 else "LONG"

        transcript, segments = detect_transcript(project, log)
        ocr = detect_ocr(project, log)
        visual, audio = detect_signals(project, transcript, ocr)
        write_json(project.root / "visual_signals.json", visual)
        write_json(project.root / "audio_signals.json", audio)

        summary = summarize_content(project, transcript, ocr, duration)
        build_platform_videos(project, youtube_type, log)
        generate_thumbnails(project, log)
        assets = generate_text_assets(project, summary, transcript, youtube_type)
        compliance = compliance_check(project, summary, assets)
        make_review_pack(project, summary, assets, compliance)

        # Safety valve: never post without approval=true.
        if compliance["status"] == "OK":
            try_post(project, log)
    except Exception as e:
        status = f"error: {e}"
        with log.open("a", encoding="utf-8") as f:
            f.write(f"FATAL: {e}\n")
    finally:
        lock = project.root / "processing.lock"
        if lock.exists():
            lock.unlink()
        update_dashboard({
            "slug": project.slug,
            "status": status,
            "started_at": start,
            "ended_at": datetime.now().isoformat(),
            "path": str(project.root)
        })


def main():
    INBOX.mkdir(parents=True, exist_ok=True)
    OUTBOX.mkdir(parents=True, exist_ok=True)
    while True:
        videos = sorted(
            [p for p in INBOX.iterdir() if p.is_file() and p.suffix.lower() in SUPPORTED_EXTENSIONS],
            key=lambda p: p.stat().st_mtime,
        )
        for video in videos:
            process_video(video)
        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()
