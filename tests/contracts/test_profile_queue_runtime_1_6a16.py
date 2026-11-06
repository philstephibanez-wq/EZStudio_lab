"""Python integration-boundary tests; deliberately never execute the scientific runner."""
from __future__ import annotations

import ast
import importlib.util
import os
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
WORKER = ROOT / 'analysis' / 'worker_entrypoint.py'
LAUNCHER = ROOT / 'analysis' / 'queue_profile_jobobject_launcher.py'
BRIDGE = ROOT / 'src' / 'Service' / 'GlobalQueueProfileBridge.php'
ENV = ROOT / 'src' / 'Service' / 'GlobalQueueProfileEnvironment.php'


def load_worker():
    spec = importlib.util.spec_from_file_location('ezs_worker_contract', WORKER)
    module = importlib.util.module_from_spec(spec)
    assert spec and spec.loader
    spec.loader.exec_module(module)
    return module


def test_python_sources_compile_without_engine_import():
    for source in (WORKER, LAUNCHER):
        ast.parse(source.read_text(encoding='utf-8-sig'), filename=str(source))


def test_worker_model_paths_are_deterministic_when_empty():
    worker = load_worker()
    with patch.dict(os.environ, {'AI_MODELS_ROOT': 'X:/models'}, clear=True):
        env = worker._common_env(Path('X:/storage'))
    assert env['AI_MODELS_ROOT'] == 'X:/models'
    assert env['EZSTUDIO_PROFILE_MODELS'].replace('\\', '/').endswith('/audio/profile')
    assert env['EZSTUDIO_BEAT_THIS_CHECKPOINT'].replace('\\', '/').endswith('/audio/rhythm/beat-this/beat_this-final0.ckpt')
    assert env['PYTHONUTF8'] == '1'


def test_worker_does_not_launch_profile_during_import():
    worker = load_worker()
    assert worker.PROTOCOL == 'ezscore.analysis-job.v2'
    assert 'profile' in worker.ALLOWED_KINDS


def test_job_object_wrapper_preserves_windows_guards():
    text = LAUNCHER.read_text(encoding='utf-8-sig')
    for guard in ('AssignProcessToJobObject', 'KILL_ON_JOB_CLOSE', 'canonical_worker_path_required', 'windows_jobobject_required'):
        assert guard in text


def test_bridge_has_no_replay_and_preserves_secrets():
    text = BRIDGE.read_text(encoding='utf-8-sig')
    assert 'GlobalQueueProfileEnvironment::build(' in text
    assert "'claim_generation' => $generation" in text
    assert "'lab_job_id' => $labJobId" in text
    assert 'profile_worker_exit_' in text
    assert 'file_put_contents($diagnosticLog' in text
    assert 'runOne(' in text


def test_environment_contract_contains_critical_paths_and_secret_filter():
    text = ENV.read_text(encoding='utf-8-sig')
    for name in ('AI_MODELS_ROOT','EZSTUDIO_PROFILE_DEP_ROOT','EZSTUDIO_PASST_PYTHON',
                 'EZSTUDIO_LOG_ROOT','EZS_QUEUE_EXECUTOR_TOKEN','EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'):
        assert name in text
