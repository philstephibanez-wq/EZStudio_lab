$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$orch = "H:\EZS_orchestrator"
$python = "H:\Python\pythoncore-3.14-64\python.exe"

if (-not (Test-Path -LiteralPath $orch -PathType Container)) {
    throw "ORCHESTRATOR_ROOT_MISSING: $orch"
}
if (-not (Test-Path -LiteralPath $python -PathType Leaf)) {
    throw "PYTHON_MISSING: $python"
}

Set-Location $root

$env:APP_ENV = "dev"
$env:APP_DEBUG = "1"
$env:EZSTUDIO_PYTHON = $python
$env:AI_MODELS_ROOT = "H:\AIModels"
$env:BS_ROFORMER_MODELS_PATH = "H:\AIModels\audio\separation\bs-roformer"
$env:MELBAND_ROFORMER_MODELS_PATH = "H:\AIModels\audio\separation\melband-roformer"
$env:EZSTUDIO_PROFILE_MODELS = "H:\AIModels\audio\profile"
$env:EZSTUDIO_BEAT_THIS_CHECKPOINT = "H:\AIModels\audio\rhythm\beat-this\beat_this-final0.ckpt"

$storageRoot = Join-Path $root "var\storage"
$runtimeRoot = Join-Path $root "var\runtime"
$tmpRoot = Join-Path $root "var\tmp"
$logsRoot = Join-Path $root "var\logs"

New-Item -ItemType Directory -Force -Path `
    $storageRoot, `
    $runtimeRoot, `
    $tmpRoot, `
    $logsRoot | Out-Null

$env:EZSTUDIO_STORAGE_ROOT = $storageRoot
$env:EZSTUDIO_TMP_ROOT = $tmpRoot
$env:EZSTUDIO_LOG_ROOT = $logsRoot
$env:EZSTUDIO_RUNTIME_ROOT = $storageRoot
$env:EZSTUDIO_DEP_ROOT = Join-Path $runtimeRoot "deps"
$env:EZSTUDIO_PROFILE_DEP_ROOT = Join-Path $runtimeRoot "deps\profile-r3b12"
$env:EZSTUDIO_PROFILE_R3B13_DEP_ROOT = Join-Path $runtimeRoot "deps\profile-r3b13"
$env:EZSTUDIO_PROFILE_R3B10_DEP_ROOT = Join-Path $runtimeRoot "deps\profile-r3b10"
$env:EZSTUDIO_PASST_PYTHON = Join-Path $runtimeRoot "venvs\passt-r3b12\Scripts\python.exe"
$env:EZSTUDIO_PASST_TORCH_HOME = Join-Path $runtimeRoot "cache\passt-torch-home"
$env:PANNS_LABELS_CSV = Join-Path $runtimeRoot "deps\panns-data\class_labels_indices.csv"
$env:EZSTUDIO_TORCH_HOME = Join-Path $runtimeRoot "cache\torch-home"
$env:EZSTUDIO_STEMS_CACHE_ROOT = Join-Path $storageRoot "stems"
$env:EZSTUDIO_OBSERVABILITY_ROOT = Join-Path $logsRoot "observability"
$env:EZSTUDIO_EXPORT_ROOT = Join-Path $storageRoot "exports"
$env:EZSTUDIO_JOB_ROOT = Join-Path $tmpRoot "jobs"
$env:KEEP_UPLOADS = "1"

Write-Host "=== EZStudio LAB - démarrage manuel canonique ==="
Write-Host "MODELS=$env:AI_MODELS_ROOT"
Write-Host "STORAGE=$env:EZSTUDIO_STORAGE_ROOT"
Write-Host "TMP=$env:EZSTUDIO_TMP_ROOT"
Write-Host "LOGS=$env:EZSTUDIO_LOG_ROOT"
Write-Host "CACHE_CLEAR=YES"
Write-Host "CACHE_PREDELETE=NO"
Write-Host "CADDY=YES"
Write-Host "VISIBLE_CHILD_CONSOLE=NO"
Write-Host "PUBLIC=http://127.0.0.1:8702"
Write-Host ""

Push-Location $orch
try {
    @'
from config.loader import load_runtime_config
from contracts.target import Target
from server_manager.manager import ServerManager
from caddy_manager.manager import CaddyManager

config = load_runtime_config()
servers = ServerManager(config)
caddy = CaddyManager(config)

before_server = servers.view(Target.LAB, "dev")
before_caddy = caddy.view(Target.LAB)

print(f"LAB backend before: state={before_server.ownership} pid={before_server.pid}")
print(f"LAB Caddy before:   state={before_caddy.ownership} pid={before_caddy.pid}")

server = servers.start(Target.LAB, "dev")
edge = caddy.start(Target.LAB)

print(f"LAB backend: pid={server.pid} http={server.http_alive} ownership={server.ownership}")
print(f"LAB Caddy:   pid={edge.pid} http={edge.http_alive} ownership={edge.ownership}")

if not server.process_alive or not server.http_alive:
    raise SystemExit("LAB_BACKEND_NOT_HEALTHY")
if not edge.process_alive or not edge.http_alive:
    raise SystemExit("LAB_CADDY_NOT_HEALTHY")

print("LAB_START_OK http://127.0.0.1:8702")
'@ | & $python -
    if ($LASTEXITCODE -ne 0) {
        throw "LAB_START_KO"
    }
}
finally {
    Pop-Location
}
