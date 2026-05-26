<?php
$videoDir = "videos/";
$viewsDir = "views/";
if (!file_exists($viewsDir)) mkdir($viewsDir, 0777, true);

$theme = isset($_GET['theme']) ? basename($_GET['theme']) : '';
$file  = isset($_GET['video']) ? basename($_GET['video']) : '';

$themePath = $videoDir . $theme . "/";
$videoPath = $themePath . $file;

if (empty($theme) || empty($file) || !is_dir($themePath) || !file_exists($videoPath)) {
    header("Location: index.php");
    exit;
}

/* Incrémenter le compteur de vues */
$filename = pathinfo($file, PATHINFO_FILENAME);
$vf       = $viewsDir . $filename . ".txt";
$views    = file_exists($vf) ? (int)file_get_contents($vf) : 0;
$views++;
file_put_contents($vf, $views);

$displayName = ucfirst(str_replace(['_','-'], ' ', $filename));

/* Autres vidéos du même thème */
$allVideos   = glob($themePath . "*.mp4") ?: [];
$allThemes   = array_filter(glob($videoDir . "*"), 'is_dir');
$icons       = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚","🎓","💼","🌐","📱"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>MucoAcadémie – <?php echo htmlspecialchars($displayName); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --navy:#0d1b4b;--indigo:#e8192c;--indigo-d:#c0141f;--indigo-l:#fff1f2;
  --bg:#f5f7fa;--card:#ffffff;--muted:#6b7280;--border:#e5e7eb;--radius:10px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--navy);font-family:"Segoe UI",system-ui,sans-serif;}
a{text-decoration:none;color:inherit;}

.navbar{background:var(--navy);padding:0 5%;height:64px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;top:0;z-index:999;
  box-shadow:0 2px 12px rgba(0,0,0,.35);}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:1.2rem;}
