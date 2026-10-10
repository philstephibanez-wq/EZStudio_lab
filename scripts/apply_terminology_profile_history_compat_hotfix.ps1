$ErrorActionPreference='Stop'
$python='H:\Python\pythoncore-3.14-64\python.exe'
if(-not (Test-Path $python)){ throw "Python introuvable: $python" }

& $python (Join-Path $PSScriptRoot 'apply_terminology_profile_history_compat_hotfix.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

php (Join-Path $PSScriptRoot 'test_terminology_profile_history_compat_hotfix.php')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host 'TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX_OK' -ForegroundColor Green
