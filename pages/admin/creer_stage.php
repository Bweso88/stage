<?php
// pages/admin/creer_stage.php
requireAdmin();
if (!hasRole('administrateur', 'superviseur')) {
    flash('Accès refusé.', 'error');
    redirect('backoffice.php?page=candidatures');
}
define('STAGIA_PAGE', 'Programmer un stage');
$pdo = getPDO();

$cand_id = (int)($_GET['cand_id'] ?? 0);
if (!$cand_id) {
    flash('Candidature introuvable.', 'error');
    redirect('backoffice.php?page=candidatures');
}

$stmt = $pdo->prepare(
    "SELECT c.*, u.nom, u.prenom, u.email, u.civilite,
            s.id AS stag_id, s.niveau_etude,
            d.libelle AS direction_libelle,
            dm.libelle AS domaine_libelle
     FROM candidatures c
     JOIN stagiaires s ON s.id = c.stagiaire_id
     JOIN utilisateurs u ON u.id = s.utilisateur_id
     LEFT JOIN directions d  ON d.id  = c.direction_id
     LEFT JOIN domaines  dm ON dm.id = c.domaine_id
     WHERE c.id = ? AND c.statut_global = 'validee'"
);
$stmt->execute([$cand_id]);
$c = $stmt->fetch();

if (!$c) {
    flash('Candidature introuvable ou non encore validée.', 'error');
    redirect('backoffice.php?page=candidatures');
}

$existing = $pdo->prepare('SELECT id FROM stages WHERE candidature_id = ?');
$existing->execute([$cand_id]);
if ($existing->fetchColumn()) {
    flash('Un stage existe déjà pour cette candidature.', 'error');
    redirect('backoffice.php?page=candidature_detail&id=' . $cand_id);
}

$admins = $pdo->query(
    "SELECT id, nom, prenom, role FROM utilisateurs
     WHERE role != 'stagiaire' AND actif = 1 ORDER BY nom"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="backoffice.php?page=candidatures">Candidatures</a>
  <span>›</span>
  <a href="backoffice.php?page=candidature_detail&amp;id=<?= $cand_id ?>"><?= h($c['reference']) ?></a>
  <span>›</span>
  <span>Programmer le stage</span>
</div>

<div class="page-header">
  <h1>🎓 Programmer le stage</h1>
  <p>Candidature <strong><?= h($c['reference']) ?></strong> — <?= h($c['civilite'] . ' ' . $c['prenom'] . ' ' . $c['nom']) ?></p>
</div>

<div style="max-width:680px">

  <!-- Récapitulatif -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3 class="card-title">Récapitulatif candidature</h3></div>
    <div class="card-body">
      <div class="info-row">
        <span class="info-label">Candidat</span>
        <span class="info-val fw-bold"><?= h($c['civilite'] . ' ' . $c['prenom'] . ' ' . $c['nom']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Email</span>
        <span class="info-val"><?= h($c['email']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Niveau</span>
        <span class="info-val"><?= h(strtoupper($c['niveau_etude'])) ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Direction</span>
        <span class="info-val"><?= h($c['direction_libelle'] ?? '—') ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Domaine</span>
        <span class="info-val"><?= h($c['domaine_libelle'] ?? '—') ?></span>
      </div>
    </div>
  </div>

  <!-- Formulaire -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Paramètres du stage</h3></div>
    <div class="card-body">
      <form method="POST" action="backoffice.php">
        <input type="hidden" name="action" value="creer_stage_depuis_cand">
        <input type="hidden" name="candidature_id" value="<?= $cand_id ?>">

        <div class="form-grid">
          <div class="form-group">
            <label class="field-required">Date de début</label>
            <input type="date" name="date_debut" class="form-control" required
                   value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="field-required">Date de fin</label>
            <input type="date" name="date_fin" class="form-control" required
                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Encadrant</label>
          <select name="encadrant_id" class="form-control">
            <option value="">— Aucun encadrant —</option>
            <?php foreach ($admins as $a): ?>
            <option value="<?= (int)$a['id'] ?>">
              <?= h($a['prenom'] . ' ' . $a['nom']) ?> (<?= h($a['role']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;font-size:12px;color:var(--navy)">
            <input type="checkbox" name="remboursement_transport" id="transp"
                   onchange="document.getElementById('montant_wrap').style.display=this.checked?'block':'none'"
                   style="width:auto">
            Remboursement de transport
          </label>
        </div>

        <div class="form-group" id="montant_wrap" style="display:none">
          <label>Montant (GNF / mois)</label>
          <input type="number" name="montant_transport" class="form-control"
                 value="0" min="0" step="1000">
        </div>

        <div class="form-actions" style="margin-top:24px;padding-top:16px;border-top:1px solid var(--gray-border)">
          <button type="submit" class="btn btn-success" style="font-size:15px;padding:12px 24px">
            🎓 Créer le stage
          </button>
          <a href="backoffice.php?page=candidature_detail&amp;id=<?= $cand_id ?>" class="btn btn-secondary">
            Annuler
          </a>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
