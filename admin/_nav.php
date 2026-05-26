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
if ($isAdmin) $nav[] = ["users.php","M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z","Utilisateurs"];
?>
<!DOCTYPE html>
<html lang="fr" id="adminRoot">
<head>
<meta charset="UTF-8">
<title><?php echo $pageTitle ?? "Admin"; ?> — MucoAcadémie</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
/* ═══════════════════════════════════════════════
   DESIGN SYSTEM — FormaPro Admin
   ═══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  /* Palette */
  --bg:       #0f1117;
  --surface:  #1a1d27;
  --surface2: #222535;
  --border:   #2e3148;
  --text:     #e8eaf0;
  --muted:    #6b7280;
  --subtle:   #374151;

  /* Accent — MUCODEC rouge */
  --indigo:   #e8192c;
  --indigo-h: #c0141f;
  --indigo-l: rgba(232,25,44,.12);

  /* Status */
  --green:    #10b981;
  --yellow:   #f59e0b;
  --red:      #ef4444;
  --blue:     #0ea5e9;

  /* Layout */
  --sb: 240px;
  --top: 58px;
  --r:  10px;
}

html, body { height: 100%; }

body {
  font-family: "Inter", "Segoe UI", system-ui, sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 14px;
  line-height: 1.5;
  display: flex;
  flex-direction: column;
}

a { text-decoration: none; color: inherit; }
button { font-family: inherit; }

/* ── TOPBAR ─────────────────────────────────── */
.adm-top {
  position: fixed; top: 0; left: 0; right: 0; z-index: 200;
  height: var(--top);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  padding: 0 20px 0 0;
  gap: 0;
}

.adm-top-logo {
  width: var(--sb);
  display: flex; align-items: center; gap: 9px;
  padding: 0 20px;
  flex-shrink: 0;
  font-weight: 800; font-size: 1rem; color: var(--text);
  border-right: 1px solid var(--border);
  height: 100%;
}
.adm-top-logo img { background:#fff; border-radius:7px; padding:2px; }
.adm-top-logo span { color: var(--indigo); }

.adm-top-right {
  flex: 1; display: flex; align-items: center;
  justify-content: flex-end; gap: 12px;
  padding-left: 20px;
}

.adm-user {
  display: flex; align-items: center; gap: 9px;
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: default;
}
.adm-avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--indigo-l); border: 1px solid var(--indigo);
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: .78rem; color: var(--indigo);
  flex-shrink: 0;
}
.adm-user-name { font-size: .83rem; font-weight: 600; color: var(--text); }
.adm-user-role {
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .04em;
  padding: 1px 7px; border-radius: 10px;
  background: var(--indigo-l); color: var(--indigo);
}

.adm-theme-btn {
  width: 32px; height: 32px; border-radius: 7px;
  background: transparent; border: 1px solid var(--border);
  cursor: pointer; color: var(--muted);
  display: flex; align-items: center; justify-content: center;
  transition: all .2s;
}
.adm-theme-btn:hover { background: var(--surface2); color: var(--text); }

.adm-logout {
  padding: 6px 14px; border-radius: 7px;
  border: 1px solid var(--border);
  font-size: .82rem; font-weight: 600; color: var(--muted);
  background: transparent; cursor: pointer;
  transition: all .2s;
}
.adm-logout:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,.06); }

/* ── SIDEBAR ─────────────────────────────────── */
.adm-sidebar {
  position: fixed; top: var(--top); left: 0; bottom: 0;
  width: var(--sb); z-index: 100;
  background: var(--surface);
  border-right: 1px solid var(--border);
  padding: 16px 10px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 4px;
}

.adm-sb-label {
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--subtle);
  padding: 14px 10px 6px; margin-top: 4px;
}

.adm-sb-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px;
  color: var(--muted); font-size: .855rem; font-weight: 500;
  transition: all .15s; position: relative;
}
.adm-sb-link svg { width: 17px; height: 17px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; }
.adm-sb-link:hover { background: var(--surface2); color: var(--text); }
.adm-sb-link.on {
  background: var(--indigo-l); color: var(--indigo); font-weight: 700;
  box-shadow: inset 3px 0 0 var(--indigo);
}

.adm-sb-div { height: 1px; background: var(--border); margin: 10px 4px; }

.adm-sb-ext {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px;
  color: var(--muted); font-size: .83rem; font-weight: 500;
  transition: all .15s; margin-top: auto;
}
.adm-sb-ext svg { width: 15px; height: 15px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; }
.adm-sb-ext:hover { background: var(--surface2); color: var(--text); }

/* ── CONTENT ─────────────────────────────────── */
.adm-main {
  margin-left: var(--sb);
  margin-top: var(--top);
  padding: 28px 32px;
  min-height: calc(100vh - var(--top));
  flex: 1;
}

/* ── COMPONENTS ──────────────────────────────── */
.adm-title {
  font-size: 1.3rem; font-weight: 800; color: var(--text);
  margin-bottom: 24px;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
}
.card-hover { transition: transform .2s, box-shadow .2s, border-color .2s; }
.card-hover:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(0,0,0,.3);
  border-color: var(--indigo);
}

