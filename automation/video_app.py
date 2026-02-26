#!/usr/bin/env python3
from __future__ import annotations

import cgi
import html
import json
import os
import shutil
import signal
import subprocess
from datetime import datetime
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOME = Path.home()
WORKSPACE = Path(__file__).resolve().parents[1]
AUTOMATION_DIR = WORKSPACE / "automation"
PIPELINE_SCRIPT = AUTOMATION_DIR / "video_pipeline.py"
PID_FILE = AUTOMATION_DIR / ".video_pipeline.pid"
APP_LOG = AUTOMATION_DIR / "video_app_runner.log"

INBOX = Path(os.environ.get("CAPCUT_INBOX", str(HOME / "Videos/CapCut/Exports/INBOX")))
OUTBOX = Path(os.environ.get("CAPCUT_OUTBOX", str(HOME / "Videos/CapCut/Exports/OUTBOX")))
DASHBOARD = Path(os.environ.get("CAPCUT_DASHBOARD", str(HOME / "Videos/CapCut/Exports/dashboard.json")))

HOST = os.environ.get("VIDEO_APP_HOST", "127.0.0.1")
PORT = int(os.environ.get("VIDEO_APP_PORT", "8765"))


def ensure_dirs():
    INBOX.mkdir(parents=True, exist_ok=True)
    OUTBOX.mkdir(parents=True, exist_ok=True)


def read_json(path: Path, fallback):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return fallback


def tail(path: Path, lines: int = 30) -> str:
    if not path.exists():
        return ""
    try:
        data = path.read_text(encoding="utf-8", errors="ignore").splitlines()
        return "\n".join(data[-lines:])
    except Exception:
        return ""


def is_pid_running(pid: int) -> bool:
    if pid <= 0:
        return False
    try:
        os.kill(pid, 0)
        return True
    except OSError:
        return False


def get_pipeline_pid() -> int | None:
    if not PID_FILE.exists():
        return None
    try:
        pid = int(PID_FILE.read_text(encoding="utf-8").strip())
        if is_pid_running(pid):
            return pid
    except Exception:
        pass
    try:
        PID_FILE.unlink(missing_ok=True)
    except Exception:
        pass
    return None


def start_pipeline() -> tuple[bool, str]:
    existing = get_pipeline_pid()
    if existing:
        return False, f"Pipeline draait al (PID {existing})."

    with APP_LOG.open("a", encoding="utf-8") as f:
        f.write(f"\n[{datetime.now().isoformat()}] Start pipeline requested\n")

    out = APP_LOG.open("a", encoding="utf-8")
    proc = subprocess.Popen(
        ["python3", str(PIPELINE_SCRIPT)],
        cwd=str(WORKSPACE),
        stdout=out,
        stderr=out,
        preexec_fn=os.setsid,
    )
    PID_FILE.write_text(str(proc.pid), encoding="utf-8")
    return True, f"Pipeline gestart (PID {proc.pid})."


def stop_pipeline() -> tuple[bool, str]:
    pid = get_pipeline_pid()
    if not pid:
        return False, "Pipeline draait niet."

    try:
        os.killpg(pid, signal.SIGTERM)
    except Exception:
        try:
            os.kill(pid, signal.SIGTERM)
        except Exception as e:
            return False, f"Stoppen mislukt: {e}"

    PID_FILE.unlink(missing_ok=True)
    return True, f"Pipeline gestopt (PID {pid})."


def list_projects(limit: int = 20):
    projects = []
    if not OUTBOX.exists():
        return projects
    for d in sorted([p for p in OUTBOX.iterdir() if p.is_dir()], key=lambda p: p.stat().st_mtime, reverse=True)[:limit]:
        review = read_json(d / "review_pack.json", {})
        compliance = (review.get("compliance") or {}).get("status", "-")
        approval = read_json(d / "approval.json", {})
        approved = bool(approval.get("approved") is True)
        projects.append({
            "name": d.name,
            "path": str(d),
            "compliance": compliance,
            "approved": approved,
            "updated": datetime.fromtimestamp(d.stat().st_mtime).strftime("%Y-%m-%d %H:%M"),
        })
    return projects


def set_approval(project_name: str, approved: bool):
    project_dir = OUTBOX / project_name
    approval_file = project_dir / "approval.json"
    if not approval_file.exists():
        return False, "approval.json niet gevonden"
    data = read_json(approval_file, {})
    data["approved"] = approved
    data["approved_by"] = "David"
    data["approved_at"] = datetime.now().isoformat(timespec="seconds")
    approval_file.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    return True, f"Approval gezet op {approved}"


