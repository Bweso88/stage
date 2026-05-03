<?php
/**
 * StagIA - Liste candidatures
 */
define('STAGIA_PAGE', 'Candidatures');
require_once __DIR__ . '/../../includes/header.php';

$pdo = getPDO();

// Filters
$fStatut = $_GET['statut'] ?? '';
$fDir    = (int)($_GET['direction'] ?? 0);
$fSearch = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];
if ($fStatut) { $where[] = 'c.statut_global = ?'; $params[] = $fStatut; }
if ($fDir)    { $where[] = 'c.direction_id = ?';  $params[] = $fDir; }
if ($fSearch) { $where[] = '(c.reference LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)'; $params[] = "%$fSearch%"; $params[] = "%$fSearch%"; $params[] = "%$fSearch%"; }

$sql = 'SELECT c.*, u.nom, u.prenom, o.titre as offre_titre, d.libelle as direction_libelle FROM candidatures c JOIN stagiaires s ON s.id = c.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id LEFT JOIN offres_stage o ON o.id = c.offre_id LEFT JOIN directions d ON d.id = c.direction_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.date_candidature DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidatures = $stmt->fetchAll();

$directions = $pdo->query('SELECT * FROM directions WHERE actif = 1 ORDER BY libelle')->fetchAll();
$statuts = ['soumise','niv1_en_cours','niv1_valide','validee','niv1_rejete','rejetee','complement'];
?>

<div class="page-header">
  <h1>Candidatures</h1>
  <p>Gestion et validation des candidatures de stage.</p>
</div>

<!-- Filters -->
<div class="card mb-6">
  <form method="get" class="filter-bar" style="margin-bottom:0">
    <input type="hidden" name="page" value="candidatures">
    <input type="text" name="q" class="form-control" placeholder="Recherche..." value="<?= h($fSearch) ?>" style="max-width:220px">
    <select name="statut" class="form-control" style="max-width:200px">
      <option value="">Tous les statuts</option>
      <?php foreach ($statuts as $s): ?>
      <option value="<?= $s ?>" <?= $fStatut===$s?'selected':'' ?>><?= h(ucfirst(str_replace('_',' ',$s))) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="direction" class="form-control" style="max-width:200px">
      <option value="">Toutes les directions</option>
      <?php foreach ($directions as $d): ?>
      <option value="<?= $d['id'] ?>" <?= $fDir===$d['id']?'selected':'' ?>><?= h($d['abreviation'] ?: $d['libelle']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    <a href="backoffice.php?page=candidatures" class="btn btn-secondary btn-sm">R&eacute;init.</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">📋 <?= count($candidatures) ?> candidature(s)</span>
  </div>
  <?php if (empty($candidatures)): ?>
  <div class="empty-state"><div class="empty-icon">📋</div><p>Aucune candidature trouv&eacute;e.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>R&eacute;f&eacute;rence</th>
          <th>Stagiaire</th>
          <th>Type</th>
          <th>Offre / Direction</th>
          <th>Score</th>
          <th>Workflow</th>
          <th>Statut</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($candidatures as $c): ?>
        <tr>
          <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="fw-bold" style="color:var(--navy)"><?= h($c['reference']) ?></a></td>
          <td><?= h($c['prenom'] . ' ' . $c['nom']) ?></td>
          <td><?= $c['type_candidature']==='offre'?'<span class="badge badge-info">Offre</span>':'<span class="badge badge-secondary">Spontan&eacute;e</span>' ?></td>
          <td><?= h($c['offre_titre'] ?? ($c['direction_libelle'] ?? 'N/A')) ?></td>
          <td><strong><?= number_format((float)$c['score_tri'],1) ?></strong>/100</td>
          <td><?= workflowDots($c['statut_global']) ?></td>
          <td><?= statusBadge($c['statut_global']) ?></td>
          <td class="text-sm text-muted"><?= h(date('d/m/Y', strtotime($c['date_candidature']))) ?></td>
          <td>
            <div class="td-actions">
              <a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="btn btn-primary btn-xs">D&eacute;tail</a>
              <?php if (in_array($c['statut_global'], ['soumise','niv1_en_cours'], true) && hasRole('administrateur','habilite','superviseur')): ?>
              <button class="btn btn-warning btn-xs" onclick="openModal('modal-valider-<?= $c['id'] ?>')">Valider</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Modals validation (OUTSIDE table) -->
<?php foreach ($candidatures as $c):
  if (!in_array($c['statut_global'], ['soumise','niv1_en_cours'], true)) continue;
  $niveau = $c['statut_global'] === 'soumise' ? 'niv1' : 'niv2';
?>
<div class="modal-overlay" id="modal-valider-<?= $c['id'] ?>">
  <div class="modal">
    <div class="modal-header">
      <h3>D&eacute;cision – <?= h($c['reference']) ?> (<?= $niveau === 'niv1' ? 'Niveau 1' : 'Niveau 2' ?>)</h3>
      <button class="modal-close" onclick="closeModal('modal-valider-<?= $c['id'] ?>')">×</button>
    </div>
    <form method="post" action="backoffice.php">
      <input type="hidden" name="action" value="valider_candidature">
      <input type="hidden" name="candidature_id" value="<?= $c['id'] ?>">
      <input type="hidden" name="niveau" value="<?= $niveau ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>D&eacute;cision</label>
          <select name="statut" class="form-control" required>
            <option value="valide">✅ Valider</option>
            <option value="rejete">❌ Rejeter</option>
            <option value="complement">📝 Demander un compl&eacute;ment</option>
          </select>
        </div>
        <div class="form-group mt-2">
          <label>Commentaire</label>
          <textarea name="commentaire" class="form-control" rows="3" placeholder="Observations..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-valider-<?= $c['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer la d&eacute;cision</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
