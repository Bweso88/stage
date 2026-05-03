<?php
if (!defined('STAGIA_LOADED')) { require_once __DIR__ . '/../config.php'; }
requireAdmin();

$currentPage = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page'] ?? 'dashboard');
$currentUser = $_SESSION['user'];

// Compter les notifications
$pdo = getPDO();
$nbNotif = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND statut_lecture = 'non_lu'");
    $stmt->execute([$currentUser['id']]);
    $nbNotif = (int)$stmt->fetchColumn();
} catch (Throwable) {}

// Compter les candidatures en attente selon le rôle
$nbPending = 0;
try {
    if (hasRole('habilite', 'administrateur')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global = 'soumise'");
        $nbPending = (int)$stmt->fetchColumn();
    } elseif (hasRole('superviseur')) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global = 'niv1_valide'");
        $nbPending = (int)$stmt->fetchColumn();
    }
} catch (Throwable) {}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StagIA — Backoffice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ─── Reset ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy:      #1b2a6b;
            --navy-dark: #142059;
            --red:       #e8001c;
            --red-dark:  #c40018;
            --gray-bg:   #f4f5f7;
            --gray-line: #e2e4ea;
            --text:      #111827;
            --text-muted:#6b7280;
            --sidebar-w: 240px;
        }
        html, body { height: 100%; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gray-bg);
            color: var(--text);
            display: flex;
        }

        /* ─── Sidebar ────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 22px 24px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-brand .brand-name {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.5px;
        }
        .sidebar-brand .brand-name span { color: var(--red); }
        .sidebar-brand .brand-sub {
            font-size: 11px;
            color: rgba(255,255,255,.5);
            margin-top: 2px;
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
            overflow-y: auto;
        }
        .nav-section {
            padding: 10px 16px 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,.35);
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background .15s, color .15s;
            position: relative;
            border-radius: 0;
        }
        .nav-item:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }
        .nav-item.active {
            background: var(--red);
            color: #fff;
        }
        .nav-item .badge-nav {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 999px;
            min-width: 18px;
            text-align: center;
        }
        .nav-item.active .badge-nav {
            background: rgba(255,255,255,.3);
        }
        .nav-icon { width: 18px; text-align: center; flex-shrink: 0; }
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-user { display: flex; align-items: center; gap: 10px; }
        .sidebar-user .avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--red);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            flex-shrink: 0;
        }
        .sidebar-user .user-info { flex: 1; min-width: 0; }
        .sidebar-user .user-name {
            font-size: 13px; font-weight: 600; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sidebar-user .user-role {
            font-size: 11px; color: rgba(255,255,255,.5);
        }
        .sidebar-user .logout-btn {
            color: rgba(255,255,255,.5);
            text-decoration: none;
            font-size: 18px;
            transition: color .15s;
        }
        .sidebar-user .logout-btn:hover { color: var(--red); }

        /* ─── Main Layout ─────────────────────────────────────── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--gray-line);
            padding: 0 28px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--navy);
        }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .notif-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 18px;
            transition: background .15s;
        }
        .notif-btn:hover { background: var(--gray-bg); }
        .notif-dot {
            position: absolute;
            top: 3px; right: 3px;
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .page-content {
            padding: 24px 28px;
            flex: 1;
        }

        /* ─── Cards ───────────────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid var(--gray-line);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body { padding: 20px; }

        /* ─── Stats ───────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--gray-line);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-icon.navy { background: rgba(27,42,107,.1); color: var(--navy); }
        .stat-icon.red   { background: rgba(232,0,28,.1); color: var(--red); }
        .stat-icon.green { background: rgba(22,163,74,.1); color: #16a34a; }
        .stat-icon.amber { background: rgba(217,119,6,.1); color: #d97706; }
        .stat-icon.teal  { background: rgba(13,148,136,.1); color: #0d9488; }
        .stat-value { font-size: 26px; font-weight: 700; color: var(--navy); }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ─── Sections grid ───────────────────────────────────── */
        .section-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .section-full { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media (max-width: 900px) { .section-grid { grid-template-columns: 1fr; } }

        /* ─── Buttons ─────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, opacity .15s;
            white-space: nowrap;
        }
        .btn:hover { opacity: .88; }
        .btn-red    { background: var(--red);   color: #fff; }
        .btn-navy   { background: var(--navy);  color: #fff; }
        .btn-amber  { background: #d97706;      color: #fff; }
        .btn-ghost  { background: var(--gray-bg); color: var(--navy); border: 1px solid var(--gray-line); }
        .btn-danger { background: #dc2626;      color: #fff; }
        .btn-success{ background: #16a34a;      color: #fff; }
        .btn-sm     { padding: 5px 10px; font-size: 12px; border-radius: 6px; }
        .btn-xs     { padding: 3px 8px; font-size: 11px; border-radius: 5px; }

        /* ─── Badges ──────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-info     { background: #eff6ff; color: #1d4ed8; }
        .badge-warning  { background: #fffbeb; color: #b45309; }
        .badge-success  { background: #f0fdf4; color: #15803d; }
        .badge-danger   { background: #fef2f2; color: #b91c1c; }
        .badge-primary  { background: #eef2ff; color: #4338ca; }
        .badge-secondary{ background: #f3f4f6; color: #4b5563; }
        .badge-amber    { background: #fffbeb; color: #b45309; }
        .badge-teal     { background: #f0fdfa; color: #0f766e; }
        .badge-rose     { background: #fff1f2; color: #be123c; }
        .badge-emerald  { background: #ecfdf5; color: #065f46; }
        .badge-blue     { background: #eff6ff; color: #1e40af; }
        .badge-gray     { background: #f3f4f6; color: #374151; }

        /* ─── Tables ──────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        th {
            background: var(--gray-bg);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--gray-line);
            white-space: nowrap;
        }
        td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--gray-line);
            vertical-align: middle;
            color: var(--text);
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(27,42,107,.02); }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 36px; margin-bottom: 10px; }

        /* ─── Forms ───────────────────────────────────────────── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        @media (max-width: 700px) { .form-grid, .form-grid-3 { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label {
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
        }
        input[type="text"], input[type="email"], input[type="number"],
        input[type="date"], input[type="password"], input[type="tel"],
        select, textarea {
            padding: 9px 12px;
            border: 1.5px solid var(--gray-line);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
            color: var(--text);
            background: #fff;
            transition: border-color .2s;
            outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--navy); }
        input:disabled, select:disabled, textarea:disabled {
            background: var(--gray-bg);
            color: var(--text-muted);
            cursor: not-allowed;
        }
        textarea { resize: vertical; min-height: 80px; }

        /* ─── Filter bar ──────────────────────────────────────── */
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px;
            font-size: 13px;
        }

        /* ─── Modals ──────────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            animation: modal-in .2s ease;
        }
        .modal-lg { max-width: 720px; }
        .modal-xl { max-width: 900px; }
        @keyframes modal-in {
            from { transform: translateY(-20px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--gray-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-title { font-size: 15px; font-weight: 600; color: var(--navy); }
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
            border-radius: 6px;
            transition: background .15s;
        }
        .modal-close:hover { background: var(--gray-bg); }
        .modal-body { padding: 22px; }
        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--gray-line);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ─── Alerts ──────────────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
        .alert-danger  { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

        /* ─── Workflow dots ────────────────────────────────────── */
        .workflow-dots { display: flex; align-items: center; gap: 4px; }
        .dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot.done    { background: #16a34a; }
        .dot.active  { background: var(--navy); box-shadow: 0 0 0 3px rgba(27,42,107,.2); }
        .dot.pending { background: #d1d5db; }
        .dot.rejected{ background: var(--red); }
        .dot-line { width: 16px; height: 2px; background: #d1d5db; display: inline-block; }

        /* ─── Misc ────────────────────────────────────────────── */
        .mono { font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
        .avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--navy);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
        .text-muted { color: var(--text-muted); }
        .text-sm    { font-size: 12px; }
        .fw-600     { font-weight: 600; }
        .d-flex     { display: flex; }
        .gap-8      { gap: 8px; }
        .align-center { align-items: center; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .page-header h1 { font-size: 20px; font-weight: 700; color: var(--navy); }
        .progress-wrap {
            background: var(--gray-line);
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 999px;
            background: var(--navy);
            transition: width .3s;
        }
        .progress-bar.green { background: #16a34a; }
        .progress-bar.amber { background: #d97706; }
        .progress-bar.red   { background: var(--red); }
        .divider { border: none; border-top: 1px solid var(--gray-line); margin: 16px 0; }

        /* ─── Flash messages ──────────────────────────────────── */
        .flash-container {
            position: fixed;
            top: 70px; right: 20px;
            z-index: 5000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 360px;
        }
        .flash-msg {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            animation: flash-in .3s ease;
        }
        @keyframes flash-in {
            from { transform: translateX(30px); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        .flash-success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
        .flash-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">Stag<span>IA</span></div>
        <div class="brand-sub">Gestion des stages</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="backoffice.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Tableau de bord
        </a>

        <div class="nav-section">Candidatures</div>
        <a href="backoffice.php?page=candidatures" class="nav-item <?= $currentPage === 'candidatures' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Candidatures
            <?php if ($nbPending > 0): ?>
                <span class="badge-nav"><?= $nbPending ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Stages</div>
        <a href="backoffice.php?page=stages" class="nav-item <?= $currentPage === 'stages' ? 'active' : '' ?>">
            <span class="nav-icon">🎓</span> Stages
        </a>
        <a href="backoffice.php?page=renouvellements" class="nav-item <?= $currentPage === 'renouvellements' ? 'active' : '' ?>">
            <span class="nav-icon">🔄</span> Renouvellements
        </a>

        <div class="nav-section">Gestion</div>
        <a href="backoffice.php?page=offres" class="nav-item <?= $currentPage === 'offres' ? 'active' : '' ?>">
            <span class="nav-icon">📢</span> Offres de stage
        </a>
        <a href="backoffice.php?page=stagiaires" class="nav-item <?= $currentPage === 'stagiaires' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span> Stagiaires
        </a>
        <a href="backoffice.php?page=directions" class="nav-item <?= $currentPage === 'directions' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span> Directions
        </a>

        <div class="nav-section">Rapports</div>
        <a href="backoffice.php?page=rapports" class="nav-item <?= $currentPage === 'rapports' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Rapports
        </a>

        <?php if (hasRole('administrateur')): ?>
        <div class="nav-section">Administration</div>
        <a href="backoffice.php?page=utilisateurs" class="nav-item <?= $currentPage === 'utilisateurs' ? 'active' : '' ?>">
            <span class="nav-icon">👤</span> Utilisateurs
        </a>
        <a href="backoffice.php?page=email-config" class="nav-item <?= $currentPage === 'email-config' ? 'active' : '' ?>">
            <span class="nav-icon">✉️</span> Config Email
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($currentUser['prenom'], 0, 1) . substr($currentUser['nom'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= h($currentUser['prenom'] . ' ' . $currentUser['nom']) ?></div>
                <div class="user-role"><?= h(ucfirst($currentUser['role'])) ?></div>
            </div>
            <a href="backoffice.php?action=logout" class="logout-btn" title="Se déconnecter">⏻</a>
        </div>
    </div>
</aside>

<!-- Main -->
<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-title">
            <?php
            $titles = [
                'dashboard'          => 'Tableau de bord',
                'candidatures'       => 'Candidatures',
                'candidature_detail' => 'Détail candidature',
                'stages'             => 'Stages',
                'stage_detail'       => 'Détail stage',
                'renouvellements'    => 'Renouvellements',
                'offres'             => 'Offres de stage',
                'stagiaires'         => 'Stagiaires',
                'stagiaire_detail'   => 'Profil stagiaire',
                'directions'         => 'Directions & domaines',
                'rapports'           => 'Rapports',
                'utilisateurs'       => 'Utilisateurs',
                'analyse-cv'         => 'Analyse IA du CV',
                'email-config'       => 'Configuration email',
                'lettre_stage'       => 'Lettre de stage',
            ];
            echo $titles[$currentPage] ?? 'Backoffice';
            ?>
        </div>
        <div class="topbar-actions">
            <button class="notif-btn" title="Notifications">
                🔔
                <?php if ($nbNotif > 0): ?>
                    <span class="notif-dot"></span>
                <?php endif; ?>
            </button>
            <a href="/index.php" class="btn btn-ghost btn-sm" target="_blank">🌐 Site public</a>
        </div>
    </header>

    <!-- Flash messages -->
    <?php if ($flash): ?>
    <div class="flash-container">
        <div class="flash-msg flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= h($flash['msg']) ?>
        </div>
    </div>
    <script>setTimeout(() => { const el = document.querySelector('.flash-msg'); if (el) el.style.opacity = '0'; }, 3500);</script>
    <?php endif; ?>

    <main class="page-content">
