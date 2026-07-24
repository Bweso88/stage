<?php
// includes/header.php
if (!defined('STAGIA_PAGE')) define('STAGIA_PAGE', '');
$currentPage = STAGIA_PAGE;
$user = $_SESSION['user'] ?? null;
$isAdminArea = ($user && $user['role'] !== 'stagiaire');

// Count unread notifications
$notifCount = 0;
if ($user) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND statut_lecture = ?');
        $stmt->execute([$user['id'], 'non_lu']);
        $notifCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
}

$flash = getFlash();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StagIA – <?= h(STAGIA_PAGE ?: 'Tableau de bord') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:15px}
body{font-family:'Montserrat',sans-serif;background:#f4f5f7;color:#2c3e50;display:flex;min-height:100vh}

/* ── Variables ── */
:root{
  --navy:#1b2a6b;
  --navy-dark:#132050;
  --navy-light:#2d3f8c;
  --red:#e8001c;
  --red-dark:#c0001a;
  --gray-bg:#f4f5f7;
  --gray-border:#e0e3ed;
  --gray-text:#6b7280;
  --white:#ffffff;
  --success:#10b981;
  --warning:#f59e0b;
  --info:#3b82f6;
  --sidebar-w:250px;
}

/* ── Sidebar ── */
.sidebar{
  width:var(--sidebar-w);
  background:var(--navy);
  min-height:100vh;
  position:fixed;
  left:0;top:0;
  display:flex;flex-direction:column;
  z-index:100;
  transition:transform .3s;
}
.sidebar-logo{
  padding:24px 20px 16px;
  border-bottom:1px solid rgba(255,255,255,.1);
}
.sidebar-logo a{
  display:flex;align-items:center;gap:10px;
  text-decoration:none;
}
.sidebar-logo .logo-text{
  font-size:22px;font-weight:800;color:#fff;letter-spacing:1px;
}
.sidebar-logo .logo-text span{color:var(--red);}
.sidebar-logo .logo-sub{font-size:10px;color:rgba(255,255,255,.5);letter-spacing:2px;text-transform:uppercase;}

.sidebar-nav{flex:1;overflow-y:auto;padding:10px 0;}
.nav-section{padding:16px 20px 6px;font-size:10px;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:1.5px;}
.nav-link{
  display:flex;align-items:center;gap:10px;
  padding:10px 20px;
  color:rgba(255,255,255,.75);
  text-decoration:none;
  font-size:13px;font-weight:500;
  transition:all .2s;
  border-left:3px solid transparent;
}
.nav-link:hover{background:rgba(255,255,255,.08);color:#fff;border-left-color:rgba(255,255,255,.3);}
.nav-link.active{background:rgba(255,255,255,.12);color:#fff;border-left-color:var(--red);font-weight:600;}
.nav-link .icon{width:18px;text-align:center;opacity:.8;}
.nav-link.active .icon{opacity:1;}

.sidebar-footer{
  padding:16px 20px;
  border-top:1px solid rgba(255,255,255,.15);
}
.sidebar-logout{
  display:flex;align-items:center;gap:8px;
  padding:10px 14px;border-radius:8px;
  background:rgba(232,0,28,.15);
  color:#ff6b7a;
  text-decoration:none;
  font-size:13px;font-weight:600;
  transition:all .2s;
}
.sidebar-logout:hover{background:var(--red);color:#fff;}

/* ── Main layout ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}

/* ── Topbar ── */
.topbar{
  background:#fff;
  border-bottom:1px solid var(--gray-border);
  padding:0 28px;
  height:60px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:90;
  box-shadow:0 2px 8px rgba(0,0,0,.05);
}
.topbar-title{font-size:16px;font-weight:700;color:var(--navy);}
.topbar-right{display:flex;align-items:center;gap:16px;}
.topbar-notif{position:relative;cursor:pointer;}
.topbar-notif .bell{font-size:20px;color:var(--gray-text);}
.notif-badge{
  position:absolute;top:-4px;right:-4px;
  background:var(--red);color:#fff;
  border-radius:50%;width:18px;height:18px;
  font-size:10px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}
.topbar-user{display:flex;align-items:center;gap:8px;cursor:pointer;}
.topbar-user .avatar{
  width:36px;height:36px;border-radius:50%;
  background:var(--navy);color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:13px;
}
.topbar-user .uname{font-size:13px;font-weight:600;color:var(--navy);}
.topbar-user .urole{font-size:11px;color:var(--gray-text);}
.topbar-hamburger{display:none;cursor:pointer;font-size:22px;color:var(--navy);}

/* ── Page content ── */
.page-content{padding:28px;flex:1;}
.page-header{margin-bottom:24px;}
.page-header h1{font-size:22px;font-weight:800;color:var(--navy);}
.page-header p{color:var(--gray-text);font-size:13px;margin-top:4px;}
.breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-text);margin-bottom:8px;}
.breadcrumb a{color:var(--navy);text-decoration:none;}
.breadcrumb a:hover{text-decoration:underline;}

/* ── Cards ── */
.card{background:#fff;border-radius:10px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid var(--gray-border);}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--gray-border);}
.card-title{font-size:15px;font-weight:700;color:var(--navy);}
.card-body{;}
.card + .card{margin-top:20px;}

/* ── Stat cards ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{
  background:#fff;border-radius:10px;padding:20px;
  border:1px solid var(--gray-border);
  display:flex;align-items:center;gap:16px;
  box-shadow:0 2px 8px rgba(0,0,0,.04);
}
.stat-icon{
  width:50px;height:50px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:22px;flex-shrink:0;
}
.stat-icon.navy{background:rgba(27,42,107,.1);color:var(--navy);}
.stat-icon.red{background:rgba(232,0,28,.1);color:var(--red);}
.stat-icon.green{background:rgba(16,185,129,.1);color:var(--success);}
.stat-icon.orange{background:rgba(245,158,11,.1);color:var(--warning);}
.stat-icon.blue{background:rgba(59,130,246,.1);color:var(--info);}
.stat-val{font-size:28px;font-weight:800;color:var(--navy);}
.stat-label{font-size:12px;color:var(--gray-text);font-weight:500;}

/* ── Buttons ── */
.btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:9px 18px;border-radius:7px;
  font-size:13px;font-weight:600;font-family:inherit;
  border:none;cursor:pointer;transition:all .2s;
  text-decoration:none;white-space:nowrap;
}
.btn:hover{transform:translateY(-1px);}
.btn-primary{background:var(--navy);color:#fff;}
.btn-primary:hover{background:var(--navy-dark);}
.btn-danger{background:var(--red);color:#fff;}
.btn-danger:hover{background:var(--red-dark);}
.btn-success{background:var(--success);color:#fff;}
.btn-success:hover{background:#059669;}
.btn-warning{background:var(--warning);color:#fff;}
.btn-warning:hover{background:#d97706;}
.btn-info{background:var(--info);color:#fff;}
.btn-info:hover{background:#2563eb;}
.btn-secondary{background:#e5e7eb;color:#374151;}
.btn-secondary:hover{background:#d1d5db;}
.btn-outline{background:transparent;border:2px solid var(--navy);color:var(--navy);}
.btn-outline:hover{background:var(--navy);color:#fff;}
.btn-sm{padding:6px 13px;font-size:12px;}
.btn-xs{padding:4px 9px;font-size:11px;}
.btn-lg{padding:12px 24px;font-size:15px;}
.btn-block{width:100%;justify-content:center;}

/* ── Badges ── */
.badge{
  display:inline-flex;align-items:center;
  padding:3px 9px;border-radius:99px;
  font-size:11px;font-weight:700;white-space:nowrap;
}
.badge-success{background:rgba(16,185,129,.12);color:#059669;}
.badge-danger{background:rgba(232,0,28,.1);color:var(--red);}
.badge-warning{background:rgba(245,158,11,.12);color:#b45309;}
.badge-info{background:rgba(59,130,246,.12);color:#2563eb;}
.badge-primary{background:rgba(27,42,107,.1);color:var(--navy);}
.badge-secondary{background:#e5e7eb;color:#374151;}

/* ── Table ── */
.table-wrap{overflow-x:auto;border-radius:8px;border:1px solid var(--gray-border);}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:var(--navy);color:#fff;padding:11px 14px;font-weight:600;font-size:12px;text-align:left;white-space:nowrap;}
tbody td{padding:11px 14px;border-bottom:1px solid var(--gray-border);vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:#f8f9fc;}
.td-actions{display:flex;gap:6px;flex-wrap:wrap;}

/* ── Forms ── */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group label{font-size:12px;font-weight:600;color:var(--navy);}
.form-control{
  width:100%;padding:9px 13px;
  border:1.5px solid var(--gray-border);border-radius:7px;
  font-size:13px;font-family:inherit;
  transition:border-color .2s;background:#fff;
}
.form-control:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(27,42,107,.08);}
.form-control.error{border-color:var(--red);}
textarea.form-control{min-height:100px;resize:vertical;}
.form-hint{font-size:11px;color:var(--gray-text);}
.form-error{font-size:11px;color:var(--red);}
.form-actions{display:flex;gap:10px;margin-top:8px;}
.field-required::after{content:' *';color:var(--red);}

/* ── Modals ── */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.5);z-index:1000;
  align-items:center;justify-content:center;
  padding:20px;
}
.modal-overlay.open{display:flex;}
.modal{
  background:#fff;border-radius:12px;
  width:100%;max-width:580px;max-height:90vh;
  overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.modal-lg{max-width:780px;}
.modal-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:20px 24px;border-bottom:1px solid var(--gray-border);
  background:var(--navy);border-radius:12px 12px 0 0;
}
.modal-header h3{font-size:16px;font-weight:700;color:#fff;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);font-size:22px;cursor:pointer;line-height:1;}
.modal-close:hover{color:#fff;}
.modal-body{padding:24px;}
.modal-footer{padding:16px 24px;border-top:1px solid var(--gray-border);display:flex;gap:10px;justify-content:flex-end;}

/* ── Alerts / Flash ── */
.alert{
  padding:13px 18px;border-radius:8px;margin-bottom:18px;
  font-size:13px;font-weight:500;
  display:flex;align-items:flex-start;gap:10px;
}
.alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#065f46;}
.alert-error{background:rgba(232,0,28,.08);border:1px solid rgba(232,0,28,.2);color:#991b1b;}
.alert-warning{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#92400e;}
.alert-info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);color:#1e40af;}

/* ── Tabs ── */
.tabs{display:flex;border-bottom:2px solid var(--gray-border);margin-bottom:20px;gap:0;}
.tab{
  padding:10px 20px;font-size:13px;font-weight:600;
  color:var(--gray-text);border-bottom:2px solid transparent;
  cursor:pointer;transition:all .2s;margin-bottom:-2px;
  text-decoration:none;
}
.tab:hover{color:var(--navy);}
.tab.active{color:var(--navy);border-bottom-color:var(--red);}

/* ── Workflow dots ── */
.workflow-dots{display:flex;align-items:center;gap:0;}
.dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.dot.done{background:var(--success);}
.dot.active{background:var(--navy);box-shadow:0 0 0 3px rgba(27,42,107,.2);}
.dot.pending{background:#d1d5db;}
.dot.rejected{background:var(--red);}
.dot-line{flex:1;height:2px;background:#d1d5db;min-width:14px;}

/* ── Misc ── */
.divider{height:1px;background:var(--gray-border);margin:20px 0;}
.text-muted{color:var(--gray-text);}
.text-navy{color:var(--navy);}
.text-red{color:var(--red);}
.text-success{color:var(--success);}
.text-sm{font-size:12px;}
.fw-bold{font-weight:700;}
.mb-0{margin-bottom:0;}
.mt-2{margin-top:8px;}
.mt-4{margin-top:16px;}
.mt-6{margin-top:24px;}
.mb-4{margin-bottom:16px;}
.mb-6{margin-bottom:24px;}
.d-flex{display:flex;}
.align-center{align-items:center;}
.justify-between{justify-content:space-between;}
.gap-2{gap:8px;}
.gap-3{gap:12px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.info-row{display:flex;padding:8px 0;border-bottom:1px solid var(--gray-border);font-size:13px;}
.info-row:last-child{border-bottom:none;}
.info-label{min-width:180px;font-weight:600;color:var(--navy);}
.info-val{color:#374151;}
.empty-state{text-align:center;padding:48px 20px;color:var(--gray-text);}
.empty-state .empty-icon{font-size:48px;margin-bottom:12px;}
.empty-state p{font-size:14px;}
.filter-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
.filter-bar .form-control{max-width:200px;}
.pagination{display:flex;gap:6px;margin-top:16px;}
.page-btn{padding:6px 12px;border:1px solid var(--gray-border);border-radius:6px;font-size:12px;font-weight:600;color:var(--navy);text-decoration:none;transition:all .2s;}
.page-btn:hover,.page-btn.active{background:var(--navy);color:#fff;border-color:var(--navy);}

/* ── Responsive ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .topbar-hamburger{display:block;}
  .grid-2,.grid-3,.form-grid{grid-template-columns:1fr;}
  .stat-grid{grid-template-columns:1fr 1fr;}
  .page-content{padding:16px;}
}
</style>
</head>
<body>

<?php if ($user): ?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="<?= $isAdminArea ? 'backoffice.php' : 'espace-stagiaire.php' ?>">
      <?php
        $logoPath = __DIR__ . '/../assets/img/logo.gif';
        $logoExists = file_exists($logoPath);
      ?>
      <?php if ($logoExists): ?>
      <img src="assets/img/logo.gif" alt="MUCODEC" style="height:52px;width:auto;background:#fff;border-radius:6px;padding:4px 8px;">
      <?php else: ?>
      <div>
        <div class="logo-text">Stag<span>IA</span></div>
        <div class="logo-sub">Gestion des stages</div>
      </div>
      <?php endif ?>
    </a>
  </div>

  <nav class="sidebar-nav">
    <?php if ($isAdminArea): ?>
    <div class="nav-section">Principal</div>
    <a href="backoffice.php?page=dashboard" class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>">
      <span class="icon">📊</span> Tableau de bord
    </a>
    <div class="nav-section">Candidatures</div>
    <a href="backoffice.php?page=candidatures" class="nav-link <?= $currentPage==='candidatures'?'active':'' ?>">
      <span class="icon">📋</span> Candidatures
    </a>
    <a href="backoffice.php?page=offres" class="nav-link <?= $currentPage==='offres'?'active':'' ?>">
      <span class="icon">📢</span> Offres de stage
    </a>
    <div class="nav-section">Stages</div>
    <a href="backoffice.php?page=stages" class="nav-link <?= $currentPage==='stages'?'active':'' ?>">
      <span class="icon">🎓</span> Stages
    </a>
    <a href="backoffice.php?page=renouvellements" class="nav-link <?= $currentPage==='renouvellements'?'active':'' ?>">
      <span class="icon">🔄</span> Renouvellements
    </a>
    <div class="nav-section">Personnes</div>
    <a href="backoffice.php?page=stagiaires" class="nav-link <?= $currentPage==='stagiaires'?'active':'' ?>">
      <span class="icon">👥</span> Stagiaires
    </a>
    <?php if (hasRole('administrateur','superviseur')): ?>
    <a href="backoffice.php?page=utilisateurs" class="nav-link <?= $currentPage==='utilisateurs'?'active':'' ?>">
      <span class="icon">👤</span> Utilisateurs
    </a>
    <?php endif; ?>
    <div class="nav-section">Configuration</div>
    <a href="backoffice.php?page=directions" class="nav-link <?= $currentPage==='directions'?'active':'' ?>">
      <span class="icon">🏢</span> Directions
    </a>
    <a href="backoffice.php?page=rapports" class="nav-link <?= $currentPage==='rapports'?'active':'' ?>">
      <span class="icon">📈</span> Rapports
    </a>
    <?php if (hasRole('administrateur')): ?>
    <a href="backoffice.php?page=analyse-cv" class="nav-link <?= $currentPage==='analyse-cv'?'active':'' ?>">
      <span class="icon">🤖</span> Analyse CV (IA)
    </a>
    <a href="backoffice.php?page=email-config" class="nav-link <?= $currentPage==='email-config'?'active':'' ?>">
      <span class="icon">✉️</span> Config. Email
    </a>
    <?php endif; ?>

    <?php else: ?>
    <div class="nav-section">Mon espace</div>
    <a href="espace-stagiaire.php" class="nav-link <?= $currentPage==='home'?'active':'' ?>">
      <span class="icon">🏠</span> Accueil
    </a>
    <a href="espace-stagiaire.php?tab=candidatures" class="nav-link <?= $currentPage==='candidatures'?'active':'' ?>">
      <span class="icon">📋</span> Mes candidatures
    </a>
    <a href="espace-stagiaire.php?tab=stage" class="nav-link <?= $currentPage==='stage'?'active':'' ?>">
      <span class="icon">🎓</span> Mon stage
    </a>
    <a href="espace-stagiaire.php?tab=profil" class="nav-link <?= $currentPage==='profil'?'active':'' ?>">
      <span class="icon">👤</span> Mon profil
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-logout">
      <span>⬅</span> D&eacute;connexion
    </a>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
      <span class="topbar-hamburger" onclick="toggleSidebar()">☰</span>
      <span class="topbar-title"><?= h(STAGIA_PAGE ?: 'StagIA') ?></span>
    </div>
    <div class="topbar-right">
      <div class="topbar-notif" title="Notifications">
        <span class="bell">🔔</span>
        <?php if ($notifCount > 0): ?>
        <span class="notif-badge"><?= $notifCount ?></span>
        <?php endif; ?>
      </div>
      <?php
        $prenom = $user['prenom'] ?? '';
        $nom    = $user['nom'] ?? '';
        $initiales = strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1));
        if ($initiales === '') $initiales = 'U';
      ?>
      <div class="topbar-user">
        <div class="avatar"><?= h($initiales) ?></div>
        <div>
          <div class="uname"><?= h(trim($prenom . ' ' . $nom)) ?></div>
          <div class="urole"><?= h(ucfirst($user['role'] ?? '')) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : $flash['type']) ?>">
      <?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'error' ? '❌' : 'ℹ️') ?>
      <?= h($flash['msg']) ?>
    </div>
    <?php endif; ?>

<?php else: ?>
<div class="main" style="margin-left:0">
<div class="page-content">
<?php if (isset($flash) && $flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
  <?= h($flash['msg']) ?>
</div>
<?php endif; ?>
<?php endif; ?>
