<?php
if (!hasRole('administrateur')) {
    echo '<div class="alert alert-danger">Accès réservé aux administrateurs.</div>';
    return;
}
$pdo = getPDO();

$utilisateurs = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM stagiaires s WHERE s.utilisateur_id = u.id) AS est_stagiaire
    FROM utilisateurs u
    ORDER BY u.role, u.nom
")->fetchAll();

$roles = ['administrateur', 'habilite', 'superviseur', 'directeur', 'stagiaire'];
?>

<div class="page-header">
    <h1>Gestion des utilisateurs</h1>
    <button class="btn btn-red" onclick="openModal('modal-creer-user')">+ Créer un utilisateur</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:<?= $u['actif'] ? 'var(--navy)' : '#9ca3af' ?>"><?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?></div>
                            <div>
                                <div class="fw-600"><?= h($u['civilite'] . ' ' . $u['prenom'] . ' ' . $u['nom']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm"><?= h($u['email']) ?></td>
                    <td>
                        <?php
                        $roleBadge = [
                            'administrateur' => 'badge-rose',
                            'habilite'       => 'badge-blue',
                            'superviseur'    => 'badge-teal',
                            'directeur'      => 'badge-amber',
                            'stagiaire'      => 'badge-gray',
                        ];
                        ?>
                        <span class="badge <?= $roleBadge[$u['role']] ?? 'badge-gray' ?>"><?= ucfirst($u['role']) ?></span>
                    </td>
                    <td><?= $u['actif'] ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-danger">Inactif</span>' ?></td>
                    <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['id'] !== (int)$_SESSION['user']['id']): ?>
                        <a href="backoffice.php?toggle_user=<?= $u['id'] ?>" class="btn btn-ghost btn-xs" onclick="return confirm('Changer le statut de cet utilisateur ?')">
                            <?= $u['actif'] ? 'Désactiver' : 'Activer' ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted text-sm">Vous</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal créer utilisateur (HORS tableau) -->
<div class="modal-overlay" id="modal-creer-user">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Créer un utilisateur</div>
            <button class="modal-close" onclick="closeModal('modal-creer-user')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=create_user">
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
                            <?php foreach ($roles as $r): ?>
                            <?php if ($r !== 'stagiaire'): ?>
                            <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required placeholder="Prénom">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required placeholder="Nom">
                    </div>
                    <div class="form-group full">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="email@stagia.org">
                    </div>
                    <div class="form-group full">
                        <label>Mot de passe *</label>
                        <input type="password" name="mot_de_passe" required minlength="6" placeholder="Minimum 6 caractères">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-creer-user')">Annuler</button>
                <button type="submit" class="btn btn-red">Créer</button>
            </div>
        </form>
    </div>
</div>
