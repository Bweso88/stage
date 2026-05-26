<?php
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

/* Icônes par ordre cyclique */
$icons  = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚","🎓","💼","🌐","📱"];
$colors = ["#6366f1","#0ea5e9","#10b981","#f59e0b","#ef4444","#8b5cf6","#06b6d4","#84cc16","#f97316","#ec4899","#14b8a6","#a855f7"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>FormaPro – Votre espace de formation vidéo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="css/bootstrap.min.css" rel="stylesheet">
<style>
/* ===== VARIABLES ===== */
:root{
  --navy:#1c1d1f;
  --navy2:#2d2f31;
  --indigo:#6366f1;
  --indigo-d:#4f46e5;
  --sky:#0ea5e9;
  --bg:#f5f7fa;
  --card:#ffffff;
  --muted:#6b7280;
  --border:#e5e7eb;
  --radius:10px;
}
/* ===== RESET / BASE ===== */
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--navy);font-family:"Segoe UI",system-ui,sans-serif;line-height:1.6;}
a{text-decoration:none;color:inherit;}
img{display:block;}

/* ===== NAVBAR ===== */
.navbar{background:var(--navy);padding:0 5%;height:64px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;top:0;z-index:999;
  box-shadow:0 2px 12px rgba(0,0,0,.35);}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:1.25rem;}
