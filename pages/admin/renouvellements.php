<?php
// pages/admin/renouvellements.php
requireAdmin();
define('STAGIA_PAGE', 'Renouvellements');
$pdo = getPDO();

$ren_stmt = $pdo->prepare('
    SELECT r.*,
           sg.reference AS ref_stage,
           u.nom, u.prenom,
           tu.nom AS dec_nom, tu.prenom AS dec_prenom
    FROM renouvellements_stage r
    JOIN stages sg ON r.stage_id = sg.id
    JOIN stagiaires st ON sg.stagiaire_id = st.id
    JOIN utilisateurs u ON st.utilisateur_id = u.id
    LEFT JOIN utilisateurs tu ON r.traite_par = tu.id
    ORDER BY CASE WHEN r.statut = \'en_attente\' THEN 0 ELSE 1 END, r.id DESC
');
$ren_stmt->execute();
$renouvellements = $ren_stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Renouvellements de stage</h1>
  <span class="badge badge-warning">
    <?= count(array_filter($renouvellements, fn($r) => $r['statut'] === 'en_attente')) ?> en attente
  </span>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Stage</th>
          <th>Stagiaire</th>
          <th>Renouvellement #</th>
          <th>Période proposée</th>
          <th>Durée</th>
          <th>Motif</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($renouvellements)): ?>
        <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:40px">Aucun renouvellement.</td></tr>
        <?php endif ?>
        <?php foreach ($renouvellements as $r): ?>
        <tr>
          <td><a href="backoffice.php?page=stage_detail&id=<?= $r['stage_id'] ?>"><code class="mono"><?= h($r['ref_stage']) ?></code></a></td>
          <td><?= h($r['prenom'] . ' ' . $r['nom']) ?></td>
          <td><?= (int)$r['numero_renouvellement'] ?></td>
          <td>
            <?= $r['date_debut_proposee'] ? date('d/m/Y', strtotime($r['date_debut_proposee'])) : '—' ?>
            → <?= $r['date_fin_proposee'] ? date('d/m/Y', strtotime($r['date_fin_proposee'])) : '—' ?>
          </td>
          <td><?= number_format((float)$r['duree_ajoutee_mois'], 1) ?> mois</td>
          <td><small><?= h(mb_substr($r['motif_demande'] ?? '', 0, 50)) ?><?= strlen($r['motif_demande'] ?? '') > 50 ? '…' : '' ?></small></td>
          <td><?= statusBadge($r['statut']) ?></td>
          <td>
            <?php if ($r['statut'] === 'en_attente' && hasRole('habilite','administrateur','superviseur')): ?>
            <button class="btn btn-sm btn-success" onclick="openModal('modal-valider-<?= $r['id'] ?>')">✔</button>
            <button class="btn btn-sm btn-danger" onclick="openModal('modal-rejeter-<?= $r['id'] ?>')">✗</button>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:.8rem"><?= $r['dec_prenom'] ? h($r['dec_prenom'] . ' ' . $r['dec_nom']) : '—' ?></span>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modals Valider / Rejeter -->
<?php foreach ($renouvellements as $r): if ($r['statut'] !== 'en_attente') continue; ?>

<div class="modal-overlay" id="modal-valider-<?= $r['id'] ?>">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Valider le renouvellement</h3>
      <button class="modal-close" onclick="closeModal('modal-valider-<?= $r['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="backoffice.php?action=valider_renouvellement">
      <input type="hidden" name="renouvellement_id" value="<?= $r['id'] ?>">
      <input type="hidden" name="decision" value="valide">
      <div class="modal-body">
        <p>Valider le renouvellement #<?= $r['numero_renouvellement'] ?> pour <strong><?= h($r['prenom'] . ' ' . $r['nom']) ?></strong> ?</p>
        <p style="font-size:.85rem;color:#6b7280">
          Période : <?= $r['date_debut_proposee'] ? date('d/m/Y', strtotime($r['date_debut_proposee'])) : '—' ?>
          → <?= $r['date_fin_proposee'] ? date('d/m/Y', strtotime($r['date_fin_proposee'])) : '—' ?>
          (<?= number_format((float)$r['duree_ajoutee_mois'], 1) ?> mois)
        </p>
        <div class="form-group" style="margin-top:12px">
          <label>Commentaire (optionnel)</label>
          <textarea name="commentaire" rows="2" placeholder="Remarques éventuelles…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-valider-<?= $r['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-success">Valider</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-rejeter-<?= $r['id'] ?>">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Rejeter le renouvellement</h3>
      <button class="modal-close" onclick="closeModal('modal-rejeter-<?= $r['id'] ?>')">✕</button>
    </div>
    <form method="POST" action="backoffice.php?action=valider_renouvellement">
      <input type="hidden" name="renouvellement_id" value="<?= $r['id'] ?>">
      <input type="hidden" name="decision" value="rejete">
      <div class="modal-body">
        <div class="form-group">
          <label>Motif de rejet *</label>
          <textarea name="commentaire" rows="3" required placeholder="Expliquez la raison du rejet…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-rejeter-<?= $r['id'] ?>')">Annuler</button>
        <button type="submit" class="btn btn-danger">Rejeter</button>
      </div>
    </form>
  </div>
</div>

<?php endforeach ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
