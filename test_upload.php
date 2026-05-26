<?php
/* PAGE DE DIAGNOSTIC — à supprimer après dépannage */

$videoDir = __DIR__ . "/videos/";
$result   = [];

/* ── Test upload ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result["method"]         = "POST reçu ✅";
    $result["post_theme"]     = $_POST["theme"] ?? "(vide — post_max_size dépassé ?)";
    $result["files_received"] = !empty($_FILES["video"]["name"]) ? $_FILES["video"]["name"] : "(aucun fichier reçu)";
    $result["content_length"] = $_SERVER["CONTENT_LENGTH"] ?? "non défini";
    $result["file_error"]     = $_FILES["video"]["error"] ?? "n/a";
    $result["file_size"]      = isset($_FILES["video"]["size"]) ? round($_FILES["video"]["size"]/1024/1024, 2)." Mo" : "n/a";

    $errCodes = [
        0 => "OK — pas d'erreur",
        1 => "UPLOAD_ERR_INI_SIZE — dépasse upload_max_filesize",
        2 => "UPLOAD_ERR_FORM_SIZE — dépasse MAX_FILE_SIZE",
        3 => "UPLOAD_ERR_PARTIAL — upload partiel",
        4 => "UPLOAD_ERR_NO_FILE — aucun fichier",
        6 => "UPLOAD_ERR_NO_TMP_DIR — dossier temp manquant",
        7 => "UPLOAD_ERR_CANT_WRITE — écriture impossible",
        8 => "UPLOAD_ERR_EXTENSION — bloqué par extension PHP",
    ];
    $errCode = $_FILES["video"]["error"] ?? -1;
    $result["file_error_msg"] = $errCodes[$errCode] ?? "code inconnu: $errCode";

    $theme    = basename($_POST["theme"] ?? "");
    $themeDir = $videoDir . $theme . "/";
    if ($theme) {
        $result["theme_dir"]       = $themeDir;
        $result["theme_dir_exist"] = is_dir($themeDir) ? "✅ existe" : "❌ n'existe PAS sur le disque";
        $result["theme_writable"]  = is_writable($themeDir) ? "✅ accessible en écriture" : "❌ non accessible en écriture";
    }

    if (!empty($_FILES["video"]["tmp_name"]) && $errCode === 0 && $theme && is_dir($themeDir)) {
        $dest = $themeDir . basename($_FILES["video"]["name"]);
        $ok   = move_uploaded_file($_FILES["video"]["tmp_name"], $dest);
        $result["move_result"] = $ok ? "✅ Fichier enregistré : $dest" : "❌ move_uploaded_file a échoué";
    }
}

$info = [
    "PHP version"          => phpversion(),
    "upload_max_filesize"  => ini_get("upload_max_filesize"),
    "post_max_size"        => ini_get("post_max_size"),
    "max_execution_time"   => ini_get("max_execution_time")."s",
    "upload_tmp_dir"       => ini_get("upload_tmp_dir") ?: sys_get_temp_dir(),
    "videos/ existe"       => is_dir($videoDir) ? "✅ oui" : "❌ non — créez le dossier",
    "videos/ inscriptible" => is_writable($videoDir) ? "✅ oui" : "❌ non",
];

$themes = is_dir($videoDir) ? array_filter(glob($videoDir . "*"), "is_dir") : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Diagnostic Upload</title>
<style>
body{font-family:monospace;background:#0f1117;color:#e8eaf0;padding:30px;line-height:1.6;}
h2{color:#6366f1;border-bottom:1px solid #2e3148;padding-bottom:8px;}
.box{background:#1a1d27;border:1px solid #2e3148;border-radius:8px;padding:16px;margin-bottom:20px;}
.ok{color:#4ade80;} .err{color:#f87171;} .warn{color:#facc15;}
table{width:100%;border-collapse:collapse;}
td{padding:6px 10px;border-bottom:1px solid #2e3148;}
td:first-child{color:#9ca3af;width:250px;}
form{background:#1a1d27;border:1px solid #2e3148;border-radius:8px;padding:20px;}
select,input[type=file]{background:#0f1117;color:#e8eaf0;border:1px solid #2e3148;
  padding:8px;border-radius:6px;width:100%;margin-bottom:12px;}
button{background:#6366f1;color:#fff;border:none;padding:10px 24px;
  border-radius:6px;cursor:pointer;font-size:1rem;}
</style>
</head>
<body>

<h2>🔧 Diagnostic Upload — FormaPro</h2>
<p style="color:#f87171">⚠️ Supprimez ce fichier après le dépannage.</p>

<div class="box">
<h2>⚙️ Config PHP</h2>
<table>
<?php foreach ($info as $k => $v):
  $cls = (str_contains($v,"❌")) ? "err" : ((str_contains($v,"✅")) ? "ok" : "");
?>
<tr><td><?php echo $k; ?></td><td class="<?php echo $cls; ?>"><?php echo htmlspecialchars($v); ?></td></tr>
<?php endforeach; ?>
</table>
</div>

<div class="box">
<h2>📁 Thèmes détectés dans videos/</h2>
<?php if (empty($themes)): ?>
  <p class="err">Aucun sous-dossier trouvé dans <code><?php echo htmlspecialchars($videoDir); ?></code><br>
  Créez d'abord un thème depuis l'admin, ou créez le dossier manuellement.</p>
<?php else: ?>
<table>
<?php foreach ($themes as $t):
  $n = basename($t); $w = is_writable($t);
?>
<tr>
  <td><?php echo htmlspecialchars($n); ?></td>
  <td class="<?php echo $w?'ok':'err'; ?>"><?php echo $w ? "✅ inscriptible" : "❌ non inscriptible"; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<?php if (!empty($result)): ?>
<div class="box">
<h2>📤 Résultat de l'envoi</h2>
<table>
<?php foreach ($result as $k => $v):
  $cls = (str_contains((string)$v,"❌")) ? "err" : ((str_contains((string)$v,"✅")) ? "ok" : "");
?>
<tr><td><?php echo htmlspecialchars($k); ?></td><td class="<?php echo $cls; ?>"><?php echo htmlspecialchars((string)$v); ?></td></tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<div class="box">
<h2>📋 Formulaire de test</h2>
<form method="post" enctype="multipart/form-data">
  <label>Thème :</label>
  <select name="theme">
    <option value="">— choisir —</option>
    <?php foreach ($themes as $t): $n=basename($t); ?>
    <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
    <?php endforeach; ?>
  </select>
  <label>Fichier vidéo MP4 :</label>
  <input type="file" name="video" accept="video/mp4">
  <button type="submit">Tester l'upload</button>
</form>
</div>

</body>
</html>
