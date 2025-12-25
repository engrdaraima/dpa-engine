
#!/usr/bin/env python3
"""
DPA-EXCO Mini War Room (single-file Flask app)

Usage:
  pip install flask pydantic requests
  python app.py

This app calls Google Gemini 2.0 flash (generateContent) by default.
It injects the DPA_SYSTEM_PROMPT into the user payload so the model
returns the DPA-E dialectic JSON array.

Security notes:
 - API keys are used only in-flight and never stored.
 - Do not run this on a public host without adding CSRF and TLS.
"""

import os
import sys
import time
import json
import logging
from typing import List, Any
from flask import Flask, request, render_template_string
from pydantic import BaseModel, Field, ValidationError
import requests

# --- Config & Logging ---
LOG = logging.getLogger("dpa_warroom")
handler = logging.StreamHandler(sys.stderr)
handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
LOG.addHandler(handler)
LOG.setLevel(logging.INFO)

app = Flask(__name__)
app.secret_key = os.environ.get("DPA_APP_SECRET") or os.urandom(24)

# --- THE DPA-E SYSTEM SOUL ---
DPA_SYSTEM_PROMPT = """
# SYSTEM SETTING: THE EXECUTIVE BOARD (DPA-E)
Return ONLY a raw JSON array: [{"agent":"Name","emoji":"Emoji","message":"Text"}]
1. 👑 Daraima: Lead. 2. ⚖️ Justice: CFO/Cynic. 3. 💻 Moses: CTO/Pragmatist. 
Rules: No robotic headers. Agents MUST argue. End with a Scorecard.
"""

# --- Input model ---
class PromptIn(BaseModel):
    api_key: str = Field(..., min_length=10)
    prompt: str = Field(..., min_length=1)
    model: str = Field("gemini-2.0-flash", min_length=1)
    endpoint: str | None = None  # optional override
    max_retries: int = Field(3, ge=1, le=6)
    timeout: int = Field(60, ge=5, le=300)

# --- Networking helpers ---
def do_post_with_backoff(url: str, headers: dict, body: dict, timeout: int, max_retries: int):
    last_exc = None
    backoff = 0.5
    for attempt in range(1, max_retries + 1):
        try:
            LOG.info("POST %s (attempt %d)", url, attempt)
            r = requests.post(url, json=body, headers=headers, timeout=timeout)
            if r.status_code in (429,) or 500 <= r.status_code < 600:
                last_exc = Exception(f"Upstream status {r.status_code}")
                time.sleep(backoff * (2 ** (attempt - 1)))
                continue
            r.raise_for_status()
            return r.json()
        except requests.RequestException as ex:
            last_exc = ex
            LOG.warning("Request failed: %s", ex)
            time.sleep(backoff * (2 ** (attempt - 1)))
    raise last_exc

def safe_parse_dpa_array(text: str) -> List[dict]:
    """
    Try to extract a JSON array from text. The model may wrap code fences or
    include leading text. We attempt common cleanups before json.loads.
    """
    tries = []
    t = text.strip()
    # Remove triple backticks and language hints
    if t.startswith("```") and t.endswith("```"):
        # drop outer fences
        # also handle ```json ... ```
        parts = t.split("```")
        # parts like ['', 'json\n[...]\n', '']
        inner = "".join(p for p in parts if p and not p.lower().startswith("json"))
        t = inner.strip()
    # Sometimes the model returns a Markdown block labeled json
    t = t.replace("```json", "").replace("```", "").strip()
    # Attempt to find the first '[' and last ']' and parse that slice
    try:
        first = t.index("[")
        last = t.rindex("]") + 1
        candidate = t[first:last]
        return json.loads(candidate)
    except Exception as e:
        tries.append(str(e))
    # Final attempt: try to parse full text
    try:
        return json.loads(t)
    except Exception as e:
        tries.append(str(e))
    LOG.debug("safe_parse_dpa_array failed: %s", tries)
    raise ValueError("Could not parse model output as JSON array.")

