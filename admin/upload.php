<?php
require "auth.php";
$pageTitle = "Upload vidéo – Admin";

$videoDir = "../videos/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');
$message  = "";
$msgType  = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "upload";

    if ($action === "upload" && isset($_FILES["video"])) {
        // Déterminer le thème cible
        if (!empty($_POST["new_theme"])) {
            $themeName = trim($_POST["new_theme"]);
            $themeName = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $themeName);
        } else {
            $themeName = basename($_POST["theme"] ?? "");
        }

        if (empty($themeName)) {
            $message = "Veuillez sélectionner ou créer un thème.";
            $msgType = "danger";
        } else {
            $targetDir = $videoDir . $themeName . "/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $fileName = basename($_FILES["video"]["name"]);
            // Sécuriser le nom de fichier
            $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($ext !== "mp4") {
                $message = "Seuls les fichiers MP4 sont autorisés.";
                $msgType = "danger";
            } elseif ($_FILES["video"]["size"] > 500 * 1024 * 1024) {
                $message = "La vidéo ne doit pas dépasser 500 Mo.";
                $msgType = "danger";
            } elseif (move_uploaded_file($_FILES["video"]["tmp_name"], $targetDir . $fileName)) {
                $message = "Vidéo <strong>" . htmlspecialchars($fileName) . "</strong> ajoutée dans le thème <strong>" . htmlspecialchars($themeName) . "</strong>.";
                $msgType = "success";
                // Rafraîchir la liste des thèmes
                $themes = array_filter(glob($videoDir . "*"), 'is_dir');
            } else {
                $message = "Erreur lors de l'upload. Vérifiez les permissions du dossier.";
                $msgType = "danger";
            }
        }
    }
}
?>
<?php include "_nav.php"; ?>

<h4 class="mb-4 fw-bold">📤 Upload d'une vidéo</h4>

<?php if ($message): ?>
  <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm p-4">
      <form method="post" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="action" value="upload">

        <!-- Thème -->
        <div class="mb-3">
          <label class="form-label fw-semibold">📁 Thème (catégorie)</label>
          <?php if (!empty($themes)): ?>
            <select name="theme" class="form-select mb-2" id="themeSelect">
              <option value="">— Choisir un thème existant —</option>
              <?php foreach ($themes as $t): ?>
                <option value="<?php echo htmlspecialchars(basename($t)); ?>">
                  <?php echo htmlspecialchars(basename($t)); ?>
                  (<?php echo count(glob($t . "/*.mp4")); ?> vidéo(s))
                </option>
              <?php endforeach; ?>
            </select>
            <div class="text-muted small mb-2 text-center">— ou —</div>
          <?php endif; ?>
          <div class="input-group">
            <span class="input-group-text">🆕</span>
            <input type="text" name="new_theme" class="form-control" id="newTheme"
                   placeholder="Créer un nouveau thème…">
          </div>
          <div class="form-text">Si vous saisissez un nouveau nom, il sera créé automatiquement.</div>
        </div>

        <!-- Fichier vidéo -->
        <div class="mb-3">
          <label class="form-label fw-semibold">🎬 Fichier vidéo (MP4, max 500 Mo)</label>
          <input type="file" name="video" class="form-control" accept="video/mp4"
                 onchange="previewVideo(this)" required>
        </div>

        <!-- Prévisualisation -->
        <div class="mb-3" id="previewBox" style="display:none">
          <label class="form-label fw-semibold">Aperçu</label>
          <video id="preview" controls style="width:100%;max-height:260px;border-radius:8px;background:#000"></video>
        </div>

        <button class="btn btn-primary w-100" type="submit">
          📤 Envoyer la vidéo
        </button>
      </form>
    </div>
  </div>

  <!-- Aide / rappel -->
  <div class="col-lg-5">
    <div class="card shadow-sm p-4 h-100">
      <h6 class="fw-bold mb-3">💡 Conseils</h6>
      <ul class="list-unstyled small text-muted">
        <li class="mb-2">✅ Format accepté : <strong>MP4</strong> uniquement</li>
        <li class="mb-2">✅ Taille max : <strong>500 Mo</strong></li>
        <li class="mb-2">✅ Nommez vos fichiers clairement (ex : <code>01_Introduction.mp4</code>)</li>
        <li class="mb-2">✅ Chaque thème correspond à un dossier dans <code>videos/</code></li>
        <li class="mb-2">✅ Vous pouvez créer autant de thèmes que nécessaire</li>
      </ul>
      <hr>
      <h6 class="fw-bold mb-2">📁 Thèmes existants</h6>
      <?php if (empty($themes)): ?>
        <p class="text-muted small">Aucun thème. Créez-en un lors de l'upload.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush small">
          <?php foreach ($themes as $t): ?>
            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
              <span>📁 <?php echo htmlspecialchars(basename($t)); ?></span>
              <span class="badge bg-primary rounded-pill"><?php echo count(glob($t . "/*.mp4")); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function previewVideo(input) {
    const box   = document.getElementById("previewBox");
    const video = document.getElementById("preview");
    if (input.files[0]) {
        video.src = URL.createObjectURL(input.files[0]);
        box.style.display = "block";
        video.load();
    }
}
// Si on tape dans "nouveau thème", vider la sélection existante
document.getElementById("newTheme")?.addEventListener("input", function () {
    const sel = document.getElementById("themeSelect");
    if (sel) sel.value = "";
});
</script>

<?php include "_nav_end.php"; ?>
