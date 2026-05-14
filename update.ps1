# ============================================================
#  FormaPro – Script de mise à jour
#  Usage : clic droit > "Exécuter avec PowerShell"
# ============================================================

param(
    [string]$Branch    = "claude/study-video-app-ph3I7",
    [string]$RepoUrl   = "https://github.com/Bweso88/cours.git"
)

function Write-Ok   { param($m) Write-Host "  [OK] $m" -ForegroundColor Green  }
function Write-Err  { param($m) Write-Host "  [KO] $m" -ForegroundColor Red    }
function Write-Step { param($m) Write-Host "`n>>> $m" -ForegroundColor Cyan    }

Clear-Host
Write-Host ""
Write-Host "  FormaPro – Mise a jour" -ForegroundColor Magenta
Write-Host "  ─────────────────────────────────────────" -ForegroundColor DarkGray
Write-Host ""

# Aller dans le dossier du script
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir
Write-Ok "Dossier : $ScriptDir"

# ── 1. Vérifier Git ──────────────────────────────────────────────────────────
Write-Step "Vérification de Git"
try {
    git --version | Out-Null
    Write-Ok "Git détecté"
} catch {
    Write-Err "Git non trouvé. Installez Git depuis https://git-scm.com"
    pause; exit 1
}

# ── 2. Initialiser le dépôt si pas encore fait ───────────────────────────────
Write-Step "Vérification du dépôt"
if (!(Test-Path ".git")) {
    Write-Host "  Initialisation du dépôt Git..." -ForegroundColor Yellow
    git init
    git remote add origin $RepoUrl
    Write-Ok "Dépôt initialisé"
} else {
    Write-Ok "Dépôt Git existant"
}

# ── 3. Sauvegarder db.php avant le pull ──────────────────────────────────────
Write-Step "Sauvegarde de la configuration"
$dbFile     = "admin\db.php"
$dbBackup   = "admin\db.php.bak"
if (Test-Path $dbFile) {
    Copy-Item $dbFile $dbBackup -Force
    Write-Ok "admin/db.php sauvegardé"
}

# ── 4. Pull ──────────────────────────────────────────────────────────────────
Write-Step "Téléchargement des mises à jour"
git fetch origin $Branch 2>&1 | ForEach-Object { Write-Host "  $_" -ForegroundColor DarkGray }
git checkout $Branch      2>&1 | Out-Null
git pull origin $Branch   2>&1 | ForEach-Object { Write-Host "  $_" -ForegroundColor DarkGray }

if ($LASTEXITCODE -eq 0) {
    Write-Ok "Code mis à jour depuis la branche $Branch"
} else {
    Write-Err "Échec du pull. Vérifiez votre connexion ou les conflits Git."
    pause; exit 1
}

# ── 5. Restaurer db.php ───────────────────────────────────────────────────────
Write-Step "Restauration de la configuration"
if (Test-Path $dbBackup) {
    Copy-Item $dbBackup $dbFile -Force
    Remove-Item $dbBackup -Force
    Write-Ok "admin/db.php restauré (configuration conservée)"
}

# ── 6. S'assurer que les dossiers existent ───────────────────────────────────
Write-Step "Vérification des dossiers"
@("videos","views","images") | ForEach-Object {
    if (!(Test-Path $_)) {
        New-Item -ItemType Directory -Path $_ | Out-Null
        Write-Ok "Dossier créé : $_"
    } else {
        Write-Ok "OK : $_\"
    }
}

# ── Résumé ────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ─────────────────────────────────────────" -ForegroundColor DarkGray
Write-Host "  Mise a jour terminee !" -ForegroundColor Green
Write-Host "  ─────────────────────────────────────────" -ForegroundColor DarkGray
Write-Host ""

pause