def save_uploaded_file(handler: BaseHTTPRequestHandler) -> tuple[bool, str]:
    ctype, _ = cgi.parse_header(handler.headers.get("content-type"))
    if ctype != "multipart/form-data":
        return False, "Upload mislukt: geen multipart/form-data"

    fs = cgi.FieldStorage(
        fp=handler.rfile,
        headers=handler.headers,
        environ={"REQUEST_METHOD": "POST", "CONTENT_TYPE": handler.headers.get("content-type")},
    )
    if "file" not in fs:
        return False, "Geen bestand ontvangen"

    fileitem = fs["file"]
    filename = Path(fileitem.filename or "").name
    if not filename.lower().endswith(".mp4"):
        return False, "Alleen .mp4 bestanden zijn toegestaan"

    target = INBOX / filename
    with target.open("wb") as f:
        shutil.copyfileobj(fileitem.file, f)
    return True, f"Bestand geüpload naar INBOX: {filename}"


def page_html(message: str = "") -> str:
    pid = get_pipeline_pid()
    status = f"🟢 Actief (PID {pid})" if pid else "🔴 Uit"
    projects = list_projects()
    dash = read_json(DASHBOARD, {"runs": []})
    runs = list(reversed(dash.get("runs", [])[-8:]))
    logs = tail(APP_LOG, 20)

    rows = []
    for p in projects:
        q = urlencode({"project": p["name"]})
        rows.append(
            f"<tr><td>{html.escape(p['name'])}</td>"
            f"<td>{html.escape(p['compliance'])}</td>"
            f"<td>{'✅' if p['approved'] else '❌'}</td>"
            f"<td>{html.escape(p['updated'])}</td>"
            f"<td><a href='/project?{q}'>Open</a></td></tr>"
        )

    run_rows = []
    for r in runs:
        run_rows.append(
            f"<tr><td>{html.escape(r.get('slug','-'))}</td>"
            f"<td>{html.escape(r.get('status','-'))}</td>"
            f"<td>{html.escape(r.get('ended_at','-'))}</td></tr>"
        )

    msg_block = f"<p style='background:#eef7ff;padding:10px;border-radius:8px'>{html.escape(message)}</p>" if message else ""

    return f"""
<!doctype html>
<html>
<head>
  <meta charset='utf-8'/>
  <meta http-equiv='refresh' content='10'>
  <title>Video Automation App</title>
  <style>
    body {{ font-family: Inter, Arial, sans-serif; max-width: 1100px; margin: 24px auto; line-height: 1.45; }}
    .card {{ border:1px solid #ddd; border-radius:12px; padding:16px; margin-bottom:16px; }}
    button {{ padding:10px 14px; border-radius:10px; border:1px solid #ccc; cursor:pointer; }}
    table {{ border-collapse: collapse; width:100%; }}
    th, td {{ border-bottom:1px solid #eee; text-align:left; padding:8px; }}
    .muted {{ color:#666; }}
    code {{ background:#f5f5f5; padding:2px 6px; border-radius:6px; }}
    #dropzone {{ border:2px dashed #9aa; border-radius:12px; padding:24px; text-align:center; background:#fafcfe; }}
    #dropzone.drag {{ border-color:#2d7; background:#f0fff4; }}
  </style>
</head>
<body>
  <h1>🎬 Video Automation App</h1>
  <p class='muted'>Simpel gebruik: 1) Start pipeline 2) Upload of sleep MP4 in INBOX 3) Open project 4) Klik Approved</p>
  {msg_block}

  <div class='card'>
    <h2>Status</h2>
    <p><b>Pipeline:</b> {status}</p>
    <form method='post' action='/start' style='display:inline'><button>Start pipeline</button></form>
    <form method='post' action='/stop' style='display:inline'><button>Stop pipeline</button></form>
    <p class='muted'>INBOX: <code>{html.escape(str(INBOX))}</code><br/>OUTBOX: <code>{html.escape(str(OUTBOX))}</code></p>
  </div>

  <div class='card'>
    <h2>Upload video (.mp4)</h2>
    <div id='dropzone'>
      Sleep je MP4 hierheen (drag & drop), of kies bestand hieronder.
      <form id='uploadForm' method='post' action='/upload' enctype='multipart/form-data' style='margin-top:12px'>
        <input type='file' name='file' accept='.mp4,video/mp4' required>
        <button type='submit'>Upload naar INBOX</button>
      </form>
    </div>
  </div>

  <div class='card'>
    <h2>Snelle stappen (voor leken)</h2>
    <ol>
      <li>Klik <b>Start pipeline</b>.</li>
      <li>Upload of sleep je CapCut-export (<code>.mp4</code>) hierboven.</li>
      <li>Wacht tot project in de lijst verschijnt (compliance = OK).</li>
      <li>Open project en check <code>review_pack.html</code>.</li>
      <li>Klik <b>Approve & Post</b> als alles klopt.</li>
    </ol>
  </div>

  <div class='card'>
    <h2>Projecten (laatste)</h2>
    <table>
      <thead><tr><th>Project</th><th>Compliance</th><th>Approved</th><th>Updated</th><th></th></tr></thead>
      <tbody>{''.join(rows) or '<tr><td colspan="5" class="muted">Nog geen projecten</td></tr>'}</tbody>
    </table>
  </div>

  <div class='card'>
    <h2>Recente runs</h2>
    <table>
      <thead><tr><th>Project</th><th>Status</th><th>Einde</th></tr></thead>
      <tbody>{''.join(run_rows) or '<tr><td colspan="3" class="muted">Geen runs</td></tr>'}</tbody>
    </table>
  </div>

  <div class='card'>
    <h2>Laatste logs</h2>
    <pre>{html.escape(logs or 'Nog geen logs')}</pre>
  </div>

<script>
const dz = document.getElementById('dropzone');
['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => {{ e.preventDefault(); dz.classList.add('drag'); }}));
['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => {{ e.preventDefault(); dz.classList.remove('drag'); }}));
dz.addEventListener('drop', async (e) => {{
  const files = e.dataTransfer.files;
  if (!files || !files.length) return;
  const file = files[0];
  if (!file.name.toLowerCase().endsWith('.mp4')) {{ alert('Alleen .mp4'); return; }}
  const fd = new FormData();
  fd.append('file', file);
  const res = await fetch('/upload', {{ method:'POST', body: fd }});
  const txt = await res.text();
  document.open(); document.write(txt); document.close();
}});
</script>
</body>
</html>
"""


