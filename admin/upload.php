<?php
require "auth.php";
$pageTitle = "Upload vidéo";

$videoDir = "../videos/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');
$message  = ""; $msgType = "ok";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $themeName = basename($_POST["theme"] ?? "");

    if (empty($themeName) || !is_dir($videoDir . $themeName)) {
        $message = "Veuillez sélectionner un thème valide."; $msgType = "err";
    } elseif (!isset($_FILES["video"]) || $_FILES["video"]["error"] !== UPLOAD_ERR_OK) {
        $message = "Erreur lors de la réception du fichier."; $msgType = "err";
    } else {
        $targetDir = $videoDir . $themeName . "/";
        $fileName  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES["video"]["name"]));
        $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($ext !== "mp4") {
            $message = "Seuls les fichiers MP4 sont acceptés."; $msgType = "err";
        } elseif ($_FILES["video"]["size"] > 500 * 1024 * 1024) {
            $message = "Fichier trop volumineux (max 500 Mo)."; $msgType = "err";
        } elseif (move_uploaded_file($_FILES["video"]["tmp_name"], $targetDir . $fileName)) {
            $message = "Vidéo <strong>" . htmlspecialchars($fileName) . "</strong> ajoutée dans le thème <strong>" . htmlspecialchars($themeName) . "</strong>.";
            $themes  = array_filter(glob($videoDir . "*"), 'is_dir');
        } else {
            $message = "Échec de l'upload. Vérifiez les permissions du dossier."; $msgType = "err";
        }
    }
}
?>
<?php include "_nav.php"; ?>

<div class="adm-title">Upload d'une vidéo</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

  <div class="card" style="padding:28px">

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (empty($themes)): ?>
    <div style="text-align:center;padding:40px 20px">
      <div style="font-size:3rem;margin-bottom:14px">📁</div>
      <div style="font-weight:700;font-size:1rem;margin-bottom:8px;color:var(--text)">Aucun thème disponible</div>
      <div style="color:var(--muted);font-size:.875rem;margin-bottom:20px">
        Vous devez d'abord créer un thème avant de pouvoir uploader une vidéo.
      </div>
      <a href="manage_themes.php"><button class="btn btn-primary">Créer un thème →</button></a>
    </div>

    <?php else: ?>
    <form method="post" enctype="multipart/form-data">

      <div class="field">
        <label>Thème de formation</label>
        <select name="theme" required
          style="width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none;cursor:pointer">
          <option value="">— Sélectionner un thème —</option>
          <?php foreach ($themes as $t):
            $n = basename($t);
            $c = count(glob($t . "/*.mp4") ?: []);
          ?>
          <option value="<?php echo htmlspecialchars($n); ?>">
            <?php echo htmlspecialchars($n); ?> (<?php echo $c; ?> vidéo<?php echo $c>1?'s':''; ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:.75rem;color:var(--muted);margin-top:6px">
          Thème manquant ? <a href="manage_themes.php" style="color:var(--indigo);font-weight:600">Créer un thème →</a>
        </div>
      </div>

      <div class="field" style="margin-top:20px">
        <label>Fichier vidéo</label>
        <label id="dropZone" for="fileInput"
          style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
                 padding:40px 20px;border:2px dashed var(--border);border-radius:10px;cursor:pointer;
                 transition:border-color .2s;background:var(--bg)">
          <span style="font-size:2.5rem">📤</span>
          <span style="font-weight:700;font-size:.95rem;color:var(--text)">Glissez votre vidéo ici</span>
          <span style="font-size:.8rem;color:var(--muted)">ou cliquez pour parcourir</span>
          <span style="font-size:.75rem;color:var(--subtle)">MP4 uniquement · max 500 Mo</span>
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
        <video id="preview" controls style="width:100%;max-height:240px;border-radius:10px;background:#000;display:block"></video>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:24px;padding:13px;font-size:.95rem">
        Envoyer la vidéo →
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card" style="padding:20px">
    <div style="font-weight:700;font-size:.9rem;margin-bottom:14px">💡 Informations</div>
    <?php foreach ([
      ["Format",    "MP4 uniquement"],
      ["Taille max","500 Mo"],
      ["Nommage",   "01_Introduction.mp4"],
    ] as [$k,$v]): ?>
    <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:.83rem">
      <span style="color:var(--muted)"><?php echo $k; ?></span>
      <span style="font-weight:600;color:var(--text)"><?php echo $v; ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($themes)): ?>
    <div style="margin-top:18px">
      <div style="font-weight:700;font-size:.85rem;margin-bottom:10px">📁 Thèmes disponibles</div>
      <?php foreach ($themes as $t): $n=basename($t); $c=count(glob($t."/*.mp4")?:[]); ?>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:.82rem">
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
  var f=input.files[0]; if(!f) return;
  var dz=document.getElementById("dropZone");
  dz.style.borderColor="var(--indigo)"; dz.style.borderStyle="solid";
  var fi=document.getElementById("fileInfo"); fi.style.display="flex";
  document.getElementById("fileName").textContent=f.name+" ("+Math.round(f.size/1024/1024*10)/10+" Mo)";
  document.getElementById("preview").src=URL.createObjectURL(f);
  document.getElementById("previewBox").style.display="block";
}
var dz=document.getElementById("dropZone");
if(dz){
  dz.addEventListener("dragover",function(e){e.preventDefault();dz.style.borderColor="var(--indigo)";});
  dz.addEventListener("dragleave",function(){dz.style.borderColor="var(--border)";});
  dz.addEventListener("drop",function(e){e.preventDefault();var fi=document.getElementById("fileInput");fi.files=e.dataTransfer.files;handleFile(fi);});
}
</script>

<?php include "_nav_end.php"; ?>