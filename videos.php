<?php
require_once __DIR__ . '/viewer_auth.php';
requireViewerAuth();

$videoDir = "videos/";
$viewsDir = "views/";
if (!file_exists($viewsDir)) mkdir($viewsDir, 0777, true);

$theme     = isset($_GET['theme']) ? basename($_GET['theme']) : '';
$themePath = $videoDir . $theme . "/";
$videos    = [];

if (!empty($theme) && is_dir($themePath)) {
    $videos = glob($themePath . "*.mp4") ?: [];
}
$videoCount = count($videos);

$totalViews = 0;
foreach ($videos as $v) {
    $vf = $viewsDir . pathinfo($v, PATHINFO_FILENAME) . ".txt";
    $totalViews += file_exists($vf) ? (int)file_get_contents($vf) : 0;
}

$allThemes = array_filter(glob($videoDir . "*"), 'is_dir');
$icons = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚","🎓","💼","🌐","📱"];
$coverFiles = glob($themePath . "cover.*") ?: [];
$coverImg   = $coverFiles ? $coverFiles[0] : "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>MucoAcadémie – <?php echo htmlspecialchars($theme ?: 'Vidéos'); ?></title>
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
.topbar-left{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#747686;}
.topbar-left a{color:#0037b0;font-weight:600;}
.topbar-left a:hover{color:#1d4ed8;}
.topbar-sep{color:#c4c5d7;}
.topbar-current{font-weight:700;color:#131b2e;}
.topbar-user{display:flex;align-items:center;gap:8px;font-size:.88rem;color:#747686;}

/* CONTENT */
.content{padding:24px;flex:1;}

/* COURSE BANNER */
.course-banner{border-radius:16px;padding:28px 32px;color:#fff;margin-bottom:24px;position:relative;overflow:hidden;background:linear-gradient(135deg,#0037b0,#1d4ed8);}
.course-banner.has-cover{background-size:cover;background-position:center;}
.course-banner::before{content:"";position:absolute;inset:0;background:rgba(0,0,0,.45);pointer-events:none;}
.banner-inner{position:relative;z-index:1;}
.banner-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;}
.banner-title{font-size:1.6rem;font-weight:800;line-height:1.2;margin-bottom:12px;}
.banner-meta{display:flex;gap:20px;flex-wrap:wrap;}
.banner-meta-item{display:flex;align-items:center;gap:6px;font-size:.88rem;color:rgba(255,255,255,.85);}
.banner-meta-item strong{color:#fff;}

/* MAIN GRID */
.page-grid{display:grid;grid-template-columns:1fr 260px;gap:24px;align-items:start;}
@media(max-width:1000px){.page-grid{grid-template-columns:1fr;}.themes-sidebar{display:none;}}

/* SECTION */
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.section-marker{width:4px;height:24px;background:#0037b0;border-radius:4px;display:inline-block;margin-right:10px;flex-shrink:0;}
.section-title{font-size:1.1rem;font-weight:800;color:#131b2e;display:flex;align-items:center;}
.section-count{font-size:.82rem;font-weight:600;color:#747686;background:#e2e7ff;padding:3px 10px;border-radius:20px;}

/* VIDEO GRID */
.video-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px;}
.video-card{background:#fff;border-radius:12px;border:1px solid #c4c5d7;overflow:hidden;transition:.2s;box-shadow:0 1px 3px rgba(19,27,46,.05);}
.video-card:hover{box-shadow:0 8px 24px rgba(19,27,46,.1);transform:translateY(-3px);}
.video-thumb{height:140px;background:#e2e7ff;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.video-thumb video{width:100%;height:100%;object-fit:cover;display:block;}
.video-play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.2);opacity:0;transition:.2s;}
.video-card:hover .video-play-overlay{opacity:1;}
.play-circle{width:48px;height:48px;border-radius:50%;background:#0037b0;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,55,176,.4);}
.play-circle svg{width:20px;height:20px;fill:#fff;margin-left:3px;}
.video-num{position:absolute;top:8px;left:8px;background:rgba(0,0,0,.6);color:#fff;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:12px;}
.video-body{padding:12px 14px 14px;}
.video-title{font-size:.9rem;font-weight:700;color:#131b2e;line-height:1.35;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.video-footer{display:flex;align-items:center;justify-content:space-between;}
.video-views{color:#747686;font-size:.78rem;display:flex;align-items:center;gap:4px;}
.btn-watch{background:#0037b0;color:#fff;padding:5px 14px;border-radius:6px;font-size:.78rem;font-weight:600;transition:.15s;}
.video-card:hover .btn-watch{background:#1d4ed8;}

/* THEMES SIDEBAR */
.themes-sidebar{}
.sidebar-box{background:#fff;border-radius:12px;border:1px solid #c4c5d7;padding:18px;position:sticky;top:72px;}
.sidebar-box-title{font-size:.88rem;font-weight:700;color:#131b2e;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #e2e7ff;}
.theme-link{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;transition:.15s;margin-bottom:2px;font-size:.88rem;color:#434655;}
.theme-link:hover{background:#f2f3ff;color:#131b2e;}
.theme-link.active{background:#eaedff;color:#0037b0;font-weight:700;}
.theme-link-icon{font-size:1rem;flex-shrink:0;}
.theme-link-name{flex:1;font-weight:600;}
.theme-link-count{background:#e2e7ff;color:#747686;font-size:.72rem;font-weight:600;padding:2px 7px;border-radius:10px;}
.theme-link.active .theme-link-count{background:#0037b010;color:#0037b0;}

/* EMPTY STATE */
.empty-state{text-align:center;padding:60px 20px;}
.empty-icon{font-size:3.5rem;margin-bottom:16px;}
.empty-title{font-size:1.1rem;font-weight:700;color:#131b2e;margin-bottom:8px;}
.empty-sub{font-size:.9rem;color:#747686;}
.empty-link{color:#0037b0;font-weight:600;}

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
      <a href="index.php" class="nav-item">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Tableau de bord</span>
      </a>
      <?php if (!empty($allThemes)): ?>
      <div class="sidebar-section" style="margin-top:8px;">Formations</div>
      <?php foreach(array_values($allThemes) as $i => $t): ?>
        <?php $tn = basename($t); ?>
      <a href="videos.php?theme=<?php echo urlencode($tn); ?>" class="nav-item <?php echo $tn === $theme ? 'active' : ''; ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($tn); ?></span>
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
    <div class="topbar-left">
      <a href="index.php">Accueil</a>
      <span class="topbar-sep">›</span>
      <?php if (!empty($theme)): ?>
        <span class="topbar-current"><?php echo htmlspecialchars($theme); ?></span>
      <?php else: ?>
        <span class="topbar-current">Formations</span>
      <?php endif; ?>
    </div>
    <div class="topbar-user">
      <?php if (!empty($_SESSION['viewer']['login'])): ?>
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span><?php echo htmlspecialchars($_SESSION['viewer']['login']); ?></span>
      <?php endif; ?>
    </div>
  </header>

  <div class="content">

    <?php if (!empty($theme) && is_dir($themePath)): ?>
    <div class="course-banner <?php echo $coverImg ? 'has-cover' : ''; ?>"
         <?php echo $coverImg ? 'style="background-image:url('.htmlspecialchars($coverImg).')"' : ''; ?>>
      <div class="banner-inner">
        <div class="banner-badge">🎓 Formation</div>
        <div class="banner-title"><?php echo htmlspecialchars($theme); ?></div>
        <div class="banner-meta">
          <div class="banner-meta-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            <strong><?php echo $videoCount; ?></strong> tutoriel(s)
          </div>
          <div class="banner-meta-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <strong><?php echo $totalViews; ?></strong> vue(s)
          </div>
          <div class="banner-meta-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Accès libre
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="course-banner">
      <div class="banner-inner">
        <div class="banner-title">Thème introuvable</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="page-grid">
      <main>
        <?php if (empty($theme) || !is_dir($themePath)): ?>
          <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <div class="empty-title">Thème introuvable</div>
            <div class="empty-sub"><a href="index.php" class="empty-link">← Retour aux formations</a></div>
          </div>
        <?php elseif (empty($videos)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title">Aucune vidéo dans ce thème</div>
            <div class="empty-sub">Des contenus seront ajoutés prochainement.</div>
          </div>
        <?php else: ?>
          <div class="section-header">
            <div class="section-title">
              <span class="section-marker"></span>
              Tutoriels disponibles
            </div>
            <span class="section-count"><?php echo $videoCount; ?> vidéo(s)</span>
          </div>
          <div class="video-grid">
            <?php foreach ($videos as $idx => $video): ?>
              <?php
                $file        = basename($video);
                $filename    = pathinfo($file, PATHINFO_FILENAME);
                $vf          = $viewsDir . $filename . ".txt";
                $views       = file_exists($vf) ? (int)file_get_contents($vf) : 0;
                $displayName = ucfirst(str_replace(['_','-'], ' ', $filename));
              ?>
              <a href="watch.php?theme=<?php echo urlencode($theme); ?>&video=<?php echo urlencode($file); ?>" class="video-card">
                <div class="video-thumb">
                  <video preload="metadata" muted>
                    <source src="<?php echo htmlspecialchars($video); ?>#t=0.1" type="video/mp4">
                  </video>
                  <div class="video-num"><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></div>
                  <div class="video-play-overlay">
                    <div class="play-circle">
                      <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                  </div>
                </div>
                <div class="video-body">
                  <div class="video-title"><?php echo htmlspecialchars($displayName); ?></div>
                  <div class="video-footer">
                    <div class="video-views">
                      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      <?php echo $views; ?> vue(s)
                    </div>
                    <span class="btn-watch">Regarder →</span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </main>

      <aside class="themes-sidebar">
        <div class="sidebar-box">
          <div class="sidebar-box-title">📚 Autres formations</div>
          <?php foreach (array_values($allThemes) as $i => $t):
            $tn = basename($t);
            $tc = count(glob($t . "/*.mp4") ?: []);
          ?>
            <a href="videos.php?theme=<?php echo urlencode($tn); ?>"
               class="theme-link <?php echo $tn === $theme ? 'active' : ''; ?>">
              <span class="theme-link-icon"><?php echo $icons[$i % count($icons)]; ?></span>
              <span class="theme-link-name"><?php echo htmlspecialchars($tn); ?></span>
              <span class="theme-link-count"><?php echo $tc; ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>

  </div>
</div>

</body>
</html>
