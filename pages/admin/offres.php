<?php
// pages/admin/offres.php
requireAdmin();
if (!hasRole('administrateur', 'habilite', 'superviseur')) {
    flash('Accès refusé.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Offres de stage');
$pdo = getPDO();

$offres = $pdo->query('
    SELECT o.*, d.libelle AS direction, dom.libelle AS domaine,
           u.nom AS createur_nom, u.prenom AS createur_prenom,
           (SELECT COUNT(*) FROM candidatures c WHERE c.offre_id = o.id) AS nb_candidatures
    FROM offres_stage o
    LEFT JOIN directions d ON o.direction_id = d.id
    LEFT JOIN domaines dom ON o.domaine_id = dom.id
    LEFT JOIN utilisateurs u ON o.creee_par = u.id
    ORDER BY o.created_at DESC
')->fetchAll();

$directions = $pdo->query('SELECT * FROM directions WHERE actif=1 ORDER BY libelle')->fetchAll();
$domaines   = $pdo->query('SELECT * FROM domaines WHERE actif=1 ORDER BY libelle')->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Offres de stage</h1>
  <?php if (hasRole('administrateur','habilite')): ?>
  <button class="btn btn-red" onclick="openModal('modal-create-offre')">+ Nouvelle offre</button>
  <?php endif ?>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="search-offre" placeholder="Rechercher…" oninput="filterOffres()">
    <select id="filter-statut" onchange="filterOffres()">
      <option value="">Tous les statuts</option>
      <option value="ouverte">Ouvertes</option>
      <option value="fermee">Fermées</option>
      <option value="archivee">Archivées</option>
    </select>
  </div>
  <div class="table-wrap">
    <table id="table-offres">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Titre</th>
          <th>Direction</th>
          <th>Niveau min</th>
          <th>Places</th>
          <th>Date limite</th>
          <th>Candidatures</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($offres as $o): ?>
        <tr data-statut="<?= h($o['statut']) ?>" data-search="<?= h(strtolower($o['titre'] . ' ' . $o['direction'])) ?>">
          <td><code class="mono"><?= h($o['reference']) ?></code></td>
          <td><strong><?= h($o['titre']) ?></strong><br><small class="text-muted"><?= h($o['direction'] ?? '—') ?></small></td>
          <td><?= h($o['direction'] ?? '—') ?></td>
          <td><?= h($o['niveau_minimum'] ?? '—') ?></td>
          <td><?= (int)$o['nb_places'] ?></td>
          <td><?= $o['date_limite'] ? date('d/m/Y', strtotime($o['date_limite'])) : '—' ?></td>
          <td><span class="badge badge-blue"><?= (int)$o['nb_candidatures'] ?></span></td>
          <td><?= statusBadge($o['statut']) ?></td>
          <td>
            <button class="btn btn-sm btn-ghost" onclick="openModal('modal-edit-<?= $o['id'] ?>')">✏️</button>
            <a href="backoffice.php?toggle_offre=<?= $o['id'] ?>" class="btn btn-sm btn-ghost"
               title="<?= $o['statut'] === 'ouverte' ? 'Fermer' : 'Rouvrir' ?>">
              <?= $o['statut'] === 'ouverte' ? '🔒' : '🔓' ?>
            </a>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Créer Offre -->
<?php if (hasRole('administrateur','habilite')): ?>
<div class="modal-overlay" id="modal-create-offre">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3 class="modal-title">Nouvelle offre de stage</h3>
      <button class="modal-close" onclick="closeModal('modal-create-offre')">✕</button>
    </div>
    <form method="POST" action="backoffice.php?action=creer_offre">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Titre *</label>
            <input type="text" name="titre" required placeholder="Ex: Stagiaire Développeur Web">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Description *</label>
            <textarea name="description" rows="3" required placeholder="Description du poste…"></textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Profil recherché</label>
            <textarea name="profil_recherche" rows="2" placeholder="Profil attendu…"></textarea>
          </div>
          <div class="form-group">
            <label>Direction</label>
            <select name="direction_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($directions as $d): ?>
              <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Domaine</label>
            <select name="domaine_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($domaines as $d): ?>
              <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Niveau minimum</label>
            <select name="niveau_minimum">
              <option value="bac">Bac</option>
              <option value="bac+2">Bac+2</option>
              <option value="bac+3" selected>Bac+3</option>
              <option value="bac+4">Bac+4</option>
              <option value="bac+5">Bac+5</option>
            </select>
          </div>
          <div class="form-group">
            <label>Nombre de places</label>
            <input type="number" name="nb_places" value="1" min="1" max="50">
          </div>
          <div class="form-group">
            <label>Date limite</label>
            <input type="date" name="date_limite" min="<?= date('Y-m-d') ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-offre')">Annuler</button>
        <button type="submit" class="btn btn-red">Créer l'offre</button>
      </div>
    </form>
  </div>
</div>

<!-- Modals Edit Offre -->
<?php foreach ($offres as $o): ?>
<div class="modal-overlay" id="modal-edit-<?= $o['id'] ?>">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3 class="modal-title">Modifier — <?= h($o['titre']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-edit-<?= $o['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="backoffice.php?action=modifier_offre">
      <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Titre *</label>
            <input type="text" name="titre" value="<?= h($o['titre']) ?>" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Description</label>
            <textarea name="description" rows="3"><?= h($o['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Profil recherché</label>
            <textarea name="profil_recherche" rows="2"><?= h($o['profil_recherche'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Direction</label>
            <select name="direction_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($directions as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $o['direction_id'] == $d['id'] ? 'selected' : '' ?>><?= h($d['libelle']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Domaine</label>
            <select name="domaine_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($domaines as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $o['domaine_id'] == $d['id'] ? 'selected' : '' ?>><?= h($d['libelle']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Niveau minimum</label>
            <select name="niveau_minimum">
              <?php foreach (['bac','bac+2','bac+3','bac+4','bac+5'] as $n): ?>
              <option value="<?= $n ?>" <?= $o['niveau_minimum'] === $n ? 'selected' : '' ?>><?= $n ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Nombre de places</label>
            <input type="number" name="nb_places" value="<?= (int)$o['nb_places'] ?>" min="1">
          </div>
          <div class="form-group">
            <label>Date limite</label>
            <input type="date" name="date_limite" value="<?= h($o['date_limite'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-<?= $o['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-red">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>
<?php endif ?>

<script>
function filterOffres() {
  const search = document.getElementById('search-offre').value.toLowerCase();
  const statut = document.getElementById('filter-statut').value;
  document.querySelectorAll('#table-offres tbody tr').forEach(row => {
    const matchSearch = !search || row.dataset.search.includes(search);
    const matchStatut = !statut || row.dataset.statut === statut;
    row.style.display = matchSearch && matchStatut ? '' : 'none';
  });
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
