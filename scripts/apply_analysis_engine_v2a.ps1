$ErrorActionPreference='Stop'
$python='H:\Python\pythoncore-3.14-64\python.exe'
if(-not (Test-Path $python)){ throw "Python introuvable: $python" }

if(-not (Test-Path '.\python\ezstudio\runtime\engine_registry.py')){
    throw 'Checkpoint runtime attendu absent.'
}

Write-Host '1/2 Contrat Analysis Engine V2A...' -ForegroundColor Cyan
& $python .\scripts\test_analysis_engine_v2a.py
if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }

Write-Host '2/2 Contrats déclaratifs existants...' -ForegroundColor Cyan
if(Test-Path '.\scripts\test_declarative_runtime_v1b.py'){
    & $python .\scripts\test_declarative_runtime_v1b.py
    if($LASTEXITCODE -ne 0){ exit $LASTEXITCODE }
}

Write-Host 'ANALYSIS_ENGINE_V2A_APPLIED' -ForegroundColor Green
