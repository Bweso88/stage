<?php
require "auth.php";
$pageTitle = "Gérer les thèmes";
$videoDir  = "../videos/";
$msg = ""; $msgType = "ok";

/* ── Créer un thème ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["name"]));
    if (empty($name)) { $msg = "Nom invalide."; $msgType = "err"; }
    elseif (is_dir($videoDir.$name)) { $msg = "Ce thème existe déjà."; $msgType = "warn"; }
    else {
        mkdir($videoDir.$name, 0755, true);
        if (!empty($_FILES["cover"]["name"]) && $_FILES["cover"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["cover"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg","jpeg","png","webp","gif"])) {
                // Supprimer ancienne couverture si existe
                foreach (glob($videoDir.$name."/cover.*") as $old) unlink($old);
                move_uploaded_file($_FILES["cover"]["tmp_name"], $videoDir.$name."/cover.".$ext);
            }
        }
        $msg = "Thème <strong>".htmlspecialchars($name)."</strong> créé.";
    }
}

/* ── Renommer un thème ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["rename"])) {
    $old = basename($_POST["old_name"]);
    $new = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["new_name"]));
    if (!$old || !$new) { $msg = "Noms invalides."; $msgType = "err"; }
    elseif (!is_dir($videoDir.$old)) { $msg = "Thème introuvable."; $msgType = "err"; }
    elseif (is_dir($videoDir.$new)) { $msg = "Ce nom existe déjà."; $msgType = "warn"; }
    else { rename($videoDir.$old, $videoDir.$new); $msg = "Thème renommé en <strong>".htmlspecialchars($new)."</strong>."; }
}

/* ── Changer la photo de couverture ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cover_theme"])) {
    $name = basename($_POST["cover_theme"]);
    $themeDir = $videoDir.$name."/";
    if (is_dir($themeDir) && !empty($_FILES["cover"]["name"]) && $_FILES["cover"]["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES["cover"]["name"], PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg","jpeg","png","webp","gif"])) {
            foreach (glob($themeDir."cover.*") as $old) unlink($old);
            move_uploaded_file($_FILES["cover"]["tmp_name"], $themeDir."cover.".$ext);
            $msg = "Photo de couverture mise à jour pour <strong>".htmlspecialchars($name)."</strong>.";
        } else { $msg = "Format non supporté (JPG, PNG, WebP, GIF)."; $msgType = "err"; }
    } else { $msg = "Erreur lors de l'upload."; $msgType = "err"; }
}

/* ── Supprimer un thème ── */
if (isset($_GET["delete"])) {
    $name = basename($_GET["delete"]); $path = $videoDir.$name;
    if (!is_dir($path)) { $msg = "Thème introuvable."; $msgType = "err"; }
    else {
        $vids = glob($path."/*.mp4") ?: [];
        if (!empty($vids) && empty($_GET["force"])) {
            $msg = "Ce thème contient ".count($vids)." vidéo(s). <a href='?delete=".urlencode($name)."&force=1' style='color:#fca5a5;font-weight:700' onclick=\"return confirm('Supprimer le thème ET toutes ses vidéos ?')\">Forcer la suppression</a>.";
            $msgType = "warn";
        } else {
            foreach (glob($path."/*") as $f) unlink($f);
            rmdir($path);
            $msg = "Thème <strong>".htmlspecialchars($name)."</strong> supprimé.";
        }
    }
}

$themes = array_filter(glob($videoDir . "*"), 'is_dir');
$colors = ["#6366f1","#0ea5e9","#10b981","#f59e0b","#ef4444","#8b5cf6","#06b6d4","#84cc16"];
$icons  = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚"];

function getCover(string $themeDir): string {
    $files = glob($themeDir . "cover.*");
    return $files ? $files[0] : "";
}
?>
<?php include "_nav.php"; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
  <div class="page-title" style="margin-bottom:0">Gérer les thèmes</div>
  <button onclick="document.getElementById('modalCreate').style.display='flex'"
    style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer">
    + Nouveau thème
  </button>
</div>

<?php if ($msg): ?>
<div style="background:<?php echo $msgType==='ok'?'#052e16':($msgType==='warn'?'#451a03':'#450a0a'); ?>;border:1px solid <?php echo $msgType==='ok'?'#166534':($msgType==='warn'?'#92400e':'#991b1b'); ?>;color:<?php echo $msgType==='ok'?'#86efac':($msgType==='warn'?'#fcd34d':'#fca5a5'); ?>;padding:12px 16px;border-radius:8px;font-size:.875rem;margin-bottom:20px">
  <?php echo $msg; ?>
</div>
<?php endif; ?>

