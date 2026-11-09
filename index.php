<?php
require_once __DIR__ . '/viewer_auth.php';
requireViewerAuth();

$videoDir = "videos/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');

$totalVideos = 0;
$themeData   = [];
foreach ($themes as $themeDir) {
    $name   = basename($themeDir);
    $videos = glob($themeDir . "/*.mp4") ?: [];
    $count  = count($videos);
    $totalVideos += $count;
    $coverFiles = glob($themeDir . "/cover.*") ?: [];
    $cover  = $coverFiles ? $coverFiles[0] : "";
    $themeData[] = ["name" => $name, "count" => $count, "path" => $themeDir, "cover" => $cover];
}
$totalThemes = count($themeData);

$icons  = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚","🎓","💼","🌐","📱"];
$colors = ["#6366f1","#0ea5e9","#10b981","#f59e0b","#ef4444","#8b5cf6","#06b6d4","#84cc16","#f97316","#ec4899","#14b8a6","#a855f7"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>MucoAcadémie – Formations</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
::-webkit-scrollbar{display:none;}
body{background:#faf8ff;font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:#131b2e;}
a{text-decoration:none;color:inherit;}

/* SIDEBAR */
.sidebar{position:fixed;left:0;top:0;height:100vh;width:260px;background:#fff;border-right:1px solid #c4c5d7;display:flex;flex-direction:column;justify-content:space-between;z-index:50;overflow-y:auto;}
.sidebar-logo{height:64px;display:flex;align-items:center;padding:0 20px;gap:10px;border-bottom:1px solid #e2e7ff;flex-shrink:0;}
.sidebar-logo img{background:#fff;border-radius:8px;padding:2px;}
.sidebar-logo-text{font-size:1rem;font-weight:800;color:#131b2e;line-height:1.1;}
.sidebar-logo-sub{font-size:.72rem;font-weight:700;color:#0037b0;text-transform:uppercase;letter-spacing:.08em;}
.sidebar-nav{padding:12px;}
.sidebar-section{font-size:.72rem;font-weight:700;color:#747686;text-transform:uppercase;letter-spacing:.08em;padding:4px 8px 8px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:.9rem;font-weight:600;color:#434655;cursor:pointer;margin-bottom:2px;transition:.15s;}
.nav-item:hover{background:#f2f3ff;color:#131b2e;}
.nav-item.active{background:#1d4ed8;color:#fff;}
.nav-item svg{flex-shrink:0;}
.sidebar-bottom{padding:12px 12px 16px;border-top:1px solid #e2e7ff;flex-shrink:0;}
.sidebar-user{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f2f3ff;border-radius:8px;margin-bottom:8px;}
.user-avatar{width:36px;height:36px;border-radius:50%;background:#0037b0;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.95rem;flex-shrink:0;}
.user-name{font-size:.88rem;font-weight:700;color:#131b2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;}
.user-role{font-size:.75rem;color:#747686;}
.sidebar-logout{display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border-radius:8px;background:#fef2f2;color:#ba1a1a;font-size:.88rem;font-weight:600;transition:.15s;}
.sidebar-logout:hover{background:#fee2e2;}
.sidebar-admin{display:flex;align-items:center;justify-content:center;gap:8px;padding:8px 12px;border-radius:8px;color:#747686;font-size:.82rem;font-weight:600;margin-top:6px;transition:.15s;}
.sidebar-admin:hover{background:#f2f3ff;}

/* MAIN LAYOUT */
.main-wrap{margin-left:260px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{position:sticky;top:0;height:56px;background:rgba(250,248,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:40;}
.topbar-title{font-size:1rem;font-weight:700;color:#131b2e;}
.topbar-user{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#747686;}

/* CONTENT */
.content{padding:24px;flex:1;}
.welcome-banner{background:linear-gradient(135deg,#0037b0 0%,#1d4ed8 50%,#2151da 100%);border-radius:16px;padding:32px;color:#fff;margin-bottom:24px;position:relative;overflow:hidden;}
.welcome-banner::before{content:"";position:absolute;top:-60px;right:-60px;width:300px;height:300px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none;}
.welcome-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:4px 14px;border-radius:20px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px;}
.welcome-title{font-size:1.7rem;font-weight:800;line-height:1.2;margin-bottom:8px;position:relative;z-index:1;}
.welcome-sub{font-size:.95rem;color:rgba(255,255,255,.8);margin-bottom:20px;position:relative;z-index:1;}
.stats-row{display:flex;gap:24px;flex-wrap:wrap;position:relative;z-index:1;}
.stat-item strong{font-size:1.4rem;font-weight:800;display:block;color:#fff;}
.stat-item small{font-size:.8rem;color:rgba(255,255,255,.7);}

/* SECTION */
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.section-marker{width:4px;height:24px;background:#0037b0;border-radius:4px;display:inline-block;margin-right:10px;flex-shrink:0;}
.section-title{font-size:1.2rem;font-weight:800;color:#131b2e;display:flex;align-items:center;}
.section-count{font-size:.82rem;font-weight:600;color:#747686;background:#e2e7ff;padding:3px 10px;border-radius:20px;}

/* THEME GRID */
.theme-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;}
.theme-card{background:#fff;border-radius:12px;border:1px solid #c4c5d7;overflow:hidden;transition:.2s;box-shadow:0 1px 3px rgba(19,27,46,.05);}
.theme-card:hover{box-shadow:0 8px 24px rgba(19,27,46,.1);transform:translateY(-3px);}
.theme-thumb{height:150px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.theme-count-badge{position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.6);color:#fff;font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:20px;}
.theme-body{padding:16px;}
.theme-tag{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:3px 10px;border-radius:20px;display:inline-block;margin-bottom:8px;}
.theme-name{font-size:1rem;font-weight:700;color:#131b2e;margin-bottom:12px;line-height:1.3;}
.theme-footer{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid #e2e7ff;}
.theme-meta{font-size:.8rem;color:#747686;}
.btn-access{background:#0037b0;color:#fff;padding:6px 16px;border-radius:6px;font-size:.82rem;font-weight:600;transition:.15s;}
.theme-card:hover .btn-access{background:#1d4ed8;}

/* EMPTY STATE */
.empty-state{text-align:center;padding:60px 20px;}
.empty-icon{font-size:3.5rem;margin-bottom:16px;}
.empty-title{font-size:1.2rem;font-weight:700;color:#131b2e;margin-bottom:8px;}
.empty-sub{font-size:.9rem;color:#747686;}

/* FEATURES SECTION */
.features-section{background:#fff;border-radius:16px;border:1px solid #c4c5d7;padding:32px;margin-top:24px;}
.features-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:20px;margin-top:20px;}
.feature-card{text-align:center;padding:20px 16px;}
.feature-icon{margin-bottom:12px;display:flex;align-items:center;justify-content:center;}
.feature-icon img{width:64px;height:64px;object-fit:contain;}
.feature-title{font-size:.95rem;font-weight:700;color:#131b2e;margin-bottom:6px;}
.feature-text{font-size:.82rem;color:#747686;line-height:1.5;}

footer{background:#131b2e;padding:28px 24px;margin-top:24px;border-radius:12px;}
.footer-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.1);}
.footer-brand{color:#fff;font-weight:800;}
.footer-links{display:flex;gap:16px;}
.footer-links a{color:#747686;font-size:.85rem;transition:.15s;}
.footer-links a:hover{color:#fff;}
.footer-copy{color:#434655;font-size:.8rem;padding-top:14px;}

@media(max-width:768px){
  .sidebar{display:none;}
  .main-wrap{margin-left:0;}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div>
    <div class="sidebar-logo">
      <img src="images/mucoacademie.png" width="36" height="36" alt="Logo">
      <div>
        <div class="sidebar-logo-text">MucoAcadémie</div>
        <div class="sidebar-logo-sub">Formations</div>
      </div>
    </div>
    <div class="sidebar-nav">
      <div class="sidebar-section">Navigation</div>
      <a href="index.php" class="nav-item active">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Tableau de bord</span>
      </a>
      <?php if (!empty($themeData)): ?>
      <div class="sidebar-section" style="margin-top:8px;">Formations</div>
      <?php foreach(array_slice($themeData, 0, 6) as $t): ?>
      <a href="videos.php?theme=<?php echo urlencode($t['name']); ?>" class="nav-item">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($t['name']); ?></span>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="sidebar-bottom">
    <?php if (!empty($_SESSION['viewer']['fullname'])): ?>
    <div class="sidebar-user">
      <div class="user-avatar"><?php echo strtoupper(mb_substr($_SESSION['viewer']['fullname'], 0, 1)); ?></div>
      <div style="min-width:0;">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['viewer']['fullname']); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['viewer']['direction'] ?? 'Apprenant'); ?></div>
      </div>
    </div>
    <?php endif; ?>
    <a href="viewer_logout.php" class="sidebar-logout">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Déconnexion
    </a>
    <a href="admin/login.php" class="sidebar-admin">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      Espace Admin
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main-wrap">
  <header class="topbar">
    <div class="topbar-title">Tableau de bord</div>
    <div class="topbar-user">
      <?php if (!empty($_SESSION['viewer']['login'])): ?>
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span><?php echo htmlspecialchars($_SESSION['viewer']['login']); ?></span>
      <?php endif; ?>
    </div>
  </header>

  <div class="content">

    <div class="welcome-banner">
      <div class="welcome-badge">🎓 Espace de formation</div>
      <div class="welcome-title">Bienvenue sur MucoAcadémie</div>
      <div class="welcome-sub">Accédez à l'ensemble des tutoriels vidéo de la DRH – Service Formation.</div>
      <div class="stats-row">
        <div class="stat-item">
          <strong><?php echo $totalVideos; ?></strong>
          <small>Vidéos disponibles</small>
        </div>
        <div class="stat-item">
          <strong><?php echo $totalThemes; ?></strong>
          <small>Thèmes de formation</small>
        </div>
        <div class="stat-item">
          <strong>100%</strong>
          <small>Gratuit &amp; accessible</small>
        </div>
      </div>
    </div>

    <div id="formations">
      <div class="section-header">
        <div class="section-title">
          <span class="section-marker"></span>
          Nos formations
        </div>
        <span class="section-count"><?php echo $totalThemes; ?> thème(s)</span>
      </div>

      <?php if (empty($themeData)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <div class="empty-title">Aucune formation disponible</div>
          <div class="empty-sub">Les formations seront publiées prochainement.</div>
        </div>
      <?php else: ?>
        <div class="theme-grid">
          <?php foreach ($themeData as $i => $theme): ?>
            <?php
              $color = $colors[$i % count($colors)];
              $icon  = $icons[$i % count($icons)];
            ?>
            <a href="videos.php?theme=<?php echo urlencode($theme['name']); ?>" class="theme-card">
              <div class="theme-thumb" style="background:linear-gradient(135deg,<?php echo $color; ?>22,<?php echo $color; ?>44);">
                <?php if (!empty($theme['cover'])): ?>
                  <img src="<?php echo htmlspecialchars($theme['cover']); ?>" alt="<?php echo htmlspecialchars($theme['name']); ?>"
                       style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                <?php else: ?>
                  <span style="font-size:3rem;filter:drop-shadow(0 4px 8px rgba(0,0,0,.2));"><?php echo $icon; ?></span>
                <?php endif; ?>
                <div class="theme-count-badge"><?php echo $theme['count']; ?> vidéo(s)</div>
              </div>
              <div class="theme-body">
                <div class="theme-tag" style="background:<?php echo $color; ?>18;color:<?php echo $color; ?>;">Formation</div>
                <div class="theme-name"><?php echo htmlspecialchars($theme['name']); ?></div>
                <div class="theme-footer">
                  <span class="theme-meta">🎬 <?php echo $theme['count']; ?> tutoriel(s)</span>
                  <span class="btn-access">Accéder →</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="features-section">
      <div class="section-title"><span class="section-marker"></span>Pourquoi MucoAcadémie ?</div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><img src="images/feature_videos.png" alt="Vidéos HD"></div>
          <div class="feature-title">Vidéos HD</div>
          <div class="feature-text">Des tutoriels clairs et bien structurés, accessibles depuis n'importe quel navigateur.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><img src="images/feature_themes.png" alt="Organisé par thèmes"></div>
          <div class="feature-title">Organisé par thèmes</div>
          <div class="feature-text">Trouvez rapidement la formation dont vous avez besoin grâce aux catégories.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><img src="images/feature_responsive.png" alt="Responsive"></div>
          <div class="feature-title">Responsive</div>
          <div class="feature-text">Apprenez sur ordinateur, tablette ou mobile, à votre convenance.</div>
        </div>
        <div class="feature-card">
          <div class="feature-icon"><img src="images/feature_secure.png" alt="Accès sécurisé"></div>
          <div class="feature-title">Accès sécurisé</div>
          <div class="feature-text">Espace d'administration protégé pour gérer les contenus en toute sécurité.</div>
        </div>
      </div>
    </div>

    <footer>
      <div class="footer-inner">
        <div class="footer-brand">MucoAcadémie · MUCODEC</div>
        <nav class="footer-links">
          <a href="index.php">Accueil</a>
          <a href="#formations">Formations</a>
          <a href="admin/login.php">Administration</a>
        </nav>
      </div>
      <p class="footer-copy">&copy; <?php echo date('Y'); ?> DIDSI – DRH Service Formation. Tous droits réservés.</p>
    </footer>

  </div>
</div>

</body>
</html>
