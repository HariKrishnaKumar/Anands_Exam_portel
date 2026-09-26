"""
Faculty portal walkthrough driven by Browser-Use + Gemini 3.

Independent second verification pass: a real agent drives the browser through
the faculty login and dashboard and reports what it actually saw.

Security contract:
  * The Gemini API key lives in `<repo>/.browseruse.env` at the repo root.
  * This script never prints, echoes or logs the key â€” only the key's
    *presence* is reported.
  * The key is loaded into the process environment for browser-use/langchain.

Run:
  %USERPROFILE%\\.venv-browseruse\\Scripts\\python.exe tests\\browseruse\\faculty_walkthrough.py
"""
import os
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
KEY_FILE = REPO / ".browseruse.env"

# Model must be Gemini 3 or above (never 2.5).
MODEL = os.environ.get("BROWSERUSE_MODEL", "gemini-3.5-flash")
BASE_URL = os.environ.get("APP_BASE_URL", "http://localhost:8000")

FACULTY_EMAIL = "faculty@bgsmalur.edu"
FACULTY_PASSWORD = "BGSCCMALUR@563130"
COLLEGE = "BGS Institute Of Management Malur"


def load_key() -> bool:
    """Load the key file into the environment. Never read it back out."""
    if not KEY_FILE.exists():
        print(f"[key] MISSING: {KEY_FILE.name}")
        return False
    if KEY_FILE.stat().st_size == 0:
        print(f"[key] EMPTY: {KEY_FILE.name} (0 bytes) â€” paste the Gemini key, then re-run")
        return False

    # python-dotenv style parse: set keys without ever echoing values.
    for raw in KEY_FILE.read_text(errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        name, _, value = line.partition("=")
        name = name.strip()
        value = value.strip().strip('"').strip("'")
        if name and value:
            os.environ[name] = value

    present = [n for n in ("GOOGLE_API_KEY", "GEMINI_API_KEY") if os.environ.get(n)]
    print(f"[key] loaded from {KEY_FILE.name}; env var(s) set: {', '.join(present) or 'NONE'}")
    return bool(present)


TASK = f"""
You are verifying the faculty portal of a local exam platform.

1. Open {BASE_URL}/faculty-login.php
2. Choose the college "{COLLEGE}" from the College dropdown.
3. Enter the email {FACULTY_EMAIL} and password {FACULTY_PASSWORD}.
4. Submit the form.
5. You should land on a faculty dashboard. Confirm all of the following and
   report each as PASS or FAIL with the exact text you observed:
   a. The page heading mentions "{COLLEGE}".
   b. A "Students in scope" KPI exists and shows the number 5.
   c. A student roster table (#facultyRoster) is present with 5 rows.
   d. The page contains a "Read-only" indicator.
   e. There is NO Delete / Remove / Edit button anywhere on the page.
   f. The URL contains /faculty/dashboard.php
Finish by printing a short checklist of a-f.
"""


def main() -> int:
    print(f"[model] {MODEL} (Gemini 3+ required)")
    if not load_key():
        print("[result] NOT EXECUTED â€” no API key available. Supply .browseruse.env and re-run.")
        return 2

    try:
        from langchain_google_genai import ChatGoogleGenerativeAI
        from browser_use import Agent
        from browser_use.llm import ChatGoogle  # type: ignore
    except Exception as exc:  # pragma: no cover - environment dependent
        print(f"[result] NOT EXECUTED â€” import failed: {type(exc).__name__}: {exc}")
        return 3

    try:
        llm = ChatGoogle(model=MODEL)
    except Exception:
        # Older browser-use builds expose ChatGoogleGenerativeAI directly.
        llm = ChatGoogleGenerativeAI(model=MODEL)

    agent = Agent(task=TASK, llm=llm)
    history = agent.run_sync()

    final = ""
    try:
        final = history.final_result() or ""
    except Exception:
        final = str(history)

    print("[agent final message]")
    print(final)
    print(f"[result] EXECUTED â€” model={MODEL}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
