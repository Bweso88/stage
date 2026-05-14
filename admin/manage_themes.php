<?php
require "auth.php";
$pageTitle = "Gérer les thèmes – Admin";

$videoDir = "../videos/";
$msg      = "";
$msgType  = "info";

/* Créer un thème */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["name"]));
    if (empty($name)) {
        $msg = "Nom de thème invalide.";
        $msgType = "danger";
    } elseif (is_dir($videoDir . $name)) {
        $msg = "Ce thème existe déjà.";
        $msgType = "warning";
    } else {
        mkdir($videoDir . $name, 0755, true);
        $msg = "Thème <strong>" . htmlspecialchars($name) . "</strong> créé.";
        $msgType = "success";
    }
}

/* Renommer un thème */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["rename"])) {
    $old = basename($_POST["old_name"]);
    $new = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["new_name"]));
    if (empty($old) || empty($new)) {
        $msg = "Noms invalides.";
        $msgType = "danger";
    } elseif (!is_dir($videoDir . $old)) {
        $msg = "Thème source introuvable.";
        $msgType = "danger";
    } elseif (is_dir($videoDir . $new)) {
        $msg = "Un thème avec ce nom existe déjà.";
        $msgType = "warning";
    } else {
        rename($videoDir . $old, $videoDir . $new);
        $msg = "Thème renommé en <strong>" . htmlspecialchars($new) . "</strong>.";
        $msgType = "success";
    }
}

/* Supprimer un thème */
if (isset($_GET["delete"])) {
    $name = basename($_GET["delete"]);
    $path = $videoDir . $name;
    if (!is_dir($path)) {
        $msg = "Thème introuvable.";
        $msgType = "danger";
    } else {
        $videos = glob($path . "/*.mp4") ?: [];
        $force  = !empty($_GET["force"]);
        if (!empty($videos) && !$force) {
            $msg = "Ce thème contient " . count($videos) . " vidéo(s). "
                 . "<a href='?delete=" . urlencode($name) . "&force=1' class='alert-link' "
                 . "onclick=\"return confirm('Supprimer le thème ET toutes ses vidéos ?')\">Forcer la suppression</a>.";
            $msgType = "warning";
        } else {
            // Supprimer récursivement
            foreach (glob($path . "/*") as $f) { unlink($f); }
            rmdir($path);
            $msg = "Thème <strong>" . htmlspecialchars($name) . "</strong> supprimé.";
            $msgType = "success";
        }
    }
}

$themes = array_filter(glob($videoDir . "*"), 'is_dir');
?>
<?php include "_nav.php"; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h4 class="fw-bold mb-0">📁 Gérer les thèmes</h4>
  <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
    + Nouveau thème
  </button>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
    <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (empty($themes)): ?>
  <div class="alert alert-warning">Aucun thème. Commencez par en créer un.</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($themes as $themeDir):
      $name      = basename($themeDir);
      $videos    = glob($themeDir . "/*.mp4") ?: [];
      $count     = count($videos);
      $totalViews = 0;
      foreach ($videos as $v) {
          $vf = "../views/" . pathinfo($v, PATHINFO_FILENAME) . ".txt";
          $totalViews += file_exists($vf) ? (int)file_get_contents($vf) : 0;
      }
  ?>
  <div class="col-md-4 col-sm-6">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div style="font-size:2.5rem">📁</div>
        <h5 class="fw-bold mt-2"><?php echo htmlspecialchars($name); ?></h5>
        <p class="text-muted small mb-1">🎬 <?php echo $count; ?> vidéo(s)</p>
        <p class="text-muted small">👁️ <?php echo $totalViews; ?> vue(s)</p>
        <div class="d-flex gap-2 justify-content-center mt-3">
          <a href="../videos.php?theme=<?php echo urlencode($name); ?>" target="_blank"
             class="btn btn-sm btn-outline-primary">Voir</a>
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                  data-bs-target="#renameModal" data-name="<?php echo htmlspecialchars($name); ?>">
            ✏️
          </button>
          <a href="?delete=<?php echo urlencode($name); ?>"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Supprimer ce thème ?');">🗑</a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal créer -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="create" value="1">
        <div class="modal-header">
          <h5 class="modal-title">📁 Nouveau thème</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nom du thème</label>
          <input type="text" name="name" class="form-control" placeholder="ex : Comptabilité" required>
          <div class="form-text">Lettres, chiffres, tirets et espaces uniquement.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-success">Créer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal renommer -->
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="rename" value="1">
        <input type="hidden" name="old_name" id="rOldName">
        <div class="modal-header">
          <h5 class="modal-title">✏️ Renommer le thème</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Nouveau nom</label>
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
    var name = e.relatedTarget.dataset.name;
    document.getElementById('rOldName').value = name;
    document.getElementById('rNewName').value = name;
});
</script>

<?php include "_nav_end.php"; ?>
