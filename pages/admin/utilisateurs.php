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
$civilites = ['M.' => 'M.', 'Mme' => 'Mme'];

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Gestion des utilisateurs</h1>
  <button class="btn btn-danger btn-sm" onclick="openModal('modal-create-user')">+ Nouvel utilisateur</button>
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
                <span class="badge badge-info" style="margin-left:4px">Moi</span>
                <?php endif ?>
              </div>
            </div>
          </td>
          <td><?= h($u['email']) ?></td>
          <td><span class="badge badge-primary"><?= h($roles[$u['role']] ?? $u['role']) ?></span></td>
          <td><?= (int)$u['nb_validations'] ?></td>
          <td><?= $u['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-secondary">Inactif</span>' ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-sm btn-secondary" onclick="openModal('modal-edit-<?= $u['id'] ?>')">✏️ Modifier</button>
            <?php if ($u['id'] != ($_SESSION['user']['id'] ?? 0)): ?>
            <a href="backoffice.php?action=toggle_user&id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary"
               onclick="return confirm('Changer le statut de cet utilisateur ?')"
               title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>">
              <?= $u['actif'] ? '🔒 Désactiver' : '🔓 Activer' ?>
            </a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modals Modifier (hors tableau) -->
<?php foreach ($users as $u): ?>
<div class="modal-overlay" id="modal-edit-<?= $u['id'] ?>">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <h3>Modifier — <?= h($u['prenom'] . ' ' . $u['nom']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-edit-<?= $u['id'] ?>')">×</button>
    </div>
    <form method="POST" action="backoffice.php">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Civilité</label>
            <select name="civilite">
              <?php foreach ($civilites as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($u['civilite'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group">
            <label>Rôle *</label>
            <select name="role" required <?= $u['id'] == ($_SESSION['user']['id'] ?? 0) ? 'disabled title="Vous ne pouvez pas modifier votre propre rôle"' : '' ?>>
              <?php foreach ($roles as $val => $label): ?>
              <option value="<?= $val ?>" <?= $u['role'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach ?>
            </select>
            <?php if ($u['id'] == ($_SESSION['user']['id'] ?? 0)): ?>
            <input type="hidden" name="role" value="<?= h($u['role']) ?>">
            <?php endif ?>
          </div>
          <div class="form-group">
            <label>Prénom *</label>
            <input type="text" name="prenom" value="<?= h($u['prenom']) ?>" required>
          </div>
          <div class="form-group">
            <label>Nom *</label>
            <input type="text" name="nom" value="<?= h($u['nom']) ?>" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Email *</label>
            <input type="email" name="email" value="<?= h($u['email']) ?>" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Nouveau mot de passe <span class="text-muted" style="font-weight:400">(laisser vide = inchangé)</span></label>
            <input type="password" name="nouveau_mdp" minlength="8" placeholder="Min. 8 caractères">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-<?= $u['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>

<!-- Modal Créer Utilisateur -->
<div class="modal-overlay" id="modal-create-user">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 class="modal-title">Nouvel utilisateur</h3>
      <button class="modal-close" onclick="closeModal('modal-create-user')">×</button>
    </div>
    <form method="POST" action="backoffice.php">
      <input type="hidden" name="action" value="create_user">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Civilité</label>
            <select name="civilite">
              <option value="M.">M.</option>
              <option value="Mme">Mme</option>
            </select>
          </div>
          <div class="form-group">
            <label>Rôle *</label>
            <select name="role" required>
              <?php foreach ($roles as $val => $label): ?>
              <option value="<?= $val ?>"><?= $label ?></option>
              <?php endforeach ?>
            </select>
          </div>
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
            <input type="password" name="password" required minlength="8" placeholder="Min. 8 caractères">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-user')">Annuler</button>
        <button type="submit" class="btn btn-danger">Créer</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
