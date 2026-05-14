<?php
require "auth.php";
require "db.php";
$pageTitle = "Tableau de bord – Espace Formation";

$videoDir = "../videos/";
$viewsDir = "../views/";

// Stats par thème
$themes       = array_filter(glob($videoDir . "*"), 'is_dir');
$themeLabels  = [];
$themeCounts  = [];
$totalVideos  = 0;
$totalViews   = 0;
$lastVideos   = [];

foreach ($themes as $themeDir) {
    $name   = basename($themeDir);
    $videos = glob($themeDir . "/*.mp4") ?: [];
    $count  = count($videos);
    $themeLabels[] = $name;
    $themeCounts[] = $count;
    $totalVideos  += $count;
    foreach ($videos as $v) {
        $vf = $viewsDir . pathinfo($v, PATHINFO_FILENAME) . ".txt";
        $totalViews += file_exists($vf) ? (int)file_get_contents($vf) : 0;
        $lastVideos[] = ["theme" => $name, "file" => $v, "mtime" => filemtime($v)];
    }
}

usort($lastVideos, fn($a, $b) => $b["mtime"] - $a["mtime"]);
$lastVideos = array_slice($lastVideos, 0, 6);

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$isAdmin    = $_SESSION["user"]["role"] === "ADMIN";

$logins = [];
if ($isAdmin) {
    $stmt = $pdo->query("
        SELECT u.fullname, l.login_at
        FROM user_logins l JOIN users u ON u.id = l.user_id
        ORDER BY l.login_at DESC LIMIT 8
    ");
    $logins = $stmt->fetchAll();
}
?>
<?php include "_nav.php"; ?>

<h4 class="mb-4 fw-bold">📊 Tableau de bord</h4>

<!-- KPI cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card card-hover shadow-sm p-3 text-center">
      <div style="font-size:2.4rem">🎬</div>
      <div class="fs-2 fw-bold text-primary mt-1"><?php echo $totalVideos; ?></div>
      <div class="text-muted small">Vidéos</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-hover shadow-sm p-3 text-center">
      <div style="font-size:2.4rem">📁</div>
      <div class="fs-2 fw-bold text-warning mt-1"><?php echo count($themes); ?></div>
      <div class="text-muted small">Thèmes</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-hover shadow-sm p-3 text-center">
      <div style="font-size:2.4rem">👁️</div>
      <div class="fs-2 fw-bold text-success mt-1"><?php echo $totalViews; ?></div>
      <div class="text-muted small">Vues totales</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-hover shadow-sm p-3 text-center">
      <div style="font-size:2.4rem">👤</div>
      <div class="fs-2 fw-bold text-info mt-1"><?php echo $totalUsers; ?></div>
      <div class="text-muted small">Utilisateurs</div>
    </div>
  </div>
</div>

<!-- Quick actions -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-hover shadow-sm text-center p-3">
      <div style="font-size:2rem">📤</div>
      <h6 class="mt-2 fw-bold">Upload vidéo</h6>
      <a href="upload.php" class="btn btn-primary btn-sm mt-1 w-100">Accéder</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-hover shadow-sm text-center p-3">
      <div style="font-size:2rem">📁</div>
      <h6 class="mt-2 fw-bold">Gérer les thèmes</h6>
      <a href="manage_themes.php" class="btn btn-warning btn-sm mt-1 w-100">Accéder</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-hover shadow-sm text-center p-3">
      <div style="font-size:2rem">🎬</div>
      <h6 class="mt-2 fw-bold">Gérer les vidéos</h6>
      <a href="manage_videos.php" class="btn btn-danger btn-sm mt-1 w-100">Accéder</a>
    </div>
  </div>
</div>

<!-- Charts & last videos -->
<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card shadow-sm p-3">
      <h6 class="fw-bold mb-3">Vidéos par thème</h6>
      <canvas id="themesChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm p-3 h-100">
      <h6 class="fw-bold mb-3">🆕 Dernières vidéos ajoutées</h6>
      <?php if (empty($lastVideos)): ?>
        <p class="text-muted small">Aucune vidéo.</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($lastVideos as $v): ?>
          <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0">
            <span class="text-truncate" style="max-width:200px" title="<?php echo htmlspecialchars(pathinfo($v['file'], PATHINFO_FILENAME)); ?>">
              🎬 <?php echo htmlspecialchars(pathinfo($v['file'], PATHINFO_FILENAME)); ?>
            </span>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">
              <?php echo htmlspecialchars($v['theme']); ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Dernières connexions (ADMIN) -->
<?php if ($isAdmin && !empty($logins)): ?>
<div class="card shadow-sm p-3 mb-4">
  <h6 class="fw-bold mb-3">🔐 Dernières connexions</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Utilisateur</th><th>Date & heure</th></tr></thead>
      <tbody>
        <?php foreach ($logins as $l): ?>
        <tr>
          <td><?php echo htmlspecialchars($l["fullname"]); ?></td>
          <td><?php echo date("d/m/Y H:i", strtotime($l["login_at"])); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('themesChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($themeLabels); ?>,
        datasets: [{
            label: 'Nombre de vidéos',
            data: <?php echo json_encode($themeCounts); ?>,
            backgroundColor: ['#0d6efd','#dc3545','#ffc107','#198754','#6f42c1','#0dcaf0'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
</script>

<?php include "_nav_end.php"; ?>