.navbar-brand img{background:#fff;border-radius:8px;padding:3px;}
.navbar-brand span{color:var(--indigo);}
.navbar-links{display:flex;align-items:center;gap:24px;}
.navbar-links a{color:#d1d5db;font-size:.9rem;transition:color .2s;}
.navbar-links a:hover{color:#fff;}
.btn-nav{background:var(--indigo);color:#fff !important;padding:7px 20px;border-radius:6px;
  font-weight:600;font-size:.85rem;transition:background .2s;}
.btn-nav:hover{background:var(--indigo-d);}

/* ===== HERO ===== */
.hero{background:linear-gradient(135deg,#1c1d1f 0%,#1e3a5f 50%,#1c1d1f 100%);
  padding:80px 5% 90px;position:relative;overflow:hidden;}
.hero::before{content:"";position:absolute;top:-60px;right:-60px;width:420px;height:420px;
  border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,.25) 0%,transparent 70%);}
.hero::after{content:"";position:absolute;bottom:-80px;left:10%;width:300px;height:300px;
  border-radius:50%;background:radial-gradient(circle,rgba(14,165,233,.2) 0%,transparent 70%);}
.hero-inner{max-width:680px;position:relative;z-index:1;}
.hero-badge{display:inline-block;background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.4);
  color:#a5b4fc;padding:4px 14px;border-radius:20px;font-size:.8rem;font-weight:600;
  letter-spacing:.05em;text-transform:uppercase;margin-bottom:20px;}
.hero h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:800;color:#fff;line-height:1.2;margin-bottom:16px;}
.hero h1 span{color:var(--indigo);}
.hero p{color:#9ca3af;font-size:1.05rem;max-width:520px;margin-bottom:32px;}
.hero-stats{display:flex;gap:32px;flex-wrap:wrap;}
.hero-stat{display:flex;flex-direction:column;}
.hero-stat strong{color:#fff;font-size:1.6rem;font-weight:800;}
.hero-stat small{color:#9ca3af;font-size:.82rem;}

/* ===== SECTION ===== */
.section{padding:60px 5%;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;}
.section-title{font-size:1.5rem;font-weight:800;color:var(--navy);}
.section-title span{color:var(--indigo);}
.section-link{color:var(--indigo);font-size:.88rem;font-weight:600;display:flex;align-items:center;gap:4px;}
.section-link:hover{color:var(--indigo-d);}

/* ===== COURSE CARDS ===== */
.courses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:22px;}
.course-card{background:var(--card);border-radius:var(--radius);overflow:hidden;
  box-shadow:0 2px 10px rgba(0,0,0,.07);transition:transform .25s,box-shadow .25s;
  display:flex;flex-direction:column;}
.course-card:hover{transform:translateY(-6px);box-shadow:0 12px 32px rgba(0,0,0,.13);}
.course-thumb{height:160px;display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;}
.course-thumb-icon{font-size:3.5rem;filter:drop-shadow(0 4px 8px rgba(0,0,0,.3));}
.course-thumb-count{position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,.65);
  color:#fff;font-size:.75rem;font-weight:600;padding:3px 9px;border-radius:20px;}
.course-body{padding:16px;flex:1;display:flex;flex-direction:column;gap:8px;}
.course-tag{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:3px 10px;
  border-radius:20px;display:inline-block;width:fit-content;}
.course-name{font-size:1rem;font-weight:700;color:var(--navy);line-height:1.3;}
.course-meta{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:10px;
  border-top:1px solid var(--border);}
.course-meta span{color:var(--muted);font-size:.8rem;display:flex;align-items:center;gap:4px;}
.btn-play{background:var(--indigo);color:#fff;padding:6px 16px;border-radius:6px;
  font-size:.82rem;font-weight:600;transition:background .2s;}
.btn-play:hover{background:var(--indigo-d);color:#fff;}

/* ===== EMPTY ===== */
.empty-state{text-align:center;padding:60px 20px;}
.empty-state .big-icon{font-size:4rem;margin-bottom:16px;}
.empty-state h3{font-size:1.3rem;font-weight:700;color:var(--navy);margin-bottom:8px;}
.empty-state p{color:var(--muted);}

/* ===== WHY SECTION ===== */
.why-section{background:var(--navy);padding:70px 5%;}
.why-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:30px;margin-top:40px;}
.why-card{text-align:center;padding:30px 20px;}
.why-icon{font-size:2.5rem;margin-bottom:14px;}
.why-card h4{color:#fff;font-weight:700;font-size:1rem;margin-bottom:8px;}
.why-card p{color:#9ca3af;font-size:.85rem;line-height:1.5;}
.why-title{color:#fff;font-size:1.6rem;font-weight:800;text-align:center;}
.why-title span{color:var(--indigo);}

/* ===== FOOTER ===== */
footer{background:#111;padding:40px 5% 24px;}
.footer-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;
  padding-bottom:24px;border-bottom:1px solid #2d2f31;}
.footer-brand{color:#fff;font-weight:800;font-size:1.1rem;}
.footer-brand span{color:var(--indigo);}
.footer-links{display:flex;gap:20px;flex-wrap:wrap;}
.footer-links a{color:#6b7280;font-size:.85rem;transition:color .2s;}
.footer-links a:hover{color:#fff;}
.footer-copy{color:#4b5563;font-size:.8rem;text-align:center;padding-top:20px;}

@media(max-width:640px){
  .navbar-links{display:none;}
  .hero{padding:50px 5% 60px;}
  .hero-stats{gap:20px;}
  .section{padding:40px 5%;}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="images/MUCODEC.gif" width="38" height="38" alt="Logo">
    Forma<span>Pro</span>
  </a>
  <nav class="navbar-links">
    <a href="index.php">Accueil</a>
    <a href="#formations">Formations</a>
    <a href="admin/login.php" class="btn-nav">Admin</a>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge">🎓 Espace de formation</div>
    <h1>Apprenez à votre <span>rythme</span>,<br>où que vous soyez</h1>
    <p>Accédez à l'ensemble des tutoriels vidéo de la DRH – Service Formation. Des formations pratiques, organisées par thèmes.</p>
    <div class="hero-stats">
      <div class="hero-stat">
        <strong><?php echo $totalVideos; ?></strong>
        <small>Vidéos disponibles</small>
      </div>
      <div class="hero-stat">
        <strong><?php echo $totalThemes; ?></strong>
        <small>Thèmes de formation</small>
      </div>
      <div class="hero-stat">
        <strong>100%</strong>
        <small>Gratuit &amp; accessible</small>
      </div>
    </div>
  </div>
</section>

<!-- FORMATIONS -->
<section class="section" id="formations">
  <div class="section-header">
    <h2 class="section-title">Nos <span>formations</span></h2>
    <span class="section-link"><?php echo $totalThemes; ?> thème(s) disponible(s)</span>
  </div>

  <?php if (empty($themeData)): ?>
    <div class="empty-state">
      <div class="big-icon">📭</div>
      <h3>Aucune formation disponible</h3>
      <p>Les formations seront publiées prochainement.</p>
    </div>
  <?php else: ?>
    <div class="courses-grid">
      <?php foreach ($themeData as $i => $theme): ?>
        <?php
          $color = $colors[$i % count($colors)];
          $icon  = $icons[$i % count($icons)];
        ?>
        <a href="videos.php?theme=<?php echo urlencode($theme['name']); ?>" class="course-card">
          <div class="course-thumb" style="background:linear-gradient(135deg,<?php echo $color; ?>22,<?php echo $color; ?>44);">
            <?php if (!empty($theme['cover'])): ?>
              <img src="<?php echo htmlspecialchars($theme['cover']); ?>" alt="<?php echo htmlspecialchars($theme['name']); ?>"
                   style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
            <?php else: ?>
              <div class="course-thumb-icon"><?php echo $icon; ?></div>
            <?php endif; ?>
            <div class="course-thumb-count"><?php echo $theme['count']; ?> vidéo(s)</div>
          </div>
          <div class="course-body">
            <span class="course-tag" style="background:<?php echo $color; ?>18;color:<?php echo $color; ?>;">
              Formation
            </span>
            <div class="course-name"><?php echo htmlspecialchars($theme['name']); ?></div>
            <div class="course-meta">
              <span>🎬 <?php echo $theme['count']; ?> tutoriel(s)</span>
              <span class="btn-play">Accéder →</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- WHY -->
<section class="why-section">
  <h2 class="why-title">Pourquoi <span>FormaPro</span> ?</h2>
  <div class="why-grid">
    <div class="why-card">
      <div class="why-icon">🎬</div>
      <h4>Vidéos HD</h4>
      <p>Des tutoriels clairs et bien structurés, accessibles depuis n'importe quel navigateur.</p>
    </div>
    <div class="why-card">
      <div class="why-icon">📁</div>
      <h4>Organisé par thèmes</h4>
      <p>Trouvez rapidement la formation dont vous avez besoin grâce aux catégories.</p>
    </div>
    <div class="why-card">
      <div class="why-icon">📱</div>
      <h4>Responsive</h4>
      <p>Apprenez sur ordinateur, tablette ou mobile, à votre convenance.</p>
    </div>
    <div class="why-card">
      <div class="why-icon">🔒</div>
      <h4>Accès sécurisé</h4>
      <p>Espace d'administration protégé pour gérer les contenus en toute sécurité.</p>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div class="footer-brand">Forma<span>Pro</span> · MUCODEC</div>
    <nav class="footer-links">
      <a href="index.php">Accueil</a>
      <a href="#formations">Formations</a>
      <a href="admin/login.php">Administration</a>
    </nav>
  </div>
  <p class="footer-copy">&copy; <?php echo date('Y'); ?> DIDSI – DRH Service Formation. Tous droits réservés.</p>
</footer>

<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
