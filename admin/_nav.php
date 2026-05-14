<?php
// Partial: top navbar + sidebar – included in every admin page
$logo      = "../images/MUCODEC.gif";
$isAdmin   = isset($_SESSION["user"]["role"]) && $_SESSION["user"]["role"] === "ADMIN";
$current   = basename($_SERVER["PHP_SELF"]);

$navItems = [
    ["href" => "dashboard.php",      "icon" => "📊", "label" => "Tableau de bord"],
    ["href" => "upload.php",         "icon" => "📤", "label" => "Upload vidéo"],
    ["href" => "manage_videos.php",  "icon" => "🎬", "label" => "Gérer les vidéos"],
    ["href" => "manage_themes.php",  "icon" => "📁", "label" => "Gérer les thèmes"],
];
if ($isAdmin) {
    $navItems[] = ["href" => "users.php", "icon" => "👤", "label" => "Utilisateurs"];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle ?? "Admin – Espace Formation"; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--sidebar-w:230px;--bg:#f4f6f9;--card:#fff;--text:#212529;--sidebar-bg:#1b3b6f;--sidebar-text:#c8d9f0;--sidebar-active:#ffffff;}
[data-theme=dark]{--bg:#121212;--card:#1e1e2e;--text:#eaeaea;--sidebar-bg:#0d1b35;--sidebar-text:#8fafd4;--sidebar-active:#ffffff;}
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--text);margin:0;font-family:"Segoe UI",Roboto,sans-serif;display:flex;flex-direction:column;min-height:100vh;}
.topbar{background:linear-gradient(90deg,#1b3b6f,#2d8adb);padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:fixed;top:0;left:0;right:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.2);}
.topbar-brand{color:#fff;font-weight:700;font-size:1.05rem;display:flex;align-items:center;gap:8px;}
.topbar-brand img{background:#fff;border-radius:50%;padding:2px;}
.topbar-right{display:flex;align-items:center;gap:14px;color:#fff;}
.topbar-right a{color:#fff;font-size:.85rem;}
.theme-btn{background:none;border:none;cursor:pointer;font-size:1.2rem;}
.wrapper{display:flex;margin-top:56px;flex:1;}
.sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);min-height:calc(100vh - 56px);padding:20px 0;position:fixed;top:56px;left:0;bottom:0;overflow-y:auto;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:11px 22px;color:var(--sidebar-text);text-decoration:none;font-size:.9rem;transition:background .15s,color .15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.12);color:var(--sidebar-active);}
.sidebar a.active{font-weight:600;border-left:3px solid #2d8adb;}
.main-content{margin-left:var(--sidebar-w);padding:30px;flex:1;width:calc(100% - var(--sidebar-w));}
.card{background:var(--card);border:none;border-radius:12px;}
.card-hover{transition:transform .2s,box-shadow .2s;}
.card-hover:hover{transform:translateY(-4px);box-shadow:0 10px 24px rgba(0,0,0,.15);}
@media(max-width:768px){.sidebar{display:none;}.main-content{margin-left:0;padding:15px;}}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-brand">
    <img src="<?php echo $logo; ?>" width="36" height="36" alt="Logo">
    Espace Formation – Admin
  </div>
  <div class="topbar-right">
    <button class="theme-btn" onclick="toggleTheme()" title="Thème clair/sombre">🌙</button>
    <span><?php echo htmlspecialchars($_SESSION["user"]["fullname"]); ?></span>
    <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
  </div>
</div>

<div class="wrapper">
  <nav class="sidebar">
    <?php foreach ($navItems as $item): ?>
      <a href="<?php echo $item['href']; ?>"
         class="<?php echo $current === $item['href'] ? 'active' : ''; ?>">
        <span><?php echo $item['icon']; ?></span>
        <?php echo $item['label']; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="main-content">
