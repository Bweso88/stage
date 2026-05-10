<?php
// pages/admin/directions.php
requireAdmin();
if (!hasRole('administrateur')) {
    flash('Seuls les administrateurs peuvent gérer les directions.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Directions & Domaines');
$pdo = getPDO();

$directions = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM offres_stage o WHERE o.direction_id = d.id) AS nb_offres, (SELECT COUNT(*) FROM stages s WHERE s.direction_id = d.id) AS nb_stages FROM directions d ORDER BY d.libelle')->fetchAll();
$domaines   = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM candidatures c WHERE c.domaine_id = d.id) AS nb_cand FROM domaines d ORDER BY d.libelle')->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Directions &amp; Domaines</h1>
  <div style="display:flex;gap:8px">
    <button class="btn btn-danger" onclick="openModal('modal-add-dir')">+ Direction</button>
    <button class="btn btn-primary" onclick="openModal('modal-add-dom')">+ Domaine</button>
  </div>
</div>

<div class="section-grid">
  <!-- Directions -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Directions (<?= count($directions) ?>)</h3></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Libellé</th><th>Abréviation</th><th>Offres</th><th>Stages</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($directions as $d): ?>
          <tr>
            <td><strong><?= h($d['libelle']) ?></strong></td>
            <td><code><?= h($d['abreviation'] ?? '—') ?></code></td>
            <td><?= (int)$d['nb_offres'] ?></td>
            <td><?= (int)$d['nb_stages'] ?></td>
            <td><?= $d['actif'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
            <td>
              <button class="btn btn-sm btn-secondary" onclick="openModal('modal-edit-dir-<?= $d['id'] ?>')">✏️ Modifier</button>
              <a href="backoffice.php?action=toggle_dir&id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary"><?= $d['actif'] ? '🔒 Désactiver' : '🔓 Activer' ?></a>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Domaines -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Domaines (<?= count($domaines) ?>)</h3></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Libellé</th><th>Candidatures</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($domaines as $d): ?>
          <tr>
            <td><strong><?= h($d['libelle']) ?></strong></td>
            <td><?= (int)$d['nb_cand'] ?></td>
            <td><?= $d['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-secondary">Inactif</span>' ?></td>
            <td>
              <button class="btn btn-sm btn-secondary" onclick="openModal('modal-edit-dom-<?= $d['id'] ?>')">✏️ Modifier</button>
              <a href="backoffice.php?action=toggle_dom&id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary"><?= $d['actif'] ? '🔒 Désactiver' : '🔓 Activer' ?></a>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Ajouter Direction -->
<div class="modal-overlay" id="modal-add-dir">
  <div class="modal">
    <div class="modal-header">
      <h3>Nouvelle direction</h3>
      <button class="modal-close" onclick="closeModal('modal-add-dir')">✕</button>
    </div>
    <form method="POST" action="backoffice.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_direction">
      <div class="modal-body">
        <div class="form-group">
          <label>Libellé *</label>
          <input class="form-control" type="text" name="libelle" required placeholder="Ex: Direction des Ressources Humaines">
        </div>
        <div class="form-group">
          <label>Abréviation</label>
          <input class="form-control" type="text" name="abreviation" placeholder="Ex: DRH" maxlength="10">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-dir')">Annuler</button>
        <button type="submit" class="btn btn-danger">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Ajouter Domaine -->
<div class="modal-overlay" id="modal-add-dom">
  <div class="modal">
    <div class="modal-header">
      <h3>Nouveau domaine</h3>
      <button class="modal-close" onclick="closeModal('modal-add-dom')">✕</button>
    </div>
    <form method="POST" action="backoffice.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_domaine">
      <div class="modal-body">
        <div class="form-group">
          <label>Libellé *</label>
          <input class="form-control" type="text" name="libelle" required placeholder="Ex: Informatique">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-dom')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modals Edit Direction -->
<?php foreach ($directions as $d): ?>
<div class="modal-overlay" id="modal-edit-dir-<?= $d['id'] ?>">
  <div class="modal">
    <div class="modal-header">
      <h3>Modifier — <?= h($d['libelle']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-edit-dir-<?= $d['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="backoffice.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_direction">
      <input type="hidden" name="dir_id" value="<?= $d['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Libellé *</label>
          <input class="form-control" type="text" name="libelle" value="<?= h($d['libelle']) ?>" required>
        </div>
        <div class="form-group">
          <label>Abréviation</label>
          <input class="form-control" type="text" name="abreviation" value="<?= h($d['abreviation'] ?? '') ?>" maxlength="10">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-dir-<?= $d['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-danger">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>

<!-- Modals Edit Domaine -->
<?php foreach ($domaines as $d): ?>
<div class="modal-overlay" id="modal-edit-dom-<?= $d['id'] ?>">
  <div class="modal">
    <div class="modal-header">
      <h3>Modifier — <?= h($d['libelle']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-edit-dom-<?= $d['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="backoffice.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_domaine">
      <input type="hidden" name="dom_id" value="<?= $d['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Libellé *</label>
          <input class="form-control" type="text" name="libelle" value="<?= h($d['libelle']) ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-dom-<?= $d['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
