<?php
$pdo = getPDO();
$search = trim($_GET['q'] ?? '');
$params = [];
$whereSQL = '';
if ($search) {
    $whereSQL = "WHERE (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR s.telephone LIKE ?)";
    $sc = "%$search%";
    $params = [$sc, $sc, $sc, $sc];
}

$stmt = $pdo->prepare("
    SELECT s.*, u.nom, u.prenom, u.email, u.civilite, u.actif,
           d.libelle AS domaine,
           (SELECT COUNT(*) FROM candidatures c WHERE c.stagiaire_id = s.id) AS nb_cand,
           (SELECT COUNT(*) FROM stages sg WHERE sg.stagiaire_id = s.id AND sg.statut IN ('en_cours','renouvele','preparation')) AS stage_actif
    FROM stagiaires s
    JOIN utilisateurs u ON u.id = s.utilisateur_id
    LEFT JOIN domaines d ON d.id = s.domaine_principal_id
    $whereSQL
    ORDER BY s.date_inscription DESC
");
$stmt->execute($params);
$stagiaires = $stmt->fetchAll();
?>

<div class="page-header">
    <h1>Stagiaires</h1>
    <a href="backoffice.php?page=stagiaires&export=csv" class="btn btn-ghost btn-sm">📊 Export CSV</a>
</div>

<div class="filter-bar">
    <form method="GET" style="display:flex;gap:8px">
        <input type="hidden" name="page" value="stagiaires">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher..." style="width:240px">
        <button type="submit" class="btn btn-navy btn-sm">Filtrer</button>
        <?php if ($search): ?>
        <a href="backoffice.php?page=stagiaires" class="btn btn-ghost btn-sm">✕</a>
        <?php endif; ?>
    </form>
    <span class="text-muted text-sm" style="margin-left:auto"><?= count($stagiaires) ?> stagiaire(s)</span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Stagiaire</th>
                    <th>Contact</th>
                    <th>Niveau</th>
                    <th>Domaine</th>
                    <th>Candidatures</th>
                    <th>Stage actif</th>
                    <th>Score</th>
                    <th>Inscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$stagiaires): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="icon">👥</div>Aucun stagiaire</div></td></tr>
                <?php else: ?>
                <?php foreach ($stagiaires as $s): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar"><?= strtoupper(substr($s['prenom'],0,1).substr($s['nom'],0,1)) ?></div>
                            <div>
                                <div class="fw-600"><?= h($s['civilite'] . ' ' . $s['prenom'] . ' ' . $s['nom']) ?></div>
                                <div class="text-muted text-sm"><?= $s['actif'] ? '' : '<span style="color:#dc2626">Inactif</span>' ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="text-sm"><?= h($s['email']) ?></div>
                        <div class="text-muted text-sm"><?= h($s['telephone'] ?? '') ?></div>
                    </td>
                    <td><span class="badge badge-blue"><?= h($s['niveau_etude'] ?? '—') ?></span></td>
                    <td class="text-sm"><?= h($s['domaine'] ?? '—') ?></td>
                    <td><span class="badge badge-gray"><?= $s['nb_cand'] ?></span></td>
                    <td>
                        <?php if ($s['stage_actif']): ?>
                        <span class="badge badge-success">En stage</span>
                        <?php else: ?>
                        <span class="badge badge-gray">Non</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $score = calculerScore($s['id']); ?>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="progress-wrap" style="width:50px">
                                <div class="progress-bar <?= $score >= 70 ? 'green' : ($score >= 40 ? 'amber' : 'red') ?>" style="width:<?= $score ?>%"></div>
                            </div>
                            <span class="text-sm"><?= number_format($score,0) ?></span>
                        </div>
                    </td>
                    <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($s['date_inscription'])) ?></td>
                    <td>
                        <a href="backoffice.php?page=stagiaire_detail&id=<?= $s['id'] ?>" class="btn btn-ghost btn-xs">Voir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
