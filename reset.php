<?php
// Fichier temporaire – SUPPRIMEZ-LE après usage
// Accès : votre-site/reset.php?token=reset2025
if (!isset($_GET['token']) || $_GET['token'] !== 'reset2025') {
    http_response_code(403); die('Accès refusé.');
}
require __DIR__ . '/admin/db.php';
$msg = ''; $type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    if (strlen($pass) < 6) { $msg = 'Mot de passe trop court (min 6 cars).'; $type = 'err'; }
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = $pdo->prepare("UPDATE users SET password=?, active=1 WHERE username=?");
        $st->execute([$hash, $user]);
        if ($st->rowCount()) { $msg = "✅ Mot de passe de <strong>$user</strong> réinitialisé."; $type = 'ok'; }
        else {
            // Crée le compte s'il n'existe pas
            $pdo->prepare("INSERT INTO users (username,password,fullname,role,active) VALUES (?,?,'Administrateur','ADMIN',1)")
                ->execute([$user, $hash]);
            $msg = "✅ Compte <strong>$user</strong> créé avec succès."; $type = 'ok';
        }
    }
}
$users = $pdo->query("SELECT username, role, active FROM users ORDER BY id")->fetchAll();
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Reset mot de passe</title>
<style>*{box-sizing:border-box;margin:0;padding:0;}body{background:#0f1117;font-family:"Segoe UI",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.box{background:#1c1d1f;border:1px solid #2d2f31;border-radius:14px;padding:36px;width:100%;max-width:440px;}
h1{color:#fff;font-size:1.2rem;font-weight:800;margin-bottom:6px;}
p{color:#6b7280;font-size:.85rem;margin-bottom:22px;}
label{display:block;color:#9ca3af;font-size:.8rem;font-weight:600;margin-bottom:6px;margin-top:14px;}
input{width:100%;padding:10px 14px;background:#111;border:1px solid #374151;border-radius:8px;color:#fff;font-size:.9rem;outline:none;}
input:focus{border-color:#6366f1;}
button{width:100%;margin-top:18px;padding:12px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;}
button:hover{background:#4f46e5;}
.ok{background:#052e16;border:1px solid #166534;color:#86efac;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;}
.err{background:#450a0a;border:1px solid #991b1b;color:#fca5a5;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;}
hr{border:none;border-top:1px solid #2d2f31;margin:20px 0;}
.user-list{list-style:none;padding:0;}
.user-list li{color:#6b7280;font-size:.82rem;padding:5px 0;border-bottom:1px solid #1f2937;display:flex;justify-content:space-between;}
.warn{background:#451a03;border:1px solid #92400e;color:#fcd34d;padding:10px 14px;border-radius:8px;font-size:.8rem;margin-top:16px;}</style></head>
<body><div class="box">
<h1>🔑 Réinitialisation du mot de passe</h1>
<p>Fichier temporaire – supprimez-le après usage.</p>
<?php if($msg): ?><div class="<?php echo $type; ?>"><?php echo $msg; ?></div><?php endif; ?>
<form method="post">
  <label>Identifiant du compte</label>
  <input type="text" name="username" value="admin" required>
  <label>Nouveau mot de passe</label>
  <input type="password" name="password" placeholder="Minimum 6 caractères" required>
  <button type="submit">Réinitialiser</button>
</form>
<?php if(!empty($users)): ?>
<hr>
<p style="color:#6b7280;font-size:.8rem;margin-bottom:8px">Comptes existants :</p>
<ul class="user-list">
<?php foreach($users as $u): ?>
  <li><span><?php echo htmlspecialchars($u['username']); ?></span>
      <span><?php echo $u['role']; ?> — <?php echo $u['active']?'actif':'inactif'; ?></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<div class="warn">⚠️ Supprimez ce fichier après utilisation : <code>rm reset.php</code></div>
</div></body></html>
