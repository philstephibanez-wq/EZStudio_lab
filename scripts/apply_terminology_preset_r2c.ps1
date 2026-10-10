$ErrorActionPreference='Stop'
$python='H:\Python\pythoncore-3.14-64\python.exe'
if(-not (Test-Path $python)){ throw "Python introuvable: $python" }

Write-Host '1/3 Nettoyage fonctionnel...' -ForegroundColor Cyan
& $python (Join-Path $PSScriptRoot 'apply_terminology_preset_r2c.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host '2/3 Contrat R2C...' -ForegroundColor Cyan
& $python (Join-Path $PSScriptRoot 'test_terminology_preset_r2c.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host '3/3 Grep actif...' -ForegroundColor Cyan
& $python (Join-Path $PSScriptRoot 'grep_legacy_terminology_r2c.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host 'TERMINOLOGY_PRESET_R2C_APPLIED' -ForegroundColor Green
