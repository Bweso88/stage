# ============================================================
#  FormaPro – Script de déploiement PowerShell
#  Usage   : .\deploy.ps1
#  Prérequis: Git, PHP (XAMPP / WAMP / Laragon), MySQL
# ============================================================

param(
    [string]$RepoUrl    = "https://github.com/Bweso88/cours.git",
    [string]$Branch     = "claude/study-video-app-ph3I7",
    [string]$DeployDir  = "C:\xampp\htdocs\formapro",
    [string]$DBHost     = "localhost",
    [string]$DBName     = "formation",
    [string]$DBUser     = "root",
    [string]$DBPass     = "",
    [switch]$Update                     # passer -Update pour une MàJ sans réinitialiser la BDD
)

# ── Couleurs helpers ──────────────────────────────────────────────────────────
function Write-Ok    { param($m) Write-Host "  [OK] $m" -ForegroundColor Green  }
function Write-Warn  { param($m) Write-Host "  [!!] $m" -ForegroundColor Yellow }
function Write-Err   { param($m) Write-Host "  [KO] $m" -ForegroundColor Red    }
function Write-Step  { param($m) Write-Host "`n==> $m" -ForegroundColor Cyan    }

# ── Bannière ──────────────────────────────────────────────────────────────────
Clear-Host
Write-Host ""
Write-Host "  ███████╗ ██████╗ ██████╗ ███╗   ███╗ █████╗ "  -ForegroundColor Magenta
Write-Host "  ██╔════╝██╔═══██╗██╔══██╗████╗ ████║██╔══██╗"  -ForegroundColor Magenta
Write-Host "  █████╗  ██║   ██║██████╔╝██╔████╔██║███████║"  -ForegroundColor Magenta
Write-Host "  ██╔══╝  ██║   ██║██╔══██╗██║╚██╔╝██║██╔══██║"  -ForegroundColor Magenta
Write-Host "  ██║     ╚██████╔╝██║  ██║██║ ╚═╝ ██║██║  ██║"  -ForegroundColor Magenta
Write-Host "  ╚═╝      ╚═════╝ ╚═╝  ╚═╝╚═╝     ╚═╝╚═╝  ╚═╝"  -ForegroundColor Magenta
Write-Host ""
Write-Host "  FormaPro – Script de déploiement" -ForegroundColor White
Write-Host "  $(if($Update){'Mode : MISE À JOUR'}else{'Mode : INSTALLATION INITIALE'})" -ForegroundColor Gray
Write-Host ""

# ── 1. Vérifications prérequis ────────────────────────────────────────────────
Write-Step "Vérification des prérequis"

# Git
try {
    $gitV = git --version 2>&1
    Write-Ok "Git : $gitV"
} catch {
    Write-Err "Git non trouvé. Installez Git depuis https://git-scm.com"
    exit 1
}

# PHP
try {
    $phpV = php -r "echo PHP_VERSION;" 2>&1
    Write-Ok "PHP : $phpV"
} catch {
    Write-Warn "PHP introuvable dans PATH. Le script continuera mais vérifiez votre serveur."
}

# MySQL (mysql.exe)
$mysqlExe = $null
@("C:\xampp\mysql\bin\mysql.exe","C:\wamp64\bin\mysql\mysql8.0.31\mysql.exe",
  "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe","mysql") | ForEach-Object {
    if (!$mysqlExe -and (Test-Path $_ -ErrorAction SilentlyContinue)) { $mysqlExe = $_ }
}
if (!$mysqlExe) {
    try { mysql --version | Out-Null; $mysqlExe = "mysql" } catch {}
}
if ($mysqlExe) { Write-Ok "MySQL trouvé : $mysqlExe" }
else           { Write-Warn "mysql.exe introuvable – import SQL ignoré. Utilisez setup.php depuis le navigateur." }

# ── 2. Clone / Pull ───────────────────────────────────────────────────────────
Write-Step "Récupération du code source"

