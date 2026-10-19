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
import hashlib
import time

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
SAFE_TEXT = re.compile(r"^[\w .,:;()/%+&?!°#'’=-]{0,250}$", re.UNICODE)
MAX_DEPTH = 7
MAX_ITEMS = 40
MAX_REPORT = 100_000
STATE_DIR = Path(os.environ.get("EZSTUDIO_REPORTS_STATE_DIR", r"H:\\EZStudio_Run_Reports_state"))


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
        "FROM scientific_runs ORDER BY id DESC"
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
        "FROM profile_external_revisions ORDER BY id DESC"
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
        report = dict(report)
        report["truncated"] = True
        if "diagnostics" in report:
            report["diagnostics"] = {"note": "Large diagnostics retained only on MAIN."}
        if "consensus" in report:
            report["consensus"] = {"note": "Large consensus retained only on MAIN."}
        if "comparison" in report:
            report["comparison"] = {"note": "Large comparison retained only on MAIN."}
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


def _digest(report):
    return hashlib.sha256(
        json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def _load_state(path):
    try:
        state = json.loads(path.read_text(encoding="utf-8"))
        return state if isinstance(state, dict) else {}
    except (OSError, ValueError):
        return {}


def _save_state(path, state):
    path.parent.mkdir(parents=True, exist_ok=True)
    staged = path.with_suffix(".pending")
    staged.write_text(json.dumps(state, sort_keys=True), encoding="utf-8")
    os.replace(staged, path)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--db", default=str(Path(__file__).resolve().parents[1] / "data" / "benchmark.sqlite"))
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--producer", choices=("all", "lab", "assistant"), default="all")
    parser.add_argument("--run-id", type=int, help="Publish only this scientific run (requires --producer lab)")
    parser.add_argument("--revision-id", type=int, help="Publish only this revision (requires --producer assistant)")
    parser.add_argument("--limit", type=int, default=0, help="0 = all runs and revisions; positive = maximum GitHub operations")
    parser.add_argument("--state-dir", type=Path, default=STATE_DIR)
    args = parser.parse_args()
    if args.limit < 0:
        parser.error("--limit must be >= 0")
    if args.run_id is not None and (args.run_id < 1 or args.producer != "lab" or args.revision_id is not None):
        parser.error("--run-id requires --producer lab and positive run ID")
    if args.revision_id is not None and (args.revision_id < 1 or args.producer != "assistant"):
        parser.error("--revision-id requires --producer assistant and positive revision ID")
    if not Path(args.db).is_file():
        parser.error("database not found")
    state_file = args.state_dir / "published.json"
    state = {} if args.dry_run else _load_state(state_file)
    checked = 0
    attempted = 0
    published = 0
    unchanged = 0
    failed = 0
    found = False
    with sqlite3.connect(f"file:{Path(args.db).resolve().as_posix()}?mode=ro", uri=True) as db:
        for path, report in collect(db):
            if args.producer != "all" and report["producer"] != args.producer:
                continue
            if args.run_id is not None and report["run_id"] != args.run_id:
                continue
            if args.revision_id is not None and report.get("revision_id") != args.revision_id:
                continue
            found = True
            checked += 1
            digest = _digest(report)
            if not args.dry_run and state.get(path) == digest:
                unchanged += 1
                continue
            if args.limit and attempted >= args.limit:
                break
            attempted += 1
            try:
                if args.dry_run:
                    status = "dry-run"
                else:
                    status = publish(path, report)
                    state[path] = digest
                    _save_state(state_file, state)
                    published += status == "published"
                print(f"{status}: {path}", flush=True)
            except Exception as exc:
                failed += 1
                print(f"failed: {path}: {type(exc).__name__}: {str(exc)[:200]}",
                      file=sys.stderr, flush=True)
    print(f"REPORTS checked={checked} attempted={attempted} published={published} unchanged={unchanged} failed={failed}", flush=True)
    if (args.run_id is not None or args.revision_id is not None) and not found:
        print("REPORT_NOT_FOUND_OR_NOT_FINAL", file=sys.stderr, flush=True)
        return 2
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
