<?php
session_start();
require_once __DIR__ . '/admin/db.php';

// Ajouter les colonnes manquantes si nécessaire (idempotent)
try { $pdo->exec("ALTER TABLE viewers ADD COLUMN IF NOT EXISTS poste VARCHAR(255) NOT NULL DEFAULT ''"); } catch(Exception $e) {}

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

// Charger l'arborescence organisation depuis la DB
$orgData = [];
$postes  = [];
try {
    $dirs = $pdo->query("SELECT * FROM org_directions ORDER BY type DESC, nom")->fetchAll(PDO::FETCH_ASSOC);
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
    $postes = $pdo->query("SELECT nom FROM org_postes ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Helper : connexion directe (utilisateur déjà enregistré)
function autoLogin(array $existing, string $redirect): never {
    global $pdo;
    $pdo->prepare("UPDATE viewers SET last_seen=NOW() WHERE id=?")->execute([$existing['id']]);
    session_regenerate_id(true);
    $_SESSION['viewer'] = [
        'id'        => $existing['id'],
        'login'     => $existing['windows_login'],
        'fullname'  => $existing['fullname']  ?? '',
        'poste'     => $existing['poste']     ?? '',
        'direction' => $existing['direction'] ?? '',
        'service'   => $existing['service']   ?? '',
        'agence'    => $existing['agence']    ?? '',
    ];
    try {
        $pdo->prepare("INSERT INTO viewer_sessions (viewer_id,ip_address,user_agent) VALUES (?,?,?)")
            ->execute([$existing['id'], $_SERVER['REMOTE_ADDR']??'', substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
    } catch(Exception $e) {}
    header("Location: $redirect");
    exit;
}

// SSO : connexion automatique si l'utilisateur existe
if ($ssoLogin) {
    $stmt = $pdo->prepare("SELECT * FROM viewers WHERE windows_login = ?");
    $stmt->execute([$ssoLogin]);
    if ($row = $stmt->fetch()) { autoLogin($row, $redirect); }
}

$preLogin = $ssoLogin ?? ($_SESSION['pending_viewer_login'] ?? '');
$step     = empty($preLogin) ? 'login' : 'profile';
$error    = '';

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
            $stmt = $pdo->prepare("SELECT * FROM viewers WHERE windows_login = ?");
            $stmt->execute([$login]);
            if ($row = $stmt->fetch()) {
                autoLogin($row, $redirect);      // Utilisateur connu → connexion directe
            }
            $_SESSION['pending_viewer_login'] = $login;
            $preLogin = $login;
            $step     = 'profile';
        }
    }

    if ($action === 'set_profile') {
        $login     = $ssoLogin ?? ($_SESSION['pending_viewer_login'] ?? trim($_POST['login_hidden'] ?? ''));
        $fullname  = trim($_POST['fullname']  ?? '');
        $poste     = trim($_POST['poste']     ?? '');
        $direction   = trim($_POST['direction']   ?? '');
        $service     = trim($_POST['service']     ?? '');
        $agence      = trim($_POST['agence']      ?? '');
        $isDirecteur = !empty($_POST['is_directeur']);

        // Directeur : pas de service ni d'agence
        if ($isDirecteur) { $service = ''; $agence = ''; }

        if (empty($login)) {
            $error = "Session expirée. Recommencez la connexion.";
            $step  = 'login';
        } elseif (empty($direction)) {
            $error = "Veuillez sélectionner votre direction.";
            $step  = 'profile'; $preLogin = $login;
        } elseif (!$isDirecteur && empty($service) && empty($agence)) {
            $error = "Veuillez sélectionner votre service ou votre agence.";
            $step  = 'profile'; $preLogin = $login;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM viewers WHERE windows_login = ?");
            $stmt->execute([$login]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->prepare("UPDATE viewers SET fullname=?,poste=?,direction=?,service=?,agence=?,last_seen=NOW() WHERE windows_login=?")
                    ->execute([$fullname, $poste, $direction, $service, $agence, $login]);
                $vid = $existing['id'];
            } else {
                $pdo->prepare("INSERT INTO viewers (windows_login,fullname,poste,direction,service,agence) VALUES (?,?,?,?,?,?)")
                    ->execute([$login, $fullname, $poste, $direction, $service, $agence]);
                $vid = (int)$pdo->lastInsertId();
            }

            session_regenerate_id(true);
            $_SESSION['viewer'] = [
                'id'        => $vid,
                'login'     => $login,
                'fullname'  => $fullname,
                'poste'     => $poste,
                'direction' => $direction,
                'service'   => $service,
                'agence'    => $agence,
            ];
            try {
                $pdo->prepare("INSERT INTO viewer_sessions (viewer_id,ip_address,user_agent) VALUES (?,?,?)")
                    ->execute([$vid, $_SERVER['REMOTE_ADDR']??'', substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
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
$hasPostes    = !empty($postes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Accès – MucoAcadémie</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{min-height:100vh;background:#faf8ff;font-family:"Plus Jakarta Sans",system-ui,sans-serif;
  display:flex;flex-direction:column;color:#131b2e;}

.navbar{height:60px;background:#ffffff;display:flex;align-items:center;
  justify-content:space-between;padding:0 5%;border-bottom:1px solid #c4c5d7;
  position:sticky;top:0;z-index:10;box-shadow:0 1px 8px rgba(0,0,0,.04);}
.navbar-brand{display:flex;align-items:center;gap:10px;color:#131b2e;font-weight:800;
  font-size:1.1rem;text-decoration:none;}
.navbar-brand img{background:#fff;border-radius:7px;padding:2px;border:1px solid #e2e7ff;}
.navbar-brand-text{display:flex;flex-direction:column;line-height:1;}
.navbar-brand-text strong{color:#131b2e;font-size:1rem;}
.navbar-brand-text span{font-size:.65rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#0037b0;margin-top:2px;}

.page{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;}

.login-card{background:#ffffff;border:1px solid #c4c5d7;border-radius:16px;
  padding:40px 36px;width:100%;max-width:480px;
  box-shadow:0 4px 24px rgba(0,55,176,.06);}

.card-logo{display:flex;justify-content:center;margin-bottom:20px;}
.card-logo img{width:80px;height:80px;object-fit:contain;}

.login-card h1{color:#131b2e;font-size:1.4rem;font-weight:800;margin-bottom:6px;}
.login-card .subtitle{color:#434655;font-size:.88rem;margin-bottom:26px;line-height:1.6;}
.subtitle strong{color:#131b2e;}

.step-indicator{display:flex;gap:8px;margin-bottom:26px;}
.step{flex:1;height:3px;border-radius:2px;background:#e2e7ff;transition:background .3s;}
.step.done{background:#0037b0;}

.form-group{margin-bottom:16px;}
label{display:block;color:#434655;font-size:.76rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;margin-bottom:7px;}
.opt-label{color:#747686;font-weight:400;font-size:.72rem;text-transform:none;letter-spacing:0;margin-left:4px;}
.input-wrap{position:relative;}
.input-wrap>svg:first-child{position:absolute;left:12px;top:50%;transform:translateY(-50%);
  width:16px;height:16px;stroke:#747686;fill:none;stroke-width:2;pointer-events:none;}
input[type=text],select{width:100%;padding:11px 14px 11px 38px;background:#ffffff;
  border:1px solid #c4c5d7;border-radius:8px;color:#131b2e;font-size:.9rem;
  font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;
  appearance:none;-webkit-appearance:none;}
input[type=text]:focus,select:focus{border-color:#0037b0;box-shadow:0 0 0 3px rgba(0,55,176,.12);}
input::placeholder{color:#747686;}
select:disabled{opacity:.45;cursor:not-allowed;background:#f2f3ff;}
.select-arrow{position:absolute;right:12px;top:50%;transform:translateY(-50%);
  pointer-events:none;color:#747686;}
.select-arrow svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;}
.field-note{color:#747686;font-size:.76rem;margin-top:5px;line-height:1.4;}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:440px){.form-row{grid-template-columns:1fr;}}

.btn-submit{width:100%;padding:13px;background:#0037b0;color:#fff;border:none;
  border-radius:8px;font-weight:700;font-size:.95rem;font-family:inherit;cursor:pointer;
  transition:background .2s,transform .1s;margin-top:8px;}
.btn-submit:hover{background:#1d4ed8;}
.btn-submit:active{transform:scale(.98);}

.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#ba1a1a;
  padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:18px;
  display:flex;align-items:center;gap:8px;}
.alert-err svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}

.login-hint{margin-top:24px;padding-top:20px;border-top:1px solid #e2e7ff;
  color:#747686;font-size:.8rem;text-align:center;line-height:1.6;}
.login-hint strong{color:#434655;}

.section-sep{border:none;border-top:1px solid #e2e7ff;margin:20px 0 16px;}
.section-label{font-size:.72rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:#747686;margin-bottom:12px;}
.checkbox-label{display:flex;align-items:center;gap:10px;cursor:pointer;
  background:#f0f4ff;border:1px solid #c4c5d7;border-radius:8px;padding:11px 14px;
  font-size:.88rem;color:#131b2e;font-weight:600;transition:background .2s;}
.checkbox-label:hover{background:#e2e7ff;}
.checkbox-label input[type=checkbox]{width:17px;height:17px;accent-color:#0037b0;cursor:pointer;flex-shrink:0;}
</style>
</head>
<body>

<header class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="images/mucoacademie.png" width="32" height="32" alt="Logo">
    <div class="navbar-brand-text">
      <strong>MucoAcadémie</strong>
      <span>Espace Formation</span>
    </div>
  </a>
</header>

<div class="page">
  <div class="login-card">

    <?php if ($step === 'login'): ?>

      <div class="card-logo"><img src="images/mucoacademie.png" alt="MucoAcadémie"></div>
      <h1>Bienvenue</h1>
      <p class="subtitle">Identifiez-vous pour accéder aux formations MucoAcadémie.</p>

      <div class="step-indicator">
        <div class="step done"></div>
        <div class="step"></div>
      </div>

      <?php if ($error): ?>
        <div class="alert-err">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
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

      <div class="card-logo"><img src="images/mucoacademie.png" alt="MucoAcadémie"></div>
      <h1><?php echo $isFirstVisit ? 'Première connexion' : 'Votre profil'; ?></h1>
      <p class="subtitle">
        Identifiant : <strong><?php echo htmlspecialchars($preLogin); ?></strong><br>
        Renseignez vos informations pour accéder aux formations.
      </p>

      <div class="step-indicator">
        <div class="step done"></div>
        <div class="step done"></div>
      </div>

      <?php if ($error): ?>
        <div class="alert-err">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" id="profileForm">
        <input type="hidden" name="action"       value="set_profile">
        <input type="hidden" name="redirect"     value="<?php echo htmlspecialchars($redirect); ?>">
        <input type="hidden" name="login_hidden" value="<?php echo htmlspecialchars($preLogin); ?>">

        <!-- Identité -->
        <div class="section-label">Identité</div>
        <div class="form-row">
          <div class="form-group">
            <label for="fullname">Nom complet <span class="opt-label">(optionnel)</span></label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <input type="text" id="fullname" name="fullname" placeholder="Ex : Jean MUKENDI">
            </div>
          </div>
          <div class="form-group">
            <label for="poste">Poste / Fonction <span class="opt-label">(optionnel)</span></label>
            <div class="input-wrap">
              <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              <?php if ($hasPostes): ?>
                <select id="poste" name="poste">
                  <option value="">— Sélectionner —</option>
                  <?php foreach ($postes as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="select-arrow"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></span>
              <?php else: ?>
                <input type="text" id="poste" name="poste" placeholder="Ex : Chargé de clientèle">
              <?php endif; ?>
            </div>
          </div>
        </div>

        <hr class="section-sep">
        <div class="section-label">Affectation organisationnelle</div>

        <!-- Direction -->
        <div class="form-group">
          <label for="direction">Direction <span style="color:#ba1a1a">*</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <?php if ($hasOrgData): ?>
              <select id="direction" name="direction" required onchange="onDirectionChange(this)">
                <option value="">— Sélectionner une direction —</option>
                <?php foreach ($orgData as $d): ?>
                  <option value="<?php echo htmlspecialchars($d['nom']); ?>" data-type="<?php echo $d['type']; ?>">
                    <?php echo htmlspecialchars($d['nom']); ?>
                    <?php echo $d['type'] === 'regionale' ? ' · Régionale' : ' · Centrale'; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="select-arrow"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></span>
            <?php else: ?>
              <input type="text" id="direction" name="direction" placeholder="Ex : DRH, DOSI, Direction Commerciale…" required>
            <?php endif; ?>
          </div>
        </div>

        <!-- Directeur -->
        <div class="form-group" id="directeur-group" style="display:none;">
          <label class="checkbox-label">
            <input type="checkbox" id="is_directeur" name="is_directeur" value="1" onchange="onDirecteurChange(this)">
            <span>Je suis directeur de cette direction</span>
          </label>
        </div>

        <!-- Service -->
        <div class="form-group" id="service-group">
          <label for="service">Service <span style="color:#ba1a1a">*</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <?php if ($hasOrgData): ?>
              <select id="service" name="service" required disabled>
                <option value="">— Sélectionner d'abord une direction —</option>
              </select>
              <span class="select-arrow"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></span>
            <?php else: ?>
              <input type="text" id="service" name="service" placeholder="Ex : Service Formation…" required>
            <?php endif; ?>
          </div>
        </div>

        <!-- Agence (uniquement directions régionales) -->
        <div class="form-group" id="agence-group" style="display:none;">
          <label for="agence">Agence <span class="opt-label">(optionnel si vous êtes au siège de la direction)</span></label>
          <div class="input-wrap">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <select id="agence" name="agence" onchange="onAgenceChange(this)">
              <option value="">— Sélectionner une agence —</option>
            </select>
            <span class="select-arrow"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></span>
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
  var svcGroup  = document.getElementById('service-group');
  var ageSel    = document.getElementById('agence');
  var ageGroup  = document.getElementById('agence-group');
  var dirGroup  = document.getElementById('directeur-group');
  var dirChk    = document.getElementById('is_directeur');

  // Reset
  svcSel.innerHTML = '<option value="">— Sélectionner un service —</option>';
  svcSel.disabled  = !dir;
  svcSel.value     = '';
  dirChk.checked   = false;

  if (dir) {
    dir.services.forEach(function(s){
      var opt = document.createElement('option');
      opt.value = s; opt.textContent = s;
      svcSel.appendChild(opt);
    });

    // Toujours afficher la checkbox "Directeur" dès qu'une direction est choisie
    dirGroup.style.display = '';

    if (dir.type === 'regionale' && dir.agences.length > 0) {
      ageSel.innerHTML = '<option value="">— Sélectionner une agence —</option>';
      dir.agences.forEach(function(a){
        var opt = document.createElement('option');
        opt.value = a; opt.textContent = a;
        ageSel.appendChild(opt);
      });
      ageSel.required        = false;
      ageGroup.style.display = '';
      svcGroup.style.display = '';
      svcSel.required        = true;
    } else {
      ageSel.required        = false;
      ageSel.innerHTML       = '<option value=""></option>';
      ageGroup.style.display = 'none';
      svcGroup.style.display = '';
      svcSel.required        = true;
    }
  } else {
    dirGroup.style.display = 'none';
    ageSel.required        = false;
    ageSel.innerHTML       = '';
    ageGroup.style.display = 'none';
    svcGroup.style.display = 'none';
    svcSel.required        = false;
  }
}

function onDirecteurChange(chk) {
  var svcGroup = document.getElementById('service-group');
  var svcSel   = document.getElementById('service');
  var ageGroup = document.getElementById('agence-group');
  var ageSel   = document.getElementById('agence');

  if (chk.checked) {
    // Directeur : masquer service et agence
    svcGroup.style.display = 'none';
    svcSel.required        = false;
    svcSel.value           = '';
    ageGroup.style.display = 'none';
    ageSel.required        = false;
    ageSel.value           = '';
  } else {
    // Pas directeur : rétablir le comportement normal
    onDirectionChange(document.getElementById('direction'));
  }
}

function onAgenceChange(sel) {
  var svcGroup = document.getElementById('service-group');
  var svcSel   = document.getElementById('service');
  if (sel.value) {
    // Agence choisie → service masqué et non requis
    svcGroup.style.display = 'none';
    svcSel.required        = false;
    svcSel.value           = '';
  } else {
    // Pas d'agence → service affiché et requis
    svcGroup.style.display = '';
    svcSel.required        = true;
  }
}
</script>
<?php endif; ?>
</body>
</html>
