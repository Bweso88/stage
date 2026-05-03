<?php
$pdo = getPDO();

$tab    = $_GET['tab']    ?? 'actifs';
$search = trim($_GET['q'] ?? '');

$statutsActifs   = ['preparation', 'en_cours', 'renouvele'];
$statutsArchives = ['termine', 'interrompu', 'annule'];
$statutsAll      = $tab === 'archives' ? $statutsArchives : $statutsActifs;

$placeholders = implode(',', array_fill(0, count($statutsAll), '?'));
$params = $statutsAll;

$whereExtra = '';
if ($search) {
    $whereExtra = "AND (u.nom LIKE ? OR u.prenom LIKE ? OR s.reference LIKE ?)";
    $sc = "%$search%";
    $params = array_merge($params, [$sc, $sc, $sc]);
}

$stmt = $pdo->prepare("
    SELECT s.*,
           u.nom, u.prenom,
           d.libelle AS direction,
           dom.libelle AS domaine,
           eu.nom AS enc_nom, eu.prenom AS enc_prenom
    FROM stages s
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN domaines dom ON dom.id = s.domaine_id
    LEFT JOIN utilisateurs eu ON eu.id = s.encadrant_id
    WHERE s.statut IN ($placeholders) $whereExtra
    ORDER BY s.date_fin ASC
");
$stmt->execute($params);
$stages = $stmt->fetchAll();

$nbActifs   = (int)$pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('preparation','en_cours','renouvele')")->fetchColumn();
$nbArchives = (int)$pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('termine','interrompu','annule')")->fetchColumn();
?>

<div class="page-header">
    <h1>Stages</h1>
</div>

<!-- Onglets -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--gray-line)">
    <a href="backoffice.php?page=stages&tab=actifs" class="btn btn-ghost btn-sm" style="border-radius:8px 8px 0 0;border-bottom:2px solid <?= $tab !== 'archives' ? 'var(--navy)' : 'transparent' ?>;margin-bottom:-2px">
        Actifs <span class="badge badge-blue" style="margin-left:4px"><?= $nbActifs ?></span>
    </a>
    <a href="backoffice.php?page=stages&tab=archives" class="btn btn-ghost btn-sm" style="border-radius:8px 8px 0 0;border-bottom:2px solid <?= $tab === 'archives' ? 'var(--navy)' : 'transparent' ?>;margin-bottom:-2px">
        Archives <span class="badge badge-gray" style="margin-left:4px"><?= $nbArchives ?></span>
    </a>
</div>

<div class="filter-bar">
    <form method="GET" style="display:flex;gap:8px">
        <input type="hidden" name="page" value="stages">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher..." style="width:220px">
        <button type="submit" class="btn btn-navy btn-sm">Filtrer</button>
        <?php if ($search): ?>
        <a href="backoffice.php?page=stages&tab=<?= h($tab) ?>" class="btn btn-ghost btn-sm">✕</a>
        <?php endif; ?>
    </form>
    <span class="text-muted text-sm" style="margin-left:auto"><?= count($stages) ?> résultat(s)</span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Stagiaire</th>
                    <th>Direction</th>
                    <th>Encadrant</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Durée (mois)</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$stages): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="icon">🎓</div>Aucun stage</div></td></tr>
                <?php else: ?>
                <?php foreach ($stages as $s): ?>
                <?php
                    $fin = strtotime($s['date_fin']);
                    $today = time();
                    $jours = (int)(($fin - $today) / 86400);
                    $finStyle = ($tab !== 'archives' && $jours < 7) ? 'color:#dc2626;font-weight:600' : (($tab !== 'archives' && $jours < 14) ? 'color:#d97706' : '');
                ?>
                <tr>
                    <td><a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="mono"><?= h($s['reference']) ?></a></td>
                    <td class="fw-600"><?= h($s['prenom'] . ' ' . $s['nom']) ?></td>
                    <td class="text-sm"><?= h($s['direction'] ?? '—') ?></td>
                    <td class="text-sm"><?= $s['enc_nom'] ? h($s['enc_prenom'] . ' ' . $s['enc_nom']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= date('d/m/Y', strtotime($s['date_debut'])) ?></td>
                    <td style="<?= $finStyle ?>"><?= date('d/m/Y', $fin) ?></td>
                    <td><?= number_format((float)$s['duree_totale_mois'], 1) ?></td>
                    <td><?= statusBadge($s['statut']) ?></td>
                    <td>
                        <a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="btn btn-ghost btn-xs">Voir</a>
                        <a href="backoffice.php?page=lettre_stage&id=<?= $s['id'] ?>" class="btn btn-ghost btn-xs" target="_blank">📄</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