/* Badges */
.badge {
  display: inline-flex; align-items: center;
  padding: 2px 9px; border-radius: 20px;
  font-size: .72rem; font-weight: 700;
}
.badge-indigo { background: var(--indigo-l); color: var(--indigo); }
.badge-green  { background: rgba(16,185,129,.12); color: var(--green); }
.badge-red    { background: rgba(239,68,68,.12); color: var(--red); }
.badge-yellow { background: rgba(245,158,11,.12); color: var(--yellow); }
.badge-gray   { background: rgba(107,114,128,.12); color: var(--muted); }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px 18px; border-radius: 8px;
  font-size: .855rem; font-weight: 700; cursor: pointer;
  border: none; transition: all .2s; white-space: nowrap;
}
.btn-primary { background: var(--indigo); color: #fff; }
.btn-primary:hover { background: var(--indigo-h); }
.btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--surface2); color: var(--text); }
.btn-danger { background: rgba(239,68,68,.1); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.btn-danger:hover { background: rgba(239,68,68,.2); }
.btn-sm { padding: 5px 12px; font-size: .78rem; }

/* Alerts */
.alert {
  padding: 12px 16px; border-radius: 8px;
  font-size: .855rem; margin-bottom: 20px;
  display: flex; align-items: flex-start; gap: 10px;
}
.alert-ok   { background:#052e16; border:1px solid #166534; color:#86efac; }
.alert-err  { background:#450a0a; border:1px solid #991b1b; color:#fca5a5; }
.alert-warn { background:#451a03; border:1px solid #92400e; color:#fcd34d; }

/* Forms */
.field { margin-bottom: 16px; }
.field label {
  display: block; font-size: .78rem; font-weight: 700;
  color: var(--muted); text-transform: uppercase; letter-spacing: .05em;
  margin-bottom: 7px;
}
.field input, .field select, .field textarea {
  width: 100%; padding: 10px 14px;
  background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
  color: var(--text); font-size: .9rem; outline: none;
  transition: border-color .2s, box-shadow .2s;
  font-family: inherit;
}
.field input:focus, .field select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 3px rgba(232,25,44,.15);
}

/* Table */
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th {
  padding: 10px 16px; text-align: left;
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .05em; color: var(--muted);
  border-bottom: 1px solid var(--border);
}
.adm-table td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.adm-table tr:last-child td { border-bottom: none; }
.adm-table tbody tr:hover { background: rgba(255,255,255,.02); }

/* Modal */
.modal-back {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.7); backdrop-filter: blur(4px);
  z-index: 999; align-items: center; justify-content: center;
}
.modal-back.open { display: flex; }
.modal-box {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 14px; padding: 28px; width: 100%;
  max-width: 440px; margin: 16px;
  box-shadow: 0 24px 64px rgba(0,0,0,.5);
}
.modal-title { font-weight: 800; font-size: 1.05rem; margin-bottom: 20px; color: var(--text); }

/* KPI grid */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px,1fr)); gap: 14px; margin-bottom: 24px; }
.kpi-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r); padding: 18px;
  display: flex; align-items: center; gap: 14px;
}
.kpi-icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; flex-shrink: 0;
}
.kpi-val { font-size: 1.7rem; font-weight: 800; line-height: 1; }
.kpi-label { font-size: .75rem; color: var(--muted); margin-top: 3px; }

@media (max-width: 768px) {
  .adm-sidebar { display: none; }
  .adm-main { margin-left: 0; padding: 16px; }
  .adm-top-logo { width: auto; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="adm-top">
  <a href="dashboard.php" class="adm-top-logo">
    <img src="../images/MUCODEC.gif" width="30" height="30" alt="">
    Muco<span>Académie</span>
  </a>
  <div class="adm-top-right">
    <div class="adm-user">
      <div class="adm-avatar"><?php echo $avatar; ?></div>
      <div>
        <div class="adm-user-name"><?php echo htmlspecialchars($me); ?></div>
      </div>
      <span class="adm-user-role"><?php echo $_SESSION["user"]["role"]; ?></span>
    </div>
    <button class="adm-theme-btn" onclick="toggleTheme()" title="Thème">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <a href="logout.php"><button class="adm-logout">Déconnexion</button></a>
  </div>
</header>

<!-- SIDEBAR -->
<nav class="adm-sidebar">
  <span class="adm-sb-label">Menu principal</span>
  <?php foreach($nav as [$href, $path, $label]): ?>
  <a href="<?php echo $href; ?>" class="adm-sb-link <?php echo $current===$href?'on':''; ?>">
    <svg viewBox="0 0 24 24"><path d="<?php echo $path; ?>"/></svg>
    <?php echo $label; ?>
  </a>
  <?php endforeach; ?>

  <div class="adm-sb-div"></div>

  <a href="../index.php" target="_blank" class="adm-sb-ext">
    <svg viewBox="0 0 24 24"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
    Voir le site public
  </a>
</nav>

<!-- MAIN -->
<main class="adm-main">
