<?php
require "auth.php";
$pageTitle = "Upload vidéo";

$videoDir = "../videos/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');
$message  = ""; $msgType = "ok"; $debug = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ── Détection du dépassement de post_max_size ──────────────────────
       Quand le fichier dépasse post_max_size, PHP vide $_POST et $_FILES
       entièrement sans lever d'erreur visible. On le détecte par CONTENT_LENGTH. */
    $contentLength = (int)($_SERVER["CONTENT_LENGTH"] ?? 0);

    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        $max = ini_get('post_max_size');
        $message = "❌ Fichier trop volumineux : votre PHP a une limite <strong>post_max_size = $max</strong>.<br>
                    Ouvrez <code>C:\\xampp\\php\\php.ini</code>, cherchez et modifiez :<br>
                    <code>upload_max_filesize = 500M</code><br>
                    <code>post_max_size = 600M</code><br>
                    Puis redémarrez Apache depuis le panneau XAMPP.";
        $msgType = "err";
    } else {
        $themeName = basename($_POST["theme"] ?? "");
        $targetDir = $videoDir . $themeName . "/";

        if (empty($themeName) || !is_dir($targetDir)) {
            $message = "Sélectionnez un thème valide."; $msgType = "err";
        } elseif (empty($_FILES["video"]["name"])) {
            $message = "Aucun fichier reçu. Vérifiez que vous avez bien sélectionné un fichier MP4."; $msgType = "err";
        } else {
            $err  = $_FILES["video"]["error"];
            $size = $_FILES["video"]["size"];
            $name = $_FILES["video"]["name"];
            $tmp  = $_FILES["video"]["tmp_name"];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $max = ini_get('upload_max_filesize');
                $message = "❌ Fichier trop volumineux. Limite PHP actuelle : <strong>upload_max_filesize = $max</strong>.<br>
                            Ouvrez <code>C:\\xampp\\php\\php.ini</code> et augmentez :<br>
                            <code>upload_max_filesize = 500M</code> et <code>post_max_size = 600M</code><br>
                            Puis redémarrez Apache.";
                $msgType = "err";
            } elseif ($err !== UPLOAD_ERR_OK) {
                $codes = [1=>"Dépasse upload_max_filesize",2=>"Dépasse MAX_FILE_SIZE",3=>"Upload partiel",4=>"Aucun fichier",6=>"Dossier temp manquant",7=>"Ecriture impossible",8=>"Extension PHP bloquée"];
                $message = "Erreur upload PHP : " . ($codes[$err] ?? "code $err") . "."; $msgType = "err";
            } elseif ($ext !== "mp4") {
                $message = "Seuls les fichiers <strong>MP4</strong> sont acceptés."; $msgType = "err";
            } elseif (!is_dir($targetDir)) {
                $message = "Le dossier du thème <code>$targetDir</code> n'existe pas sur le serveur. Recréez le thème."; $msgType = "err";
            } elseif (!is_writable($targetDir)) {
                $message = "Le dossier <code>$targetDir</code> n'est pas accessible en écriture.<br>
                            Sous Windows, vérifiez les permissions du dossier <code>videos\\$themeName</code>."; $msgType = "err";
            } else {
                $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($name));
                $dest     = $targetDir . $fileName;
                if (move_uploaded_file($tmp, $dest)) {
                    $message = "✅ Vidéo <strong>" . htmlspecialchars($fileName) . "</strong> enregistrée dans <strong>" . htmlspecialchars($themeName) . "</strong>.";
                    $themes  = array_filter(glob($videoDir . "*"), 'is_dir');
                } else {
                    $message = "Échec de l'enregistrement. Vérifiez les permissions du dossier <code>$targetDir</code>."; $msgType = "err";
                }
            }
        }
    }
}

// Infos PHP utiles
$phpMaxUpload = ini_get('upload_max_filesize');
$phpMaxPost   = ini_get('post_max_size');
?>
<?php include "_nav.php"; ?>

<div class="adm-title">Upload d'une vidéo</div>

