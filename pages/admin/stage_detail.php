<?php
// pages/admin/stage_detail.php
requireAdmin();
define('STAGIA_PAGE', 'Détail du stage');
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { flash('Stage introuvable.', 'error'); redirect('backoffice.php?page=stages'); }

$stmt = $pdo->prepare('
    SELECT sg.*,
           u.nom, u.prenom, u.email,
           d.libelle AS direction_libelle,
           dom.libelle AS domaine_libelle,
           enc.nom AS enc_nom, enc.prenom AS enc_prenom,
           c.reference AS ref_cand
    FROM stages sg
    JOIN stagiaires st ON sg.stagiaire_id = st.id
    JOIN utilisateurs u ON st.utilisateur_id = u.id
    LEFT JOIN directions d ON sg.direction_id = d.id
    LEFT JOIN domaines dom ON sg.domaine_id = dom.id
    LEFT JOIN utilisateurs enc ON sg.encadrant_id = enc.id
    LEFT JOIN candidatures c ON sg.candidature_id = c.id
    WHERE sg.id = ?
');
$stmt->execute([$id]);
$stage = $stmt->fetch();
if (!$stage) { flash('Stage introuvable.', 'error'); redirect('backoffice.php?page=stages'); }

$renouvellements = $pdo->prepare('
    SELECT r.*, u.nom AS dec_nom, u.prenom AS dec_prenom
    FROM renouvellements_stage r
    LEFT JOIN utilisateurs u ON r.traite_par = u.id
    WHERE r.stage_id = ?
    ORDER BY r.numero_renouvellement DESC
');
$renouvellements->execute([$id]);
$renouvellements = $renouvellements->fetchAll();

$encadrants = $pdo->query('SELECT id, nom, prenom FROM utilisateurs WHERE actif=1 AND role IN (\'habilite\',\'superviseur\',\'administrateur\') ORDER BY nom')->fetchAll();

// Calculate duration
$mois = 0;
if ($stage['date_debut'] && $stage['date_fin']) {
    $d1  = new DateTime($stage['date_debut']);
    $d2  = new DateTime($stage['date_fin']);
    $mois = round($d1->diff($d2)->days / 30, 1);
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <a href="backoffice.php?page=stages" class="btn btn-ghost">← Retour</a>
  <h1>Stage <?= h($stage['reference']) ?></h1>
  <?= statusBadge($stage['statut']) ?>
  <div style="margin-left:auto;display:flex;gap:8px">
    <a href="backoffice.php?page=lettre_stage&id=<?= $id ?>" class="btn btn-navy" target="_blank">🖨 Imprimer lettre</a>
    <a href="api/generer-lettre.php?stage_id=<?= $id ?>" class="btn btn-ghost">⬇ Télécharger .docx</a>
  </div>
</div>

<div class="section-grid">
  <!-- Infos principales -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Informations du stage</h3></div>
    <div class="card-body">
      <table style="width:100%;font-size:.86rem">
        <tr><td style="color:#9ca3af;width:40%">Référence</td><td><code class="mono"><?= h($stage['reference']) ?></code></td></tr>
        <tr><td style="color:#9ca3af">Candidature</td><td><a href="backoffice.php?page=candidature_detail&id=<?= $stage['candidature_id'] ?>"><code class="mono"><?= h($stage['ref_cand'] ?? '—') ?></code></a></td></tr>
        <tr><td style="color:#9ca3af">Stagiaire</td><td><a href="backoffice.php?page=stagiaire_detail&id=<?= $stage['stagiaire_id'] ?>"><?= h($stage['prenom'] . ' ' . $stage['nom']) ?></a></td></tr>
        <tr><td style="color:#9ca3af">Email</td><td><?= h($stage['email']) ?></td></tr>
        <tr><td style="color:#9ca3af">Direction</td><td><?= h($stage['direction_libelle'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Domaine</td><td><?= h($stage['domaine_libelle'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Encadrant</td><td><?= $stage['enc_prenom'] ? h($stage['enc_prenom'] . ' ' . $stage['enc_nom']) : '—' ?></td></tr>
        <tr><td style="color:#9ca3af">Date début</td><td><?= $stage['date_debut'] ? date('d/m/Y', strtotime($stage['date_debut'])) : '—' ?></td></tr>
        <tr><td style="color:#9ca3af">Date fin</td><td><?= $stage['date_fin'] ? date('d/m/Y', strtotime($stage['date_fin'])) : '—' ?></td></tr>
        <tr><td style="color:#9ca3af">Durée</td><td><?= $mois ?> mois</td></tr>
        <tr><td style="color:#9ca3af">Renouvellements</td><td><?= (int)$stage['nb_renouvellements'] ?> / 4</td></tr>
        <tr><td style="color:#9ca3af">Transport</td><td>
          <?= $stage['remboursement_transport'] ? '<span class="badge badge-success">Oui</span> ' . ($stage['montant_transport'] ? number_format($stage['montant_transport'], 0, ',', ' ') . ' GNF' : '') : '<span class="badge badge-gray">Non</span>' ?>
        </td></tr>
      </table>
    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <?php if (hasRole('habilite','administrateur') && in_array($stage['statut'], ['en_cours','renouvele','preparation'], true)): ?>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Actions</h3></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-success" onclick="openModal('modal-terminer')">✔ Marquer terminé</button>
        <button class="btn btn-ghost" onclick="openModal('modal-modifier-dates')">📅 Modifier les dates</button>
        <button class="btn btn-ghost" onclick="openModal('modal-modifier-enc')">👤 Modifier encadrant</button>
        <button class="btn btn-ghost" onclick="openModal('modal-transport')">🚌 Gérer transport</button>
        <button class="btn btn-danger" onclick="openModal('modal-archiver')">📁 Archiver</button>
      </div>
    </div>
    <?php endif ?>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Progression</h3></div>
      <div class="card-body">
        <div style="margin-bottom:8px;font-size:.82rem;color:#6b7280">Durée totale max : 5 mois</div>
        <?php
        $totalMois = max($mois, (float)$stage['duree_totale_mois']);
        $pct = min(100, ($totalMois / 5) * 100);
        ?>
        <div class="progress-wrap">
          <div class="progress-bar <?= $pct > 80 ? 'danger' : ($pct > 60 ? 'warning' : '') ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div style="font-size:.82rem;color:#6b7280;margin-top:4px"><?= $totalMois ?> / 5 mois</div>
      </div>
    </div>
  </div>
</div>

<!-- Renouvellements -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <h3 class="card-title">Renouvellements (<?= count($renouvellements) ?>)</h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Période proposée</th><th>Durée</th><th>Motif</th><th>Statut</th><th>Décision</th></tr>
      </thead>
      <tbody>
        <?php if (empty($renouvellements)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:24px">Aucun renouvellement.</td></tr>
        <?php endif ?>
        <?php foreach ($renouvellements as $r): ?>
        <tr>
          <td><?= (int)$r['numero_renouvellement'] ?></td>
          <td><?= $r['date_debut_proposee'] ? date('d/m/Y', strtotime($r['date_debut_proposee'])) : '—' ?> → <?= $r['date_fin_proposee'] ? date('d/m/Y', strtotime($r['date_fin_proposee'])) : '—' ?></td>
          <td><?= number_format((float)$r['duree_ajoutee_mois'], 1) ?> mois</td>
          <td><small><?= h(mb_substr($r['motif_demande'] ?? '', 0, 60)) ?><?= strlen($r['motif_demande'] ?? '') > 60 ? '…' : '' ?></small></td>
          <td><?= statusBadge($r['statut']) ?></td>
          <td><?= $r['dec_prenom'] ? h($r['dec_prenom'] . ' ' . $r['dec_nom']) : '—' ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modals Actions -->
<div class="modal-overlay" id="modal-terminer">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Marquer comme terminé</h3><button class="modal-close" onclick="closeModal('modal-terminer')">✕</button></div>
    <form method="POST" action="backoffice.php?action=gerer_stage">
      <input type="hidden" name="stage_id" value="<?= $id ?>">
      <input type="hidden" name="sous_action" value="terminer">
      <div class="modal-body"><p>Confirmer la fin du stage de <strong><?= h($stage['prenom'] . ' ' . $stage['nom']) ?></strong> ?</p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-terminer')">Annuler</button>
        <button type="submit" class="btn btn-success">Confirmer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-modifier-dates">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Modifier les dates</h3><button class="modal-close" onclick="closeModal('modal-modifier-dates')">✕</button></div>
    <form method="POST" action="backoffice.php?action=gerer_stage">
      <input type="hidden" name="stage_id" value="<?= $id ?>">
      <input type="hidden" name="sous_action" value="dates">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Date début</label>
            <input type="date" name="date_debut" value="<?= h($stage['date_debut'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Date fin</label>
            <input type="date" name="date_fin" value="<?= h($stage['date_fin'] ?? '') ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-modifier-dates')">Annuler</button>
        <button type="submit" class="btn btn-red">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-modifier-enc">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Modifier l'encadrant</h3><button class="modal-close" onclick="closeModal('modal-modifier-enc')">✕</button></div>
    <form method="POST" action="backoffice.php?action=gerer_stage">
      <input type="hidden" name="stage_id" value="<?= $id ?>">
      <input type="hidden" name="sous_action" value="encadrant">
      <div class="modal-body">
        <div class="form-group">
          <label>Encadrant</label>
          <select name="encadrant_id">
            <option value="">— Aucun —</option>
            <?php foreach ($encadrants as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $stage['encadrant_id'] == $e['id'] ? 'selected' : '' ?>><?= h($e['prenom'] . ' ' . $e['nom']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-modifier-enc')">Annuler</button>
        <button type="submit" class="btn btn-red">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-transport">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Gérer le transport</h3><button class="modal-close" onclick="closeModal('modal-transport')">✕</button></div>
    <form method="POST" action="backoffice.php?action=gerer_stage">
      <input type="hidden" name="stage_id" value="<?= $id ?>">
      <input type="hidden" name="sous_action" value="transport">
      <div class="modal-body">
        <div class="form-group">
          <label>Remboursement transport</label>
          <select name="remboursement_transport">
            <option value="0" <?= !$stage['remboursement_transport'] ? 'selected' : '' ?>>Non</option>
            <option value="1" <?= $stage['remboursement_transport'] ? 'selected' : '' ?>>Oui</option>
          </select>
        </div>
        <div class="form-group">
          <label>Montant mensuel (GNF)</label>
          <input type="number" name="montant_transport" value="<?= h($stage['montant_transport'] ?? '0') ?>" min="0" step="1000">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-transport')">Annuler</button>
        <button type="submit" class="btn btn-red">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-archiver">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">Archiver le stage</h3><button class="modal-close" onclick="closeModal('modal-archiver')">✕</button></div>
    <form method="POST" action="backoffice.php?action=gerer_stage">
      <input type="hidden" name="stage_id" value="<?= $id ?>">
      <input type="hidden" name="sous_action" value="archiver">
      <div class="modal-body">
        <div class="alert alert-warning">Cette action archivera définitivement le stage.</div>
        <div class="form-group" style="margin-top:12px">
          <label>Statut final</label>
          <select name="statut_final">
            <option value="termine">Terminé</option>
            <option value="interrompu">Interrompu</option>
            <option value="annule">Annulé</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-archiver')">Annuler</button>
        <button type="submit" class="btn btn-danger">Archiver</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
