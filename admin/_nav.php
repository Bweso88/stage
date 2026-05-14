<?php
$logo    = "../images/MUCODEC.gif";
$isAdmin = isset($_SESSION["user"]["role"]) && $_SESSION["user"]["role"] === "ADMIN";
$current = basename($_SERVER["PHP_SELF"]);

$navItems = [
    ["href"=>"dashboard.php",     "icon"=>'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>', "label"=>"Tableau de bord"],
    ["href"=>"upload.php",        "icon"=>'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>', "label"=>"Upload vidéo"],
    ["href"=>"manage_videos.php", "icon"=>'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>', "label"=>"Gérer les vidéos"],
    ["href"=>"manage_themes.php", "icon"=>'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>', "label"=>"Gérer les thèmes"],
];
if ($isAdmin) {
    $navItems[] = ["href"=>"users.php","icon"=>'<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',"label"=>"Utilisateurs"];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle ?? "Admin – FormaPro"; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --sb-w:240px;--topbar-h:60px;
  --sb-bg:#1c1d1f;--sb-hover:rgba(255,255,255,.07);--sb-active:#6366f1;
  --bg:#f5f7fa;--card:#fff;--text:#1c1d1f;--muted:#6b7280;--border:#e5e7eb;
  --indigo:#6366f1;--indigo-d:#4f46e5;
}
[data-theme=dark]{--bg:#0f1117;--card:#1c1d1f;--text:#f3f4f6;--border:#2d2f31;--sb-bg:#111;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:"Segoe UI",system-ui,sans-serif;
  display:flex;flex-direction:column;min-height:100vh;}

/* TOPBAR */
.topbar{height:var(--topbar-h);background:var(--sb-bg);display:flex;align-items:center;
  justify-content:space-between;padding:0 20px 0 0;position:fixed;top:0;left:0;right:0;z-index:200;
  box-shadow:0 1px 0 rgba(255,255,255,.06);}
.topbar-left{width:var(--sb-w);display:flex;align-items:center;gap:10px;padding:0 20px;flex-shrink:0;}
.topbar-logo{display:flex;align-items:center;gap:9px;color:#fff;font-weight:800;font-size:1rem;}
.topbar-logo img{background:#fff;border-radius:7px;padding:2px;}
.topbar-logo span{color:var(--indigo);}
.topbar-right{display:flex;align-items:center;gap:14px;}
.topbar-user{display:flex;align-items:center;gap:9px;color:#d1d5db;font-size:.85rem;}
.topbar-user-avatar{width:32px;height:32px;border-radius:50%;background:var(--indigo);
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;}
.topbar-actions{display:flex;align-items:center;gap:8px;}
.btn-theme{background:none;border:none;cursor:pointer;color:#9ca3af;padding:6px;border-radius:6px;
  line-height:1;transition:color .2s,background .2s;}
.btn-theme:hover{color:#fff;background:rgba(255,255,255,.08);}
.btn-logout{color:#9ca3af;font-size:.82rem;padding:6px 14px;border:1px solid #374151;
  border-radius:6px;transition:all .2s;}
.btn-logout:hover{color:#fff;border-color:#6b7280;background:rgba(255,255,255,.05);}
.topbar-sep{width:1px;height:24px;background:#2d2f31;}

/* SIDEBAR */
.sidebar{width:var(--sb-w);background:var(--sb-bg);position:fixed;top:var(--topbar-h);
  left:0;bottom:0;overflow-y:auto;padding:20px 12px;border-right:1px solid rgba(255,255,255,.05);
  scrollbar-width:thin;scrollbar-color:#374151 transparent;}
.sb-section{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;
  color:#4b5563;padding:12px 10px 6px;margin-top:6px;}
.sb-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
  color:#9ca3af;font-size:.875rem;transition:all .18s;cursor:pointer;margin-bottom:2px;}
.sb-link:hover{background:var(--sb-hover);color:#d1d5db;}
.sb-link.active{background:rgba(99,102,241,.15);color:#a5b4fc;font-weight:600;}
.sb-link.active svg{stroke:#a5b4fc;}
.sb-link svg{flex-shrink:0;opacity:.8;}
.sb-link.active svg{opacity:1;}
.sb-divider{height:1px;background:#2d2f31;margin:10px 0;}

/* WRAPPER */
.wrapper{display:flex;margin-top:var(--topbar-h);}
.main-content{margin-left:var(--sb-w);flex:1;padding:28px 32px;min-height:calc(100vh - var(--topbar-h));}
@media(max-width:768px){
  .sidebar{display:none;}
  .main-content{margin-left:0;padding:16px;}
}

/* CARDS */
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:none;}
.card-hover{transition:transform .2s,box-shadow .2s,border-color .2s;}
.card-hover:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08);border-color:#d1d5db;}
[data-theme=dark] .card-hover:hover{box-shadow:0 8px 24px rgba(0,0,0,.3);}

/* MISC */
.page-title{font-size:1.3rem;font-weight:800;margin-bottom:24px;display:flex;align-items:center;gap:10px;}
.badge-indigo{background:#eef2ff;color:var(--indigo);font-weight:600;padding:3px 10px;border-radius:20px;font-size:.75rem;}
[data-theme=dark] .badge-indigo{background:rgba(99,102,241,.2);color:#a5b4fc;}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-left">
    <a href="dashboard.php" class="topbar-logo">
      <img src="<?php echo $logo; ?>" width="30" height="30" alt="Logo">
      Forma<span>Pro</span>
    </a>
  </div>
  <div class="topbar-right">
    <div class="topbar-user">
      <div class="topbar-user-avatar">
        <?php echo strtoupper(mb_substr($_SESSION["user"]["fullname"], 0, 1)); ?>
      </div>
      <span><?php echo htmlspecialchars($_SESSION["user"]["fullname"]); ?></span>
      <span class="badge-indigo"><?php echo $_SESSION["user"]["role"]; ?></span>
    </div>
    <div class="topbar-sep"></div>
    <div class="topbar-actions">
      <button class="btn-theme" onclick="toggleTheme()" title="Thème">
        <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
  </div>
</div>

<!-- SIDEBAR -->
<nav class="sidebar">
  <div class="sb-section">Navigation</div>
  <?php foreach ($navItems as $item): ?>
    <a href="<?php echo $item['href']; ?>" class="sb-link <?php echo $current===$item['href']?'active':''; ?>">
      <?php echo $item['icon']; ?>
      <?php echo $item['label']; ?>
    </a>
  <?php endforeach; ?>
  <div class="sb-divider"></div>
  <a href="../index.php" target="_blank" class="sb-link">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Voir le site
  </a>
</nav>

<div class="wrapper">
  <main class="main-content">
