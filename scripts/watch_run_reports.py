"""MAIN-only report event watcher.

Observes committed LAB/Assistant results in read-only SQLite and publishes each
new/changed report once. GitHub I/O is outside scientific workers.
The existing durable published.json is the retry journal; no SQLite writes.
"""
from __future__ import annotations

import argparse
from contextlib import closing
from pathlib import Path
import sqlite3
import sys
import time

import publish_run_reports as publisher


def publish_changes(db_path: Path, state_dir: Path, batch_size: int = 5) -> tuple[int, int]:
    state_file = state_dir / "published.json"
    state = publisher._load_state(state_file)
    pushed = errors = 0
    # Separate producers: a large historical LAB backlog never starves Assistant.
    with closing(sqlite3.connect(f"file:{db_path.resolve().as_posix()}?mode=ro", uri=True, timeout=5)) as db:
        for producer in ("assistant", "lab"):
            attempted = 0
            for path, report in publisher.collect(db):
                if report["producer"] != producer:
                    continue
                digest = publisher._digest(report)
                if state.get(path) == digest:
                    continue
                if attempted >= batch_size:
                    break
                attempted += 1
                try:
                    outcome = publisher.publish(path, report)
                    state[path] = digest
                    publisher._save_state(state_file, state)
                    pushed += 1
                    print(f"{producer}: {outcome}: {path}", flush=True)
                except Exception as exc:
                    errors += 1
                    print(f"{producer}: failed: {path}: {type(exc).__name__}: {str(exc)[:200]}",
                          file=sys.stderr, flush=True)
    return pushed, errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--db", type=Path, default=Path(__file__).resolve().parents[1] / "data" / "benchmark.sqlite")
    parser.add_argument("--state-dir", type=Path, default=publisher.STATE_DIR)
    parser.add_argument("--interval", type=float, default=2.0)
    parser.add_argument("--batch-size", type=int, default=5)
    parser.add_argument("--once", action="store_true", help="Single nonblocking report check")
    args = parser.parse_args()
    if not (1 <= args.interval <= 3600) or not (1 <= args.batch_size <= 30):
        parser.error("interval must be 1..3600 seconds and batch-size 1..30")
    if not args.db.is_file():
        parser.error("database not found")
    print("REPORT_WATCHER_STARTED", flush=True)
    while True:
        try:
            pushed, errors = publish_changes(args.db, args.state_dir, args.batch_size)
            if pushed or errors:
                print(f"REPORT_WATCHER pushed={pushed} failed={errors}", flush=True)
        except Exception as exc:
            print(f"REPORT_WATCHER_FAILED {type(exc).__name__}: {str(exc)[:200]}",
                  file=sys.stderr, flush=True)
            if args.once:
                return 1
        if args.once:
            return 1 if errors else 0
        time.sleep(args.interval)


if __name__ == "__main__":
    raise SystemExit(main())
