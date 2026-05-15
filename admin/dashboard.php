<?php
require "auth.php";
require "db.php";
$pageTitle = "Tableau de bord";

$videoDir = "../videos/";
$viewsDir = "../views/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');

$themeLabels = []; $themeCounts = [];
$totalVideos = 0;  $totalViews  = 0; $lastVideos = [];

foreach ($themes as $t) {
    $name   = basename($t);
    $vids   = glob($t . "/*.mp4") ?: [];
    $themeLabels[] = $name;
    $themeCounts[] = count($vids);
    $totalVideos  += count($vids);
    foreach ($vids as $v) {
        $vf = $viewsDir . pathinfo($v, PATHINFO_FILENAME) . ".txt";
        $totalViews += file_exists($vf) ? (int)file_get_contents($vf) : 0;
        $lastVideos[] = ["theme"=>$name,"file"=>$v,"mtime"=>filemtime($v)];
    }
}
usort($lastVideos, fn($a,$b) => $b["mtime"]-$a["mtime"]);
$lastVideos  = array_slice($lastVideos, 0, 5);
$totalUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$isAdmin     = $_SESSION["user"]["role"] === "ADMIN";
$logins      = [];
if ($isAdmin) {
    $logins = $pdo->query("SELECT u.fullname,l.login_at FROM user_logins l JOIN users u ON u.id=l.user_id ORDER BY l.login_at DESC LIMIT 6")->fetchAll();
}
?>
<?php include "_nav.php"; ?>
<div class="page-title">Tableau de bord</div>

<!-- KPI -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px">
  <?php
  $kpis = [
    ["icon"=>"🎬","val"=>$totalVideos,"label"=>"Vidéos","color"=>"#6366f1","bg"=>"rgba(99,102,241,.1)"],
    ["icon"=>"📁","val"=>count($themes),"label"=>"Thèmes","color"=>"#f59e0b","bg"=>"rgba(245,158,11,.1)"],
    ["icon"=>"👁️","val"=>$totalViews,"label"=>"Vues totales","color"=>"#10b981","bg"=>"rgba(16,185,129,.1)"],
    ["icon"=>"👤","val"=>$totalUsers,"label"=>"Utilisateurs","color"=>"#0ea5e9","bg"=>"rgba(14,165,233,.1)"],
  ];
  foreach($kpis as $k): ?>
  <div class="card" style="padding:20px;display:flex;align-items:center;gap:14px">
    <div style="width:48px;height:48px;border-radius:12px;background:<?php echo $k['bg']; ?>;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0"><?php echo $k['icon']; ?></div>
    <div>
      <div style="font-size:1.7rem;font-weight:800;color:<?php echo $k['color']; ?>;line-height:1"><?php echo $k['val']; ?></div>
      <div style="font-size:.78rem;color:var(--muted);margin-top:3px"><?php echo $k['label']; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Actions rapides -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px">
  <?php
  $actions = [
    ["href"=>"upload.php","icon"=>"📤","label"=>"Upload vidéo","desc"=>"Ajouter un tutoriel","color"=>"#6366f1"],
    ["href"=>"manage_themes.php","icon"=>"📁","label"=>"Gérer les thèmes","desc"=>count($themes)." thème(s)","color"=>"#f59e0b"],
    ["href"=>"manage_videos.php","icon"=>"🎬","label"=>"Gérer les vidéos","desc"=>$totalVideos." vidéo(s)","color"=>"#ef4444"],
  ];
  if($isAdmin) $actions[] = ["href"=>"users.php","icon"=>"👤","label"=>"Utilisateurs","desc"=>$totalUsers." compte(s)","color"=>"#10b981"];
  foreach($actions as $a): ?>
  <a href="<?php echo $a['href']; ?>" style="text-decoration:none">
    <div class="card card-hover" style="padding:18px;cursor:pointer">
      <div style="font-size:1.6rem;margin-bottom:10px"><?php echo $a['icon']; ?></div>
      <div style="font-weight:700;font-size:.92rem;color:var(--text);margin-bottom:4px"><?php echo $a['label']; ?></div>
      <div style="font-size:.78rem;color:var(--muted)"><?php echo $a['desc']; ?></div>
      <div style="margin-top:12px;font-size:.78rem;font-weight:600;color:<?php echo $a['color']; ?>">Accéder →</div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Graphique + dernières vidéos -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card" style="padding:22px">
    <div style="font-weight:700;font-size:.95rem;margin-bottom:18px;color:var(--text)">Vidéos par thème</div>
    <?php if(empty($themeLabels)): ?>
      <p style="color:var(--muted);font-size:.85rem">Aucun thème créé.</p>
    <?php else: ?>
      <canvas id="chartThemes" height="180"></canvas>
    <?php endif; ?>
  </div>
  <div class="card" style="padding:22px">
    <div style="font-weight:700;font-size:.95rem;margin-bottom:18px;color:var(--text)">Dernières vidéos ajoutées</div>
    <?php if(empty($lastVideos)): ?>
      <p style="color:var(--muted);font-size:.85rem">Aucune vidéo.</p>
    <?php else: ?>
      <?php foreach($lastVideos as $v): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
        <div style="width:34px;height:34px;border-radius:8px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">🎬</div>
        <div style="flex:1;min-width:0">
          <div style="font-size:.82rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars(pathinfo($v['file'],PATHINFO_FILENAME)); ?></div>
          <div style="font-size:.74rem;color:var(--muted)"><?php echo htmlspecialchars($v['theme']); ?></div>
        </div>
        <div style="font-size:.7rem;color:var(--muted);white-space:nowrap"><?php echo date('d/m',filemtime($v['file'])); ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Dernières connexions -->
<?php if($isAdmin && !empty($logins)): ?>
<div class="card" style="padding:22px">
  <div style="font-weight:700;font-size:.95rem;margin-bottom:16px;color:var(--text)">Dernières connexions</div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
      <thead><tr style="border-bottom:2px solid var(--border)">
        <th style="padding:8px 12px;text-align:left;color:var(--muted);font-weight:600;font-size:.75rem;text-transform:uppercase">Utilisateur</th>
        <th style="padding:8px 12px;text-align:left;color:var(--muted);font-weight:600;font-size:.75rem;text-transform:uppercase">Date &amp; heure</th>
      </tr></thead>
      <tbody>
      <?php foreach($logins as $l): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px 12px;color:var(--text);font-weight:500"><?php echo htmlspecialchars($l["fullname"]); ?></td>
          <td style="padding:10px 12px;color:var(--muted)"><?php echo date("d/m/Y à H:i", strtotime($l["login_at"])); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if(!empty($themeLabels)): ?>
new Chart(document.getElementById('chartThemes'),{
  type:'bar',
  data:{labels:<?php echo json_encode($themeLabels); ?>,datasets:[{label:'Vidéos',
    data:<?php echo json_encode($themeCounts); ?>,
    backgroundColor:['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6'],
    borderRadius:6,borderSkipped:false}]},
  options:{responsive:true,plugins:{legend:{display:false}},
    scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(255,255,255,.05)'}},
            x:{grid:{display:false}}}}
});
<?php endif; ?>
</script>
<?php include "_nav_end.php"; ?>
