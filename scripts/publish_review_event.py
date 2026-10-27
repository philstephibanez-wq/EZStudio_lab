"""Publish exactly one completed PROFILE external review; no backlog, no scheduler.

Caller owns exclusive writer policy for publisher.published.json.
"""
from __future__ import annotations
import argparse
from contextlib import closing
from pathlib import Path
import sqlite3
import sys
import publish_run_reports as reports

def select_revision(db_path: Path, revision_id: int):
    if revision_id <= 0:
        raise ValueError("revision_id_positive_required")
    with closing(sqlite3.connect(f"file:{db_path.resolve().as_posix()}?mode=ro",uri=True,timeout=5)) as db:
        row=db.execute(
            "SELECT r.id,r.run_id,r.status FROM profile_external_revisions r "
            "JOIN scientific_runs s ON s.id=r.run_id "
            "WHERE r.id=? AND s.item='profile' AND s.state='done'",(revision_id,)
        ).fetchone()
        if row is None or row[2] != "completed":
            return None
        exact_path=f"assistant/profile/run-{row[1]:06d}/revision-{row[0]:06d}.json"
        for path,report in reports.collect(db):
            if path == exact_path:
                if report.get("revision_id") != revision_id or report.get("status") != "completed":
                    raise RuntimeError("revision_report_mismatch")
                return path,report
    raise RuntimeError("completed_revision_report_missing")

def publish_revision(db_path: Path, state_dir: Path, revision_id: int, dry_run: bool=False):
    item=select_revision(db_path,revision_id)
    if item is None:
        return "not_completed"
    path,report=item
    if dry_run:
        return "ready"
    state_file=state_dir/"published.json"
    state=reports._load_state(state_file)
    digest=reports._digest(report)
    if state.get(path)==digest:
        return "unchanged"
    status=reports.publish(path,report)
    state[path]=digest
    reports._save_state(state_file,state)
    return status

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument("--revision-id",type=int,required=True)
    parser.add_argument("--db",type=Path,default=Path(__file__).resolve().parents[1]/"data"/"benchmark.sqlite")
    parser.add_argument("--state-dir",type=Path,default=reports.STATE_DIR)
    parser.add_argument("--dry-run",action="store_true")
    args=parser.parse_args()
    if args.revision_id<1:
        parser.error("positive revision id required")
    if not args.db.is_file():
        parser.error("LAB DB unavailable")
    try:
        status=publish_revision(args.db,args.state_dir,args.revision_id,args.dry_run)
    except Exception as exc:
        print("TARGETED_REVISION_FAILED:"+type(exc).__name__,file=sys.stderr)
        return 1
    print("TARGETED_REVISION_"+status.upper())
    return 0

if __name__=="__main__":
    raise SystemExit(main())
