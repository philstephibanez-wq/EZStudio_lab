from __future__ import annotations

import argparse
import sqlite3
from pathlib import Path


def table_columns(conn: sqlite3.Connection, table: str) -> set[str]:
    return {
        str(row[1])
        for row in conn.execute(f'PRAGMA table_info("{table}")').fetchall()
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--db",
        default=str(
            Path(__file__).resolve().parents[1] / "data" / "benchmark.sqlite"
        ),
    )
    args = parser.parse_args()
    db = Path(args.db).resolve()
    if not db.is_file():
        raise RuntimeError(f"database_missing:{db}")

    conn = sqlite3.connect(db)
    conn.execute("PRAGMA busy_timeout=5000")
    conn.execute("PRAGMA foreign_keys=ON")

    cols = table_columns(conn, "analysis_resource_locks")
    if "phase" not in cols:
        raise RuntimeError(
            "analysis_resource_locks.phase_missing:"
            f"columns={sorted(cols)}"
        )
    if "region" in cols:
        raise RuntimeError(
            "analysis_resource_locks.region_still_present:"
            f"columns={sorted(cols)}"
        )

    active = int(
        conn.execute(
            "SELECT COUNT(*) FROM analysis_jobs "
            "WHERE status IN ('queued','running','cancelling')"
        ).fetchone()[0]
    )
    if active:
        raise RuntimeError(f"active_analysis_jobs:{active}")

    conn.execute("BEGIN IMMEDIATE")
    try:
        # Drop every historical/canonical variant so no stale trigger body
        # survives the column rename.
        for name in (
            "trg_analysis_jobs_region_lock_insert",
            "trg_analysis_jobs_region_lock_claim",
            "trg_analysis_jobs_phase_lock_insert",
            "trg_analysis_jobs_phase_lock_claim",
        ):
            conn.execute(f'DROP TRIGGER IF EXISTS "{name}"')

        conn.executescript(
            """
CREATE TRIGGER trg_analysis_jobs_phase_lock_insert
BEFORE INSERT ON analysis_jobs
BEGIN
    SELECT CASE WHEN EXISTS(
        SELECT 1
        FROM analysis_resource_locks l
        WHERE l.song_id = NEW.song_id
          AND l.expires_at_epoch > CAST(strftime('%s','now') AS INTEGER)
          AND l.phase = CASE
              WHEN NEW.kind IN ('benchmark','chords','chords_scientific')
              THEN 'chords'
              ELSE NEW.kind
          END
    ) THEN RAISE(ABORT, 'analysis_phase_locked') END;
END;

CREATE TRIGGER trg_analysis_jobs_phase_lock_claim
BEFORE UPDATE OF status ON analysis_jobs
WHEN NEW.status='running' AND OLD.status='queued'
BEGIN
    SELECT CASE WHEN EXISTS(
        SELECT 1
        FROM analysis_resource_locks l
        WHERE l.song_id = NEW.song_id
          AND l.expires_at_epoch > CAST(strftime('%s','now') AS INTEGER)
          AND l.phase = CASE
              WHEN NEW.kind IN ('benchmark','chords','chords_scientific')
              THEN 'chords'
              ELSE NEW.kind
          END
    ) THEN RAISE(ABORT, 'analysis_phase_locked') END;
END;
"""
        )

        conn.commit()
    except Exception:
        conn.rollback()
        raise

    triggers = {
        str(row[0]): str(row[1])
        for row in conn.execute(
            "SELECT name,sql FROM sqlite_master "
            "WHERE type='trigger' AND name LIKE 'trg_analysis_jobs_%_lock_%'"
        ).fetchall()
    }

    expected = {
        "trg_analysis_jobs_phase_lock_insert",
        "trg_analysis_jobs_phase_lock_claim",
    }
    if set(triggers) != expected:
        raise RuntimeError(f"unexpected_trigger_set:{sorted(triggers)}")

    for name, sql in triggers.items():
        if "l.region" in sql:
            raise RuntimeError(f"stale_region_reference:{name}")
        if "l.phase" not in sql:
            raise RuntimeError(f"phase_reference_missing:{name}")

    fk = conn.execute("PRAGMA foreign_key_check").fetchall()
    if fk:
        raise RuntimeError(f"foreign_key_check_failed:{fk[:5]}")

    integrity = str(conn.execute("PRAGMA integrity_check").fetchone()[0])
    if integrity.lower() != "ok":
        raise RuntimeError(f"integrity_check_failed:{integrity}")

    conn.close()

    print("TERMINOLOGY_PHASE_LOCK_TRIGGER_HOTFIX_OK")
    print("analysis_jobs triggers rebuilt against analysis_resource_locks.phase")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
