<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}
require "db.php";

$error    = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && (int)$user["active"] === 0) {
        $error = "Ce compte est désactivé. Contactez l'administrateur.";
    } elseif ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["user"] = [
            "id"       => $user["id"],
            "username" => $user["username"],
            "fullname" => $user["fullname"],
            "role"     => $user["role"]
        ];
        try {
            $pdo->prepare("INSERT INTO user_logins (user_id) VALUES (?)")->execute([$user["id"]]);
        } catch (Exception $e) { /* table optionnelle */ }
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion – MucoAcadémie</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{
  min-height:100vh;
  background:#0f1117;
  font-family:"Segoe UI",system-ui,sans-serif;
  display:flex;
  flex-direction:column;
}

/* ── NAVBAR ── */
.navbar{
  height:60px;background:#1c1d1f;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 5%;border-bottom:1px solid #2d2f31;
  position:sticky;top:0;z-index:10;
}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:1.15rem;text-decoration:none;}
.navbar-brand img{background:#fff;border-radius:7px;padding:2px;}
.navbar-brand span{color:#e8192c;}
.back-link{color:#6b7280;font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:6px;transition:color .2s;}
.back-link:hover{color:#fff;}

/* ── LAYOUT ── */
.page{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 60px);}
@media(max-width:768px){.page{grid-template-columns:1fr;}.left-panel{display:none;}}

/* ── LEFT PANEL ── */
.left-panel{
  background:linear-gradient(145deg,#0d1b4b 0%,#1c1d1f 60%,#0f1117 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:60px 48px;position:relative;overflow:hidden;
}
.left-panel::before{
  content:"";position:absolute;top:-100px;right:-100px;
  width:400px;height:400px;border-radius:50%;
  background:radial-gradient(circle,rgba(232,25,44,.2) 0%,transparent 70%);
}
.left-panel::after{
  content:"";position:absolute;bottom:-80px;left:-60px;
  width:300px;height:300px;border-radius:50%;
  background:radial-gradient(circle,rgba(14,165,233,.15) 0%,transparent 70%);
}
.left-inner{position:relative;z-index:1;text-align:center;max-width:340px;}
.left-badge{
  display:inline-block;background:rgba(232,25,44,.15);border:1px solid rgba(232,25,44,.3);
  color:#ff8a94;padding:5px 16px;border-radius:20px;font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.08em;margin-bottom:28px;
}
.left-inner h2{color:#fff;font-size:1.9rem;font-weight:800;line-height:1.2;margin-bottom:14px;}
.left-inner h2 span{color:#e8192c;}
.left-inner p{color:#6b7280;font-size:.9rem;line-height:1.6;margin-bottom:36px;}
.features{display:flex;flex-direction:column;gap:14px;text-align:left;}
.feature{display:flex;align-items:flex-start;gap:12px;}
.feature-icon{
  width:36px;height:36px;border-radius:8px;background:rgba(232,25,44,.15);
  display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;margin-top:1px;
}
.feature-text strong{color:#e5e7eb;font-size:.88rem;display:block;margin-bottom:2px;}
.feature-text span{color:#4b5563;font-size:.8rem;}

/* ── RIGHT PANEL ── */
.right-panel{
  background:#0f1117;
  display:flex;align-items:center;justify-content:center;
  padding:40px 32px;
}
.login-box{width:100%;max-width:400px;}
.login-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px;}
.login-logo img{background:#fff;border-radius:10px;padding:3px;}
.login-logo-text{font-size:1.2rem;font-weight:800;color:#fff;}
.login-logo-text span{color:#e8192c;}
.login-box h1{color:#fff;font-size:1.5rem;font-weight:800;margin-bottom:6px;}
.login-box .subtitle{color:#6b7280;font-size:.88rem;margin-bottom:28px;}

/* ── FORM ── */
.form-group{margin-bottom:18px;}
label{display:block;color:#9ca3af;font-size:.82rem;font-weight:600;margin-bottom:7px;}
.input-wrap{position:relative;}
.input-wrap svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);
  width:16px;height:16px;stroke:#4b5563;fill:none;stroke-width:2;pointer-events:none;}
input[type=text],input[type=password]{
  width:100%;padding:12px 14px 12px 40px;
  background:#1c1d1f;border:1px solid #2d2f31;border-radius:9px;
  color:#f3f4f6;font-size:.92rem;outline:none;transition:border-color .2s,box-shadow .2s;
}
input:focus{border-color:#e8192c;box-shadow:0 0 0 3px rgba(232,25,44,.15);}
input::placeholder{color:#374151;}

.show-pass{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:#4b5563;padding:2px;
  transition:color .2s;
}
.show-pass:hover{color:#9ca3af;}

.btn-login{
  width:100%;padding:13px;background:#e8192c;color:#fff;border:none;
  border-radius:9px;font-weight:700;font-size:.95rem;cursor:pointer;
  transition:background .2s,transform .1s;margin-top:6px;
}
.btn-login:hover{background:#c0141f;}
.btn-login:active{transform:scale(.98);}

.alert-err{
  background:#450a0a;border:1px solid #7f1d1d;color:#fca5a5;
  padding:11px 14px;border-radius:8px;font-size:.85rem;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
}

.divider{border:none;border-top:1px solid #1f2937;margin:24px 0;}
.login-footer{text-align:center;color:#374151;font-size:.82rem;}
.login-footer a{color:#e8192c;font-weight:600;text-decoration:none;}
.login-footer a:hover{color:#ff6b78;}
</style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
  <a href="../index.php" class="navbar-brand">
    <img src="../images/MUCODEC.gif" width="34" height="34" alt="Logo">
    Muco<span>Académie</span>
  </a>
  <a href="../index.php" class="back-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    Retour au site
  </a>
</header>

<div class="page">

  <!-- PANNEAU GAUCHE -->
  <div class="left-panel">
    <div class="left-inner">
      <div class="left-badge">🔐 Espace Administration</div>
      <h2>Gérez vos <span>formations</span> en toute simplicité</h2>
      <p>Accédez au tableau de bord pour uploader des vidéos, créer des thèmes et administrer les utilisateurs.</p>
      <div class="features">
        <div class="feature">
          <div class="feature-icon">📤</div>
          <div class="feature-text">
            <strong>Upload de vidéos</strong>
            <span>Ajoutez vos tutoriels MP4 par thème</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-icon">📁</div>
          <div class="feature-text">
            <strong>Gestion des thèmes</strong>
            <span>Créez, renommez ou supprimez des catégories</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-icon">👤</div>
          <div class="feature-text">
            <strong>Utilisateurs</strong>
            <span>Gérez les accès et les rôles</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-icon">📊</div>
          <div class="feature-text">
            <strong>Statistiques</strong>
            <span>Suivez les vues et l'activité</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PANNEAU DROIT -->
  <div class="right-panel">
    <div class="login-box">

      <div class="login-logo">
        <img src="../images/MUCODEC.gif" width="42" height="42" alt="Logo">
        <div class="login-logo-text">Muco<span>Académie</span></div>
      </div>

      <h1>Connexion</h1>
      <p class="subtitle">Accédez à votre espace d'administration</p>

      <?php if ($error): ?>
        <div class="alert-err">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">

        <div class="form-group">
          <label for="username">Identifiant</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="username" name="username"
                   value="<?php echo htmlspecialchars($username); ?>"
                   placeholder="Votre identifiant" autofocus required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Mot de passe</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="password" name="password"
                   placeholder="Votre mot de passe" required>
            <button type="button" class="show-pass" onclick="togglePass()" title="Afficher/masquer">
              <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">Se connecter</button>
      </form>

      <hr class="divider">
      <p class="login-footer">
        Mot de passe oublié ? <a href="<?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http'; ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo ltrim(dirname(dirname($_SERVER['PHP_SELF'])),'/'); ?>/setup.php">Réinitialiser via setup.php</a>
      </p>

    </div>
  </div>

</div><!-- /page -->

<script>
function togglePass() {
    var inp  = document.getElementById('password');
    var icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>

</body>
</html>
