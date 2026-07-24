<?php
/**
 * Stage - Connexion backoffice (administrateurs / habilités / superviseurs / directeurs)
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn() && isAdmin()) {
    redirect('backoffice.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ? AND role != ? AND actif = 1');
        $stmt->execute([$email, 'stagiaire']);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id'     => $user['id'],
                'nom'    => $user['nom'],
                'prenom' => $user['prenom'],
                'email'  => $user['email'],
                'role'   => $user['role'],
            ];
            redirect('backoffice.php');
        } else {
            $error = 'Email ou mot de passe incorrect, ou acc&egrave;s refus&eacute;.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stage – Connexion Backoffice</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Montserrat',sans-serif;background:linear-gradient(135deg,#1b2a6b 0%,#2d3f8c 60%,#1b2a6b 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.wrap{width:100%;max-width:420px;padding:20px;}
.card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.25);}
.card-header{background:#1b2a6b;padding:36px 32px;text-align:center;}
.logo{font-size:36px;font-weight:800;color:#fff;letter-spacing:2px;}
.logo span{color:#e8001c;}
.logo-sub{font-size:11px;color:rgba(255,255,255,.5);letter-spacing:3px;text-transform:uppercase;margin-top:4px;}
.card-body{padding:32px;}
.subtitle{text-align:center;font-size:14px;color:#6b7280;margin-bottom:24px;font-weight:500;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:12px;font-weight:700;color:#1b2a6b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
.form-control{width:100%;padding:12px 15px;border:2px solid #e0e3ed;border-radius:8px;font-size:14px;font-family:inherit;transition:all .2s;}
.form-control:focus{outline:none;border-color:#1b2a6b;box-shadow:0 0 0 3px rgba(27,42,107,.08);}
.btn-login{width:100%;padding:13px;background:#1b2a6b;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:8px;}
.btn-login:hover{background:#132050;}
.alert-error{background:rgba(232,0,28,.08);border:1px solid rgba(232,0,28,.25);color:#991b1b;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.footer-links{text-align:center;margin-top:20px;font-size:12px;color:#9ca3af;}
.footer-links a{color:#1b2a6b;text-decoration:none;font-weight:600;}
.footer-links a:hover{text-decoration:underline;}
.demo-info{background:#f0f4ff;border:1px solid #c7d2fe;border-radius:8px;padding:14px 16px;font-size:12px;color:#3730a3;margin-top:20px;}
.demo-info strong{display:block;margin-bottom:6px;font-size:13px;}
.demo-info div{font-family:monospace;background:#fff;border:1px solid #c7d2fe;border-radius:5px;padding:4px 8px;margin-top:4px;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="card-header">
      <?php if (file_exists(__DIR__ . '/assets/img/logo.gif')): ?>
      <div style="margin-bottom:12px">
        <img src="assets/img/logo.gif" alt="MUCODEC" style="height:70px;width:auto;background:#fff;border-radius:8px;padding:6px 12px;">
      </div>
      <?php else: ?>
      <div class="logo">Stage</div>
      <?php endif ?>
      <div class="logo-sub">Backoffice Administration</div>
    </div>
    <div class="card-body">
      <p class="subtitle">Connexion r&eacute;serv&eacute;e au personnel administratif</p>

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= $error ?></div>
      <?php endif; ?>

      <?php
      $flash = getFlash();
      if ($flash): ?>
      <div class="alert-error">⚠️ <?= h($flash['msg']) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="admin@stagia.org" required value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">Se connecter</button>
      </form>

      <div class="footer-links">
        <a href="index.php">← Retour aux offres de stage</a>
      </div>

      <div class="demo-info">
        <strong>Comptes de d&eacute;monstration (mot de passe: <code>password</code>)</strong>
        <div>admin@stagia.org — Administrateur</div>
        <div>m.diallo@stagia.org — Habilit&eacute;</div>
        <div>i.camara@stagia.org — Superviseur</div>
        <div>f.barry@stagia.org — Directeur</div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
