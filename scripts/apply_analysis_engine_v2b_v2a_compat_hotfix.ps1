$ErrorActionPreference='Stop'
$python='H:\Python\pythoncore-3.14-64\python.exe'

& $python (Join-Path $PSScriptRoot 'apply_analysis_engine_v2b_v2a_compat_hotfix.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

& $python (Join-Path $PSScriptRoot 'test_analysis_engine_v2b_v2a_compat_hotfix.py')
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

& $python .\scripts\test_analysis_engine_v2a.py
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

& $python .\scripts\test_analysis_engine_v2b.py
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

& $python .\scripts\test_profile_r3b35c_contract.py
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

& $python .\scripts\test_declarative_runtime_v1b.py
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host 'ANALYSIS_ENGINE_V2B_V2A_COMPAT_HOTFIX_OK' -ForegroundColor Green
