<?php
$pdo = getPDO();

$directions = $pdo->query("
    SELECT d.*, (SELECT COUNT(*) FROM offres_stage o WHERE o.direction_id = d.id) AS nb_offres,
                (SELECT COUNT(*) FROM stages s WHERE s.direction_id = d.id) AS nb_stages
    FROM directions d ORDER BY d.libelle
")->fetchAll();

$domaines = $pdo->query("
    SELECT dom.*, (SELECT COUNT(*) FROM candidatures c WHERE c.domaine_id = dom.id) AS nb_cand
    FROM domaines dom ORDER BY dom.libelle
")->fetchAll();
?>

<div class="page-header">
    <h1>Directions & Domaines</h1>
</div>

<div class="section-grid">
    <!-- Directions -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏢 Directions</div>
                <button class="btn btn-red btn-sm" onclick="openModal('modal-add-dir')">+ Ajouter</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Abrév.</th>
                            <th>Offres</th>
                            <th>Stages</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$directions): ?>
                        <tr><td colspan="6"><div class="empty-state">Aucune direction</div></td></tr>
                        <?php else: ?>
                        <?php foreach ($directions as $d): ?>
                        <tr>
                            <td class="fw-600"><?= h($d['libelle']) ?></td>
                            <td><span class="badge badge-blue"><?= h($d['abreviation'] ?? '—') ?></span></td>
                            <td><?= $d['nb_offres'] ?></td>
                            <td><?= $d['nb_stages'] ?></td>
                            <td><?= $d['actif'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-ghost btn-xs" onclick="openModal('modal-edit-dir-<?= $d['id'] ?>')">Éditer</button>
                                <a href="backoffice.php?toggle_dir=<?= $d['id'] ?>" class="btn btn-ghost btn-xs"><?= $d['actif'] ? 'Désactiver' : 'Activer' ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Domaines -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏷️ Domaines</div>
                <button class="btn btn-red btn-sm" onclick="openModal('modal-add-dom')">+ Ajouter</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Candidatures</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$domaines): ?>
                        <tr><td colspan="4"><div class="empty-state">Aucun domaine</div></td></tr>
                        <?php else: ?>
                        <?php foreach ($domaines as $d): ?>
                        <tr>
                            <td class="fw-600"><?= h($d['libelle']) ?></td>
                            <td><?= $d['nb_cand'] ?></td>
                            <td><?= $d['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-ghost btn-xs" onclick="openModal('modal-edit-dom-<?= $d['id'] ?>')">Éditer</button>
                                <a href="backoffice.php?toggle_dom=<?= $d['id'] ?>" class="btn btn-ghost btn-xs"><?= $d['actif'] ? 'Désactiver' : 'Activer' ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal add direction -->
<div class="modal-overlay" id="modal-add-dir">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvelle direction</div>
            <button class="modal-close" onclick="closeModal('modal-add-dir')">×</button>
        </div>
        <form method="POST" action="backoffice.php?page=directions">
            <input type="hidden" name="add_direction" value="1">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Libellé *</label>
                    <input type="text" name="libelle" required placeholder="Direction des Ressources Humaines">
                </div>
                <div class="form-group">
                    <label>Abréviation</label>
                    <input type="text" name="abreviation" placeholder="DRH" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-dir')">Annuler</button>
                <button type="submit" class="btn btn-red">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal add domaine -->
<div class="modal-overlay" id="modal-add-dom">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouveau domaine</div>
            <button class="modal-close" onclick="closeModal('modal-add-dom')">×</button>
        </div>
        <form method="POST" action="backoffice.php?page=directions">
            <input type="hidden" name="add_domaine" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label>Libellé *</label>
                    <input type="text" name="libelle_dom" required placeholder="Informatique">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-dom')">Annuler</button>
                <button type="submit" class="btn btn-red">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modals édition directions (HORS tableau) -->
<?php foreach ($directions as $d): ?>
<div class="modal-overlay" id="modal-edit-dir-<?= $d['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier la direction</div>
            <button class="modal-close" onclick="closeModal('modal-edit-dir-<?= $d['id'] ?>')">×</button>
        </div>
        <form method="POST" action="backoffice.php?page=directions">
            <input type="hidden" name="edit_direction" value="1">
            <input type="hidden" name="dir_id" value="<?= $d['id'] ?>">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Libellé *</label>
                    <input type="text" name="libelle" value="<?= h($d['libelle']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Abréviation</label>
                    <input type="text" name="abreviation" value="<?= h($d['abreviation'] ?? '') ?>" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-dir-<?= $d['id'] ?>')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Modals édition domaines (HORS tableau) -->
<?php foreach ($domaines as $d): ?>
<div class="modal-overlay" id="modal-edit-dom-<?= $d['id'] ?>">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier le domaine</div>
            <button class="modal-close" onclick="closeModal('modal-edit-dom-<?= $d['id'] ?>')">×</button>
        </div>
        <form method="POST" action="backoffice.php?page=directions">
            <input type="hidden" name="edit_domaine" value="1">
            <input type="hidden" name="dom_id" value="<?= $d['id'] ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Libellé *</label>
                    <input type="text" name="libelle_dom" value="<?= h($d['libelle']) ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-dom-<?= $d['id'] ?>')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
