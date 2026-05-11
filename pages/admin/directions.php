<?php
// pages/admin/directions.php
requireAdmin();
if (!hasRole('administrateur')) {
    flash('Seuls les administrateurs peuvent gérer les directions.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Directions & Domaines');
$pdo = getPDO();

$show   = $_GET['show'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

$editDir = null;
$editDom = null;
if ($show === 'edit_dir' && $editId) {
    $s = $pdo->prepare('SELECT * FROM directions WHERE id = ?');
    $s->execute([$editId]);
    $editDir = $s->fetch() ?: null;
}
if ($show === 'edit_dom' && $editId) {
    $s = $pdo->prepare('SELECT * FROM domaines WHERE id = ?');
    $s->execute([$editId]);
    $editDom = $s->fetch() ?: null;
}

$directions = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM offres_stage o WHERE o.direction_id = d.id) nb_offres, (SELECT COUNT(*) FROM stages s WHERE s.direction_id = d.id) nb_stages FROM directions d ORDER BY d.libelle')->fetchAll();
$domaines   = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM candidatures c WHERE c.domaine_id = d.id) nb_cand FROM domaines d ORDER BY d.libelle')->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1>Directions &amp; Domaines</h1>
  <div style="display:flex;gap:8px">
    <a href="backoffice.php?page=directions&amp;show=new_dir" class="btn btn-danger">+ Direction</a>
    <a href="backoffice.php?page=directions&amp;show=new_dom" class="btn btn-primary">+ Domaine</a>
  </div>
</div>

<?php if ($show === 'new_dir' || $editDir): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--red)">
  <div class="card-header">
    <h3 class="card-title"><?= $editDir ? 'Modifier la direction' : 'Nouvelle direction' ?></h3>
    <a href="backoffice.php?page=directions" class="btn btn-secondary btn-sm">✕ Annuler</a>
  </div>
  <div class="card-body">
    <form method="POST" action="backoffice.php">
      <input type="hidden" name="action" value="<?= $editDir ? 'edit_direction' : 'add_direction' ?>">
      <?php if ($editDir): ?>
      <input type="hidden" name="dir_id" value="<?= (int)$editDir['id'] ?>">
      <?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="field-required">Libellé</label>
          <input class="form-control" type="text" name="libelle" required
                 value="<?= h($editDir['libelle'] ?? '') ?>"
                 placeholder="Ex: Direction des Ressources Humaines" autofocus>
        </div>
        <div class="form-group">
          <label>Abréviation</label>
          <input class="form-control" type="text" name="abreviation"
                 value="<?= h($editDir['abreviation'] ?? '') ?>"
                 placeholder="Ex: DRH" maxlength="10">
        </div>
      </div>
      <div class="form-actions" style="margin-top:16px">
        <button type="submit" class="btn btn-danger">
          <?= $editDir ? 'Enregistrer les modifications' : 'Créer la direction' ?>
        </button>
        <a href="backoffice.php?page=directions" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($show === 'new_dom' || $editDom): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--navy)">
  <div class="card-header">
    <h3 class="card-title"><?= $editDom ? 'Modifier le domaine' : 'Nouveau domaine' ?></h3>
    <a href="backoffice.php?page=directions" class="btn btn-secondary btn-sm">✕ Annuler</a>
  </div>
  <div class="card-body">
    <form method="POST" action="backoffice.php">
      <input type="hidden" name="action" value="<?= $editDom ? 'edit_domaine' : 'add_domaine' ?>">
      <?php if ($editDom): ?>
      <input type="hidden" name="dom_id" value="<?= (int)$editDom['id'] ?>">
      <?php endif; ?>
      <div class="form-group" style="max-width:420px">
        <label class="field-required">Libellé</label>
        <input class="form-control" type="text" name="libelle" required
               value="<?= h($editDom['libelle'] ?? '') ?>"
               placeholder="Ex: Informatique" autofocus>
      </div>
      <div class="form-actions" style="margin-top:16px">
        <button type="submit" class="btn btn-primary">
          <?= $editDom ? 'Enregistrer les modifications' : 'Créer le domaine' ?>
        </button>
        <a href="backoffice.php?page=directions" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Directions -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Directions (<?= count($directions) ?>)</h3>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Libellé</th><th>Abrév.</th><th>Offres</th><th>Stages</th><th>Statut</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($directions as $d): ?>
          <tr>
            <td><strong><?= h($d['libelle']) ?></strong></td>
            <td><code><?= h($d['abreviation'] ?? '—') ?></code></td>
            <td><?= (int)$d['nb_offres'] ?></td>
            <td><?= (int)$d['nb_stages'] ?></td>
            <td><?= $d['actif']
              ? '<span class="badge badge-success">Active</span>'
              : '<span class="badge badge-secondary">Inactive</span>' ?></td>
            <td class="td-actions">
              <a href="backoffice.php?page=directions&amp;show=edit_dir&amp;id=<?= (int)$d['id'] ?>"
                 class="btn btn-xs btn-secondary">✏️ Modifier</a>
              <a href="backoffice.php?action=toggle_dir&amp;id=<?= (int)$d['id'] ?>"
                 class="btn btn-xs btn-secondary"><?= $d['actif'] ? '🔒' : '🔓' ?></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$directions): ?>
          <tr><td colspan="6" class="text-muted" style="text-align:center;padding:20px">Aucune direction.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Domaines -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Domaines (<?= count($domaines) ?>)</h3>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Libellé</th><th>Candidatures</th><th>Statut</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($domaines as $d): ?>
          <tr>
            <td><strong><?= h($d['libelle']) ?></strong></td>
            <td><?= (int)$d['nb_cand'] ?></td>
            <td><?= $d['actif']
              ? '<span class="badge badge-success">Actif</span>'
              : '<span class="badge badge-secondary">Inactif</span>' ?></td>
            <td class="td-actions">
              <a href="backoffice.php?page=directions&amp;show=edit_dom&amp;id=<?= (int)$d['id'] ?>"
                 class="btn btn-xs btn-secondary">✏️ Modifier</a>
              <a href="backoffice.php?action=toggle_dom&amp;id=<?= (int)$d['id'] ?>"
                 class="btn btn-xs btn-secondary"><?= $d['actif'] ? '🔒' : '🔓' ?></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$domaines): ?>
          <tr><td colspan="4" class="text-muted" style="text-align:center;padding:20px">Aucun domaine.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
