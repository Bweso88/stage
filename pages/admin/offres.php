<?php
// pages/admin/offres.php
requireAdmin();
if (!hasRole('administrateur', 'habilite', 'superviseur')) {
    flash('Accès refusé.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Offres de stage');
$pdo = getPDO();

$show   = $_GET['show'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

$editOffre = null;
if ($show === 'edit_offre' && $editId) {
    $s = $pdo->prepare('SELECT * FROM offres_stage WHERE id = ?');
    $s->execute([$editId]);
    $editOffre = $s->fetch() ?: null;
}

$offres = $pdo->query('
    SELECT o.*, d.libelle AS direction, dom.libelle AS domaine,
           u.nom AS createur_nom, u.prenom AS createur_prenom,
           (SELECT COUNT(*) FROM candidatures c WHERE c.offre_id = o.id) AS nb_candidatures
    FROM offres_stage o
    LEFT JOIN directions d   ON o.direction_id = d.id
    LEFT JOIN domaines dom   ON o.domaine_id   = dom.id
    LEFT JOIN utilisateurs u ON o.creee_par    = u.id
    ORDER BY o.created_at DESC
')->fetchAll();

$directions = $pdo->query('SELECT * FROM directions WHERE actif = 1 ORDER BY libelle')->fetchAll();
$domaines   = $pdo->query('SELECT * FROM domaines   WHERE actif = 1 ORDER BY libelle')->fetchAll();
$niveaux    = ['bac', 'bac+2', 'bac+3', 'bac+4', 'bac+5', 'doctorat'];

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <h1>Offres de stage</h1>
  <?php if (hasRole('administrateur', 'habilite')): ?>
  <a href="backoffice.php?page=offres&amp;show=new_offre" class="btn btn-danger">+ Nouvelle offre</a>
  <?php endif; ?>
</div>

<?php if ($show === 'new_offre' || $editOffre): ?>
<div class="card" style="margin-bottom:24px;border-left:4px solid var(--red)">
  <div class="card-header">
    <h3 class="card-title"><?= $editOffre ? 'Modifier l\'offre — ' . h($editOffre['titre']) : 'Nouvelle offre de stage' ?></h3>
    <a href="backoffice.php?page=offres" class="btn btn-secondary btn-sm">✕ Annuler</a>
  </div>
  <div class="card-body">
    <form method="POST" action="backoffice.php">
      <input type="hidden" name="action" value="<?= $editOffre ? 'modifier_offre' : 'creer_offre' ?>">
      <?php if ($editOffre): ?>
      <input type="hidden" name="offre_id" value="<?= (int)$editOffre['id'] ?>">
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:16px">
        <label class="field-required">Titre</label>
        <input class="form-control" type="text" name="titre" required autofocus
               value="<?= h($editOffre['titre'] ?? '') ?>"
               placeholder="Ex: Stagiaire Développeur Web">
      </div>

      <div class="form-grid" style="margin-bottom:16px">
        <div class="form-group">
          <label>Direction</label>
          <select name="direction_id" class="form-control">
            <option value="">— Aucune —</option>
            <?php foreach ($directions as $d): ?>
            <option value="<?= (int)$d['id'] ?>"
              <?= ($editOffre['direction_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
              <?= h($d['libelle']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Domaine</label>
          <select name="domaine_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($domaines as $d): ?>
            <option value="<?= (int)$d['id'] ?>"
              <?= ($editOffre['domaine_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
              <?= h($d['libelle']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Niveau minimum</label>
          <select name="niveau_minimum" class="form-control">
            <?php foreach ($niveaux as $n): ?>
            <option value="<?= $n ?>"
              <?= ($editOffre['niveau_minimum'] ?? 'bac+3') === $n ? 'selected' : '' ?>>
              <?= strtoupper($n) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="field-required">Nombre de places</label>
          <input class="form-control" type="number" name="nb_places" min="1" max="100"
                 value="<?= (int)($editOffre['nb_places'] ?? 1) ?>">
        </div>
        <div class="form-group">
          <label>Date limite de candidature</label>
          <input class="form-control" type="date" name="date_limite"
                 value="<?= h($editOffre['date_limite'] ?? '') ?>"
                 min="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:16px">
        <label>Description</label>
        <textarea class="form-control" name="description" rows="4"
                  placeholder="Description du poste et des missions…"><?= h($editOffre['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label>Profil recherché</label>
        <textarea class="form-control" name="profil_recherche" rows="3"
                  placeholder="Compétences et qualités attendues…"><?= h($editOffre['profil_recherche'] ?? '') ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-danger">
          <?= $editOffre ? 'Enregistrer les modifications' : 'Publier l\'offre' ?>
        </button>
        <a href="backoffice.php?page=offres" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Liste des offres -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Liste des offres (<?= count($offres) ?>)</h3>
    <div style="display:flex;gap:8px">
      <input type="text" id="search-offre" class="form-control"
             placeholder="Rechercher…" oninput="filterOffres()" style="max-width:200px">
      <select id="filter-statut" class="form-control" onchange="filterOffres()" style="max-width:160px">
        <option value="">Tous les statuts</option>
        <option value="ouverte">Ouvertes</option>
        <option value="fermee">Fermées</option>
        <option value="archivee">Archivées</option>
      </select>
    </div>
  </div>
  <div class="table-wrap">
    <table id="table-offres">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Titre</th>
          <th>Direction</th>
          <th>Niveau</th>
          <th>Places</th>
          <th>Date limite</th>
          <th>Candidatures</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($offres as $o): ?>
        <tr data-statut="<?= h($o['statut']) ?>"
            data-search="<?= h(strtolower($o['titre'] . ' ' . ($o['direction'] ?? ''))) ?>">
          <td><code><?= h($o['reference']) ?></code></td>
          <td>
            <strong><?= h($o['titre']) ?></strong>
            <?php if ($o['direction']): ?>
            <br><span class="text-muted text-sm"><?= h($o['direction']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= h($o['direction'] ?? '—') ?></td>
          <td><span class="badge badge-info"><?= h(strtoupper($o['niveau_minimum'] ?? '—')) ?></span></td>
          <td><?= (int)$o['nb_places'] ?></td>
          <td class="text-sm"><?= $o['date_limite'] ? date('d/m/Y', strtotime($o['date_limite'])) : '—' ?></td>
          <td><span class="badge badge-primary"><?= (int)$o['nb_candidatures'] ?></span></td>
          <td><?= statusBadge($o['statut']) ?></td>
          <td class="td-actions">
            <?php if (hasRole('administrateur', 'habilite')): ?>
            <a href="backoffice.php?page=offres&amp;show=edit_offre&amp;id=<?= (int)$o['id'] ?>"
               class="btn btn-xs btn-secondary">✏️ Modifier</a>
            <?php endif; ?>
            <a href="backoffice.php?action=toggle_offre&amp;id=<?= (int)$o['id'] ?>"
               class="btn btn-xs btn-secondary"
               title="<?= $o['statut'] === 'ouverte' ? 'Fermer l\'offre' : 'Rouvrir l\'offre' ?>">
              <?= $o['statut'] === 'ouverte' ? '🔒 Fermer' : '🔓 Rouvrir' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$offres): ?>
        <tr>
          <td colspan="9" class="text-muted" style="text-align:center;padding:32px">
            Aucune offre de stage pour le moment.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function filterOffres() {
  var search = document.getElementById('search-offre').value.toLowerCase();
  var statut = document.getElementById('filter-statut').value;
  document.querySelectorAll('#table-offres tbody tr').forEach(function(row) {
    var matchSearch = !search || (row.dataset.search || '').includes(search);
    var matchStatut = !statut || row.dataset.statut === statut;
    row.style.display = (matchSearch && matchStatut) ? '' : 'none';
  });
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
