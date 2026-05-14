<?php
/**
 * FormaPro – Script d'installation & mise à jour
 * Accès : http://votre-domaine/setup.php
 * SUPPRIMEZ ce fichier après l'installation !
 */

define('SETUP_VERSION', '1.0');
define('LOCK_FILE', __DIR__ . '/.setup_done');

session_start();

/* ── Sécurité : clé d'accès ─────────────────────────────────────────── */
$ACCESS_KEY = 'formasetup2025';   // ← CHANGEZ cette clé avant de déployer

if (!isset($_SESSION['setup_auth'])) {
    if (isset($_POST['setup_key']) && $_POST['setup_key'] === $ACCESS_KEY) {
        $_SESSION['setup_auth'] = true;
    } elseif (file_exists(LOCK_FILE) && !isset($_GET['update'])) {
        die('<div style="font-family:sans-serif;text-align:center;padding:80px;color:#dc2626;">
              <h2>🔒 Installation déjà effectuée</h2>
              <p>Supprimez ce fichier du serveur pour des raisons de sécurité.</p>
              <p><a href="index.php" style="color:#6366f1">← Retour au site</a></p></div>');
    } else {
        showKeyForm(); exit;
    }
}

/* ── Étape courante ──────────────────────────────────────────────────── */
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

/* ── Traitement des étapes POST ─────────────────────────────────────── */
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) { $result = stepDB();      redirect(3); }
    if ($step === 3) { $result = stepTables();  redirect(4); }
    if ($step === 4) { $result = stepAdmin();   redirect(5); }
    if ($step === 5) { $result = stepFolders(); redirect(6); }
}

/* ═══════════════════════════════════════════════════════════════════════
   FONCTIONS DE CHAQUE ÉTAPE
   ═══════════════════════════════════════════════════════════════════════ */

