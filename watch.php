<?php
require_once __DIR__ . '/viewer_auth.php';
requireViewerAuth();

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

$filename = pathinfo($file, PATHINFO_FILENAME);
$vf       = $viewsDir . $filename . ".txt";
$views    = file_exists($vf) ? (int)file_get_contents($vf) : 0;
$views++;
file_put_contents($vf, $views);

$watchLogId = null;
if (!empty($_SESSION['viewer']['id'])) {
    try {
        $pdo->prepare("INSERT INTO video_watch_log (viewer_id, theme, video) VALUES (?, ?, ?)")
            ->execute([$_SESSION['viewer']['id'], $theme, $file]);
        $watchLogId = (int)$pdo->lastInsertId();
    } catch (Exception $e) {}
}

$displayName = ucfirst(str_replace(['_','-'], ' ', $filename));
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#faf8ff;color:#131b2e;font-family:"Plus Jakarta Sans",system-ui,sans-serif;display:flex;flex-direction:column;min-height:100vh;}
a{text-decoration:none;color:inherit;}
img{display:block;}

/* ── SIDEBAR ── */
.sidebar{position:fixed;left:0;top:0;height:100%;width:260px;background:#fff;
  border-right:1px solid #c4c5d7;z-index:50;display:flex;flex-direction:column;
  box-shadow:1px 0 8px rgba(0,0,0,.04);}
