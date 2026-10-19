"""Read-only MAIN publisher of sanitized LAB and Assistant run summaries.

Execution is best-effort and independent of the scientific workers.
Requires gh auth with write permission on private EZStudio_Run_Reports.
No models, audio, stems, paths, credentials, prompts or raw traces are exported.
"""
from __future__ import annotations

import argparse
import base64
import json
import os
from pathlib import Path
import re
import sqlite3
import subprocess
import sys
import tempfile

DEST = "philstephibanez-wq/EZStudio_Run_Reports"
PHASES = {"profile": "profile", "stems": "stems", "chords": "chords_n", "lyrics": "lyrics"}
FORBIDDEN = re.compile(
    r"token|secret|password|credential|authorization|cookie|bearer|"
    r"api.key|private.key|prompt|request|response|trace|"
    r"path|filepath|filename|directory|artifact|stem|audio|"
    r"hash|sha256|environment|config|command|url|uri|"
    r"raw|base64|binary|waveform|embedding|model.path",
    re.I,
)
SAFE_TEXT = re.compile(r"^[\\w .,:;()/%+&?!°#'’=\\-]{0,250}$", re.UNICODE)
MAX_DEPTH = 7
MAX_ITEMS = 40
MAX_REPORT = 100_000


def sanitize(value, depth=0):
    """Explicit bounds + secret/path denylist; discard unknown free text."""
    if depth > MAX_DEPTH:
        return None
    if value is None or isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        return value if SAFE_TEXT.fullmatch(value) and not re.search(
            r"[A-Za-z]:[\\\\/]|\\\\\\\\|https?://|@", value
        ) else None
    if isinstance(value, list):
        return [x for x in (sanitize(v, depth + 1) for v in value[:MAX_ITEMS]) if x is not None]
    if isinstance(value, dict):
        return {
            str(k): clean for k, v in list(value.items())[:MAX_ITEMS]
            if not FORBIDDEN.search(str(k))
            and (clean := sanitize(v, depth + 1)) is not None
        }
    return None


def parse_json(raw):
    try:
        obj = json.loads(raw or "{}")
        return obj if isinstance(obj, dict) else {}
    except (ValueError, TypeError):
        return {}


def collect(db):
    db.row_factory = sqlite3.Row
    runs = db.execute(
        "SELECT id, public_id, item, state, engine_name, engine_version, "
        "metrics_json, diagnostics_json, created_at, finished_at "
        "FROM scientific_runs ORDER BY id"
    )
    for run in runs:
        phase = PHASES.get(run["item"])
        if not phase or run["state"] not in {"done", "error", "failed", "cancelled"}:
            continue
        report = {
            "schema": "ezstudio.run-report.v1", "producer": "lab",
            "run_id": run["id"], "public_id": run["public_id"],
            "phase": phase, "state": run["state"],
            "engine": sanitize(run["engine_name"]),
            "engine_version": sanitize(run["engine_version"]),
            "created_at": run["created_at"], "finished_at": run["finished_at"],
            "metrics": sanitize(parse_json(run["metrics_json"])),
            "diagnostics": sanitize(parse_json(run["diagnostics_json"])),
        }
        yield f"lab/{phase}/run-{run['id']:06d}.json", report

    exists = db.execute(
        "SELECT name FROM sqlite_master WHERE type='table' "
        "AND name='profile_external_revisions'"
    ).fetchone()
    if not exists:
        return
    revisions = db.execute(
        "SELECT id, run_id, reviewer, status, consensus_json, "
        "comparison_json, confidence, created_at, updated_at "
        "FROM profile_external_revisions ORDER BY id"
    )
    for rev in revisions:
        report = {
            "schema": "ezstudio.run-report.v1", "producer": "assistant",
            "phase": "profile", "run_id": rev["run_id"],
            "revision_id": rev["id"], "reviewer": sanitize(rev["reviewer"]),
            "status": rev["status"], "created_at": rev["created_at"],
            "updated_at": rev["updated_at"], "confidence": rev["confidence"],
            "consensus": sanitize(parse_json(rev["consensus_json"])),
            "comparison": sanitize(parse_json(rev["comparison_json"])),
            "note": "Consultative; not independent scientific validation.",
        }
        yield (
            f"assistant/profile/run-{rev['run_id']:06d}/revision-{rev['id']:06d}.json",
            report,
        )


def gh(*args, input_json=None):
    command = ["gh", "api", *args]
    payload = None if input_json is None else json.dumps(input_json).encode("utf-8")
    res = subprocess.run(command, input=payload, stdout=subprocess.PIPE,
                         stderr=subprocess.PIPE, timeout=40, check=False)
    if res.returncode:
        raise RuntimeError("github_api_failed: " + res.stderr.decode("utf-8", "replace")[:240])
    return json.loads(res.stdout or b"{}")


def publish(path, report):
    raw = (json.dumps(report, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode("utf-8")
    if len(raw) > MAX_REPORT:
        raise ValueError("report_exceeds_safety_limit")
    endpoint = f"repos/{DEST}/contents/{path}"
    sha = None
    try:
        old = gh(endpoint)
        old_raw = base64.b64decode(old.get("content", ""))
        if old_raw == raw:
            return "unchanged"
        sha = old["sha"]
    except RuntimeError as exc:
        if "404" not in str(exc):
            raise
    payload = {
        "message": "report: " + path, "branch": "main",
        "content": base64.b64encode(raw).decode("ascii"),
    }
    if sha:
        payload["sha"] = sha
    gh("--method", "PUT", endpoint, "--input", "-", input_json=payload)
    return "published"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--db", default=str(Path(__file__).resolve().parents[1] / "data" / "benchmark.sqlite"))
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--limit", type=int, default=100)
    args = parser.parse_args()
    if args.limit < 1 or args.limit > 1000:
        parser.error("--limit must be 1..1000")
    if not Path(args.db).is_file():
        parser.error("database not found")
    failed = 0
    checked = 0
    with sqlite3.connect(f"file:{Path(args.db).resolve().as_posix()}?mode=ro", uri=True) as db:
        for path, report in collect(db):
            if checked >= args.limit:
                break
            checked += 1
            try:
                status = "dry-run" if args.dry_run else publish(path, report)
                print(f"{status}: {path}", flush=True)
            except Exception as exc:
                failed += 1
                print(f"failed: {path}: {type(exc).__name__}: {str(exc)[:200]}",
                      file=sys.stderr, flush=True)
    print(f"REPORTS checked={checked} failed={failed}", flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
