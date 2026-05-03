<?php
// pages/admin/utilisateurs.php
requireAdmin();
if (!hasRole('administrateur')) {
    flash('Seuls les administrateurs peuvent gérer les utilisateurs.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Utilisateurs');
$pdo = getPDO();

$users = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM validations_candidature v WHERE v.validateur_id = u.id) AS nb_validations
    FROM utilisateurs u
    WHERE u.role != 'stagiaire'
    ORDER BY u.role, u.nom
")->fetchAll();

$roles = ['administrateur' => 'Administrateur', 'habilite' => 'Habilité', 'superviseur' => 'Superviseur', 'directeur' => 'Directeur'];

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Gestion des utilisateurs</h1>
  <button class="btn btn-red" onclick="openModal('modal-create-user')">+ Nouvel utilisateur</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Validations</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar"><?= mb_strtoupper(mb_substr($u['prenom'],0,1)) . mb_strtoupper(mb_substr($u['nom'],0,1)) ?></div>
              <div>
                <strong><?= h($u['prenom'] . ' ' . $u['nom']) ?></strong>
                <?php if ($u['id'] == ($_SESSION['user']['id'] ?? 0)): ?>
                <span class="badge badge-blue">Moi</span>
                <?php endif ?>
              </div>
            </div>
          </td>
          <td><?= h($u['email']) ?></td>
          <td><span class="badge badge-<?= $u['role'] === 'administrateur' ? 'rose' : ($u['role'] === 'superviseur' ? 'amber' : 'teal') ?>"><?= h($roles[$u['role']] ?? $u['role']) ?></span></td>
          <td><?= (int)$u['nb_validations'] ?></td>
          <td><?= $u['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
          <td>
            <?php if ($u['id'] != ($_SESSION['user']['id'] ?? 0)): ?>
            <a href="backoffice.php?toggle_user=<?= $u['id'] ?>" class="btn btn-sm btn-ghost"
               title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>">
              <?= $u['actif'] ? '🔒' : '🔓' ?>
            </a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Créer Utilisateur -->
<div class="modal-overlay" id="modal-create-user">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 class="modal-title">Nouvel utilisateur</h3>
      <button class="modal-close" onclick="closeModal('modal-create-user')">✕</button>
    </div>
    <form method="POST" action="backoffice.php?action=create_user">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Prénom *</label>
            <input type="text" name="prenom" required>
          </div>
          <div class="form-group">
            <label>Nom *</label>
            <input type="text" name="nom" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Email *</label>
            <input type="email" name="email" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Mot de passe *</label>
            <input type="password" name="mot_de_passe" required minlength="8" placeholder="Min. 8 caractères">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Rôle *</label>
            <select name="role" required>
              <?php foreach ($roles as $val => $label): ?>
              <option value="<?= $val ?>"><?= $label ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-user')">Annuler</button>
        <button type="submit" class="btn btn-red">Créer</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