.sidebar-logo{height:64px;display:flex;align-items:center;padding:0 20px;gap:10px;
  border-bottom:1px solid #e2e7ff;}
.sidebar-logo img{background:#fff;border-radius:8px;border:1px solid #e2e7ff;}
.sidebar-logo-text{display:flex;flex-direction:column;line-height:1;}
.sidebar-logo-text strong{font-size:1rem;font-weight:800;color:#131b2e;}
.sidebar-logo-text span{font-size:.65rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#0037b0;margin-top:2px;}
.sidebar-section{padding:12px 16px 4px;}
.sidebar-section-label{font-size:.68rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#747686;padding:0 8px;}
.sidebar-nav{display:flex;flex-direction:column;gap:2px;padding:4px 16px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
  font-size:.88rem;font-weight:600;color:#434655;transition:background .15s,color .15s;}
.nav-link:hover{background:#eaedff;color:#0037b0;}
.nav-link.active{background:#eaedff;color:#0037b0;}
.nav-link svg{width:18px;height:18px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:2;}
.sidebar-footer{margin-top:auto;padding:16px;}
.user-chip{background:#f2f3ff;border:1px solid #c4c5d7;border-radius:10px;padding:12px 14px;
  display:flex;align-items:center;gap:10px;}
.user-avatar{width:34px;height:34px;border-radius:50%;background:#0037b0;color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;flex-shrink:0;}
.user-info{flex:1;min-width:0;}
.user-login{font-size:.82rem;font-weight:700;color:#131b2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.user-role{font-size:.72rem;color:#747686;}
.logout-btn{font-size:.72rem;color:#ba1a1a;font-weight:600;display:block;text-align:right;
  margin-top:6px;transition:color .15s;}
.logout-btn:hover{color:#7f1d1d;}

/* ── MAIN LAYOUT ── */
.main-wrap{margin-left:260px;display:flex;flex-direction:column;min-height:100vh;}

/* ── HEADER ── */
.top-header{position:sticky;top:0;z-index:40;height:56px;background:rgba(255,255,255,.9);
  backdrop-filter:blur(12px);border-bottom:1px solid #e2e7ff;
  display:flex;align-items:center;padding:0 24px;gap:8px;
  box-shadow:0 1px 8px rgba(0,0,0,.04);}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:.82rem;flex-wrap:wrap;}
.breadcrumb a{color:#0037b0;font-weight:600;transition:color .15s;}
.breadcrumb a:hover{color:#1d4ed8;}
.breadcrumb-sep{color:#c4c5d7;font-size:.9rem;}
.breadcrumb-current{color:#434655;font-weight:600;max-width:300px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── WATCH LAYOUT ── */
.watch-grid{display:grid;grid-template-columns:1fr 320px;flex:1;min-height:0;}
@media(max-width:900px){.watch-grid{grid-template-columns:1fr;}}

/* ── VIDEO PLAYER ── */
.player-col{display:flex;flex-direction:column;}
.player-bg{background:#000;width:100%;}
.player-bg video{width:100%;max-height:calc(100vh - 56px);display:block;outline:none;}

.video-info-bar{background:#fff;border-bottom:1px solid #e2e7ff;padding:16px 24px;}
.video-title{font-size:1.2rem;font-weight:800;color:#131b2e;margin-bottom:10px;line-height:1.3;}
.video-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.meta-pill{display:flex;align-items:center;gap:5px;font-size:.8rem;color:#434655;}
.meta-pill svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.back-btn{display:inline-flex;align-items:center;gap:5px;background:#eaedff;color:#0037b0;
  padding:6px 14px;border-radius:8px;font-size:.8rem;font-weight:700;transition:background .15s;}
.back-btn:hover{background:#e2e7ff;}
.back-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}

/* ── PLAYLIST ── */
.playlist-col{background:#fff;border-left:1px solid #e2e7ff;overflow-y:auto;
  height:calc(100vh - 56px);position:sticky;top:56px;}
.playlist-head{padding:14px 18px;font-weight:700;font-size:.85rem;background:#f2f3ff;
  border-bottom:1px solid #e2e7ff;position:sticky;top:0;z-index:2;color:#131b2e;}
.playlist-count{font-weight:400;color:#747686;font-size:.78rem;margin-left:4px;}
.playlist-item{display:flex;align-items:center;gap:12px;padding:12px 16px;
  border-bottom:1px solid #f2f3ff;transition:background .15s;}
.playlist-item:hover{background:#f2f3ff;}
.playlist-item.current{background:#eaedff;border-left:3px solid #0037b0;}
.playlist-item.current .pi-name{color:#0037b0;font-weight:700;}
.pi-thumb{width:88px;height:52px;background:#eaedff;border-radius:6px;overflow:hidden;
  flex-shrink:0;position:relative;}
.pi-thumb video{width:100%;height:100%;object-fit:cover;display:block;}
.pi-play-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  background:rgba(0,0,0,.2);}
.pi-play-icon svg{width:16px;height:16px;fill:#fff;}
.pi-info{flex:1;min-width:0;}
.pi-num{font-size:.65rem;background:#0037b0;color:#fff;padding:1px 6px;border-radius:10px;
  display:inline-block;margin-bottom:3px;font-weight:700;}
.pi-num.current-num{background:#006c4a;}
.pi-name{font-size:.82rem;font-weight:600;color:#131b2e;line-height:1.35;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.pi-views{font-size:.7rem;color:#747686;margin-top:3px;display:flex;align-items:center;gap:3px;}
.pi-views svg{width:10px;height:10px;stroke:currentColor;fill:none;stroke-width:2;}

@media(max-width:640px){
  .sidebar{display:none;}
  .main-wrap{margin-left:0;}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <img src="images/mucoacademie.png" width="36" height="36" alt="Logo">
    <div class="sidebar-logo-text">
      <strong>MucoAcadémie</strong>
      <span>Espace Formation</span>
    </div>
  </div>

  <div class="sidebar-section">
    <span class="sidebar-section-label">Navigation</span>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Tableau de bord
    </a>
    <a href="videos.php?theme=<?php echo urlencode($theme); ?>" class="nav-link active">
      <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      <?php echo htmlspecialchars($theme); ?>
    </a>
  </nav>

  <?php
  $i = 0;
  foreach ($allThemes as $td) {
    $tn = basename($td);
    if ($tn !== $theme) {
      echo '<nav class="sidebar-nav" style="padding-top:0;">';
      echo '<a href="videos.php?theme='.urlencode($tn).'" class="nav-link">';
      echo '<svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><path d="M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>';
      echo htmlspecialchars($tn);
      echo '</a></nav>';
    }
    $i++;
    if ($i >= 5) break;
  }
  ?>

  <div class="sidebar-footer">
    <?php if (!empty($_SESSION['viewer']['login'])): ?>
    <div class="user-chip">
      <div class="user-avatar"><?php echo strtoupper(mb_substr($_SESSION['viewer']['login'], 0, 1)); ?></div>
      <div class="user-info">
        <div class="user-login"><?php echo htmlspecialchars($_SESSION['viewer']['login']); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['viewer']['direction'] ?? 'Apprenant'); ?></div>
      </div>
    </div>
    <a href="viewer_logout.php" class="logout-btn">Déconnexion →</a>
    <?php endif; ?>
  </div>
</aside>

<!-- MAIN -->
<div class="main-wrap">

  <!-- TOP HEADER -->
  <header class="top-header">
    <nav class="breadcrumb">
      <a href="index.php">Accueil</a>
      <span class="breadcrumb-sep">›</span>
      <a href="videos.php?theme=<?php echo urlencode($theme); ?>"><?php echo htmlspecialchars($theme); ?></a>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current"><?php echo htmlspecialchars($displayName); ?></span>
    </nav>
  </header>

  <!-- WATCH GRID -->
  <div class="watch-grid">

    <!-- PLAYER COL -->
    <div class="player-col">
      <div class="player-bg">
        <video controls autoplay preload="auto">
          <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
          Votre navigateur ne supporte pas la lecture vidéo.
        </video>
      </div>
      <div class="video-info-bar">
        <div class="video-title"><?php echo htmlspecialchars($displayName); ?></div>
        <div class="video-meta">
          <div class="meta-pill">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php echo $views; ?> vue(s)
          </div>
          <div class="meta-pill">
            <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <?php echo htmlspecialchars($theme); ?>
          </div>
          <div class="meta-pill">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php echo count($allVideos); ?> vidéo(s) dans ce thème
          </div>
          <a href="videos.php?theme=<?php echo urlencode($theme); ?>" class="back-btn">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Retour au thème
          </a>
        </div>
      </div>
    </div>

    <!-- PLAYLIST COL -->
    <aside class="playlist-col">
      <div class="playlist-head">
        Dans ce thème <span class="playlist-count">(<?php echo count($allVideos); ?> vidéos)</span>
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
         class="playlist-item <?php echo $isCurrent ? 'current' : ''; ?>">
        <div class="pi-thumb">
          <video preload="metadata" muted>
            <source src="<?php echo htmlspecialchars($v); ?>#t=0.1" type="video/mp4">
          </video>
          <?php if (!$isCurrent): ?>
          <div class="pi-play-icon">
            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </div>
          <?php endif; ?>
        </div>
        <div class="pi-info">
          <span class="pi-num <?php echo $isCurrent ? 'current-num' : ''; ?>"><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></span>
          <div class="pi-name"><?php echo htmlspecialchars($lbl); ?></div>
          <div class="pi-views">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php echo $vc; ?> vue(s)
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </aside>

  </div><!-- /watch-grid -->
</div><!-- /main-wrap -->

<script>
(function(){
  var video  = document.querySelector('.player-bg video');
  var logId  = <?php echo $watchLogId ? (int)$watchLogId : 'null'; ?>;
  if (!video || !logId) return;

  function send(sec) {
    navigator.sendBeacon('track_video.php',
      new URLSearchParams({log_id: logId, seconds: Math.round(sec)}));
  }

  setInterval(function(){ if (!video.paused) send(video.currentTime); }, 30000);
  window.addEventListener('beforeunload', function(){ send(video.currentTime); });
})();
</script>
</body>
</html>