<?php if (empty($themes)): ?>
<div style="text-align:center;padding:60px 20px" class="card">
  <div style="font-size:3rem;margin-bottom:14px">📭</div>
  <div style="font-weight:700;font-size:1rem;margin-bottom:6px;color:var(--text)">Aucun thème</div>
  <div style="color:var(--muted);font-size:.85rem">Commencez par créer votre premier thème.</div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px">
  <?php foreach ($themes as $i => $td):
    $name    = basename($td);
    $vids    = glob($td."/*.mp4") ?: [];
    $cnt     = count($vids);
    $views   = 0;
    foreach ($vids as $v) { $vf = "../views/".pathinfo($v,PATHINFO_FILENAME).".txt"; $views += file_exists($vf) ? (int)file_get_contents($vf) : 0; }
    $col     = $colors[$i % count($colors)];
    $ico     = $icons[$i % count($icons)];
    $cover   = getCover($td."/");
    $coverUrl = $cover ? htmlspecialchars("../".$cover) : "";
  ?>
  <div class="card card-hover" style="overflow:hidden">
    <!-- Bannière : photo ou couleur -->
    <?php if ($coverUrl): ?>
    <div style="height:130px;overflow:hidden;position:relative">
      <img src="<?php echo $coverUrl; ?>" alt="Couverture" style="width:100%;height:100%;object-fit:cover;display:block">
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 50%,rgba(0,0,0,.5))"></div>
    </div>
    <?php else: ?>
    <div style="height:130px;background:linear-gradient(135deg,<?php echo $col; ?>33,<?php echo $col; ?>66);display:flex;align-items:center;justify-content:center;font-size:3rem;position:relative">
      <?php echo $ico; ?>
      <div style="position:absolute;bottom:8px;right:10px;font-size:.7rem;color:rgba(255,255,255,.7);font-style:italic">Aucune photo</div>
    </div>
    <?php endif; ?>

    <div style="padding:16px">
      <div style="font-weight:700;font-size:.95rem;color:var(--text);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        <?php echo htmlspecialchars($name); ?>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-bottom:14px">🎬 <?php echo $cnt; ?> vidéo(s) &nbsp;·&nbsp; 👁 <?php echo $views; ?> vue(s)</div>

      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <!-- Photo de couverture -->
        <label title="Changer la photo"
          style="padding:6px 10px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);
                 border-radius:7px;font-size:.78rem;font-weight:600;color:#a5b4fc;cursor:pointer;white-space:nowrap">
          🖼 Photo
          <input type="file" accept="image/*" style="display:none"
            onchange="submitCover(this,'<?php echo htmlspecialchars($name,ENT_QUOTES); ?>')">
        </label>

        <a href="../videos.php?theme=<?php echo urlencode($name); ?>" target="_blank"
           style="padding:6px 10px;border:1px solid var(--border);border-radius:7px;font-size:.78rem;font-weight:600;color:var(--muted);text-decoration:none">
          Voir
        </a>
        <button onclick="openRename('<?php echo htmlspecialchars($name,ENT_QUOTES); ?>')"
          style="padding:6px 10px;background:transparent;border:1px solid var(--border);border-radius:7px;font-size:.78rem;font-weight:600;color:var(--muted);cursor:pointer">
          ✏️ Renommer
        </button>
        <a href="?delete=<?php echo urlencode($name); ?>" onclick="return confirm('Supprimer ce thème ?')"
          style="padding:6px 10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:7px;font-size:.78rem;font-weight:600;color:#fca5a5;text-decoration:none">
          🗑
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Formulaire caché pour upload de couverture -->
<form id="coverForm" method="post" enctype="multipart/form-data" style="display:none">
  <input type="hidden" name="cover_theme" id="coverThemeName">
  <input type="file" name="cover" id="coverFileInput">
</form>

<!-- Modal créer -->
<div id="modalCreate" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:420px;margin:16px">
    <div style="font-weight:800;font-size:1.05rem;margin-bottom:18px;color:var(--text)">📁 Nouveau thème</div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="create" value="1">

      <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:7px">Nom du thème *</label>
      <input type="text" name="name" placeholder="ex : Comptabilité, RH, Amplitude…" required
        style="width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none;margin-bottom:14px">

      <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:7px">Photo de couverture (optionnelle)</label>
      <label id="coverDropZone"
        style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:24px;
               border:2px dashed var(--border);border-radius:8px;cursor:pointer;
               background:var(--bg);margin-bottom:6px;transition:border-color .2s">
        <span style="font-size:2rem">🖼</span>
        <span style="font-size:.82rem;color:var(--muted)">Cliquez pour choisir une image</span>
        <span style="font-size:.72rem;color:var(--subtle)">JPG · PNG · WebP</span>
        <input type="file" name="cover" accept="image/*" style="display:none" onchange="previewCover(this)">
      </label>
      <div id="coverPreviewWrap" style="display:none;margin-bottom:14px">
        <img id="coverPreview" style="width:100%;max-height:140px;object-fit:cover;border-radius:8px;display:block">
      </div>
      <div style="font-size:.73rem;color:var(--muted);margin-bottom:18px">Vous pourrez la changer plus tard depuis les cartes.</div>

      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('modalCreate').style.display='none'"
          style="flex:1;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:.88rem;color:var(--muted);cursor:pointer">Annuler</button>
        <button type="submit"
          style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal renommer -->
<div id="modalRename" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:400px;margin:16px">
    <div style="font-weight:800;font-size:1.05rem;margin-bottom:18px;color:var(--text)">✏️ Renommer le thème</div>
    <form method="post">
      <input type="hidden" name="rename" value="1">
      <input type="hidden" name="old_name" id="rOldName">
      <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:7px">Nouveau nom</label>
      <input type="text" name="new_name" id="rNewName" required
        style="width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none;margin-bottom:18px">
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('modalRename').style.display='none'"
          style="flex:1;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:.88rem;color:var(--muted);cursor:pointer">Annuler</button>
        <button type="submit"
          style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Renommer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRename(name) {
  document.getElementById('rOldName').value = name;
  document.getElementById('rNewName').value = name;
  document.getElementById('modalRename').style.display = 'flex';
}

/* Soumettre le changement de photo depuis la carte */
function submitCover(input, theme) {
  if (!input.files[0]) return;
  document.getElementById('coverThemeName').value = theme;
  var dt = new DataTransfer();
  dt.items.add(input.files[0]);
  document.getElementById('coverFileInput').files = dt.files;
  document.getElementById('coverForm').submit();
}

/* Aperçu dans le modal de création */
function previewCover(input) {
  if (!input.files[0]) return;
  var url = URL.createObjectURL(input.files[0]);
  document.getElementById('coverPreview').src = url;
  document.getElementById('coverPreviewWrap').style.display = 'block';
  document.getElementById('coverDropZone').style.borderColor = '#6366f1';
}
</script>

<?php include "_nav_end.php"; ?>
