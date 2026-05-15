<?php
require "auth.php";
$pageTitle = "Gérer les vidéos";

$videoDir = "../videos/";
$viewsDir = "../views/";
$msg = ""; $msgType = "ok";

if (isset($_GET["delete"])) {
    $theme = basename($_GET["theme"] ?? "");
    $file  = basename($_GET["delete"]);
    $path  = $videoDir . $theme . "/" . $file;
    if ($theme && file_exists($path)) {
        unlink($path);
        $vf = $viewsDir . pathinfo($file, PATHINFO_FILENAME) . ".txt";
        if (file_exists($vf)) unlink($vf);
        $msg = "Vidéo <strong>" . htmlspecialchars($file) . "</strong> supprimée.";
    } else { $msg = "Vidéo introuvable."; $msgType = "err"; }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["rename"])) {
    $theme   = basename($_POST["theme"] ?? "");
    $oldFile = basename($_POST["old_file"]);
    $newName = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', trim($_POST["new_name"])) . ".mp4";
    $oldPath = $videoDir . $theme . "/" . $oldFile;
    $newPath = $videoDir . $theme . "/" . $newName;
    if ($theme && file_exists($oldPath) && !file_exists($newPath)) {
        rename($oldPath, $newPath);
        $oldVf = $viewsDir . pathinfo($oldFile, PATHINFO_FILENAME) . ".txt";
        $newVf = $viewsDir . pathinfo($newName, PATHINFO_FILENAME) . ".txt";
        if (file_exists($oldVf)) rename($oldVf, $newVf);
        $msg = "Vidéo renommée en <strong>" . htmlspecialchars($newName) . "</strong>.";
    } else { $msg = "Renommage impossible."; $msgType = "err"; }
}

$themes      = array_filter(glob($videoDir . "*"), 'is_dir');
$filterTheme = $_GET["filter"] ?? "";
?>
<?php include "_nav.php"; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
  <div class="adm-title" style="margin-bottom:0">Gérer les vidéos</div>
  <a href="upload.php"><button class="btn btn-primary">+ Ajouter une vidéo</button></a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
<?php endif; ?>

<!-- Filtre -->
<div class="card" style="padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:.82rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">Filtrer :</span>
  <a href="manage_videos.php">
    <button class="btn btn-sm <?php echo !$filterTheme?'btn-primary':'btn-ghost'; ?>">Tous les thèmes</button>
  </a>
  <?php foreach ($themes as $t): $tn=basename($t); ?>
  <a href="?filter=<?php echo urlencode($tn); ?>">
    <button class="btn btn-sm <?php echo $filterTheme===$tn?'btn-primary':'btn-ghost'; ?>">
      <?php echo htmlspecialchars($tn); ?>
    </button>
  </a>
  <?php endforeach; ?>
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
<div class="card" style="margin-bottom:20px;overflow:hidden">
  <!-- En-tête thème -->
  <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface2)">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:8px;height:8px;border-radius:50%;background:var(--indigo)"></div>
      <span style="font-weight:700;font-size:.95rem">📁 <?php echo htmlspecialchars($themeName); ?></span>
    </div>
    <span class="badge badge-indigo"><?php echo count($videos); ?> vidéo(s)</span>
  </div>

  <!-- Tableau -->
  <div style="overflow-x:auto">
    <table class="adm-table">
      <thead>
        <tr>
          <th style="width:160px">Aperçu</th>
          <th>Nom de la vidéo</th>
          <th style="width:90px">Vues</th>
          <th style="width:180px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($videos as $video):
        $file  = basename($video);
        $name  = pathinfo($file, PATHINFO_FILENAME);
        $vf    = $viewsDir . $name . ".txt";
        $views = file_exists($vf) ? (int)file_get_contents($vf) : 0;
        $label = ucfirst(str_replace(['_','-'], ' ', $name));
      ?>
        <tr>
          <td>
            <div style="background:#000;border-radius:7px;overflow:hidden;width:140px;height:80px">
              <video width="140" height="80" muted preload="metadata" style="display:block;object-fit:cover">
                <source src="<?php echo htmlspecialchars($video); ?>#t=0.1" type="video/mp4">
              </video>
            </div>
          </td>
          <td>
            <div style="font-weight:600;color:var(--text);margin-bottom:3px"><?php echo htmlspecialchars($label); ?></div>
            <div style="font-size:.75rem;color:var(--muted)"><?php echo htmlspecialchars($file); ?></div>
          </td>
          <td>
            <span class="badge badge-gray">👁 <?php echo $views; ?></span>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn btn-sm btn-ghost"
                onclick="openRename('<?php echo htmlspecialchars($themeName,ENT_QUOTES); ?>','<?php echo htmlspecialchars($file,ENT_QUOTES); ?>','<?php echo htmlspecialchars($name,ENT_QUOTES); ?>')">
                ✏️ Renommer
              </button>
              <a href="?delete=<?php echo urlencode($file); ?>&theme=<?php echo urlencode($themeName); ?>"
                 onclick="return confirm('Supprimer cette vidéo ?')">
                <button class="btn btn-sm btn-danger">🗑</button>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php if ($displayed === 0): ?>
<div class="card" style="padding:60px 20px;text-align:center">
  <div style="font-size:3rem;margin-bottom:14px">📭</div>
  <div style="font-weight:700;margin-bottom:6px">Aucune vidéo</div>
  <div style="color:var(--muted);font-size:.85rem;margin-bottom:16px">Commencez par uploader vos tutoriels.</div>
  <a href="upload.php"><button class="btn btn-primary">Uploader une vidéo</button></a>
</div>
<?php endif; ?>

<!-- Modal renommer -->
<div id="modalRename" class="modal-back">
  <div class="modal-box">
    <div class="modal-title">✏️ Renommer la vidéo</div>
    <form method="post">
      <input type="hidden" name="rename" value="1">
      <input type="hidden" name="theme" id="rTheme">
      <input type="hidden" name="old_file" id="rOldFile">
      <div class="field">
        <label>Nouveau nom (sans .mp4)</label>
        <input type="text" name="new_name" id="rNewName" required>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="button" class="btn btn-ghost" style="flex:1" onclick="document.getElementById('modalRename').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-primary" style="flex:1">Renommer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRename(theme, file, name) {
  document.getElementById('rTheme').value   = theme;
  document.getElementById('rOldFile').value = file;
  document.getElementById('rNewName').value = name;
  document.getElementById('modalRename').classList.add('open');
}
</script>

<?php include "_nav_end.php"; ?>
