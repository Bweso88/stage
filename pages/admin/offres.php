<?php
$pdo = getPDO();

$filtre = $_GET['filtre'] ?? '';
$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($filtre && $filtre !== 'tous') {
    $where[]  = 'o.statut = ?';
    $params[] = $filtre;
}
if ($search) {
    $where[]  = "(o.titre LIKE ? OR o.reference LIKE ?)";
    $sc       = "%$search%";
    $params   = array_merge($params, [$sc, $sc]);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT o.*, d.libelle AS direction, dom.libelle AS domaine,
           u.nom AS createur_nom, u.prenom AS createur_prenom,
           (SELECT COUNT(*) FROM candidatures c WHERE c.offre_id = o.id) AS nb_candidats
    FROM offres_stage o
    LEFT JOIN directions d ON d.id = o.direction_id
    LEFT JOIN domaines dom ON dom.id = o.domaine_id
    LEFT JOIN utilisateurs u ON u.id = o.creee_par
    $whereSQL
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$offres = $stmt->fetchAll();

$directions = $pdo->query("SELECT id, libelle FROM directions WHERE actif = 1 ORDER BY libelle")->fetchAll();
$domaines   = $pdo->query("SELECT id, libelle FROM domaines WHERE actif = 1 ORDER BY libelle")->fetchAll();
$niveaux    = ['bac','bac+2','bac+3','bac+4','bac+5','doctorat'];
?>

<div class="page-header">
    <h1>Offres de stage</h1>
    <?php if (hasRole('habilite','administrateur','superviseur')): ?>
    <button class="btn btn-red" onclick="openModal('modal-creer-offre')">+ Créer une offre</button>
    <?php endif; ?>
</div>

<div class="filter-bar">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="page" value="offres">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher..." style="width:200px">
        <select name="filtre" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <option value="ouverte"  <?= $filtre === 'ouverte'  ? 'selected' : '' ?>>Ouvertes</option>
            <option value="fermee"   <?= $filtre === 'fermee'   ? 'selected' : '' ?>>Fermées</option>
            <option value="archivee" <?= $filtre === 'archivee' ? 'selected' : '' ?>>Archivées</option>
        </select>
        <button type="submit" class="btn btn-navy btn-sm">Filtrer</button>
        <?php if ($filtre || $search): ?>
        <a href="backoffice.php?page=offres" class="btn btn-ghost btn-sm">✕</a>
        <?php endif; ?>
    </form>
    <span class="text-muted text-sm" style="margin-left:auto"><?= count($offres) ?> offre(s)</span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Titre</th>
                    <th>Direction</th>
                    <th>Niveau min.</th>
                    <th>Places</th>
                    <th>Candidatures</th>
                    <th>Date limite</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$offres): ?>
                <tr><td colspan="9"><div class="empty-state"><div class="icon">📢</div>Aucune offre</div></td></tr>
                <?php else: ?>
                <?php foreach ($offres as $o): ?>
                <tr>
                    <td class="mono"><?= h($o['reference']) ?></td>
                    <td>
                        <div class="fw-600"><?= h($o['titre']) ?></div>
                        <div class="text-muted text-sm"><?= h($o['domaine'] ?? '') ?></div>
                    </td>
                    <td class="text-sm"><?= h($o['direction'] ?? '—') ?></td>
                    <td><span class="badge badge-blue"><?= h($o['niveau_minimum']) ?></span></td>
                    <td><?= $o['nb_places'] ?></td>
                    <td>
                        <span class="badge <?= $o['nb_candidats'] > 0 ? 'badge-teal' : 'badge-gray' ?>"><?= $o['nb_candidats'] ?></span>
                    </td>
                    <td class="text-sm">
                        <?php if ($o['date_limite']): ?>
                        <?php $dl = strtotime($o['date_limite']); ?>
                        <span style="<?= $dl < time() ? 'color:#dc2626' : '' ?>"><?= date('d/m/Y', $dl) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= statusBadge($o['statut']) ?></td>
                    <td style="white-space:nowrap">
                        <a href="backoffice.php?toggle_offre=<?= $o['id'] ?>" class="btn btn-ghost btn-xs" onclick="return confirm('Changer le statut ?')">
                            <?= $o['statut'] === 'ouverte' ? 'Fermer' : 'Rouvrir' ?>
                        </a>
                        <?php if (hasRole('habilite','administrateur','superviseur')): ?>
                        <button class="btn btn-ghost btn-xs" onclick="openModal('modal-edit-<?= $o['id'] ?>')">Éditer</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal créer offre -->
<?php if (hasRole('habilite','administrateur','superviseur')): ?>
<div class="modal-overlay" id="modal-creer-offre">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">Nouvelle offre de stage</div>
            <button class="modal-close" onclick="closeModal('modal-creer-offre')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=creer_offre">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Titre de l'offre *</label>
                        <input type="text" name="titre" required placeholder="Ex: Stage Développement Web">
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
                    <div class="form-group">
                        <label>Niveau minimum</label>
                        <select name="niveau_minimum">
                            <?php foreach ($niveaux as $n): ?>
                            <option value="<?= $n ?>" <?= $n === 'bac+3' ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre de places</label>
                        <input type="number" name="nb_places" value="1" min="1" max="50">
                    </div>
                    <div class="form-group">
                        <label>Date de publication</label>
                        <input type="date" name="date_publication" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date limite de candidature</label>
                        <input type="date" name="date_limite">
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Description du stage..."></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Profil recherché</label>
                        <textarea name="profil_recherche" rows="2" placeholder="Compétences, qualités requises..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-creer-offre')">Annuler</button>
                <button type="submit" class="btn btn-red">Créer l'offre</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modals édition offres (HORS tableau) -->
<?php foreach ($offres as $o): ?>
<div class="modal-overlay" id="modal-edit-<?= $o['id'] ?>">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">Modifier — <?= h($o['reference']) ?></div>
            <button class="modal-close" onclick="closeModal('modal-edit-<?= $o['id'] ?>')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=modifier_offre">
            <?= csrfField() ?>
            <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Titre *</label>
                        <input type="text" name="titre" value="<?= h($o['titre']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Direction</label>
                        <select name="direction_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($directions as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $o['direction_id'] == $d['id'] ? 'selected' : '' ?>><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Domaine</label>
                        <select name="domaine_id">
                            <option value="">— Aucun —</option>
                            <?php foreach ($domaines as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $o['domaine_id'] == $d['id'] ? 'selected' : '' ?>><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Niveau minimum</label>
                        <select name="niveau_minimum">
                            <?php foreach ($niveaux as $n): ?>
                            <option value="<?= $n ?>" <?= $o['niveau_minimum'] === $n ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre de places</label>
                        <input type="number" name="nb_places" value="<?= $o['nb_places'] ?>" min="1">
                    </div>
                    <div class="form-group">
                        <label>Date limite</label>
                        <input type="date" name="date_limite" value="<?= h($o['date_limite'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= h($o['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Profil recherché</label>
                        <textarea name="profil_recherche" rows="2"><?= h($o['profil_recherche'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-<?= $o['id'] ?>')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