.navbar-brand span{color:var(--indigo);}
.navbar-links{display:flex;align-items:center;gap:20px;}
.navbar-links a{color:#d1d5db;font-size:.88rem;transition:color .2s;}
.navbar-links a:hover{color:#fff;}
.btn-nav{background:var(--indigo);color:#fff !important;padding:6px 18px;border-radius:6px;font-weight:600;font-size:.82rem;}

.breadcrumb-bar{background:var(--navy);padding:10px 5%;border-top:1px solid #1a2e6e;}
.breadcrumb-custom{display:flex;align-items:center;gap:8px;color:#9ca3af;font-size:.83rem;flex-wrap:wrap;}
.breadcrumb-custom a{color:#ff8a94;transition:color .2s;}
.breadcrumb-custom a:hover{color:#fff;}
.breadcrumb-sep{color:#4b5563;}

.watch-layout{display:grid;grid-template-columns:1fr 300px;gap:0;max-width:1600px;margin:0 auto;}
@media(max-width:960px){.watch-layout{grid-template-columns:1fr;}.watch-sidebar{display:none;}}

.player-wrap{background:#000;width:100%;}
.player-wrap video{width:100%;max-height:calc(100vh - 130px);display:block;outline:none;}

.video-info{padding:20px 28px;background:#fff;border-bottom:1px solid var(--border);}
.video-info h1{font-size:1.3rem;font-weight:800;color:var(--navy);line-height:1.3;margin-bottom:10px;}
.video-meta-row{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.meta-pill{display:flex;align-items:center;gap:5px;color:var(--muted);font-size:.82rem;}
.meta-pill svg{flex-shrink:0;}
.back-link{display:inline-flex;align-items:center;gap:6px;background:var(--indigo-l);color:var(--indigo);
  font-size:.82rem;font-weight:600;padding:6px 14px;border-radius:6px;transition:background .2s;}
.back-link:hover{background:#c7d2fe;}

/* Sidebar */
.watch-sidebar{background:#fff;border-left:1px solid var(--border);height:calc(100vh - 64px);
  overflow-y:auto;position:sticky;top:64px;}
.sidebar-head{padding:16px 18px;font-weight:700;font-size:.88rem;border-bottom:1px solid var(--border);
  background:var(--navy);color:#fff;display:flex;align-items:center;gap:8px;position:sticky;top:0;z-index:2;}
.sidebar-head span{color:#ff8a94;}
.sidebar-item{display:flex;align-items:center;gap:12px;padding:12px 18px;
  border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s;}
.sidebar-item:hover{background:var(--bg);}
.sidebar-item.active-vid{background:var(--indigo-l);border-left:3px solid var(--indigo);}
.sidebar-item.active-vid .sitem-name{color:var(--indigo);font-weight:700;}
.sitem-thumb{width:88px;height:52px;background:#1c1d1f;border-radius:6px;overflow:hidden;flex-shrink:0;position:relative;}
.sitem-thumb video{width:100%;height:100%;object-fit:cover;display:block;}
.sitem-play-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  background:rgba(0,0,0,.3);}
.sitem-play-icon svg{width:16px;height:16px;fill:#fff;}
.sitem-info{flex:1;min-width:0;}
.sitem-num{font-size:.68rem;background:var(--indigo);color:#fff;padding:1px 6px;border-radius:10px;
  display:inline-block;margin-bottom:4px;font-weight:700;}
.sitem-name{font-size:.82rem;font-weight:600;color:var(--navy);line-height:1.35;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.sitem-views{font-size:.72rem;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:3px;}

footer{background:#111;padding:24px 5%;text-align:center;color:#4b5563;font-size:.82rem;}
footer a{color:#6b7280;}footer a:hover{color:#fff;}
</style>
</head>
<body>

<header class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="images/MUCODEC.gif" width="36" height="36" alt="Logo" style="border-radius:6px;">
    Muco<span>Académie</span>
  </a>
  <nav class="navbar-links">
    <a href="index.php">Accueil</a>
    <a href="index.php#formations">Formations</a>
    <a href="admin/login.php" class="btn-nav">Admin</a>
  </nav>
</header>

<div class="breadcrumb-bar">
  <div class="breadcrumb-custom">
    <a href="index.php">Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <a href="index.php#formations">Formations</a>
    <span class="breadcrumb-sep">›</span>
    <a href="videos.php?theme=<?php echo urlencode($theme); ?>"><?php echo htmlspecialchars($theme); ?></a>
    <span class="breadcrumb-sep">›</span>
    <span><?php echo htmlspecialchars($displayName); ?></span>
  </div>
</div>

<div class="watch-layout">

  <!-- Player + info -->
  <div>
    <div class="player-wrap">
      <video controls autoplay preload="auto">
        <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
        Votre navigateur ne supporte pas la lecture vidéo.
      </video>
    </div>

    <div class="video-info">
      <h1><?php echo htmlspecialchars($displayName); ?></h1>
      <div class="video-meta-row">
        <div class="meta-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <?php echo $views; ?> vue(s)
        </div>
        <div class="meta-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <?php echo htmlspecialchars($theme); ?>
        </div>
        <div class="meta-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?php echo count($allVideos); ?> vidéo(s) dans ce thème
        </div>
        <a href="videos.php?theme=<?php echo urlencode($theme); ?>" class="back-link">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Retour au thème
        </a>
      </div>
    </div>
  </div>

  <!-- Sidebar: liste des vidéos -->
  <aside class="watch-sidebar">
    <div class="sidebar-head">
      📁 <span><?php echo htmlspecialchars($theme); ?></span>
      &nbsp;— <?php echo count($allVideos); ?> vidéo(s)
    </div>
    <?php foreach ($allVideos as $idx => $v):
      $f    = basename($v);
      $fn   = pathinfo($f, PATHINFO_FILENAME);
      $vvf  = $viewsDir . $fn . ".txt";
      $vc   = file_exists($vvf) ? (int)file_get_contents($vvf) : 0;
      $lbl  = ucfirst(str_replace(['_','-'], ' ', $fn));
      $isCurrent = ($f === $file);
    ?>
    <a href="watch.php?theme=<?php echo urlencode($theme); ?>&video=<?php echo urlencode($f); ?>"
       class="sidebar-item <?php echo $isCurrent ? 'active-vid' : ''; ?>">
      <div class="sitem-thumb">
        <video preload="metadata" muted>
          <source src="<?php echo htmlspecialchars($v); ?>#t=0.1" type="video/mp4">
        </video>
        <?php if (!$isCurrent): ?>
        <div class="sitem-play-icon">
          <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <?php endif; ?>
      </div>
      <div class="sitem-info">
        <span class="sitem-num"><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></span>
        <div class="sitem-name"><?php echo htmlspecialchars($lbl); ?></div>
        <div class="sitem-views">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <?php echo $vc; ?> vue(s)
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </aside>

</div>

<footer>
  &copy; <?php echo date('Y'); ?> DIDSI – DRH Service Formation &nbsp;|&nbsp;
  <a href="index.php">Accueil</a> &nbsp;|&nbsp;
  <a href="admin/login.php">Administration</a>
</footer>

</body>
</html>
