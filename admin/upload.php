<?php
require "auth.php";
$pageTitle = "Upload vidéo";

$videoDir = "../videos/";
$themes   = array_filter(glob($videoDir . "*"), 'is_dir');
$message  = ""; $msgType = "ok";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $themeName = !empty($_POST["new_theme"])
        ? preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["new_theme"]))
        : basename($_POST["theme"] ?? "");

    if (empty($themeName)) {
        $message = "Sélectionnez ou créez un thème."; $msgType = "err";
    } elseif (!isset($_FILES["video"]) || $_FILES["video"]["error"] !== UPLOAD_ERR_OK) {
        $message = "Erreur lors de la réception du fichier."; $msgType = "err";
    } else {
        $targetDir = $videoDir . $themeName . "/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES["video"]["name"]));
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== "mp4") {
            $message = "Seuls les fichiers MP4 sont acceptés."; $msgType = "err";
        } elseif ($_FILES["video"]["size"] > 500*1024*1024) {
            $message = "Fichier trop volumineux (max 500 Mo)."; $msgType = "err";
        } elseif (move_uploaded_file($_FILES["video"]["tmp_name"], $targetDir . $fileName)) {
            $message = "Vidéo <strong>".htmlspecialchars($fileName)."</strong> ajoutée dans <strong>".htmlspecialchars($themeName)."</strong>.";
            $themes  = array_filter(glob($videoDir . "*"), 'is_dir');
        } else {
            $message = "Échec de l'upload. Vérifiez les permissions du dossier."; $msgType = "err";
        }
    }
}
?>
<?php include "_nav.php"; ?>
<div class="page-title">Upload d'une vidéo</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

  <!-- FORMULAIRE -->
  <div class="card" style="padding:28px">
    <?php if($message): ?>
    <div style="background:<?php echo $msgType==='ok'?'#052e16':'#450a0a'; ?>;border:1px solid <?php echo $msgType==='ok'?'#166534':'#991b1b'; ?>;color:<?php echo $msgType==='ok'?'#86efac':'#fca5a5'; ?>;padding:12px 16px;border-radius:8px;font-size:.875rem;margin-bottom:20px">
      <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="uploadForm">

      <!-- Thème -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:.82rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">1. Choisir un thème</label>
        <?php if(!empty($themes)): ?>
        <select name="theme" id="themeSelect" style="width:100%;padding:11px 14px;background:var(--card);border:1px solid var(--border);border-radius:9px;color:var(--text);font-size:.9rem;outline:none;margin-bottom:10px">
          <option value="">— Sélectionner un thème existant —</option>
          <?php foreach($themes as $t): $n=basename($t); ?>
            <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?> (<?php echo count(glob($t."/*.mp4")); ?> vidéo(s))</option>
          <?php endforeach; ?>
        </select>
        <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:4px 0">— ou créer un nouveau thème —</div>
        <?php endif; ?>
        <input type="text" name="new_theme" id="newTheme" placeholder="Nouveau thème (ex : Amplitude, RH…)"
          style="width:100%;padding:11px 14px;background:var(--card);border:1px solid var(--border);border-radius:9px;color:var(--text);font-size:.9rem;outline:none;margin-top:8px">
      </div>

      <!-- Fichier -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:.82rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">2. Sélectionner la vidéo</label>
        <label id="dropZone" for="fileInput" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:36px 20px;border:2px dashed var(--border);border-radius:12px;cursor:pointer;transition:border-color .2s;background:var(--bg)">
          <span style="font-size:2.4rem">📤</span>
          <span style="font-weight:600;font-size:.9rem;color:var(--text)">Glissez votre vidéo ici</span>
          <span style="font-size:.8rem;color:var(--muted)">ou cliquez pour parcourir — MP4 uniquement, max 500 Mo</span>
          <input type="file" id="fileInput" name="video" accept="video/mp4" style="display:none" onchange="handleFile(this)" required>
        </label>
        <div id="fileName" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:8px;font-size:.85rem;color:#a5b4fc;font-weight:600"></div>
      </div>

      <!-- Prévisualisation -->
      <div id="previewBox" style="display:none;margin-bottom:20px">
        <label style="display:block;font-size:.82rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Aperçu</label>
        <video id="preview" controls style="width:100%;max-height:240px;border-radius:10px;background:#000;display:block"></video>
      </div>

      <button type="submit" style="width:100%;padding:13px;background:#6366f1;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:.95rem;cursor:pointer;transition:background .2s">
        Envoyer la vidéo →
      </button>
    </form>
  </div>

  <!-- SIDEBAR INFO -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card" style="padding:20px">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:14px;color:var(--text)">💡 Format accepté</div>
      <?php $tips=[['✅','Format','MP4 uniquement'],['✅','Taille max','500 Mo'],['✅','Nommage','01_Introduction.mp4'],['✅','Thèmes','Un dossier = un thème']];
      foreach($tips as $t): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);font-size:.83rem">
        <span><?php echo $t[0]; ?></span>
        <span style="color:var(--muted)"><?php echo $t[1]; ?></span>
        <span style="margin-left:auto;font-weight:600;color:var(--text)"><?php echo $t[2]; ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:20px">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:14px;color:var(--text)">📁 Thèmes existants</div>
      <?php if(empty($themes)): ?>
        <p style="color:var(--muted);font-size:.83rem">Aucun thème. Créez-en un ci-contre.</p>
      <?php else: ?>
        <?php foreach($themes as $t): $n=basename($t); $c=count(glob($t."/*.mp4")); ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:.83rem">
          <span style="color:var(--text)">📁 <?php echo htmlspecialchars($n); ?></span>
          <span style="background:rgba(99,102,241,.1);color:#a5b4fc;padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:600"><?php echo $c; ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.getElementById("newTheme").addEventListener("input",function(){
  var s=document.getElementById("themeSelect"); if(s) s.value="";
});
function handleFile(input){
  var f=input.files[0]; if(!f) return;
  var dz=document.getElementById("dropZone");
  dz.style.borderColor="#6366f1"; dz.style.borderStyle="solid";
  document.getElementById("fileName").style.display="block";
  document.getElementById("fileName").textContent="📎 "+f.name+" ("+Math.round(f.size/1024/1024*10)/10+" Mo)";
  var pv=document.getElementById("preview");
  pv.src=URL.createObjectURL(f);
  document.getElementById("previewBox").style.display="block";
}
var dz=document.getElementById("dropZone");
dz.addEventListener("dragover",function(e){e.preventDefault();dz.style.borderColor="#6366f1";});
dz.addEventListener("dragleave",function(){dz.style.borderColor="";});
dz.addEventListener("drop",function(e){e.preventDefault();var fi=document.getElementById("fileInput");fi.files=e.dataTransfer.files;handleFile(fi);});
</script>
<?php include "_nav_end.php"; ?>
