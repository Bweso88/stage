<?php
require "auth.php";
$pageTitle = "Gérer les thèmes";
$videoDir  = "../videos/";
$msg = ""; $msgType = "ok";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create"])) {
    $name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["name"]));
    if (empty($name)) { $msg="Nom invalide."; $msgType="err"; }
    elseif (is_dir($videoDir.$name)) { $msg="Ce thème existe déjà."; $msgType="warn"; }
    else { mkdir($videoDir.$name,0755,true); $msg="Thème <strong>".htmlspecialchars($name)."</strong> créé."; }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["rename"])) {
    $old = basename($_POST["old_name"]); $new = preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST["new_name"]));
    if (!$old||!$new) { $msg="Noms invalides."; $msgType="err"; }
    elseif (!is_dir($videoDir.$old)) { $msg="Thème introuvable."; $msgType="err"; }
    elseif (is_dir($videoDir.$new)) { $msg="Ce nom existe déjà."; $msgType="warn"; }
    else { rename($videoDir.$old,$videoDir.$new); $msg="Thème renommé en <strong>".htmlspecialchars($new)."</strong>."; }
}
if (isset($_GET["delete"])) {
    $name = basename($_GET["delete"]); $path = $videoDir.$name;
    if (!is_dir($path)) { $msg="Thème introuvable."; $msgType="err"; }
    else {
        $vids = glob($path."/*.mp4") ?: [];
        if (!empty($vids) && empty($_GET["force"])) {
            $msg="Ce thème contient ".count($vids)." vidéo(s). <a href='?delete=".urlencode($name)."&force=1' style='color:#fca5a5;font-weight:700' onclick=\"return confirm('Supprimer le thème ET toutes ses vidéos ?')\">Forcer la suppression</a>.";
            $msgType="warn";
        } else {
            foreach(glob($path."/*") as $f) unlink($f);
            rmdir($path); $msg="Thème <strong>".htmlspecialchars($name)."</strong> supprimé.";
        }
    }
}
$themes = array_filter(glob($videoDir . "*"), 'is_dir');
$colors = ["#6366f1","#0ea5e9","#10b981","#f59e0b","#ef4444","#8b5cf6","#06b6d4","#84cc16"];
$icons  = ["🖥️","📊","📋","🎯","💡","📈","🔧","📚"];
?>
<?php include "_nav.php"; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
  <div class="page-title" style="margin-bottom:0">Gérer les thèmes</div>
  <button onclick="document.getElementById('modalCreate').style.display='flex'"
    style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer">
    + Nouveau thème
  </button>
</div>

<?php if($msg): ?>
<div style="background:<?php echo $msgType==='ok'?'#052e16':($msgType==='warn'?'#451a03':'#450a0a'); ?>;border:1px solid <?php echo $msgType==='ok'?'#166534':($msgType==='warn'?'#92400e':'#991b1b'); ?>;color:<?php echo $msgType==='ok'?'#86efac':($msgType==='warn'?'#fcd34d':'#fca5a5'); ?>;padding:12px 16px;border-radius:8px;font-size:.875rem;margin-bottom:20px">
  <?php echo $msg; ?>
</div>
<?php endif; ?>

<?php if(empty($themes)): ?>
<div style="text-align:center;padding:60px 20px" class="card">
  <div style="font-size:3rem;margin-bottom:14px">📭</div>
  <div style="font-weight:700;font-size:1rem;margin-bottom:6px;color:var(--text)">Aucun thème</div>
  <div style="color:var(--muted);font-size:.85rem">Commencez par créer votre premier thème.</div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px">
  <?php foreach($themes as $i=>$td):
    $name  = basename($td); $vids = glob($td."/*.mp4") ?: []; $cnt = count($vids);
    $views = 0; foreach($vids as $v){ $vf="../views/".pathinfo($v,PATHINFO_FILENAME).".txt"; $views+=file_exists($vf)?(int)file_get_contents($vf):0; }
    $col   = $colors[$i%count($colors)]; $ico = $icons[$i%count($icons)];
  ?>
  <div class="card card-hover" style="overflow:hidden">
    <div style="height:6px;background:<?php echo $col; ?>"></div>
    <div style="padding:20px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <div style="width:44px;height:44px;border-radius:10px;background:<?php echo $col; ?>1a;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0"><?php echo $ico; ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.95rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($name); ?></div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:2px">🎬 <?php echo $cnt; ?> vidéo(s) &nbsp;·&nbsp; 👁️ <?php echo $views; ?> vue(s)</div>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <a href="../videos.php?theme=<?php echo urlencode($name); ?>" target="_blank"
           style="flex:1;padding:7px;text-align:center;border:1px solid var(--border);border-radius:7px;font-size:.8rem;font-weight:600;color:var(--muted);text-decoration:none;transition:all .2s">
          Voir
        </a>
        <button onclick="openRename('<?php echo htmlspecialchars($name,ENT_QUOTES); ?>')"
          style="flex:1;padding:7px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:7px;font-size:.8rem;font-weight:600;color:#a5b4fc;cursor:pointer">
          ✏️ Renommer
        </button>
        <a href="?delete=<?php echo urlencode($name); ?>" onclick="return confirm('Supprimer ce thème ?')"
          style="padding:7px 10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:7px;font-size:.8rem;font-weight:600;color:#fca5a5;text-decoration:none">
          🗑
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal créer -->
<div id="modalCreate" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:400px;margin:16px">
    <div style="font-weight:800;font-size:1.05rem;margin-bottom:18px;color:var(--text)">📁 Nouveau thème</div>
    <form method="post">
      <input type="hidden" name="create" value="1">
      <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:7px">Nom du thème</label>
      <input type="text" name="name" placeholder="ex : Comptabilité, RH, Amplitude…" required
        style="width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none;margin-bottom:6px">
      <div style="font-size:.75rem;color:var(--muted);margin-bottom:18px">Lettres, chiffres, tirets et espaces.</div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('modalCreate').style.display='none'"
          style="flex:1;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:.88rem;color:var(--muted);cursor:pointer">Annuler</button>
        <button type="submit" style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Créer</button>
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
        <button type="submit" style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Renommer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRename(name){
  document.getElementById('rOldName').value = name;
  document.getElementById('rNewName').value = name;
  document.getElementById('modalRename').style.display = 'flex';
}
</script>
<?php include "_nav_end.php"; ?>