if (Test-Path "$DeployDir\.git") {
    Write-Host "  Répertoire existant – mise à jour..." -ForegroundColor Gray
    Set-Location $DeployDir
    git fetch origin $Branch 2>&1 | ForEach-Object { Write-Host "  $_" -ForegroundColor DarkGray }
    git checkout $Branch      2>&1 | Out-Null
    git pull origin $Branch   2>&1 | ForEach-Object { Write-Host "  $_" -ForegroundColor DarkGray }
    Write-Ok "Code mis à jour (branche $Branch)"
} else {
    Write-Host "  Clonage dans : $DeployDir" -ForegroundColor Gray
    New-Item -ItemType Directory -Force -Path $DeployDir | Out-Null
    git clone --branch $Branch $RepoUrl $DeployDir 2>&1 | ForEach-Object {
        Write-Host "  $_" -ForegroundColor DarkGray
    }
    Write-Ok "Dépôt cloné"
    Set-Location $DeployDir
}

# ── 3. Dossiers & permissions ─────────────────────────────────────────────────
Write-Step "Création des dossiers"

@("videos","views","images") | ForEach-Object {
    $path = Join-Path $DeployDir $_
    if (!(Test-Path $path)) {
        New-Item -ItemType Directory -Path $path | Out-Null
        Write-Ok "Dossier créé : $path"
    } else {
        Write-Ok "Dossier existant : $_\"
    }
}

# ── 4. Configuration db.php ───────────────────────────────────────────────────
Write-Step "Configuration de la base de données"

$dbFile = Join-Path $DeployDir "admin\db.php"
$dbContent = @"
<?php
`$host = "$DBHost";
`$db   = "$DBName";
`$user = "$DBUser";
`$pass = "$DBPass";
try {
    `$pdo = new PDO("mysql:host=`$host;dbname=`$db;charset=utf8mb4",`$user,`$pass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException `$e) {
    die("Erreur de connexion à la base de données");
}
"@

Set-Content -Path $dbFile -Value $dbContent -Encoding UTF8
Write-Ok "admin/db.php configuré"

# ── 5. Import SQL (installation initiale uniquement) ─────────────────────────
if (!$Update -and $mysqlExe) {
    Write-Step "Import du schéma SQL"

    $sqlFile = Join-Path $DeployDir "schema.sql"
    if (Test-Path $sqlFile) {
        $mysqlArgs = @("-h", $DBHost, "-u", $DBUser)
        if ($DBPass -ne "") { $mysqlArgs += @("-p$DBPass") }

        # Créer la base si besoin
        $createDB = "CREATE DATABASE IF NOT EXISTS ``$DBName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        $createDB | & $mysqlExe @mysqlArgs 2>&1 | Out-Null

        # Importer schema.sql
        & $mysqlExe @mysqlArgs $DBName -e "source $sqlFile" 2>&1 | ForEach-Object {
            Write-Host "  $_" -ForegroundColor DarkGray
        }
        Write-Ok "Schéma SQL importé"
    } else {
        Write-Warn "schema.sql introuvable – utilisez setup.php depuis le navigateur"
    }
} elseif ($Update) {
    Write-Ok "Mode MàJ : import SQL ignoré (données conservées)"
}

# ── 6. Résumé final ───────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "   Déploiement terminé !" -ForegroundColor Green
Write-Host "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Site public   : http://localhost/formapro/" -ForegroundColor White
Write-Host "   Administration: http://localhost/formapro/admin/" -ForegroundColor White
Write-Host "   Installateur  : http://localhost/formapro/setup.php" -ForegroundColor White
Write-Host ""
if (!$Update) {
Write-Host "   Compte admin par défaut (si schema.sql importé) :" -ForegroundColor Yellow
Write-Host "     Identifiant : admin" -ForegroundColor Yellow
Write-Host "     Mot de passe: Admin1234  ← CHANGEZ-LE !" -ForegroundColor Red
Write-Host ""
}
Write-Host "   Dossier déployé : $DeployDir" -ForegroundColor Gray
Write-Host ""
Write-Host "  RAPPEL : Supprimez setup.php après la configuration !" -ForegroundColor Red
Write-Host ""

# Ouvrir le navigateur automatiquement
$openBrowser = Read-Host "  Ouvrir le site dans le navigateur ? (O/n)"
if ($openBrowser -ne "n" -and $openBrowser -ne "N") {
    Start-Process "http://localhost/formapro/"
}