<!-- Alerte config PHP si limite faible -->
<?php
$limitMb = (int)$phpMaxUpload;
if ($limitMb < 100): ?>
<div class="alert alert-warn">
  ⚠️ Votre PHP limite les uploads à <strong><?php echo $phpMaxUpload; ?></strong>.
  Pour uploader des vidéos volumineuses, augmentez <code>upload_max_filesize</code> et <code>post_max_size</code> dans <code>php.ini</code>
  (<code>C:\xampp\php\php.ini</code>).
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

  <div class="card" style="padding:28px">

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (empty($themes)): ?>
    <div style="text-align:center;padding:40px 20px">
      <div style="font-size:3rem;margin-bottom:14px">📁</div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:8px;color:var(--text)">Aucun thème disponible</div>
      <div style="color:var(--muted);font-size:.875rem;margin-bottom:20px">Créez d'abord un thème.</div>
      <a href="manage_themes.php"><button class="btn btn-primary">Créer un thème →</button></a>
    </div>

    <?php else: ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="MAX_FILE_SIZE" value="524288000">

      <div class="field">
        <label>Thème de formation</label>
        <select name="theme" required
          style="width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none;cursor:pointer">
          <option value="">— Sélectionner un thème —</option>
          <?php foreach ($themes as $t):
            $n = basename($t); $c = count(glob($t . "/*.mp4") ?: []);
          ?>
          <option value="<?php echo htmlspecialchars($n); ?>">
            📁 <?php echo htmlspecialchars($n); ?> — <?php echo $c; ?> vidéo(s)
          </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:.75rem;color:var(--muted);margin-top:6px">
          Thème manquant ? <a href="manage_themes.php" style="color:var(--indigo);font-weight:600">Créer un thème →</a>
        </div>
      </div>

      <div class="field" style="margin-top:20px">
        <label>Fichier vidéo (MP4)</label>
        <label id="dropZone" for="fileInput"
          style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
                 padding:40px 20px;border:2px dashed var(--border);border-radius:10px;cursor:pointer;
                 transition:border-color .2s;background:var(--bg)">
          <span style="font-size:2.5rem">📤</span>
          <span style="font-weight:700;font-size:.95rem;color:var(--text)">Glissez votre vidéo ici</span>
          <span style="font-size:.8rem;color:var(--muted)">ou cliquez pour parcourir</span>
          <span style="font-size:.75rem;color:var(--subtle)">MP4 · max <?php echo $phpMaxUpload; ?></span>
          <input type="file" id="fileInput" name="video" accept="video/mp4" style="display:none" onchange="handleFile(this)" required>
        </label>
        <div id="fileInfo" style="display:none;margin-top:10px;padding:10px 14px;
             background:var(--indigo-l);border:1px solid rgba(99,102,241,.25);
             border-radius:8px;font-size:.855rem;color:#a5b4fc;font-weight:600;
             align-items:center;gap:8px">
          <span>📎</span><span id="fileName"></span>
        </div>
      </div>

      <div id="previewBox" style="display:none;margin-top:18px">
        <div style="font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Aperçu</div>
        <video id="preview" controls style="width:100%;max-height:220px;border-radius:10px;background:#000;display:block"></video>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:24px;padding:13px;font-size:.95rem">
        Enregistrer la vidéo →
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- SIDEBAR -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card" style="padding:20px">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:14px">⚙️ Config PHP actuelle</div>
      <?php foreach ([
        ["upload_max_filesize", $phpMaxUpload],
        ["post_max_size",       $phpMaxPost],
      ] as [$k, $v]): ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.82rem">
        <span style="color:var(--muted)"><code><?php echo $k; ?></code></span>
        <span style="font-weight:700;color:<?php echo (int)$v < 100 ? 'var(--yellow)' : 'var(--green)'; ?>"><?php echo $v; ?></span>
      </div>
      <?php endforeach; ?>
      <div style="font-size:.75rem;color:var(--muted);margin-top:10px;line-height:1.5">
        Pour augmenter la limite :<br>
        <code style="font-size:.72rem">C:\xampp\php\php.ini</code><br>
        Modifier <code>upload_max_filesize</code> et <code>post_max_size</code>
      </div>
    </div>

    <?php if (!empty($themes)): ?>
    <div class="card" style="padding:20px">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:12px">📁 Thèmes</div>
      <?php foreach ($themes as $t): $n=basename($t); $c=count(glob($t."/*.mp4")?:[]); ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:.82rem">
        <span style="color:var(--text)">📁 <?php echo htmlspecialchars($n); ?></span>
        <span class="badge badge-indigo"><?php echo $c; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function handleFile(input) {
  var f = input.files[0]; if (!f) return;
  var dz = document.getElementById("dropZone");
  dz.style.borderColor = "var(--indigo)"; dz.style.borderStyle = "solid";
  var fi = document.getElementById("fileInfo"); fi.style.display = "flex";
  document.getElementById("fileName").textContent = f.name + " (" + Math.round(f.size/1024/1024*10)/10 + " Mo)";
  document.getElementById("preview").src = URL.createObjectURL(f);
  document.getElementById("previewBox").style.display = "block";
}
var dz = document.getElementById("dropZone");
if (dz) {
  dz.addEventListener("dragover",  function(e){ e.preventDefault(); dz.style.borderColor="var(--indigo)"; });
  dz.addEventListener("dragleave", function()  { dz.style.borderColor="var(--border)"; });
  dz.addEventListener("drop",      function(e) {
    e.preventDefault();
    var fi = document.getElementById("fileInput");
    fi.files = e.dataTransfer.files; handleFile(fi);
  });
}
</script>

<?php include "_nav_end.php"; ?>
