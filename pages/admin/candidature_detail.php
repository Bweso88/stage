<?php
/**
 * StagIA - Détail candidature
 */
define('STAGIA_PAGE', 'Détail candidature');
require_once __DIR__ . '/../../includes/header.php';

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('Candidature introuvable.', 'error'); redirect('backoffice.php?page=candidatures'); }

$stmt = $pdo->prepare('SELECT c.*, u.nom, u.prenom, u.email, u.civilite, s.id as stag_id, s.niveau_etude, s.telephone, s.ville, o.titre as offre_titre, o.reference as offre_ref, d.libelle as direction_libelle, dm.libelle as domaine_libelle FROM candidatures c JOIN stagiaires s ON s.id = c.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id LEFT JOIN offres_stage o ON o.id = c.offre_id LEFT JOIN directions d ON d.id = c.direction_id LEFT JOIN domaines dm ON dm.id = c.domaine_id WHERE c.id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { flash('Candidature introuvable.', 'error'); redirect('backoffice.php?page=candidatures'); }

$validations = $pdo->prepare('SELECT v.*, u.nom, u.prenom, u.role FROM validations_candidature v LEFT JOIN utilisateurs u ON u.id = v.validateur_id WHERE v.candidature_id = ? ORDER BY v.niveau_validation ASC');
$validations->execute([$id]);
$validations = $validations->fetchAll();

$stage_existe = $pdo->prepare('SELECT id FROM stages WHERE candidature_id = ?');
$stage_existe->execute([$id]);
$stage_id = $stage_existe->fetchColumn();

$val_niv1 = null; $val_niv2 = null;
foreach ($validations as $v) {
    if ($v['niveau_validation'] === 'niv1') $val_niv1 = $v;
    if ($v['niveau_validation'] === 'niv2') $val_niv2 = $v;
}

$canDecideNiv1 = in_array($c['statut_global'], ['soumise','niv1_en_cours'], true) && hasRole('administrateur','habilite','superviseur');
$canDecideNiv2 = $c['statut_global'] === 'niv1_valide' && hasRole('administrateur','superviseur','directeur');
$canCreateStage = $c['statut_global'] === 'validee' && !$stage_id && hasRole('administrateur','superviseur');
?>

<div class="breadcrumb">
  <a href="backoffice.php?page=candidatures">Candidatures</a>
  <span>›</span>
  <span><?= h($c['reference']) ?></span>
</div>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1><?= h($c['reference']) ?></h1>
    <p>Déposée le <?= h(date('d/m/Y H:i', strtotime($c['date_candidature']))) ?></p>
  </div>
  <div class="d-flex gap-2">
    <?= statusBadge($c['statut_global']) ?>
    <?php if ($canDecideNiv1): ?>
    <button class="btn btn-warning btn-sm" onclick="openModal('modal-niv1')">Décision Niv.1</button>
    <?php endif; ?>
    <?php if ($canDecideNiv2): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-niv2')">Décision Niv.2</button>
    <?php endif; ?>
    <?php if ($canCreateStage): ?>
    <a href="backoffice.php?page=creer_stage&amp;cand_id=<?= $id ?>" class="btn btn-success btn-sm">🎓 Programmer le stage</a>
    <?php endif; ?>
    <?php if ($stage_id): ?>
    <a href="backoffice.php?page=stage_detail&amp;id=<?= $stage_id ?>" class="btn btn-success btn-sm">🎓 Voir le stage</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <!-- Candidature info -->
  <div class="card">
    <div class="card-header"><span class="card-title">📋 Informations candidature</span></div>
    <div class="card-body">
      <div class="info-row"><span class="info-label">Référence</span><span class="info-val fw-bold"><?= h($c['reference']) ?></span></div>
      <div class="info-row"><span class="info-label">Type</span><span class="info-val"><?= $c['type_candidature']==='offre'?'<span class="badge badge-info">Sur offre</span>':'<span class="badge badge-secondary">Spontanée</span>' ?></span></div>
      <?php if ($c['offre_titre']): ?>
      <div class="info-row"><span class="info-label">Offre</span><span class="info-val"><?= h($c['offre_ref'] . ' – ' . $c['offre_titre']) ?></span></div>
      <?php endif; ?>
      <div class="info-row"><span class="info-label">Direction</span><span class="info-val"><?= h($c['direction_libelle'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Domaine</span><span class="info-val"><?= h($c['domaine_libelle'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Score de tri</span><span class="info-val"><strong><?= number_format((float)$c['score_tri'],1) ?>/100</strong></span></div>
      <div class="info-row"><span class="info-label">Workflow</span><span class="info-val"><?= workflowDots($c['statut_global']) ?></span></div>
    </div>
  </div>

  <!-- Stagiaire info -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">👤 Stagiaire</span>
      <a href="backoffice.php?page=stagiaire_detail&amp;id=<?= $c['stag_id'] ?>" class="btn btn-secondary btn-sm">Voir profil</a>
    </div>
    <div class="card-body">
      <div class="info-row"><span class="info-label">Nom</span><span class="info-val fw-bold"><?= h($c['civilite'] . ' ' . $c['prenom'] . ' ' . $c['nom']) ?></span></div>
      <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= h($c['email']) ?></span></div>
      <div class="info-row"><span class="info-label">Téléphone</span><span class="info-val"><?= h($c['telephone'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Ville</span><span class="info-val"><?= h($c['ville'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Niveau</span><span class="info-val"><strong><?= h(strtoupper($c['niveau_etude'])) ?></strong></span></div>
    </div>
  </div>
</div>

<!-- Motivation -->
<div class="card mt-4">
  <div class="card-header"><span class="card-title">📝 Lettre de motivation</span></div>
  <div class="card-body">
    <p style="line-height:1.8;font-size:14px;color:#374151;"><?= nl2br(h($c['motivation'] ?? 'Non renseignée.')) ?></p>
  </div>
</div>

<!-- Validations -->
<div class="card mt-4">
  <div class="card-header"><span class="card-title">✅ Historique des validations</span></div>
  <?php if (empty($validations)): ?>
  <div class="card-body"><p class="text-muted text-sm">Aucune décision enregistrée.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Niveau</th><th>Décision</th><th>Validateur</th><th>Commentaire</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($validations as $v): ?>
        <tr>
          <td><span class="badge badge-primary"><?= $v['niveau_validation'] === 'niv1' ? 'Niveau 1' : 'Niveau 2' ?></span></td>
          <td><?= statusBadge($v['statut']) ?></td>
          <td><?= h(($v['prenom'] ?? '') . ' ' . ($v['nom'] ?? 'N/A')) ?><br><span class="text-muted text-sm"><?= h($v['role'] ?? '') ?></span></td>
          <td><?= h($v['commentaire'] ?? '-') ?></td>
          <td class="text-sm text-muted"><?= $v['date_decision'] ? h(date('d/m/Y H:i', strtotime($v['date_decision']))) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Modal Décision Niv1 -->
<?php if ($canDecideNiv1): ?>
<div class="modal-overlay" id="modal-niv1">
  <div class="modal">
    <div class="modal-header">
      <h3>Décision Niveau 1 – <?= h($c['reference']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-niv1')">×</button>
    </div>
    <form method="post" action="backoffice.php">
      <input type="hidden" name="action" value="valider_candidature">
      <input type="hidden" name="candidature_id" value="<?= $id ?>">
      <input type="hidden" name="niveau" value="niv1">
      <div class="modal-body">
        <div class="form-group">
          <label>Décision</label>
          <select name="statut" class="form-control" required>
            <option value="valide">✅ Valider (passe en Niv.2)</option>
            <option value="rejete">❌ Rejeter</option>
            <option value="complement">📝 Demander un complément</option>
          </select>
        </div>
        <div class="form-group mt-2">
          <label>Commentaire</label>
          <textarea name="commentaire" class="form-control" rows="4" placeholder="Observations..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-niv1')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal Décision Niv2 -->
<?php if ($canDecideNiv2): ?>
<div class="modal-overlay" id="modal-niv2">
  <div class="modal">
    <div class="modal-header">
      <h3>Décision Finale (Niv.2) – <?= h($c['reference']) ?></h3>
      <button class="modal-close" onclick="closeModal('modal-niv2')">×</button>
    </div>
    <form method="post" action="backoffice.php">
      <input type="hidden" name="action" value="valider_candidature">
      <input type="hidden" name="candidature_id" value="<?= $id ?>">
      <input type="hidden" name="niveau" value="niv2">
      <div class="modal-body">
        <div class="form-group">
          <label>Décision finale</label>
          <select name="statut" class="form-control" required>
            <option value="valide">✅ Valider définitivement</option>
            <option value="rejete">❌ Rejeter définitivement</option>
            <option value="complement">📝 Demander un complément</option>
          </select>
        </div>
        <div class="form-group mt-2">
          <label>Commentaire</label>
          <textarea name="commentaire" class="form-control" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-niv2')">Annuler</button>
        <button type="submit" class="btn btn-danger">Décision finale</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
