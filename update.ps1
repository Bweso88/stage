# MucoAcadémie – Script de mise à jour
param([string]$Branch="claude/study-video-app-ph3I7",[string]$RepoUrl="https://github.com/Bweso88/cours.git")
function Write-Ok{param($m)Write-Host "  [OK] $m" -ForegroundColor Green}
function Write-Err{param($m)Write-Host "  [KO] $m" -ForegroundColor Red}
function Write-Step{param($m)Write-Host "`n>>> $m" -ForegroundColor Cyan}
Clear-Host
Write-Host "  MucoAcadémie – Mise à jour" -ForegroundColor Cyan
$ScriptDir=Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir
Write-Ok "Dossier : $ScriptDir"
Write-Step "Vérification de Git"
try{git --version|Out-Null;Write-Ok "Git détecté"}catch{Write-Err "Git non trouvé.";pause;exit 1}
if(!(Test-Path ".git")){git init;git remote add origin $RepoUrl;Write-Ok "Dépôt initialisé"}else{Write-Ok "Dépôt Git existant"}
$dbFile="admin\db.php";$dbBackup="admin\db.php.bak"
if(Test-Path $dbFile){Copy-Item $dbFile $dbBackup -Force;Write-Ok "admin/db.php sauvegardé"}
git fetch origin $Branch 2>&1|Out-Null;git checkout $Branch 2>&1|Out-Null;git pull origin $Branch 2>&1|Out-Null
if($LASTEXITCODE -eq 0){Write-Ok "Code mis à jour"}else{Write-Err "Échec du pull.";pause;exit 1}
if(Test-Path $dbBackup){Copy-Item $dbBackup $dbFile -Force;Remove-Item $dbBackup -Force;Write-Ok "admin/db.php restauré"}
@("videos","views","images")|ForEach-Object{if(!(Test-Path $_)){New-Item -ItemType Directory -Path $_|Out-Null;Write-Ok "Créé : $_"}else{Write-Ok "OK : $_"}}
Write-Host "  Mise à jour terminée !" -ForegroundColor Green
pause
