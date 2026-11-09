<?php
$isAdmin = isset($_SESSION["user"]["role"]) && $_SESSION["user"]["role"] === "ADMIN";
$current = basename($_SERVER["PHP_SELF"]);
$me      = $_SESSION["user"]["fullname"] ?? "?";
$avatar  = strtoupper(mb_substr($me, 0, 1));

$nav = [
  ["dashboard.php",    "M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6", "Tableau de bord"],
  ["upload.php",       "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12", "Upload vidéo"],
  ["manage_videos.php","M15 10l4.553-2.069A1 1 0 0121 8.82V15.18a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z", "Gérer les vidéos"],
  ["manage_themes.php","M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z", "Gérer les thèmes"],
];
$nav[] = ["viewers.php","M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z","Apprenants"];
$nav[] = ["directions.php","M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4","Directions"];
if ($isAdmin) $nav[] = ["users.php","M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z","Utilisateurs"];
?>
<!DOCTYPE html>
<html lang="fr" id="adminRoot">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle ?? "Admin"; ?> — MucoAcadémie</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
::-webkit-scrollbar{display:none;}
*,*::before,*::after{box-sizing:border-box;}
body{margin:0;font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#faf8ff;color:#131b2e;}
a{text-decoration:none;color:inherit;}

/* Legacy CSS vars — keep existing admin pages working without modification */
:root{
  --bg:#faf8ff;
  --surface:#ffffff;
  --surface2:#f2f3ff;
  --border:#c4c5d7;
  --text:#131b2e;
  --muted:#747686;
  --subtle:#434655;
  --indigo:#0037b0;
  --indigo-d:#1d4ed8;
  --indigo-l:rgba(0,55,176,.1);
}

/* Shared component classes used by existing admin pages */
.card{background:var(--surface);border:1px solid var(--border);border-radius:12px;box-shadow:0 1px 3px rgba(19,27,46,.05);}
.card-hover{transition:.2s;}
.card-hover:hover{box-shadow:0 4px 12px rgba(19,27,46,.08);transform:translateY(-1px);}

.page-title,.adm-title{font-size:1.4rem;font-weight:800;color:var(--text);margin-bottom:24px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 20px;border-radius:8px;font-weight:600;font-size:.88rem;cursor:pointer;border:none;transition:.2s;font-family:"Plus Jakarta Sans",system-ui,sans-serif;line-height:1;}
.btn-primary{background:#0037b0;color:#fff;}
.btn-primary:hover{background:#1d4ed8;}
.btn-danger{background:#ba1a1a;color:#fff;}
.btn-danger:hover{background:#93000a;}
.btn-outline{background:#fff;border:1px solid #c4c5d7;color:#131b2e;}
.btn-outline:hover{background:#f2f3ff;}
.btn-sm{padding:6px 14px;font-size:.82rem;}

.alert{padding:12px 16px;border-radius:8px;font-size:.88rem;margin-bottom:16px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}
.alert-ok,.alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
.alert-err,.alert-danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.alert-warn,.alert-warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}

.field{margin-bottom:16px;}
.field label{display:block;font-size:.82rem;font-weight:600;color:var(--subtle);margin-bottom:6px;font-family:"Plus Jakarta Sans",system-ui,sans-serif;}

table{width:100%;border-collapse:collapse;}
th{padding:8px 12px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--border);}
td{padding:10px 12px;font-size:.875rem;color:var(--text);border-bottom:1px solid var(--border);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f8f9ff;}

input[type=text],input[type=email],input[type=password],input[type=number],input[type=search],select,textarea{
  width:100%;padding:9px 14px;background:var(--surface);border:1px solid var(--border);
  border-radius:8px;color:var(--text);font-size:.9rem;outline:none;
  font-family:"Plus Jakarta Sans",system-ui,sans-serif;transition:.15s;
}
input[type=text]:focus,input[type=email]:focus,input[type=password]:focus,
input[type=number]:focus,input[type=search]:focus,select:focus,textarea:focus{
  border-color:#0037b0;box-shadow:0 0 0 3px rgba(0,55,176,.12);
}

/* Badge / chip */
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700;letter-spacing:.03em;}
.badge-centrale{background:#eaedff;color:#0037b0;}
.badge-regionale{background:#fef3c7;color:#92400e;}
</style>
</head>
<body>

<!-- ── SIDEBAR 260px ─────────────────────────────── -->
<aside style="position:fixed;left:0;top:0;height:100vh;width:260px;background:#ffffff;border-right:1px solid #c4c5d7;display:flex;flex-direction:column;justify-content:space-between;z-index:50;box-shadow:1px 0 8px rgba(0,0,0,.04);">

  <div style="overflow-y:auto;flex:1;">
    <!-- Logo -->
    <div style="height:64px;display:flex;align-items:center;padding:0 20px;gap:10px;border-bottom:1px solid #e2e7ff;flex-shrink:0;">
      <img src="../images/mucoacademie.png" width="36" height="36" alt="Logo" style="background:#fff;border-radius:8px;padding:2px;flex-shrink:0;">
      <div>
        <div style="font-size:1rem;font-weight:800;color:#131b2e;line-height:1.1;">MucoAcadémie</div>
        <div style="font-size:.72rem;font-weight:700;color:#0037b0;text-transform:uppercase;letter-spacing:.08em;">Administration</div>
      </div>
    </div>

    <!-- Nav links -->
    <nav style="padding:12px;">
      <div style="font-size:.72rem;font-weight:700;color:#747686;text-transform:uppercase;letter-spacing:.08em;padding:4px 8px 10px;">Navigation</div>
      <?php foreach($nav as [$href, $svgPath, $label]):
        $active = ($current === $href);
      ?>
      <a href="<?php echo $href; ?>"
         style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;margin-bottom:2px;font-size:.9rem;font-weight:600;transition:.15s;<?php echo $active ? 'background:#1d4ed8;color:#fff;' : 'color:#434655;'; ?>"
         <?php if(!$active): ?>
           onmouseover="this.style.background='#f2f3ff';this.style.color='#131b2e';"
           onmouseout="this.style.background='transparent';this.style.color='#434655';"
         <?php endif; ?>>
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="flex-shrink:0;">
          <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $svgPath; ?>"/>
        </svg>
        <span><?php echo $label; ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- Bottom: user info + logout -->
  <div style="padding:12px;border-top:1px solid #e2e7ff;">
    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f2f3ff;border-radius:8px;margin-bottom:8px;">
      <div style="width:36px;height:36px;border-radius:50%;background:#0037b0;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.95rem;flex-shrink:0;"><?php echo htmlspecialchars($avatar); ?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.88rem;font-weight:700;color:#131b2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($me); ?></div>
        <div style="font-size:.74rem;color:#747686;"><?php echo $isAdmin ? 'Administrateur' : 'Formateur'; ?></div>
      </div>
    </div>
    <a href="logout.php"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border-radius:8px;background:#fef2f2;color:#ba1a1a;font-size:.88rem;font-weight:600;transition:.15s;"
       onmouseover="this.style.background='#fee2e2';" onmouseout="this.style.background='#fef2f2';">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Déconnexion
    </a>
    <a href="../index.php"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:8px 12px;border-radius:8px;color:#747686;font-size:.82rem;font-weight:600;margin-top:6px;transition:.15s;"
       onmouseover="this.style.background='#f2f3ff';" onmouseout="this.style.background='transparent';">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      Retour au site
    </a>
  </div>
</aside>

<!-- ── MAIN WRAPPER (offset from sidebar) ─────────── -->
<div style="margin-left:260px;min-height:100vh;display:flex;flex-direction:column;">

  <!-- Sticky header bar -->
  <header style="position:sticky;top:0;height:56px;background:rgba(250,248,255,.92);backdrop-filter:blur(12px);border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:40;box-shadow:0 1px 4px rgba(0,0,0,.04);flex-shrink:0;">
    <div style="font-size:.88rem;color:#747686;display:flex;align-items:center;gap:8px;">
      <a href="dashboard.php" style="color:#747686;font-weight:600;transition:.15s;" onmouseover="this.style.color='#131b2e'" onmouseout="this.style.color='#747686'">Admin</a>
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
      <span style="color:#131b2e;font-weight:700;"><?php echo htmlspecialchars($pageTitle ?? ""); ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <?php if($isAdmin): ?>
      <span style="background:#eaddff;color:#5700c0;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.06em;">Admin</span>
      <?php endif; ?>
      <div style="width:32px;height:32px;border-radius:50%;background:#0037b0;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.88rem;"><?php echo htmlspecialchars($avatar); ?></div>
    </div>
  </header>

  <!-- Page content starts here -->
  <main style="flex:1;padding:24px;background:#faf8ff;">
