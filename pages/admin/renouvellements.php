<?php
$pdo = getPDO();

$filtre = $_GET['filtre'] ?? 'en_attente';

$where = [];
$params = [];
if ($filtre && $filtre !== 'tous') {
    $where[] = 'r.statut = ?';
    $params[] = $filtre;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.*,
           s.reference AS ref_stage, s.date_fin AS stage_date_fin, s.stagiaire_id,
           u.nom, u.prenom,
           d.libelle AS direction,
           tu.nom AS trait_nom, tu.prenom AS trait_prenom
    FROM renouvellements_stage r
    JOIN stages s ON s.id = r.stage_id
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN utilisateurs tu ON tu.id = r.traite_par
    $whereSQL
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$renouvellements = $stmt->fetchAll();

$counts = [];
foreach (['en_attente','valide','rejete','precisions'] as $st) {
    $counts[$st] = (int)$pdo->prepare("SELECT COUNT(*) FROM renouvellements_stage WHERE statut = ?")->execute([$st]) ? $pdo->query("SELECT COUNT(*) FROM renouvellements_stage WHERE statut = '$st'")->fetchColumn() : 0;
}
$counts['tous'] = (int)$pdo->query("SELECT COUNT(*) FROM renouvellements_stage")->fetchColumn();

$filtres = [
    'tous'       => 'Tous (' . $counts['tous'] . ')',
    'en_attente' => 'En attente (' . $counts['en_attente'] . ')',
    'valide'     => 'Validés',
    'rejete'     => 'Rejetés',
    'precisions' => 'Précisions requises',
];
?>

<div class="page-header">
    <h1>Renouvellements de stage</h1>
</div>

<div class="filter-bar">
    <?php foreach ($filtres as $val => $label): ?>
    <a href="backoffice.php?page=renouvellements&filtre=<?= $val ?>" class="btn <?= $filtre === $val ? 'btn-navy' : 'btn-ghost' ?> btn-sm">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Stagiaire</th>
                    <th>Direction</th>
                    <th>N°</th>
                    <th>Période proposée</th>
                    <th>Durée</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$renouvellements): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="icon">🔄</div>Aucun renouvellement</div></td></tr>
                <?php else: ?>
                <?php foreach ($renouvellements as $r): ?>
                <tr>
                    <td><a href="backoffice.php?page=stage_detail&id=<?= $r['stage_id'] ?>" class="mono"><?= h($r['ref_stage']) ?></a></td>
                    <td class="fw-600"><?= h($r['prenom'] . ' ' . $r['nom']) ?></td>
                    <td class="text-sm"><?= h($r['direction'] ?? '—') ?></td>
                    <td><?= $r['numero_renouvellement'] ?></td>
                    <td class="text-sm">
                        <?= date('d/m/Y', strtotime($r['date_debut_proposee'])) ?> →<br>
                        <?= date('d/m/Y', strtotime($r['date_fin_proposee'])) ?>
                    </td>
                    <td><?= $r['duree_ajoutee_mois'] ?> mois</td>
                    <td class="text-sm" style="max-width:150px;white-space:normal">
                        <?= $r['motif_demande'] ? h(substr($r['motif_demande'], 0, 60)) . (strlen($r['motif_demande']) > 60 ? '…' : '') : '—' ?>
                    </td>
                    <td><?= statusBadge($r['statut']) ?></td>
                    <td>
                        <?php if ($r['statut'] === 'en_attente' && hasRole('habilite','administrateur','superviseur')): ?>
                        <button class="btn btn-navy btn-xs" onclick="openModal('modal-dec-<?= $r['id'] ?>')">Décision</button>
                        <?php else: ?>
                        <span class="text-muted text-sm"><?= $r['trait_nom'] ? h($r['trait_prenom'] . ' ' . $r['trait_nom']) : '—' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals de décision (HORS tableau) -->
<?php foreach ($renouvellements as $r): ?>
<?php if ($r['statut'] === 'en_attente'): ?>
<div class="modal-overlay" id="modal-dec-<?= $r['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Décision — Renouvellement #<?= $r['numero_renouvellement'] ?> de <?= h($r['ref_stage']) ?></div>
            <button class="modal-close" onclick="closeModal('modal-dec-<?= $r['id'] ?>')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=valider_renouvellement">
            <input type="hidden" name="renouvellement_id" value="<?= $r['id'] ?>">
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom:16px">
                    <strong><?= h($r['prenom'] . ' ' . $r['nom']) ?></strong> —
                    <?= date('d/m/Y', strtotime($r['date_debut_proposee'])) ?> → <?= date('d/m/Y', strtotime($r['date_fin_proposee'])) ?>
                    (<?= $r['duree_ajoutee_mois'] ?> mois)
                </div>
                <?php if ($r['motif_demande']): ?>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Motif fourni</label>
                    <div style="padding:10px;background:var(--gray-bg);border-radius:6px;font-size:13px"><?= h($r['motif_demande']) ?></div>
                </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Décision *</label>
                    <select name="decision" required>
                        <option value="valide">✓ Approuver</option>
                        <option value="rejete">✗ Rejeter</option>
                        <option value="precisions">⚠ Demander des précisions</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Commentaire</label>
                    <textarea name="commentaire" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-dec-<?= $r['id'] ?>')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
