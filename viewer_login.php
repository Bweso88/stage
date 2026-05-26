<?php
session_start();
require_once __DIR__ . '/admin/db.php';

// Déjà connecté
if (!empty($_SESSION['viewer']['id'])) {
    header("Location: " . (isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php'));
    exit;
}

function safe_redirect(string $r): string {
    return preg_match('/^[a-zA-Z0-9\/_\-.?=&%+]+$/', $r) ? $r : 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php');

// Détection SSO Windows
$ssoLogin = null;
foreach (['REMOTE_USER', 'AUTH_USER', 'HTTP_X_REMOTE_USER'] as $k) {
    if (!empty($_SERVER[$k])) {
        $l = $_SERVER[$k];
        if (strpos($l, '\\') !== false) $l = explode('\\', $l)[1];
        elseif (strpos($l, '@') !== false) $l = explode('@', $l)[0];
        $ssoLogin = strtolower(trim($l));
        break;
    }
}

$preLogin = $ssoLogin ?? ($_GET['sso'] ?? ($_SESSION['pending_viewer_login'] ?? ''));
$step     = empty($preLogin) ? 'login' : 'profile';
$error    = '';

// Charger l'arborescence organisation depuis la DB
$orgData = [];
try {
    $dirs = $pdo->query("SELECT * FROM org_directions ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dirs as $d) {
        $svcs = $pdo->prepare("SELECT nom FROM org_services WHERE direction_id = ? ORDER BY nom");
        $svcs->execute([$d['id']]);
        $ages = $pdo->prepare("SELECT nom FROM org_agences WHERE direction_id = ? ORDER BY nom");
        $ages->execute([$d['id']]);
        $orgData[] = [
            'nom'      => $d['nom'],
            'type'     => $d['type'],
            'services' => array_column($svcs->fetchAll(PDO::FETCH_ASSOC), 'nom'),
            'agences'  => array_column($ages->fetchAll(PDO::FETCH_ASSOC), 'nom'),
        ];
    }
} catch (Exception $e) {}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_login') {
        $login = strtolower(trim($_POST['windows_login'] ?? ''));
        if (empty($login)) {
            $error = "Veuillez entrer votre identifiant Windows.";
        } elseif (!preg_match('/^[a-z0-9._@\-]+$/', $login)) {
            $error = "Identifiant invalide (lettres, chiffres, . _ @ - uniquement).";
        } else {
            $_SESSION['pending_viewer_login'] = $login;
            $preLogin = $login;
            $step = 'profile';
        }
    }

    if ($action === 'set_profile') {
        $login     = $ssoLogin ?? ($_SESSION['pending_viewer_login'] ?? trim($_POST['login_hidden'] ?? ''));
        $fullname  = trim($_POST['fullname']   ?? '');
        $direction = trim($_POST['direction']  ?? '');
        $service   = trim($_POST['service']    ?? '');
        $agence    = trim($_POST['agence']     ?? '');

        $dirType = '';
        foreach ($orgData as $d) {
            if ($d['nom'] === $direction) { $dirType = $d['type']; break; }
        }

        if (empty($login)) {
            $error = "Session expirée. Recommencez la connexion.";
            $step  = 'login';
        } elseif (empty($direction)) {
            $error = "Veuillez sélectionner votre direction.";
            $step  = 'profile'; $preLogin = $login;
        } elseif (empty($service)) {
            $error = "Veuillez sélectionner votre service.";
            $step  = 'profile'; $preLogin = $login;
        } elseif ($dirType === 'regionale' && !empty($orgData) && empty($agence)) {
            $error = "Veuillez sélectionner votre agence.";
            $step  = 'profile'; $preLogin = $login;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM viewers WHERE windows_login = ?");
            $stmt->execute([$login]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->prepare("UPDATE viewers SET fullname=?, direction=?, service=?, agence=?, last_seen=NOW()
                               WHERE windows_login=?")
                    ->execute([$fullname, $direction, $service, $agence, $login]);
                $vid = $existing['id'];
            } else {
                $pdo->prepare("INSERT INTO viewers (windows_login, fullname, direction, service, agence)
                               VALUES (?,?,?,?,?)")
                    ->execute([$login, $fullname, $direction, $service, $agence]);
                $vid = (int)$pdo->lastInsertId();
            }

            $_SESSION['viewer'] = [
                'id'        => $vid,
                'login'     => $login,
                'fullname'  => $fullname,
                'direction' => $direction,
                'service'   => $service,
                'agence'    => $agence,
            ];
            try {
                $pdo->prepare("INSERT INTO viewer_sessions (viewer_id, ip_address, user_agent)
                               VALUES (?,?,?)")
                    ->execute([$vid, $_SERVER['REMOTE_ADDR'] ?? '',
                               substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
                $_SESSION['viewer_session_logged'] = true;
            } catch (Exception $e) {}

            unset($_SESSION['pending_viewer_login']);
            header("Location: $redirect");
            exit;
        }
    }
}

$isFirstVisit = ($step === 'profile' && !empty($preLogin));
$hasOrgData   = !empty($orgData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Accès – MucoAcadémie</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{min-height:100vh;background:#0f1117;font-family:"Segoe UI",system-ui,sans-serif;
  display:flex;flex-direction:column;}

.navbar{height:60px;background:#1c1d1f;display:flex;align-items:center;
  justify-content:space-between;padding:0 5%;border-bottom:1px solid #2d2f31;
  position:sticky;top:0;z-index:10;}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;
  font-size:1.1rem;text-decoration:none;}
.navbar-brand img{background:#fff;border-radius:7px;padding:2px;}
.navbar-brand span{color:#e8192c;}

.page{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;}

.login-card{background:#1c1d1f;border:1px solid #2d2f31;border-radius:14px;
  padding:40px 36px;width:100%;max-width:440px;
  box-shadow:0 24px 64px rgba(0,0,0,.5);}

.card-icon{width:56px;height:56px;border-radius:14px;background:rgba(232,25,44,.12);
  border:1px solid rgba(232,25,44,.25);display:flex;align-items:center;
  justify-content:center;font-size:1.6rem;margin-bottom:20px;}

.login-card h1{color:#fff;font-size:1.4rem;font-weight:800;margin-bottom:6px;}
.login-card .subtitle{color:#6b7280;font-size:.88rem;margin-bottom:28px;line-height:1.5;}

.step-indicator{display:flex;gap:8px;margin-bottom:28px;}
.step{flex:1;height:3px;border-radius:2px;background:#2d2f31;transition:background .3s;}
.step.done{background:#e8192c;}

.form-group{margin-bottom:18px;}
label{display:block;color:#9ca3af;font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;margin-bottom:7px;}
.input-wrap{position:relative;}
.input-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);
  width:16px;height:16px;stroke:#4b5563;fill:none;stroke-width:2;pointer-events:none;}
input[type=text],select{width:100%;padding:11px 14px 11px 38px;background:#0f1117;
  border:1px solid #2d2f31;border-radius:8px;color:#f3f4f6;font-size:.9rem;
  outline:none;transition:border-color .2s;appearance:none;-webkit-appearance:none;}
input[type=text]:focus,select:focus{border-color:#1a2e6e;box-shadow:0 0 0 3px rgba(26,46,110,.15);}
input::placeholder{color:#374151;}
select:disabled{opacity:.45;cursor:not-allowed;}
select option{background:#1c1d1f;color:#f3f4f6;}
.select-arrow{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  pointer-events:none;color:#4b5563;}

.field-note{color:#4b5563;font-size:.78rem;margin-top:5px;}

.btn-submit{width:100%;padding:13px;background:#1a2e6e;color:#fff;border:none;
  border-radius:8px;font-weight:700;font-size:.95rem;cursor:pointer;
  transition:background .2s,transform .1s;margin-top:4px;}
.btn-submit:hover{background:#142457;}
.btn-submit:active{transform:scale(.98);}

.alert-err{background:#450a0a;border:1px solid #7f1d1d;color:#fca5a5;
  padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;}

.login-hint{margin-top:24px;padding-top:20px;border-top:1px solid #1f2937;
  color:#374151;font-size:.8rem;text-align:center;}
.login-hint strong{color:#6b7280;}
</style>
</head>
<body>

<header class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="images/mucoacademie.png" width="32" height="32" alt="Logo">
    Muco<span>Académie</span>
  </a>
</header>

<div class="page">
  <div class="login-card">

    <?php if ($step === 'login'): ?>

      <div class="card-icon">🔐</div>
      <h1>Bienvenue</h1>
      <p class="subtitle">Identifiez-vous pour accéder aux formations MucoAcadémie.</p>

      <div class="step-indicator">
        <div class="step done"></div>
        <div class="step"></div>
      </div>

      <?php if ($error): ?>
        <div class="alert-err">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action"   value="set_login">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

        <div class="form-group">
          <label for="windows_login">Identifiant Windows</label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="windows_login" name="windows_login"
                   placeholder="prénom.nom ou matricule" autofocus required>
          </div>
          <p class="field-note">Entrez votre login de session Windows (sans le domaine).</p>
        </div>

        <button type="submit" class="btn-submit">Continuer →</button>
      </form>

    <?php else: ?>

      <div class="card-icon">👤</div>
      <h1><?php echo $isFirstVisit ? 'Première connexion' : 'Votre profil'; ?></h1>
      <p class="subtitle">
        Identifiant : <strong style="color:#e8eaf0"><?php echo htmlspecialchars($preLogin); ?></strong><br>
        Renseignez votre direction et service pour accéder aux formations.
      </p>

      <div class="step-indicator">
        <div class="step done"></div>
        <div class="step done"></div>
      </div>

      <?php if ($error): ?>
        <div class="alert-err">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" id="profileForm">
        <input type="hidden" name="action"       value="set_profile">
        <input type="hidden" name="redirect"     value="<?php echo htmlspecialchars($redirect); ?>">
        <input type="hidden" name="login_hidden" value="<?php echo htmlspecialchars($preLogin); ?>">

        <div class="form-group">
          <label for="fullname">Nom complet <span style="color:#4b5563;font-weight:400;text-transform:none;">(optionnel)</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="fullname" name="fullname" placeholder="Ex : Jean MUKENDI">
          </div>
        </div>

        <div class="form-group">
          <label for="direction">Direction <span style="color:#e8192c">*</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <?php if ($hasOrgData): ?>
              <select id="direction" name="direction" required onchange="onDirectionChange(this)">
                <option value="">— Sélectionner une direction —</option>
                <?php foreach ($orgData as $d): ?>
                  <option value="<?php echo htmlspecialchars($d['nom']); ?>"
                          data-type="<?php echo $d['type']; ?>">
                    <?php echo htmlspecialchars($d['nom']); ?>
                    <?php echo $d['type'] === 'regionale' ? ' (Régionale)' : ' (Centrale)'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="select-arrow">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            <?php else: ?>
              <input type="text" id="direction" name="direction"
                     placeholder="Ex : DRH, DOSI, Direction Commerciale…" required>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label for="service">Service <span style="color:#e8192c">*</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <?php if ($hasOrgData): ?>
              <select id="service" name="service" required disabled>
                <option value="">— Sélectionner d'abord une direction —</option>
              </select>
              <span class="select-arrow">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
            <?php else: ?>
              <input type="text" id="service" name="service"
                     placeholder="Ex : Service Formation, Agence Centre…" required>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group" id="agence-group" style="display:none;">
          <label for="agence">Agence <span style="color:#e8192c">*</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <select id="agence" name="agence">
              <option value="">— Sélectionner une agence —</option>
            </select>
            <span class="select-arrow">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
          </div>
        </div>

        <button type="submit" class="btn-submit">Accéder aux formations →</button>
      </form>

    <?php endif; ?>

    <p class="login-hint">
      Cet accès est réservé aux collaborateurs MUCODEC.<br>
      <strong>Problème de connexion ?</strong> Contactez le Service Formation.
    </p>

  </div>
</div>

<?php if ($step === 'profile' && $hasOrgData): ?>
<script>
var orgData = <?php echo json_encode($orgData, JSON_UNESCAPED_UNICODE); ?>;

function onDirectionChange(sel) {
  var dirNom    = sel.value;
  var dir       = orgData.find(function(d){ return d.nom === dirNom; });
  var svcSel    = document.getElementById('service');
  var ageSel    = document.getElementById('agence');
  var ageGroup  = document.getElementById('agence-group');

  svcSel.innerHTML = '<option value="">— Sélectionner un service —</option>';
  svcSel.disabled  = !dir;

  if (dir) {
    dir.services.forEach(function(s){
      var opt = document.createElement('option');
      opt.value = s; opt.textContent = s;
      svcSel.appendChild(opt);
    });

    if (dir.type === 'regionale' && dir.agences.length > 0) {
      ageSel.innerHTML = '<option value="">— Sélectionner une agence —</option>';
      dir.agences.forEach(function(a){
        var opt = document.createElement('option');
        opt.value = a; opt.textContent = a;
        ageSel.appendChild(opt);
      });
      ageSel.required = true;
      ageGroup.style.display = '';
    } else {
      ageSel.required = false;
      ageSel.innerHTML = '';
      ageGroup.style.display = 'none';
    }
  } else {
    ageSel.required = false;
    ageSel.innerHTML = '';
    ageGroup.style.display = 'none';
  }
}
</script>
<?php endif; ?>
</body>
</html>
