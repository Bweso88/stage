<?php
require_once __DIR__ . '/config.php';
requireStagiaire();

$pdo    = getPDO();
$userId = $_SESSION['user']['id'];
$stagId = $_SESSION['user']['stagiaire_id'] ?? 0;
$flash  = getFlash();
$show   = $_GET['show'] ?? '';

// Infos stagiaire
$stag = $pdo->prepare("
    SELECT s.*, u.nom, u.prenom, u.email, u.civilite
    FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id
    WHERE s.id = ?
");
$stag->execute([$stagId]);
$stag = $stag->fetch();

// ─── Traitement POST ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    // Nouvelle candidature
    if (isset($_POST['nouvelle_candidature'])) {
        $typeC   = $_POST['type_candidature'] ?? 'spontanee';
        $offreId = (int)($_POST['offre_id'] ?? 0) ?: null;
        $domId   = (int)($_POST['domaine_id'] ?? 0) ?: null;
        $dirId   = (int)($_POST['direction_id'] ?? 0) ?: null;
        $motiv   = trim($_POST['motivation'] ?? '');

        $score = calculerScore($stagId);
        $ref   = genRef('CAND', 'candidatures', 'reference');
        $pdo->prepare("INSERT INTO candidatures (reference, stagiaire_id, offre_id, type_candidature, domaine_id, direction_id, score_tri, motivation) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$ref, $stagId, $offreId, $typeC, $domId, $dirId, $score, $motiv]);

        flash('Candidature ' . $ref . ' soumise avec succès.');
        redirect('/espace-stagiaire.php');
    }

    // Demande de renouvellement
    if (isset($_POST['demande_renouvellement'])) {
        $stageId = (int)($_POST['stage_id'] ?? 0);
        $dateFin = $_POST['date_fin_proposee'] ?? '';
        $motif   = trim($_POST['motif_demande'] ?? '');

        $stmtSt = $pdo->prepare("SELECT * FROM stages WHERE id = ? AND stagiaire_id = ?");
        $stmtSt->execute([$stageId, $stagId]);
        $stg = $stmtSt->fetch();

        if ($stg && in_array($stg['statut'], ['en_cours', 'renouvele'], true) && $dateFin) {
            $numRenouv = $stg['nb_renouvellements'] + 1;
            $diffMois  = round((strtotime($dateFin) - strtotime($stg['date_fin'])) / (30.5 * 86400), 1);
            $pdo->prepare("INSERT INTO renouvellements_stage (stage_id, numero_renouvellement, date_debut_proposee, date_fin_proposee, duree_ajoutee_mois, motif_demande) VALUES (?,?,?,?,?,?)")
                ->execute([$stageId, $numRenouv, $stg['date_fin'], $dateFin, $diffMois, $motif]);
            flash('Votre demande de renouvellement a été soumise.');
        } else {
            flash('Impossible de soumettre la demande.', 'error');
        }
        redirect('/espace-stagiaire.php');
    }
}

// ─── Données ──────────────────────────────────────────────────────────────────

$candidatures = $pdo->prepare("
    SELECT c.*, d.libelle AS direction, dom.libelle AS domaine, o.titre AS offre_titre
    FROM candidatures c
    LEFT JOIN directions d   ON d.id   = c.direction_id
    LEFT JOIN domaines dom   ON dom.id = c.domaine_id
    LEFT JOIN offres_stage o ON o.id   = c.offre_id
    WHERE c.stagiaire_id = ?
    ORDER BY c.date_candidature DESC
");
$candidatures->execute([$stagId]);
$candidatures = $candidatures->fetchAll();

$stages = $pdo->prepare("
    SELECT s.*, d.libelle AS direction, dom.libelle AS domaine,
           eu.nom AS enc_nom, eu.prenom AS enc_prenom
    FROM stages s
    LEFT JOIN directions d   ON d.id   = s.direction_id
    LEFT JOIN domaines dom   ON dom.id = s.domaine_id
    LEFT JOIN utilisateurs eu ON eu.id = s.encadrant_id
    WHERE s.stagiaire_id = ?
    ORDER BY s.date_debut DESC
");
$stages->execute([$stagId]);
$stages = $stages->fetchAll();

$notifs = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY created_at DESC LIMIT 10");
$notifs->execute([$userId]);
$notifs = $notifs->fetchAll();
$nbNonLues = count(array_filter($notifs, fn($n) => $n['statut_lecture'] === 'non_lu'));

$pdo->prepare("UPDATE notifications SET statut_lecture = 'lu' WHERE utilisateur_id = ?")->execute([$userId]);

$offresDispos = $pdo->query("SELECT id, reference, titre FROM offres_stage WHERE statut = 'ouverte' ORDER BY titre")->fetchAll();
$domaines     = $pdo->query("SELECT id, libelle FROM domaines WHERE actif = 1 ORDER BY libelle")->fetchAll();
$directions   = $pdo->query("SELECT id, libelle FROM directions WHERE actif = 1 ORDER BY libelle")->fetchAll();

$stageActif = null;
foreach ($stages as $s) {
    if (in_array($s['statut'], ['en_cours', 'renouvele', 'preparation'], true)) {
        $stageActif = $s;
        break;
    }
}

$peutRenouveler = $stageActif
    && $stageActif['nb_renouvellements'] < 3
    && in_array($stageActif['statut'], ['en_cours', 'renouvele'], true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StagIA — Mon espace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Montserrat', sans-serif; background: #f4f5f7; color: #111827; }

        .navbar { background: #1b2a6b; padding: 0 28px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .brand  { font-size: 20px; font-weight: 700; color: #fff; }
        .brand span { color: #e8001c; }
        .nav-user { display: flex; align-items: center; gap: 12px; }
        .nav-user span { color: rgba(255,255,255,.8); font-size: 13px; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border: none; border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .15s; white-space: nowrap; }
        .btn:hover { opacity: .85; }
        .btn-ghost  { background: rgba(255,255,255,.15); color: #fff; }
        .btn-red    { background: #e8001c; color: #fff; }
        .btn-navy   { background: #1b2a6b; color: #fff; }
        .btn-light  { background: #fff; color: #1b2a6b; border: 1px solid #e2e4ea; }
        .btn-sm     { padding: 5px 10px; font-size: 12px; border-radius: 6px; }

        .container { max-width: 1100px; margin: 0 auto; padding: 24px 20px; }
        .page-title { font-size: 22px; font-weight: 700; color: #1b2a6b; margin-bottom: 20px; }

        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }

        .stat-card  { background: #fff; border-radius: 10px; border: 1px solid #e2e4ea; padding: 18px; display: flex; align-items: center; gap: 14px; }
        .stat-icon  { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .si-navy  { background: rgba(27,42,107,.1); }
        .si-green { background: rgba(22,163,74,.1); }
        .si-amber { background: rgba(217,119,6,.1); }
        .stat-value { font-size: 24px; font-weight: 700; color: #1b2a6b; }
        .stat-label { font-size: 12px; color: #6b7280; }

        .card { background: #fff; border-radius: 10px; border: 1px solid #e2e4ea; overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid #e2e4ea; display: flex; align-items: center; justify-content: space-between; }
        .card-title  { font-size: 14px; font-weight: 600; color: #1b2a6b; }
        .card-body   { padding: 20px; }

        .inline-form-card { background: #fff; border-radius: 10px; border: 2px solid #1b2a6b; margin-bottom: 24px; overflow: hidden; }
        .inline-form-card .card-header { background: #1b2a6b; padding: 14px 20px; color: #fff; border-bottom: none; }
        .inline-form-card .card-title  { color: #fff; font-size: 15px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f4f5f7; color: #6b7280; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; padding: 8px 14px; text-align: left; }
        td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .badge           { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-info      { background: #eff6ff; color: #1d4ed8; }
        .badge-warning   { background: #fffbeb; color: #b45309; }
        .badge-success   { background: #f0fdf4; color: #15803d; }
        .badge-danger    { background: #fef2f2; color: #b91c1c; }
        .badge-primary   { background: #eef2ff; color: #4338ca; }
        .badge-secondary { background: #f3f4f6; color: #4b5563; }

        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }

        .form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 12.5px; font-weight: 600; color: #374151; }
        input, select, textarea { padding: 9px 12px; border: 1.5px solid #e2e4ea; border-radius: 8px; font-family: inherit; font-size: 13.5px; color: #111827; background: #fff; outline: none; transition: border-color .2s; }
        input:focus, select:focus, textarea:focus { border-color: #1b2a6b; }
        textarea { resize: vertical; min-height: 70px; }

        .empty-state { text-align: center; padding: 30px; color: #6b7280; }
        .mono   { font-family: monospace; font-size: 12px; }
        .fw-600 { font-weight: 600; }
        .text-muted { color: #6b7280; }
        .text-sm    { font-size: 12px; }

        .notif-item { padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .notif-item:last-child { border-bottom: none; }
        .notif-objet { font-weight: 600; margin-bottom: 3px; }
        .notif-msg   { color: #6b7280; font-size: 12px; }
        .notif-date  { color: #9ca3af; font-size: 11px; margin-top: 2px; }

        .offre-row { display: none; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="brand">Stag<span>IA</span></div>
    <div class="nav-user">
        <span>Bonjour, <?= h($stag['prenom']) ?></span>
        <a href="/logout.php" class="btn btn-ghost btn-sm">Déconnexion</a>
    </div>
</nav>

<div class="container">
    <?php if ($flash): ?>
    <div class="alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="page-title">Mon espace stagiaire</div>

    <!-- Stats -->
    <div class="grid-3">
        <div class="stat-card">
            <div class="stat-icon si-navy">📋</div>
            <div><div class="stat-value"><?= count($candidatures) ?></div><div class="stat-label">Candidature(s)</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green">🎓</div>
            <div><div class="stat-value"><?= count($stages) ?></div><div class="stat-label">Stage(s)</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-amber">🔔</div>
            <div><div class="stat-value"><?= $nbNonLues ?></div><div class="stat-label">Notification(s)</div></div>
        </div>
    </div>

    <!-- Stage actif -->
    <?php if ($stageActif): ?>
    <div class="alert-info">
        <div>🎓 <strong>Stage en cours</strong> — <?= h($stageActif['reference']) ?>
            (<?= date('d/m/Y', strtotime($stageActif['date_debut'])) ?> → <?= date('d/m/Y', strtotime($stageActif['date_fin'])) ?>)
        </div>
        <div style="display:flex;gap:8px">
            <a href="/pages/lettre_public.php?id=<?= $stageActif['id'] ?>" class="btn btn-light btn-sm">🖨 Lettre</a>
            <?php if ($peutRenouveler): ?>
            <a href="?show=renouvellement" class="btn btn-light btn-sm">🔄 Renouvellement</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire : Nouvelle candidature -->
    <?php if ($show === 'candidature'): ?>
    <div class="inline-form-card">
        <div class="card-header">
            <div class="card-title">📩 Nouvelle candidature</div>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="nouvelle_candidature" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type de candidature</label>
                        <select name="type_candidature" id="type-cand" onchange="document.getElementById('row-offre').style.display=this.value==='offre'?'flex':'none'">
                            <option value="spontanee">Candidature spontanée</option>
                            <option value="offre">Sur une offre</option>
                        </select>
                    </div>
                    <div class="form-group" id="row-offre" style="display:none">
                        <label>Offre visée</label>
                        <select name="offre_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($offresDispos as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= h($o['reference'] . ' — ' . $o['titre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Direction souhaitée</label>
                        <select name="direction_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($directions as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Domaine</label>
                        <select name="domaine_id">
                            <option value="">— Aucun —</option>
                            <?php foreach ($domaines as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Lettre de motivation</label>
                        <textarea name="motivation" rows="4" placeholder="Présentez-vous et expliquez votre motivation..."></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:18px">
                    <a href="/espace-stagiaire.php" class="btn btn-light">Annuler</a>
                    <button type="submit" class="btn btn-red">Soumettre la candidature</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire : Renouvellement -->
    <?php if ($show === 'renouvellement' && $stageActif && $peutRenouveler): ?>
    <div class="inline-form-card">
        <div class="card-header">
            <div class="card-title">🔄 Demande de renouvellement — <?= h($stageActif['reference']) ?></div>
        </div>
        <div class="card-body">
            <div class="alert-info" style="margin-bottom:16px">
                Stage actuel : fin le <strong><?= date('d/m/Y', strtotime($stageActif['date_fin'])) ?></strong>
                — Renouvellement n°<?= $stageActif['nb_renouvellements'] + 1 ?>/3
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="demande_renouvellement" value="1">
                <input type="hidden" name="stage_id" value="<?= $stageActif['id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nouvelle date de fin souhaitée *</label>
                        <input type="date" name="date_fin_proposee" required
                               min="<?= date('Y-m-d', strtotime($stageActif['date_fin'] . ' +1 day')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Motif</label>
                        <textarea name="motif_demande" rows="2" placeholder="Raison du renouvellement..."></textarea>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:18px">
                    <a href="/espace-stagiaire.php" class="btn btn-light">Annuler</a>
                    <button type="submit" class="btn btn-navy">Soumettre la demande</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Candidatures & Stages -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Mes candidatures</div>
                <a href="?show=candidature" class="btn btn-red btn-sm">+ Postuler</a>
            </div>
            <?php if (!$candidatures): ?>
            <div class="empty-state">
                <div style="font-size:32px;margin-bottom:8px">📭</div>
                Aucune candidature
                <div style="margin-top:12px"><a href="?show=candidature" class="btn btn-red btn-sm">Soumettre ma candidature</a></div>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>Référence</th><th>Type</th><th>Statut</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidatures as $c): ?>
                        <tr>
                            <td class="mono fw-600"><?= h($c['reference']) ?></td>
                            <td><span class="badge <?= $c['type_candidature'] === 'offre' ? 'badge-info' : 'badge-secondary' ?>"><?= $c['type_candidature'] === 'offre' ? 'Sur offre' : 'Spontanée' ?></span></td>
                            <td><?= statusBadge($c['statut_global']) ?></td>
                            <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($c['date_candidature'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">🎓 Mes stages</div>
            </div>
            <?php if (!$stages): ?>
            <div class="empty-state"><div style="font-size:32px;margin-bottom:8px">🎓</div>Aucun stage</div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>Référence</th><th>Direction</th><th>Période</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stages as $s): ?>
                        <tr>
                            <td class="mono fw-600"><?= h($s['reference']) ?></td>
                            <td class="text-sm text-muted"><?= h($s['direction'] ?? '—') ?></td>
                            <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($s['date_debut'])) ?> → <?= date('d/m/Y', strtotime($s['date_fin'])) ?></td>
                            <td><?= statusBadge($s['statut']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications -->
    <?php if ($notifs): ?>
    <div class="card">
        <div class="card-header"><div class="card-title">🔔 Mes notifications</div></div>
        <div class="card-body">
            <?php foreach ($notifs as $n): ?>
            <div class="notif-item">
                <div class="notif-objet"><?= h($n['objet']) ?></div>
                <div class="notif-msg"><?= h($n['message']) ?></div>
                <div class="notif-date"><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/widget-amina.php'; ?>

</body>
</html>
