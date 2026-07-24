<?php
/**
 * Stage - Page publique (offres + connexion stagiaire)
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    if (isStagiaire()) redirect('espace-stagiaire.php');
    else redirect('backoffice.php');
}

$error = '';

// Connexion stagiaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT u.*, s.id as stagiaire_id FROM utilisateurs u LEFT JOIN stagiaires s ON s.utilisateur_id = u.id WHERE u.email = ? AND u.role = ? AND u.actif = 1');
        $stmt->execute([$email, 'stagiaire']);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id'          => $user['id'],
                'nom'         => $user['nom'],
                'prenom'      => $user['prenom'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'stagiaire_id'=> $user['stagiaire_id'],
            ];
            redirect('espace-stagiaire.php');
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}

// Load open offers
$pdo = getPDO();
$offres = $pdo->query('SELECT o.*, d.libelle as direction_libelle, dm.libelle as domaine_libelle FROM offres_stage o LEFT JOIN directions d ON d.id = o.direction_id LEFT JOIN domaines dm ON dm.id = o.domaine_id WHERE o.statut = \'ouverte\' ORDER BY o.created_at DESC')->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stage – Offres de stage</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Montserrat',sans-serif;background:#f4f5f7;color:#2c3e50;}
:root{--navy:#1b2a6b;--red:#e8001c;--gray-bg:#f4f5f7;}

/* Navbar */
.navbar{background:var(--navy);padding:0 40px;height:64px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 12px rgba(0,0,0,.15);}
.logo{font-size:24px;font-weight:800;color:#fff;text-decoration:none;letter-spacing:1px;}
.logo span{color:var(--red);}
.nav-links{display:flex;gap:20px;align-items:center;}
.nav-links a{color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;font-weight:500;transition:color .2s;}
.nav-links a:hover{color:#fff;}
.nav-btn{background:var(--red);color:#fff!important;padding:8px 18px;border-radius:6px;font-weight:700!important;}
.nav-btn:hover{background:#c0001a!important;}

/* Hero */
.hero{background:linear-gradient(135deg,var(--navy) 0%,#2d3f8c 100%);color:#fff;padding:70px 40px;text-align:center;}
.hero h1{font-size:42px;font-weight:800;margin-bottom:12px;}
.hero h1 span{color:var(--red);}
.hero p{font-size:16px;opacity:.85;max-width:600px;margin:0 auto 28px;}
.hero-search{display:flex;gap:10px;max-width:500px;margin:0 auto;}
.hero-search input{flex:1;padding:12px 16px;border-radius:8px;border:none;font-size:14px;font-family:inherit;}
.hero-search button{padding:12px 24px;background:var(--red);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-family:inherit;}

/* Layout */
.container{max-width:1200px;margin:0 auto;padding:0 24px;}
.main-grid{display:grid;grid-template-columns:1fr 380px;gap:32px;padding:40px 0;}

/* Offers */
.section-title{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.offers-grid{display:flex;flex-direction:column;gap:16px;}
.offer-card{background:#fff;border-radius:10px;padding:22px;border:1px solid #e0e3ed;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .2s,box-shadow .2s;}
.offer-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.1);}
.offer-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;}
.offer-title{font-size:15px;font-weight:700;color:var(--navy);}
.offer-ref{font-size:11px;color:#9ca3af;}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;}
.badge-success{background:rgba(16,185,129,.12);color:#059669;}
.badge-info{background:rgba(59,130,246,.12);color:#2563eb;}
.badge-primary{background:rgba(27,42,107,.1);color:var(--navy);}
.offer-meta{display:flex;flex-wrap:wrap;gap:10px;font-size:12px;color:#6b7280;margin:8px 0;}
.offer-meta span{display:flex;align-items:center;gap:4px;}
.offer-desc{font-size:13px;color:#374151;line-height:1.6;margin-bottom:12px;}
.offer-footer{display:flex;align-items:center;justify-content:space-between;}
.offer-places{font-size:12px;color:#6b7280;}
.btn-apply{background:var(--navy);color:#fff;padding:8px 18px;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none;transition:background .2s;}
.btn-apply:hover{background:#132050;}

/* Login sidebar */
.login-card{background:#fff;border-radius:12px;padding:28px;border:1px solid #e0e3ed;box-shadow:0 4px 20px rgba(0,0,0,.08);position:sticky;top:20px;}
.login-card h2{font-size:18px;font-weight:800;color:var(--navy);margin-bottom:6px;}
.login-card p{font-size:13px;color:#6b7280;margin-bottom:20px;}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.form-group label{font-size:12px;font-weight:600;color:var(--navy);}
.form-control{width:100%;padding:10px 13px;border:1.5px solid #e0e3ed;border-radius:7px;font-size:13px;font-family:inherit;transition:border-color .2s;}
.form-control:focus{outline:none;border-color:var(--navy);}
.btn-login{width:100%;padding:11px;background:var(--navy);color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;}
.btn-login:hover{background:#132050;}
.login-divider{text-align:center;color:#9ca3af;font-size:12px;margin:16px 0;position:relative;}
.login-divider::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:#e0e3ed;}
.login-divider span{background:#fff;padding:0 10px;position:relative;}
.btn-register{display:block;text-align:center;padding:10px;background:var(--red);color:#fff;text-decoration:none;border-radius:7px;font-size:13px;font-weight:700;transition:background .2s;}
.btn-register:hover{background:#c0001a;}
.admin-link{text-align:center;margin-top:14px;font-size:12px;color:#9ca3af;}
.admin-link a{color:var(--navy);text-decoration:none;font-weight:600;}
.alert{padding:12px 16px;border-radius:7px;font-size:13px;margin-bottom:16px;}
.alert-error{background:rgba(232,0,28,.08);border:1px solid rgba(232,0,28,.2);color:#991b1b;}
.empty-offers{text-align:center;padding:60px 20px;color:#9ca3af;}
.empty-offers .icon{font-size:52px;margin-bottom:12px;}

/* Footer */
.footer{background:var(--navy);color:rgba(255,255,255,.5);padding:24px 40px;text-align:center;font-size:12px;margin-top:40px;}

@media(max-width:900px){
  .main-grid{grid-template-columns:1fr;}
  .hero h1{font-size:28px;}
  .hero{padding:40px 20px;}
  .navbar{padding:0 20px;}
}
</style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="logo">
    <?php if (file_exists(__DIR__ . '/assets/img/logo.gif')): ?>
    <img src="assets/img/logo.gif" alt="MUCODEC" style="height:44px;width:auto;background:#fff;border-radius:5px;padding:3px 8px;">
    <?php else: ?>
    Stage
    <?php endif ?>
  </a>
  <div class="nav-links">
    <a href="inscription.php" class="nav-btn">S'inscrire</a>
    <a href="admin.php">Backoffice</a>
  </div>
</nav>

<section class="hero">
  <div class="container">
    <h1>Trouvez votre stage chez <span>nous</span></h1>
    <p>D&eacute;couvrez nos offres de stage disponibles et postulez directement en ligne. Rejoignez une &eacute;quipe dynamique et d&eacute;veloppez vos comp&eacute;tences.</p>
  </div>
</section>

<div class="container">
  <div class="main-grid">
    <!-- Offers list -->
    <div>
      <h2 class="section-title">📢 Offres ouvertes (<?= count($offres) ?>)</h2>
      <?php if (empty($offres)): ?>
      <div class="empty-offers">
        <div class="icon">📭</div>
        <p>Aucune offre disponible pour le moment.<br>Revenez bient&ocirc;t ou postulez de fa&ccedil;on spontan&eacute;e.</p>
        <a href="inscription.php" style="display:inline-block;margin-top:16px;background:var(--navy);color:#fff;padding:10px 24px;border-radius:7px;text-decoration:none;font-weight:700;">Candidature spontan&eacute;e</a>
      </div>
      <?php else: ?>
      <div class="offers-grid">
        <?php foreach ($offres as $o): ?>
        <div class="offer-card">
          <div class="offer-header">
            <div>
              <div class="offer-title"><?= h($o['titre']) ?></div>
              <div class="offer-ref"><?= h($o['reference']) ?></div>
            </div>
            <span class="badge badge-success">Ouverte</span>
          </div>
          <div class="offer-meta">
            <?php if ($o['direction_libelle']): ?>
            <span>🏢 <?= h($o['direction_libelle']) ?></span>
            <?php endif; ?>
            <?php if ($o['domaine_libelle']): ?>
            <span>🎯 <?= h($o['domaine_libelle']) ?></span>
            <?php endif; ?>
            <span>📚 <?= h(strtoupper($o['niveau_minimum'])) ?> min</span>
            <?php if ($o['date_limite']): ?>
            <span>📅 Limite: <?= h(date('d/m/Y', strtotime($o['date_limite']))) ?></span>
            <?php endif; ?>
          </div>
          <p class="offer-desc"><?= h(substr($o['description'] ?? '', 0, 200)) ?><?= strlen($o['description'] ?? '') > 200 ? '...' : '' ?></p>
          <div class="offer-footer">
            <span class="offer-places">🪑 <?= (int)$o['nb_places'] ?> place(s)</span>
            <a href="inscription.php?offre_id=<?= $o['id'] ?>" class="btn-apply">Postuler →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Login sidebar -->
    <div>
      <div class="login-card">
        <h2>Espace stagiaire</h2>
        <p>Connectez-vous pour suivre vos candidatures et g&eacute;rer votre stage.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="action" value="login">
          <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.com" required value="<?= h($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <div class="login-divider"><span>ou</span></div>
        <a href="inscription.php" class="btn-register">Cr&eacute;er un compte stagiaire</a>

        <div class="admin-link">
          Vous &ecirc;tes admin ? <a href="admin.php">Connexion backoffice</a>
        </div>
      </div>
    </div>
  </div>
</div>

<footer class="footer">
  &copy; <?= date('Y') ?> Stage &mdash; Plateforme de gestion des stages
</footer>

</body>
</html>
