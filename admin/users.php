<?php
require "auth.php";
require "db.php";
$pageTitle = "Utilisateurs – Admin";

if ($_SESSION["user"]["role"] !== "ADMIN") {
    header("Location: dashboard.php");
    exit;
}

$msg     = "";
$msgType = "info";

/* Créer un utilisateur */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "create") {
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
    $check->execute([$_POST["username"]]);
    if ($check->fetchColumn() > 0) {
        $msg = "Ce nom d'utilisateur existe déjà.";
        $msgType = "danger";
    } else {
        $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username,password,fullname,role,active) VALUES (?,?,?,?,1)")
            ->execute([$_POST["username"], $hash, $_POST["fullname"], $_POST["role"]]);
        $msg = "Compte <strong>" . htmlspecialchars($_POST["fullname"]) . "</strong> créé.";
        $msgType = "success";
    }
}

/* Modifier un utilisateur */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "edit") {
    $id = (int)$_POST["id"];
    if (!empty($_POST["password"])) {
        $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET fullname=?,role=?,password=? WHERE id=?")
            ->execute([$_POST["fullname"], $_POST["role"], $hash, $id]);
    } else {
        $pdo->prepare("UPDATE users SET fullname=?,role=? WHERE id=?")
            ->execute([$_POST["fullname"], $_POST["role"], $id]);
    }
    $msg = "Compte mis à jour.";
    $msgType = "success";
}

/* Toggle actif/inactif */
if (isset($_GET["toggle"])) {
    $id = (int)$_GET["toggle"];
    if ($id !== (int)$_SESSION["user"]["id"]) {
        $curr = (int)$pdo->query("SELECT active FROM users WHERE id=$id")->fetchColumn();
        $pdo->prepare("UPDATE users SET active=? WHERE id=?")->execute([1 - $curr, $id]);
        $msg = "Statut du compte modifié.";
        $msgType = "info";
    } else {
        $msg = "Vous ne pouvez pas désactiver votre propre compte.";
        $msgType = "warning";
    }
}

/* Supprimer */
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    if ($id === (int)$_SESSION["user"]["id"]) {
        $msg = "Vous ne pouvez pas supprimer votre propre compte.";
        $msgType = "warning";
    } else {
        $pdo->prepare("DELETE FROM user_logins WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $msg = "Compte supprimé.";
        $msgType = "success";
    }
}

$users = $pdo->query("SELECT id,username,fullname,role,active,created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<?php include "_nav.php"; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h4 class="fw-bold mb-0">👤 Gestion des utilisateurs</h4>
  <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
    + Nouvel utilisateur
  </button>
</div>

<?php if ($msg): ?>
  <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
    <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Nom complet</th>
            <th>Identifiant</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Créé le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?php echo $u["id"]; ?></td>
            <td class="fw-semibold"><?php echo htmlspecialchars($u["fullname"]); ?></td>
            <td><code><?php echo htmlspecialchars($u["username"]); ?></code></td>
            <td>
              <?php if ($u["role"] === "ADMIN"): ?>
                <span class="badge bg-danger">Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary">Formateur</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u["active"]): ?>
                <span class="badge bg-success">Actif</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactif</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted">
              <?php echo $u["created_at"] ? date("d/m/Y", strtotime($u["created_at"])) : "—"; ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#editModal"
                        data-id="<?php echo $u['id']; ?>"
                        data-fullname="<?php echo htmlspecialchars($u['fullname']); ?>"
                        data-role="<?php echo $u['role']; ?>">
                  ✏️
                </button>
                <?php if ((int)$u["id"] !== (int)$_SESSION["user"]["id"]): ?>
                  <a href="?toggle=<?php echo $u['id']; ?>"
                     class="btn btn-sm <?php echo $u['active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                     title="<?php echo $u['active'] ? 'Désactiver' : 'Activer'; ?>">
                    <?php echo $u['active'] ? '⏸' : '▶'; ?>
                  </a>
                  <a href="?delete=<?php echo $u['id']; ?>"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('Supprimer ce compte ?');">🗑</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal créer -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Nouvel utilisateur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nom complet</label>
            <input type="text" name="fullname" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Identifiant</label>
            <input type="text" name="username" class="form-control" required autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Rôle</label>
            <select name="role" class="form-select">
              <option value="FORMATEUR">Formateur</option>
              <option value="ADMIN">Administrateur</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-success">Créer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal modifier -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="eId">
        <div class="modal-header">
          <h5 class="modal-title">Modifier l'utilisateur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nom complet</label>
            <input type="text" name="fullname" id="eFullname" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nouveau mot de passe <small class="text-muted">(laisser vide = inchangé)</small></label>
            <input type="password" name="password" class="form-control" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Rôle</label>
            <select name="role" id="eRole" class="form-select">
              <option value="FORMATEUR">Formateur</option>
              <option value="ADMIN">Administrateur</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('eId').value       = btn.dataset.id;
    document.getElementById('eFullname').value = btn.dataset.fullname;
    document.getElementById('eRole').value     = btn.dataset.role;
});
</script>

<?php include "_nav_end.php"; ?>