function stepDB(): array {
    $h = $_POST['db_host'] ?? 'localhost';
    $d = $_POST['db_name'] ?? 'formation';
    $u = $_POST['db_user'] ?? 'root';
    $p = $_POST['db_pass'] ?? '';

    try {
        /* Créer la BDD si elle n'existe pas */
        $pdo = new PDO("mysql:host=$h;charset=utf8mb4", $u, $p,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$d` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        /* Écrire le fichier db.php */
        $tpl = <<<PHP
<?php
\$host = "$h";
\$db   = "$d";
\$user = "$u";
\$pass = "$p";
try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$db;charset=utf8mb4",\$user,\$pass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException \$e) {
    die("Erreur de connexion à la base de données");
}
PHP;
        file_put_contents(__DIR__ . '/admin/db.php', $tpl);
        $_SESSION['db'] = compact('h','d','u','p');
        return ['ok' => true, 'msg' => "Connexion réussie. Base <strong>$d</strong> prête."];
    } catch (PDOException $e) {
        return ['ok' => false, 'msg' => "Erreur : " . htmlspecialchars($e->getMessage())];
    }
}

function stepTables(): array {
    $db = $_SESSION['db'] ?? null;
    if (!$db) return ['ok'=>false,'msg'=>'Session expirée, recommencez.'];
    try {
        $pdo = new PDO("mysql:host={$db['h']};dbname={$db['d']};charset=utf8mb4",
            $db['u'], $db['p'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(60)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            fullname   VARCHAR(120) NOT NULL,
            role       ENUM('ADMIN','FORMATEUR') NOT NULL DEFAULT 'FORMATEUR',
            active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_logins (
            id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id  INT UNSIGNED NOT NULL,
            login_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        return ['ok'=>true,'msg'=>'Tables <strong>users</strong> et <strong>user_logins</strong> créées (ou déjà existantes).'];
    } catch (PDOException $e) {
        return ['ok'=>false,'msg'=>'Erreur SQL : '.htmlspecialchars($e->getMessage())];
    }
}

function stepAdmin(): array {
    $db = $_SESSION['db'] ?? null;
    if (!$db) return ['ok'=>false,'msg'=>'Session expirée.'];

    $username = trim($_POST['admin_user'] ?? 'admin');
    $fullname = trim($_POST['admin_name'] ?? 'Administrateur');
    $password = $_POST['admin_pass'] ?? '';
    $confirm  = $_POST['admin_confirm'] ?? '';

    if (strlen($password) < 8) return ['ok'=>false,'msg'=>'Le mot de passe doit contenir au moins 8 caractères.'];
    if ($password !== $confirm)  return ['ok'=>false,'msg'=>'Les mots de passe ne correspondent pas.'];

    try {
        $pdo  = new PDO("mysql:host={$db['h']};dbname={$db['d']};charset=utf8mb4",
            $db['u'], $db['p'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
        $check->execute([$username]);
        if ($check->fetch()) {
            /* Mise à jour si l'utilisateur existe déjà */
            $pdo->prepare("UPDATE users SET password=?,fullname=?,role='ADMIN',active=1 WHERE username=?")
                ->execute([$hash,$fullname,$username]);
            $msg = "Compte <strong>$username</strong> mis à jour.";
        } else {
            $pdo->prepare("INSERT INTO users (username,password,fullname,role,active) VALUES (?,?,?,'ADMIN',1)")
                ->execute([$username,$hash,$fullname]);
            $msg = "Compte admin <strong>$username</strong> créé.";
        }
        return ['ok'=>true,'msg'=>$msg];
    } catch (PDOException $e) {
        return ['ok'=>false,'msg'=>'Erreur : '.htmlspecialchars($e->getMessage())];
    }
}

function stepFolders(): array {
    $dirs = [
        __DIR__.'/videos'  => '755',
        __DIR__.'/views'   => '755',
        __DIR__.'/images'  => '755',
    ];
    $lines = [];
    foreach ($dirs as $dir => $perm) {
        if (!is_dir($dir)) mkdir($dir, octdec($perm), true);
        @chmod($dir, octdec($perm));
        $ok = is_writable($dir);
        $lines[] = "<li>".($ok?'✅':'⚠️')." <code>".basename($dir)."/</code> — ".($ok?'accessible en écriture':'vérifiez les permissions manuellement')."</li>";
    }
    /* Créer le fichier verrou */
    file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
    return ['ok'=>true,'msg'=>'<ul style="margin:0;padding-left:18px">'.implode('',$lines).'</ul>'];
}

function redirect(int $s): void {
    header("Location: setup.php?step=$s"); exit;
}

/* ═══════════════════════════════════════════════════════════════════════
   AFFICHAGE
   ═══════════════════════════════════════════════════════════════════════ */

function showKeyForm(): void { ?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>FormaPro Setup</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#0f1117;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:"Segoe UI",sans-serif;}
.box{background:#1c1d1f;border:1px solid #2d2f31;border-radius:14px;padding:44px 40px;width:100%;max-width:380px;text-align:center;}
.logo{font-size:2.5rem;margin-bottom:16px;}
h1{color:#fff;font-size:1.3rem;font-weight:800;margin-bottom:6px;}
p{color:#6b7280;font-size:.88rem;margin-bottom:24px;}
input{width:100%;padding:11px 14px;border-radius:8px;border:1px solid #374151;background:#111;
  color:#fff;font-size:.9rem;margin-bottom:14px;outline:none;}
input:focus{border-color:#6366f1;}
button{width:100%;padding:11px;background:#6366f1;color:#fff;border:none;border-radius:8px;
  font-weight:700;font-size:.9rem;cursor:pointer;}
button:hover{background:#4f46e5;}
</style></head><body>
<div class="box">
  <div class="logo">🔐</div>
  <h1>FormaPro Setup</h1>
  <p>Saisissez la clé d'accès pour démarrer l'installation.</p>
  <form method="post">
    <input type="password" name="setup_key" placeholder="Clé d'accès…" autofocus required>
    <button type="submit">Accéder →</button>
  </form>
</div>
</body></html>
<?php }

/* ── Vérifications système ───────────────────────────────────────────── */
function sysChecks(): array {
    return [
        ['label'=>'PHP ≥ 8.0',      'ok'=> version_compare(PHP_VERSION,'8.0','>='), 'val'=>PHP_VERSION],
        ['label'=>'Extension PDO',   'ok'=> extension_loaded('pdo'),    'val'=> extension_loaded('pdo')    ? 'OK':'Manquante'],
        ['label'=>'Extension PDO MySQL','ok'=>extension_loaded('pdo_mysql'),'val'=>extension_loaded('pdo_mysql')?'OK':'Manquante'],
        ['label'=>'Extension mbstring','ok'=>extension_loaded('mbstring'),'val'=>extension_loaded('mbstring')?'OK':'Manquante'],
        ['label'=>'Dossier admin/ accessible','ok'=>is_writable(__DIR__.'/admin'),'val'=>is_writable(__DIR__.'/admin')?'Oui':'Non'],
        ['label'=>'upload_max_filesize','ok'=>true,'val'=>ini_get('upload_max_filesize')],
        ['label'=>'post_max_size',     'ok'=>true,'val'=>ini_get('post_max_size')],
    ];
}

$checks    = sysChecks();
$checksOk  = !array_filter($checks, fn($c) => !$c['ok']);
$steps = [
    1 => 'Vérifications',
    2 => 'Base de données',
    3 => 'Tables SQL',
    4 => 'Compte admin',
    5 => 'Dossiers',
    6 => 'Terminé',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>FormaPro – Setup v<?php echo SETUP_VERSION; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#0f1117;font-family:"Segoe UI",system-ui,sans-serif;color:#f3f4f6;min-height:100vh;
  display:flex;flex-direction:column;align-items:center;padding:40px 16px;}
.container{width:100%;max-width:640px;}
.header{text-align:center;margin-bottom:36px;}
.header-logo{font-size:2.4rem;margin-bottom:10px;}
.header h1{font-size:1.5rem;font-weight:800;color:#fff;}
.header h1 span{color:#6366f1;}
.header p{color:#6b7280;font-size:.88rem;margin-top:6px;}

/* STEPPER */
.stepper{display:flex;align-items:center;margin-bottom:32px;gap:0;}
.step-item{flex:1;text-align:center;position:relative;}
.step-item:not(:last-child)::after{content:"";position:absolute;top:16px;left:50%;right:-50%;
  height:2px;background:#2d2f31;z-index:0;}
.step-item.done::after,.step-item.active::after{background:#6366f1;}
.step-circle{width:32px;height:32px;border-radius:50%;border:2px solid #2d2f31;background:#1c1d1f;
  display:flex;align-items:center;justify-content:center;margin:0 auto 6px;font-size:.78rem;
  font-weight:700;position:relative;z-index:1;color:#6b7280;transition:all .3s;}
.step-item.done .step-circle{border-color:#6366f1;background:#6366f1;color:#fff;}
.step-item.active .step-circle{border-color:#6366f1;color:#6366f1;background:#1c1d1f;}
.step-label{font-size:.7rem;color:#4b5563;font-weight:600;}
.step-item.active .step-label{color:#a5b4fc;}
.step-item.done .step-label{color:#6366f1;}

/* CARD */
.card{background:#1c1d1f;border:1px solid #2d2f31;border-radius:14px;padding:32px;}
.card-title{font-size:1.1rem;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.card-title .step-num{background:#6366f1;color:#fff;width:28px;height:28px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;flex-shrink:0;}

/* FORM */
label{display:block;font-size:.82rem;font-weight:600;color:#9ca3af;margin-bottom:6px;margin-top:16px;}
label:first-of-type{margin-top:0;}
input[type=text],input[type=password],input[type=email]{width:100%;padding:10px 14px;
  border-radius:8px;border:1px solid #374151;background:#111;color:#fff;font-size:.9rem;outline:none;}
input:focus{border-color:#6366f1;}
.input-hint{font-size:.75rem;color:#4b5563;margin-top:4px;}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* BUTTONS */
.btn-primary{background:#6366f1;color:#fff;border:none;padding:11px 28px;border-radius:8px;
  font-weight:700;font-size:.9rem;cursor:pointer;width:100%;margin-top:20px;transition:background .2s;}
.btn-primary:hover{background:#4f46e5;}
.btn-primary:disabled{background:#374151;cursor:not-allowed;}

/* ALERTS */
.alert{padding:12px 16px;border-radius:8px;font-size:.875rem;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start;}
.alert-ok{background:#052e16;border:1px solid #166534;color:#86efac;}
.alert-err{background:#450a0a;border:1px solid #991b1b;color:#fca5a5;}
.alert-warn{background:#451a03;border:1px solid #92400e;color:#fcd34d;}
.alert-info{background:#0c1a3d;border:1px solid #1e40af;color:#93c5fd;}

/* CHECKS TABLE */
.checks{width:100%;border-collapse:collapse;}
.checks td{padding:9px 12px;border-bottom:1px solid #2d2f31;font-size:.87rem;}
.checks tr:last-child td{border:none;}
.checks .label{color:#d1d5db;}
.checks .val{color:#9ca3af;text-align:right;}
.badge-ok{color:#4ade80;font-weight:600;}
.badge-err{color:#f87171;font-weight:600;}

/* FINISH */
.finish-icon{font-size:4rem;text-align:center;margin-bottom:16px;}
.finish-box{background:#052e16;border:1px solid #166534;border-radius:10px;padding:20px;margin-bottom:16px;}
.finish-box h3{color:#86efac;font-size:.95rem;font-weight:700;margin-bottom:10px;}
.finish-box ul{list-style:none;padding:0;}
.finish-box li{color:#6ee7b7;font-size:.85rem;padding:4px 0;display:flex;align-items:center;gap:8px;}
.warn-box{background:#450a0a;border:1px solid #991b1b;border-radius:10px;padding:16px;
  color:#fca5a5;font-size:.85rem;display:flex;gap:10px;align-items:flex-start;}
</style>
</head>
<body>

<div class="container">
  <!-- HEADER -->
  <div class="header">
    <div class="header-logo">🚀</div>
    <h1>Forma<span>Pro</span> — Installation</h1>
    <p>Version <?php echo SETUP_VERSION; ?> &nbsp;·&nbsp; Suivez les étapes pour configurer l'application</p>
  </div>

  <!-- STEPPER -->
  <div class="stepper">
    <?php foreach ($steps as $n => $label): ?>
      <div class="step-item <?php
        echo $n < $step  ? 'done'   :
            ($n === $step ? 'active' : '');
      ?>">
        <div class="step-circle"><?php echo $n < $step ? '✓' : $n; ?></div>
        <div class="step-label"><?php echo $label; ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">

  <?php
  /* ── ÉTAPE 1 : VÉRIFICATIONS ──────────────────────────────────────── */
  if ($step === 1):
  ?>
    <div class="card-title">
      <span class="step-num">1</span> Vérifications système
    </div>

    <?php if (!$checksOk): ?>
      <div class="alert alert-err">⚠️ Certaines vérifications ont échoué. Corrigez les erreurs avant de continuer.</div>
    <?php else: ?>
      <div class="alert alert-ok">✅ Tout est en ordre. Vous pouvez continuer.</div>
    <?php endif; ?>

    <table class="checks">
      <?php foreach ($checks as $c): ?>
      <tr>
        <td class="label"><?php echo $c['label']; ?></td>
        <td class="val">
          <span class="<?php echo $c['ok'] ? 'badge-ok' : 'badge-err'; ?>">
            <?php echo htmlspecialchars($c['val']); ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <a href="setup.php?step=2">
      <button class="btn-primary" <?php echo !$checksOk ? 'disabled' : ''; ?>>
        Continuer →
      </button>
    </a>

  <?php
  /* ── ÉTAPE 2 : BASE DE DONNÉES ────────────────────────────────────── */
  elseif ($step === 2):
    if ($result && !$result['ok']): ?>
      <div class="alert alert-err">❌ <?php echo $result['msg']; ?></div>
    <?php endif; ?>
    <div class="card-title"><span class="step-num">2</span> Connexion à la base de données</div>
    <form method="post">
      <div class="row2">
        <div>
          <label>Hôte MySQL</label>
          <input type="text" name="db_host" value="localhost" required>
        </div>
        <div>
          <label>Nom de la base</label>
          <input type="text" name="db_name" value="formation" required>
        </div>
      </div>
      <div class="row2">
        <div>
          <label>Utilisateur</label>
          <input type="text" name="db_user" value="root" required>
        </div>
        <div>
          <label>Mot de passe</label>
          <input type="password" name="db_pass" placeholder="(vide si aucun)">
        </div>
      </div>
      <p class="input-hint" style="margin-top:10px">La base de données sera créée automatiquement si elle n'existe pas.</p>
      <button class="btn-primary" type="submit">Tester &amp; continuer →</button>
    </form>

  <?php
  /* ── ÉTAPE 3 : TABLES ─────────────────────────────────────────────── */
  elseif ($step === 3):
    if ($result && !$result['ok']): ?>
      <div class="alert alert-err">❌ <?php echo $result['msg']; ?></div>
    <?php elseif ($result): ?>
      <div class="alert alert-ok">✅ <?php echo $result['msg']; ?></div>
    <?php endif; ?>
    <div class="card-title"><span class="step-num">3</span> Création des tables</div>
    <div class="alert alert-info" style="margin-bottom:16px">
      ℹ️ Les tables SQL seront créées si elles n'existent pas encore. Les données existantes ne sont pas effacées.
    </div>
    <p style="color:#9ca3af;font-size:.88rem;margin-bottom:4px">Tables à créer :</p>
    <ul style="color:#6b7280;font-size:.85rem;padding-left:20px;line-height:1.9">
      <li><code style="color:#a5b4fc">users</code> — comptes utilisateurs (admin, formateurs)</li>
      <li><code style="color:#a5b4fc">user_logins</code> — journal des connexions</li>
    </ul>
    <form method="post">
      <button class="btn-primary" type="submit">Créer les tables →</button>
    </form>

  <?php
  /* ── ÉTAPE 4 : COMPTE ADMIN ───────────────────────────────────────── */
  elseif ($step === 4):
    if ($result && !$result['ok']): ?>
      <div class="alert alert-err">❌ <?php echo $result['msg']; ?></div>
    <?php elseif ($result): ?>
      <div class="alert alert-ok">✅ <?php echo $result['msg']; ?></div>
    <?php endif; ?>
    <div class="card-title"><span class="step-num">4</span> Compte administrateur</div>
    <div class="alert alert-warn">⚠️ Si le compte existe déjà, son mot de passe sera mis à jour.</div>
    <form method="post">
      <div class="row2">
        <div>
          <label>Identifiant</label>
          <input type="text" name="admin_user" value="admin" required autocomplete="off">
        </div>
        <div>
          <label>Nom complet</label>
          <input type="text" name="admin_name" value="Administrateur" required>
        </div>
      </div>
      <label>Mot de passe <span style="color:#4b5563">(min. 8 caractères)</span></label>
      <input type="password" name="admin_pass" required autocomplete="new-password">
      <label>Confirmer le mot de passe</label>
      <input type="password" name="admin_confirm" required autocomplete="new-password">
      <button class="btn-primary" type="submit">Créer le compte →</button>
    </form>

  <?php
  /* ── ÉTAPE 5 : DOSSIERS ───────────────────────────────────────────── */
  elseif ($step === 5):
    if ($result && !$result['ok']): ?>
      <div class="alert alert-err">❌ <?php echo $result['msg']; ?></div>
    <?php elseif ($result): ?>
      <div class="alert alert-ok">✅ <?php echo $result['msg']; ?></div>
    <?php endif; ?>
    <div class="card-title"><span class="step-num">5</span> Dossiers &amp; permissions</div>
    <p style="color:#9ca3af;font-size:.88rem;margin-bottom:14px">
      Les dossiers nécessaires au fonctionnement seront créés et les permissions vérifiées.
    </p>
    <ul style="color:#6b7280;font-size:.85rem;padding-left:20px;line-height:1.9">
      <li><code style="color:#a5b4fc">videos/</code> — stockage des vidéos MP4</li>
      <li><code style="color:#a5b4fc">views/</code> — compteurs de vues</li>
      <li><code style="color:#a5b4fc">images/</code> — ressources graphiques</li>
    </ul>
    <form method="post">
      <button class="btn-primary" type="submit">Configurer les dossiers →</button>
    </form>

  <?php
  /* ── ÉTAPE 6 : TERMINÉ ────────────────────────────────────────────── */
  elseif ($step === 6): ?>
    <div class="finish-icon">🎉</div>
    <div class="finish-box">
      <h3>Installation terminée avec succès !</h3>
      <ul>
        <li>✅ Base de données configurée</li>
        <li>✅ Tables SQL créées</li>
        <li>✅ Compte administrateur prêt</li>
        <li>✅ Dossiers configurés</li>
      </ul>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
      <a href="index.php" style="text-decoration:none">
        <button class="btn-primary" style="margin-top:0">🌐 Voir le site</button>
      </a>
      <a href="admin/login.php" style="text-decoration:none">
        <button class="btn-primary" style="margin-top:0;background:#059669">🔐 Administration</button>
      </a>
    </div>

    <div class="warn-box">
      <span style="font-size:1.3rem">⚠️</span>
      <div>
        <strong style="display:block;margin-bottom:4px">Action requise – Supprimez ce fichier !</strong>
        Supprimez <code>setup.php</code> du serveur immédiatement pour éviter tout accès non autorisé.
        <br><br>
        <code style="background:#111;padding:4px 8px;border-radius:4px;display:inline-block;margin-top:4px">
          rm /var/www/html/setup.php
        </code>
      </div>
    </div>

  <?php endif; ?>

  </div><!-- /card -->
</div><!-- /container -->
</body>
</html>