def project_html(name: str, message: str = "") -> str:
    pdir = OUTBOX / name
    if not pdir.exists():
        return "<h1>Project niet gevonden</h1><p><a href='/'>Terug</a></p>"

    review = read_json(pdir / "review_pack.json", {})
    summary = review.get("summary", {})
    compliance = review.get("compliance", {})
    approval = read_json(pdir / "approval.json", {})
    msg_block = f"<p style='background:#eef7ff;padding:10px;border-radius:8px'>{html.escape(message)}</p>" if message else ""

    files = sorted([f.name for f in pdir.iterdir() if f.is_file()])
    file_items = "".join(f"<li><code>{html.escape(x)}</code></li>" for x in files)

    return f"""
<!doctype html><html><head><meta charset='utf-8'><title>{html.escape(name)}</title>
<style>body{{font-family:Inter,Arial,sans-serif;max-width:1000px;margin:24px auto}} .card{{border:1px solid #ddd;border-radius:12px;padding:16px;margin-bottom:16px}} button{{padding:10px 14px;border-radius:10px;border:1px solid #ccc;cursor:pointer}} code{{background:#f5f5f5;padding:2px 6px;border-radius:6px}}</style>
</head><body>
<p><a href='/'>← Terug</a></p>
<h1>Project: {html.escape(name)}</h1>
{msg_block}
<div class='card'>
  <h2>Compliance</h2>
  <pre>{html.escape(json.dumps(compliance, ensure_ascii=False, indent=2))}</pre>
</div>
<div class='card'>
  <h2>Approval</h2>
  <pre>{html.escape(json.dumps(approval, ensure_ascii=False, indent=2))}</pre>
  <form method='post' action='/approve?project={html.escape(name)}' style='display:inline'><button>Approve & Post</button></form>
  <form method='post' action='/unapprove?project={html.escape(name)}' style='display:inline'><button>Set terug op not approved</button></form>
</div>
<div class='card'>
  <h2>Inhoud samenvatting</h2>
  <pre>{html.escape(json.dumps(summary, ensure_ascii=False, indent=2))}</pre>
</div>
<div class='card'>
  <h2>Belangrijke bestanden</h2>
  <p>Open lokaal in Finder: <code>{html.escape(str(pdir))}</code></p>
  <ul>{file_items}</ul>
</div>
</body></html>
"""


class Handler(BaseHTTPRequestHandler):
    def _send(self, body: str, status: int = 200):
        body_bytes = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body_bytes)))
        self.end_headers()
        self.wfile.write(body_bytes)

    def _redirect(self, location: str):
        self.send_response(303)
        self.send_header("Location", location)
        self.end_headers()

    def do_GET(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)
        if parsed.path == "/":
            self._send(page_html())
            return
        if parsed.path == "/project":
            name = (qs.get("project") or [""])[0]
            self._send(project_html(name))
            return
        if parsed.path in {"/start", "/stop", "/upload", "/approve", "/unapprove"}:
            self._send(
                page_html("Deze route werkt alleen via de knoppen in de app. Ga terug naar Home."),
                200,
            )
            return
        self._send("<h1>404</h1>", 404)

    def do_POST(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)
        if parsed.path == "/start":
            _, msg = start_pipeline()
            self._send(page_html(msg))
            return
        if parsed.path == "/stop":
            _, msg = stop_pipeline()
            self._send(page_html(msg))
            return
        if parsed.path == "/upload":
            _, msg = save_uploaded_file(self)
            self._send(page_html(msg))
            return
        if parsed.path in {"/approve", "/unapprove"}:
            project = (qs.get("project") or [""])[0]
            approved = parsed.path == "/approve"
            _, msg = set_approval(project, approved)
            self._send(project_html(project, msg))
            return
        self._redirect("/")


def main():
    ensure_dirs()
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"Video app running on http://{HOST}:{PORT}")
    server.serve_forever()


if __name__ == "__main__":
    main()
