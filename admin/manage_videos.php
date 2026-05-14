<?php
require "auth.php";
$pageTitle = "Gérer les vidéos – Admin";

$videoDir = "../videos/";
$viewsDir = "../views/";
$msg      = "";
$msgType  = "info";

/* Suppression */
if (isset($_GET["delete"])) {
    $theme = basename($_GET["theme"] ?? "");
    $file  = basename($_GET["delete"]);
    $path  = $videoDir . $theme . "/" . $file;
    if ($theme && file_exists($path)) {
        unlink($path);
        $vf = $viewsDir . pathinfo($file, PATHINFO_FILENAME) . ".txt";
        if (file_exists($vf)) unlink($vf);
        $msg     = "Vidéo <strong>" . htmlspecialchars($file) . "</strong> supprimée.";
        $msgType = "success";
    } else {
        $msg     = "Vidéo introuvable.";
        $msgType = "danger";
    }
}

/* Renommage */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["rename"])) {
    $theme   = basename($_POST["theme"] ?? "");
    $oldFile = basename($_POST["old_file"]);
    $newName = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', trim($_POST["new_name"])) . ".mp4";
    $oldPath = $videoDir . $theme . "/" . $oldFile;
    $newPath = $videoDir . $theme . "/" . $newName;

    if ($theme && file_exists($oldPath) && !file_exists($newPath)) {
        rename($oldPath, $newPath);
        // Rename views file too
        $oldVf = $viewsDir . pathinfo($oldFile, PATHINFO_FILENAME) . ".txt";
        $newVf = $viewsDir . pathinfo($newName, PATHINFO_FILENAME) . ".txt";
        if (file_exists($oldVf)) rename($oldVf, $newVf);
        $msg     = "Vidéo renommée en <strong>" . htmlspecialchars($newName) . "</strong>.";
        $msgType = "success";
    } else {
        $msg     = "Renommage impossible (fichier introuvable ou nom déjà pris).";
        $msgType = "danger";
    }
}

/* Collecte des vidéos par thème */
$themes    = array_filter(glob($videoDir . "*"), 'is_dir');
$filterTheme = $_GET["filter"] ?? "";
?>
<?php include "_nav.php"; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h4 class="fw-bold mb-0">🎬 Gérer les vidéos</h4>
  <a href="upload.php" class="btn btn-primary btn-sm">+ Ajouter une vidéo</a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
    <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filtre par thème -->
<div class="card shadow-sm p-3 mb-4">
  <form method="get" class="row g-2 align-items-center">
    <div class="col-auto">
      <label class="col-form-label fw-semibold">Filtrer par thème :</label>
    </div>
    <div class="col-auto">
      <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Tous les thèmes —</option>
        <?php foreach ($themes as $t): ?>
          <option value="<?php echo htmlspecialchars(basename($t)); ?>"
            <?php echo $filterTheme === basename($t) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars(basename($t)); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($filterTheme): ?>
      <div class="col-auto">
        <a href="manage_videos.php" class="btn btn-sm btn-outline-secondary">Effacer filtre</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php
$displayed = 0;
foreach ($themes as $themeDir):
    $themeName = basename($themeDir);
    if ($filterTheme && $filterTheme !== $themeName) continue;
    $videos = glob($themeDir . "/*.mp4") ?: [];
    if (empty($videos)) continue;
    $displayed++;
?>
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex align-items-center justify-content-between"
       style="background:var(--card);border-bottom:2px solid #0d6efd22;">
    <span class="fw-bold">📁 <?php echo htmlspecialchars($themeName); ?></span>
    <span class="badge bg-primary rounded-pill"><?php echo count($videos); ?> vidéo(s)</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:160px">Aperçu</th>
            <th>Nom du fichier</th>
            <th style="width:80px">Vues</th>
            <th style="width:200px">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($videos as $video):
            $file  = basename($video);
            $name  = pathinfo($file, PATHINFO_FILENAME);
            $vf    = $viewsDir . $name . ".txt";
            $views = file_exists($vf) ? (int)file_get_contents($vf) : 0;
        ?>
          <tr>
            <td>
              <video width="150" height="85" muted preload="metadata" style="border-radius:6px;background:#000">
                <source src="<?php echo htmlspecialchars($video); ?>#t=0.1" type="video/mp4">
              </video>
            </td>
            <td>
              <span class="fw-semibold"><?php echo htmlspecialchars($name); ?></span>
              <br><small class="text-muted"><?php echo htmlspecialchars($file); ?></small>
            </td>
            <td><span class="badge bg-light text-dark border">👁️ <?php echo $views; ?></span></td>
            <td>
              <!-- Renommer -->
              <button class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal"
                      data-bs-target="#renameModal"
                      data-theme="<?php echo htmlspecialchars($themeName); ?>"
                      data-file="<?php echo htmlspecialchars($file); ?>"
                      data-name="<?php echo htmlspecialchars($name); ?>">
                ✏️ Renommer
              </button>
              <!-- Supprimer -->
              <a href="?delete=<?php echo urlencode($file); ?>&theme=<?php echo urlencode($themeName); ?>"
                 class="btn btn-sm btn-danger mb-1"
                 onclick="return confirm('Supprimer cette vidéo définitivement ?');">
                🗑 Supprimer
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($displayed === 0): ?>
  <div class="alert alert-warning">Aucune vidéo trouvée.</div>
<?php endif; ?>

<!-- Modal renommage -->
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="rename" value="1">
        <input type="hidden" name="theme" id="rTheme">
        <input type="hidden" name="old_file" id="rOldFile">
        <div class="modal-header">
          <h5 class="modal-title">✏️ Renommer la vidéo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nouveau nom (sans extension)</label>
          <input type="text" name="new_name" id="rNewName" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Renommer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('renameModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('rTheme').value   = btn.dataset.theme;
    document.getElementById('rOldFile').value = btn.dataset.file;
    document.getElementById('rNewName').value = btn.dataset.name;
});
</script>

<?php include "_nav_end.php"; ?>
