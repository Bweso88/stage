<?php
require "auth.php";
require "db.php";
$pageTitle = "Utilisateurs";

if ($_SESSION["user"]["role"] !== "ADMIN") { header("Location: dashboard.php"); exit; }
$msg = ""; $msgType = "ok";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "create") {
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?"); $check->execute([$_POST["username"]]);
    if ($check->fetchColumn()) { $msg="Identifiant déjà utilisé."; $msgType="err"; }
    else {
        $pdo->prepare("INSERT INTO users (username,password,fullname,role,active) VALUES (?,?,?,?,1)")
            ->execute([$_POST["username"], password_hash($_POST["password"],PASSWORD_DEFAULT), $_POST["fullname"], $_POST["role"]]);
        $msg="Compte <strong>".htmlspecialchars($_POST["fullname"])."</strong> créé.";
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "edit") {
    $id=(int)$_POST["id"];
    if (!empty($_POST["password"]))
        $pdo->prepare("UPDATE users SET fullname=?,role=?,password=? WHERE id=?")->execute([$_POST["fullname"],$_POST["role"],password_hash($_POST["password"],PASSWORD_DEFAULT),$id]);
    else
        $pdo->prepare("UPDATE users SET fullname=?,role=? WHERE id=?")->execute([$_POST["fullname"],$_POST["role"],$id]);
    $msg="Compte mis à jour.";
}
if (isset($_GET["toggle"])) {
    $id=(int)$_GET["toggle"];
    if ($id===(int)$_SESSION["user"]["id"]) { $msg="Vous ne pouvez pas vous désactiver."; $msgType="warn"; }
    else {
        $cur=(int)$pdo->query("SELECT active FROM users WHERE id=$id")->fetchColumn();
        $pdo->prepare("UPDATE users SET active=? WHERE id=?")->execute([1-$cur,$id]);
        $msg="Statut modifié.";
    }
}
if (isset($_GET["delete"])) {
    $id=(int)$_GET["delete"];
    if ($id===(int)$_SESSION["user"]["id"]) { $msg="Impossible de supprimer votre propre compte."; $msgType="warn"; }
    else {
        $pdo->prepare("DELETE FROM user_logins WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $msg="Compte supprimé.";
    }
}
$users = $pdo->query("SELECT id,username,fullname,role,active,created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<?php include "_nav.php"; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
  <div class="page-title" style="margin-bottom:0">Utilisateurs</div>
  <button onclick="document.getElementById('modalCreate').style.display='flex'"
    style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer">
    + Nouvel utilisateur
  </button>
</div>

<?php if($msg): ?>
<div style="background:<?php echo $msgType==='ok'?'#052e16':($msgType==='warn'?'#451a03':'#450a0a'); ?>;border:1px solid <?php echo $msgType==='ok'?'#166534':($msgType==='warn'?'#92400e':'#991b1b'); ?>;color:<?php echo $msgType==='ok'?'#86efac':($msgType==='warn'?'#fcd34d':'#fca5a5'); ?>;padding:12px 16px;border-radius:8px;font-size:.875rem;margin-bottom:20px">
  <?php echo $msg; ?>
</div>
<?php endif; ?>

<div class="card" style="overflow:hidden">
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <?php foreach(["Utilisateur","Identifiant","Rôle","Statut","Créé le","Actions"] as $h): ?>
          <th style="padding:12px 16px;text-align:left;color:var(--muted);font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap"><?php echo $h; ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): $isMe=(int)$u["id"]===(int)$_SESSION["user"]["id"]; ?>
        <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='rgba(99,102,241,.04)'" onmouseout="this.style.background=''">
          <td style="padding:14px 16px">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:36px;height:36px;border-radius:50%;background:<?php echo $u['role']==='ADMIN'?'rgba(239,68,68,.15)':'rgba(99,102,241,.15)'; ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:<?php echo $u['role']==='ADMIN'?'#fca5a5':'#a5b4fc'; ?>;flex-shrink:0">
                <?php echo strtoupper(mb_substr($u['fullname'],0,1)); ?>
              </div>
              <div>
                <div style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($u["fullname"]); ?></div>
                <?php if($isMe): ?><div style="font-size:.72rem;color:var(--muted)">Vous</div><?php endif; ?>
              </div>
            </div>
          </td>
          <td style="padding:14px 16px"><code style="background:var(--bg);padding:3px 8px;border-radius:5px;font-size:.8rem;color:var(--text)"><?php echo htmlspecialchars($u["username"]); ?></code></td>
          <td style="padding:14px 16px">
            <span style="padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;<?php echo $u['role']==='ADMIN'?'background:rgba(239,68,68,.12);color:#fca5a5':'background:rgba(99,102,241,.12);color:#a5b4fc'; ?>">
              <?php echo $u["role"]; ?>
            </span>
          </td>
          <td style="padding:14px 16px">
            <span style="padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;<?php echo $u['active']?'background:rgba(16,185,129,.12);color:#6ee7b7':'background:rgba(107,114,128,.12);color:#9ca3af'; ?>">
              <?php echo $u["active"]?"Actif":"Inactif"; ?>
            </span>
          </td>
          <td style="padding:14px 16px;color:var(--muted);font-size:.82rem;white-space:nowrap"><?php echo $u["created_at"]?date("d/m/Y",strtotime($u["created_at"])):"—"; ?></td>
          <td style="padding:14px 16px">
            <div style="display:flex;gap:6px">
              <button onclick="openEdit(<?php echo htmlspecialchars(json_encode(['id'=>$u['id'],'fullname'=>$u['fullname'],'role'=>$u['role']])); ?>)"
                style="padding:6px 12px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);border-radius:6px;font-size:.78rem;font-weight:600;color:#a5b4fc;cursor:pointer">
                ✏️
              </button>
              <?php if(!$isMe): ?>
              <a href="?toggle=<?php echo $u['id']; ?>"
                style="padding:6px 12px;background:<?php echo $u['active']?'rgba(245,158,11,.1)':'rgba(16,185,129,.1)'; ?>;border:1px solid <?php echo $u['active']?'rgba(245,158,11,.2)':'rgba(16,185,129,.2)'; ?>;border-radius:6px;font-size:.78rem;font-weight:600;color:<?php echo $u['active']?'#fcd34d':'#6ee7b7'; ?>;text-decoration:none">
                <?php echo $u['active']?'⏸':'▶'; ?>
              </a>
              <a href="?delete=<?php echo $u['id']; ?>" onclick="return confirm('Supprimer ce compte ?')"
                style="padding:6px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:6px;font-size:.78rem;font-weight:600;color:#fca5a5;text-decoration:none">
                🗑
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal créer -->
<div id="modalCreate" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:420px;margin:16px">
    <div style="font-weight:800;font-size:1.05rem;margin-bottom:20px;color:var(--text)">👤 Nouvel utilisateur</div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <?php foreach([['fullname','Nom complet','text'],['username','Identifiant','text'],['password','Mot de passe','password']] as $f): ?>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:6px"><?php echo $f[1]; ?></label>
        <input type="<?php echo $f[2]; ?>" name="<?php echo $f[0]; ?>" required
          style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none">
      </div>
      <?php endforeach; ?>
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:6px">Rôle</label>
        <select name="role" style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none">
          <option value="FORMATEUR">Formateur</option>
          <option value="ADMIN">Administrateur</option>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('modalCreate').style.display='none'"
          style="flex:1;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:.88rem;color:var(--muted);cursor:pointer">Annuler</button>
        <button type="submit" style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal éditer -->
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:420px;margin:16px">
    <div style="font-weight:800;font-size:1.05rem;margin-bottom:20px;color:var(--text)">✏️ Modifier le compte</div>
    <form method="post">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="eId">
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:6px">Nom complet</label>
        <input type="text" name="fullname" id="eFullname" required
          style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none">
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:6px">Nouveau mot de passe <span style="font-weight:400;color:var(--muted)">(laisser vide = inchangé)</span></label>
        <input type="password" name="password"
          style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none">
      </div>
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:6px">Rôle</label>
        <select name="role" id="eRole" style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.9rem;outline:none">
          <option value="FORMATEUR">Formateur</option>
          <option value="ADMIN">Administrateur</option>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('modalEdit').style.display='none'"
          style="flex:1;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;font-weight:600;font-size:.88rem;color:var(--muted);cursor:pointer">Annuler</button>
        <button type="submit" style="flex:1;padding:10px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(u){
  document.getElementById('eId').value=u.id;
  document.getElementById('eFullname').value=u.fullname;
  document.getElementById('eRole').value=u.role;
  document.getElementById('modalEdit').style.display='flex';
}
</script>
<?php include "_nav_end.php"; ?>