# --- Provider call ---
def call_gemini_v2(api_key: str, prompt: str, model: str = "gemini-2.0-flash", endpoint: str | None = None,
                   max_retries: int = 3, timeout: int = 60) -> List[dict]:
    # Build endpoint if not overridden
    if endpoint:
        url = endpoint
    else:
        # Use Google's generateContent path; note: some setups use key=..., some use Authorization Bearer.
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}"

    # Combine system soul and user prompt
    combined = f"{DPA_SYSTEM_PROMPT}\n\nUSER PITCH: {prompt}"

    payload = {
        "contents": [{"parts": [{"text": combined}]}]
    }
    headers = {"Content-Type": "application/json"}
    # If the user provided a Bearer-style key (starts with 'ya29.' or 'Bearer '), prefer Authorization header
    if api_key.startswith("Bearer "):
        headers["Authorization"] = api_key
        # strip key param from URL if present
        url = url.split("?")[0]
    elif api_key.startswith("ya29.") or api_key.startswith("1/"):
        # These look like OAuth access tokens; use Authorization header
        headers["Authorization"] = f"Bearer {api_key}"
        url = url.split("?")[0]

    resp = do_post_with_backoff(url, headers, payload, timeout=timeout, max_retries=max_retries)
    # The service usually returns candidates -> content -> parts -> text
    try:
        text = resp.get("candidates", [])[0]["content"]["parts"][0]["text"]
    except Exception:
        # Fallback: try other common shapes
        text = None
        # Search recursively for any string-looking value
        def find_first_str(obj: Any):
            if isinstance(obj, str):
                return obj
            if isinstance(obj, dict):
                for v in obj.values():
                    res = find_first_str(v)
                    if res:
                        return res
            if isinstance(obj, list):
                for v in obj:
                    res = find_first_str(v)
                    if res:
                        return res
            return None
        text = find_first_str(resp) or ""
    # Now parse the DPA array
    parsed = None
    try:
        parsed = safe_parse_dpa_array(text)
    except Exception as e:
        LOG.warning("Failed to parse DPA array from model output: %s", e)
        # Provide a safe fallback single system message
        parsed = [{"agent": "System", "emoji": "⚠️", "message": "Model returned unparsable output."}]
    return parsed

# --- Flask routes ---
TEMPLATE = """
<!doctype html>
<html>
  <head>
    <meta charset="utf-8"/>
    <title>DPA War Room</title>
    <style>body{font-family:Inter,system-ui,Segoe UI,Arial; padding:24px; background:#0b1220; color:#e6eef8} .box{background:#071029;padding:16px;border-radius:8px;margin-bottom:12px}</style>
  </head>
  <body>
    <h1>🏢 DPA War Room (Python-E)</h1>
    <div class="box">
      <form method="post">
        <label>API Key (paste only for one-shot):</label><br>
        <input name="api_key" type="password" style="width:100%" required><br><br>
        <label>Model (optional):</label><br>
        <input name="model" type="text" placeholder="gemini-2.0-flash" style="width:100%"><br><br>
        <label>Endpoint override (optional):</label><br>
        <input name="endpoint" type="text" style="width:100%"><br><br>
        <label>Prompt:</label><br>
        <textarea name="prompt" rows="6" style="width:100%" required></textarea><br><br>
        <button type="submit">Consult the Board</button>
      </form>
    </div>

    {% if chat %}
      <div class="box">
        <h3>Response</h3>
        {% for m in chat %}
          <p><strong>{{ m.emoji }} {{ m.agent }}</strong>: {{ m.message }}</p>
        {% endfor %}
      </div>
    {% endif %}
  </body>
</html>
"""

@app.route("/", methods=["GET", "POST"])
def home():
    chat = None
    if request.method == "POST":
        form = {k: request.form.get(k, "").strip() for k in ("api_key", "prompt", "model", "endpoint")}
        # Validate
        try:
            inp = PromptIn(api_key=form["api_key"], prompt=form["prompt"],
                           model=form["model"] or "gemini-2.0-flash",
                           endpoint=form["endpoint"] or None)
        except ValidationError as e:
            LOG.warning("Validation failed: %s", e)
            chat = [{"agent": "System", "emoji": "⚠️", "message": "Validation error: " + str(e)}]
            return render_template_string(TEMPLATE, chat=chat)
        try:
            chat = call_gemini_v2(inp.api_key, inp.prompt, model=inp.model, endpoint=inp.endpoint,
                                  max_retries=3, timeout=inp.timeout)
        except Exception as e:
            LOG.exception("Call failed")
            chat = [{"agent": "System", "emoji": "⚠️", "message": "Request failed: check server logs."}]
    return render_template_string(TEMPLATE, chat=chat)

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 5000)), debug=False)
