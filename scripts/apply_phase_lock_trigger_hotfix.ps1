$ErrorActionPreference='Stop'
$python='H:\Python\pythoncore-3.14-64\python.exe'
if(-not (Test-Path $python)){ throw "Python introuvable: $python" }

& $python (Join-Path $PSScriptRoot 'fix_phase_lock_triggers.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host 'TERMINOLOGY_PHASE_LOCK_TRIGGER_HOTFIX_APPLIED' -ForegroundColor Green
