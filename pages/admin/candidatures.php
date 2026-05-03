<?php
$pdo = getPDO();

$filtre = $_GET['filtre'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($filtre && $filtre !== 'tous') {
    $where[] = 'c.statut_global = ?';
    $params[] = $filtre;
}
if ($search) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR c.reference LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT c.*, u.nom, u.prenom,
           d.libelle AS direction, dom.libelle AS domaine
    FROM candidatures c
    JOIN stagiaires s ON s.id = c.stagiaire_id
    JOIN utilisateurs u ON u.id = s.utilisateur_id
    LEFT JOIN directions d ON d.id = c.direction_id
    LEFT JOIN domaines dom ON dom.id = c.domaine_id
    $whereSQL
    ORDER BY c.date_candidature DESC
");
$stmt->execute($params);
$candidatures = $stmt->fetchAll();

$statuts = [
    ''             => 'Tous les statuts',
    'soumise'      => 'Soumises',
    'niv1_en_cours'=> 'Niv.1 en cours',
    'niv1_valide'  => 'Niv.1 validées',
    'validee'      => 'Validées',
    'niv1_rejete'  => 'Niv.1 rejetées',
    'rejetee'      => 'Rejetées',
    'complement'   => 'Complément requis',
];
?>

<div class="page-header">
    <h1>Candidatures</h1>
    <?php if (hasRole('habilite','administrateur')): ?>
    <button class="btn btn-red" onclick="openModal('modal-nouvelle-cand')">+ Nouvelle candidature</button>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="filter-bar">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="page" value="candidatures">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher..." style="width:220px">
        <select name="filtre" onchange="this.form.submit()">
            <?php foreach ($statuts as $val => $label): ?>
            <option value="<?= $val ?>" <?= $filtre === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-navy btn-sm">Filtrer</button>
        <?php if ($filtre || $search): ?>
        <a href="backoffice.php?page=candidatures" class="btn btn-ghost btn-sm">✕ Réinitialiser</a>
        <?php endif; ?>
    </form>
    <span class="text-muted text-sm" style="margin-left:auto"><?= count($candidatures) ?> résultat(s)</span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Stagiaire</th>
                    <th>Type</th>
                    <th>Direction</th>
                    <th>Score</th>
                    <th>Workflow</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$candidatures): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="icon">📭</div>Aucune candidature</div></td></tr>
                <?php else: ?>
                <?php foreach ($candidatures as $c): ?>
                <tr>
                    <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="mono"><?= h($c['reference']) ?></a></td>
                    <td>
                        <div class="fw-600"><?= h($c['prenom'] . ' ' . $c['nom']) ?></div>
                        <?php if ($c['domaine']): ?>
                        <div class="text-muted text-sm"><?= h($c['domaine']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $c['type_candidature'] === 'offre' ? 'badge-blue' : 'badge-gray' ?>"><?= $c['type_candidature'] === 'offre' ? 'Sur offre' : 'Spontanée' ?></span></td>
                    <td class="text-sm"><?= h($c['direction'] ?? '—') ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="progress-wrap" style="width:60px">
                                <div class="progress-bar <?= $c['score_tri'] >= 70 ? 'green' : ($c['score_tri'] >= 40 ? 'amber' : 'red') ?>" style="width:<?= min(100,(float)$c['score_tri']) ?>%"></div>
                            </div>
                            <span class="text-sm"><?= number_format((float)$c['score_tri'], 0) ?></span>
                        </div>
                    </td>
                    <td><?= workflowDots($c['statut_global']) ?></td>
                    <td><?= statusBadge($c['statut_global']) ?></td>
                    <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($c['date_candidature'])) ?></td>
                    <td>
                        <a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="btn btn-ghost btn-xs">Voir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal nouvelle candidature -->
<?php if (hasRole('habilite','administrateur')): ?>
<?php
$stagiaires = $pdo->query("SELECT s.id, u.nom, u.prenom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id ORDER BY u.nom")->fetchAll();
$offres = $pdo->query("SELECT id, reference, titre FROM offres_stage WHERE statut = 'ouverte' ORDER BY titre")->fetchAll();
$directions = $pdo->query("SELECT id, libelle FROM directions WHERE actif = 1 ORDER BY libelle")->fetchAll();
$domaines = $pdo->query("SELECT id, libelle FROM domaines WHERE actif = 1 ORDER BY libelle")->fetchAll();
?>
<div class="modal-overlay" id="modal-nouvelle-cand">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvelle candidature</div>
            <button class="modal-close" onclick="closeModal('modal-nouvelle-cand')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=soumettre_candidature">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Stagiaire *</label>
                        <select name="stagiaire_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($stagiaires as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= h($s['prenom'] . ' ' . $s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type de candidature</label>
                        <select name="type_candidature" id="nc-type" onchange="toggleOffre()">
                            <option value="spontanee">Spontanée</option>
                            <option value="offre">Sur offre</option>
                        </select>
                    </div>
                    <div class="form-group" id="nc-offre-wrap" style="display:none">
                        <label>Offre</label>
                        <select name="offre_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($offres as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= h($o['reference'] . ' — ' . $o['titre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Direction</label>
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
                        <label>Motivation</label>
                        <textarea name="motivation" rows="3" placeholder="Lettre de motivation..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-nouvelle-cand')">Annuler</button>
                <button type="submit" class="btn btn-red">Soumettre</button>
            </div>
        </form>
    </div>
</div>
<script>
function toggleOffre() {
    const t = document.getElementById('nc-type').value;
    document.getElementById('nc-offre-wrap').style.display = t === 'offre' ? '' : 'none';
}
</script>
<?php endif; ?>
