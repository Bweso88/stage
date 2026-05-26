<?php
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
<title>FormaPro – <?php echo htmlspecialchars($theme ?: 'Vidéos'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --navy:#1c1d1f;--indigo:#6366f1;--indigo-d:#4f46e5;
  --bg:#f5f7fa;--card:#ffffff;--muted:#6b7280;--border:#e5e7eb;--radius:10px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--navy);font-family:"Segoe UI",system-ui,sans-serif;}
a{text-decoration:none;color:inherit;}

.navbar{background:var(--navy);padding:0 5%;height:64px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;top:0;z-index:999;
  box-shadow:0 2px 12px rgba(0,0,0,.35);}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:1.2rem;}
.navbar-brand img{background:#fff;border-radius:8px;padding:3px;}
.navbar-brand span{color:var(--indigo);}
.navbar-links{display:flex;align-items:center;gap:20px;}
.navbar-links a{color:#d1d5db;font-size:.88rem;transition:color .2s;}
.navbar-links a:hover{color:#fff;}
.btn-nav{background:var(--indigo);color:#fff !important;padding:6px 18px;border-radius:6px;font-weight:600;font-size:.82rem;}

.course-banner{background:linear-gradient(135deg,#1c1d1f,#1e3a5f);padding:40px 5%;color:#fff;}
.breadcrumb-custom{display:flex;align-items:center;gap:8px;color:#9ca3af;font-size:.85rem;margin-bottom:16px;}
.breadcrumb-custom a{color:#a5b4fc;transition:color .2s;}
.breadcrumb-custom a:hover{color:#fff;}
.breadcrumb-sep{color:#4b5563;}
.course-banner h1{font-size:clamp(1.4rem,3vw,2.2rem);font-weight:800;margin-bottom:12px;}
.course-banner-meta{display:flex;gap:24px;flex-wrap:wrap;margin-top:16px;}
.course-banner-meta span{display:flex;align-items:center;gap:6px;color:#d1d5db;font-size:.88rem;}
.course-banner-meta strong{color:#fff;}

.page-layout{display:grid;grid-template-columns:1fr 260px;gap:30px;padding:36px 5%;max-width:1400px;margin:0 auto;}
@media(max-width:900px){.page-layout{grid-template-columns:1fr;}.sidebar-courses{display:none;}}

.videos-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.videos-header h2{font-size:1.2rem;font-weight:700;}
.videos-header span{color:var(--muted);font-size:.85rem;}
.video-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;}
.video-card{background:var(--card);border-radius:var(--radius);overflow:hidden;cursor:pointer;
  box-shadow:0 2px 8px rgba(0,0,0,.07);transition:transform .25s,box-shadow .25s;}
.video-card:hover{transform:translateY(-5px);box-shadow:0 12px 28px rgba(0,0,0,.13);}
.video-thumb{position:relative;background:#1c1d1f;overflow:hidden;}
.video-thumb video{width:100%;height:155px;object-fit:cover;display:block;}
.video-thumb-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  background:rgba(0,0,0,.25);opacity:0;transition:opacity .25s;}
.video-card:hover .video-thumb-overlay{opacity:1;}
.play-btn{width:48px;height:48px;background:var(--indigo);border-radius:50%;display:flex;
  align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(99,102,241,.5);}
.play-btn svg{width:20px;height:20px;fill:#fff;margin-left:3px;}
.video-body{padding:12px 14px 14px;}
.video-title{font-size:.9rem;font-weight:700;color:var(--navy);line-height:1.3;margin-bottom:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.video-views{color:var(--muted);font-size:.78rem;display:flex;align-items:center;gap:4px;}
.video-num{background:var(--indigo);color:#fff;font-size:.7rem;font-weight:700;
  padding:2px 8px;border-radius:12px;display:inline-block;margin-bottom:6px;}

.sidebar-courses{position:sticky;top:80px;}
.sidebar-box{background:var(--card);border-radius:var(--radius);padding:18px;
  box-shadow:0 2px 8px rgba(0,0,0,.07);}
.sidebar-box h3{font-size:.95rem;font-weight:700;margin-bottom:14px;padding-bottom:10px;
  border-bottom:1px solid var(--border);}
.sidebar-theme{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:7px;
  transition:background .15s;color:var(--navy);font-size:.87rem;}
.sidebar-theme:hover{background:#f3f4f6;}
.sidebar-theme.active{background:#eef2ff;color:var(--indigo);font-weight:600;}
.sidebar-theme-icon{font-size:1.1rem;flex-shrink:0;}
.sidebar-theme-name{flex:1;}
.sidebar-theme-count{background:#e5e7eb;color:#374151;font-size:.7rem;font-weight:600;
  padding:2px 7px;border-radius:10px;}
.sidebar-theme.active .sidebar-theme-count{background:#c7d2fe;color:var(--indigo-d);}

.empty-state{text-align:center;padding:60px 20px;}
.empty-state .big-icon{font-size:4rem;margin-bottom:16px;}

footer{background:#111;padding:28px 5%;text-align:center;color:#4b5563;font-size:.82rem;margin-top:40px;}
footer a{color:#6b7280;}footer a:hover{color:#fff;}
</style>
</head>
<body>

<header class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="images/MUCODEC.gif" width="36" height="36" alt="Logo">
    Forma<span>Pro</span>
  </a>
  <nav class="navbar-links">
    <a href="index.php">Accueil</a>
    <a href="index.php#formations">Formations</a>
    <a href="admin/login.php" class="btn-nav">Admin</a>
  </nav>
</header>

<?php if (!empty($theme) && is_dir($themePath)): ?>
<div class="course-banner" style="<?php echo $coverImg ? 'background:linear-gradient(135deg,rgba(28,29,31,.85),rgba(30,58,95,.85)),url('.htmlspecialchars($coverImg).') center/cover no-repeat' : ''; ?>">
  <div class="breadcrumb-custom">
    <a href="index.php">Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <a href="index.php#formations">Formations</a>
    <span class="breadcrumb-sep">›</span>
    <span><?php echo htmlspecialchars($theme); ?></span>
  </div>
  <h1>🎓 <?php echo htmlspecialchars($theme); ?></h1>
  <div class="course-banner-meta">
    <span>🎬 <strong><?php echo $videoCount; ?></strong> tutoriel(s)</span>
    <span>👁️ <strong><?php echo $totalViews; ?></strong> vue(s) totales</span>
    <span>🆓 <strong>Accès libre</strong></span>
  </div>
</div>
<?php else: ?>
<div class="course-banner">
  <h1>Thème introuvable</h1>
</div>
<?php endif; ?>

<div class="page-layout">
  <main>
    <?php if (empty($theme) || !is_dir($themePath)): ?>
      <div class="empty-state">
        <div class="big-icon">🔍</div>
        <h3>Thème introuvable</h3>
        <p><a href="index.php" style="color:var(--indigo)">← Retour aux formations</a></p>
      </div>
    <?php elseif (empty($videos)): ?>
      <div class="empty-state">
        <div class="big-icon">📭</div>
        <h3>Aucune vidéo dans ce thème</h3>
        <p style="color:var(--muted)">Des contenus seront ajoutés prochainement.</p>
      </div>
    <?php else: ?>
      <div class="videos-header">
        <h2>Tutoriels disponibles</h2>
        <span><?php echo $videoCount; ?> vidéo(s)</span>
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
              <div class="video-thumb-overlay">
                <div class="play-btn">
                  <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
              </div>
            </div>
            <div class="video-body">
              <span class="video-num"><?php echo str_pad($idx+1,2,'0',STR_PAD_LEFT); ?></span>
              <div class="video-title"><?php echo htmlspecialchars($displayName); ?></div>
              <div class="video-views">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php echo $views; ?> vue(s)
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <aside class="sidebar-courses">
    <div class="sidebar-box">
      <h3>📚 Autres formations</h3>
      <?php foreach ($allThemes as $i => $t):
        $tn = basename($t);
        $tc = count(glob($t . "/*.mp4") ?: []);
      ?>
        <a href="videos.php?theme=<?php echo urlencode($tn); ?>"
           class="sidebar-theme <?php echo $tn === $theme ? 'active' : ''; ?>">
          <span class="sidebar-theme-icon"><?php echo $icons[$i % count($icons)]; ?></span>
          <span class="sidebar-theme-name"><?php echo htmlspecialchars($tn); ?></span>
          <span class="sidebar-theme-count"><?php echo $tc; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<footer>
  &copy; <?php echo date('Y'); ?> DIDSI – DRH Service Formation &nbsp;|&nbsp;
  <a href="index.php">Accueil</a> &nbsp;|&nbsp;
  <a href="admin/login.php">Administration</a>
</footer>

<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
